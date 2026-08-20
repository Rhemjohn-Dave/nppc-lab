<?php

namespace App\Services;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Mail\ResultsReadyMail;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Notifications\JobOrderPendingReview;
use App\Notifications\JobOrderSubmitted;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class JobOrderService
{
    public function __construct(
        private readonly ReferenceNumberService $referenceNumbers,
        private readonly AnalystAssignmentPicker $assignments,
        private readonly AnalysisResultReportResolver $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromIntake(array $data): JobOrder
    {
        return DB::transaction(function () use ($data) {
            Customer::rememberFromIntake($data);

            $jobOrder = JobOrder::create([
                'reference_no' => $this->referenceNumbers->next(),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_contact' => $data['customer_contact'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'ownership_type' => $data['ownership_type'] ?? null,
                'classification' => $data['classification'] ?? null,
                'sampling_date' => $data['sampling_date'] ?? null,
                'sampling_time' => $data['sampling_time'] ?? null,
                'sample_collected_by' => $data['sample_collected_by'] ?? null,
                'field_data' => $data['field_data'] ?? null,
                'sample_storage_temp' => $data['sample_storage_temp'] ?? null,
                'wastewater_source' => $data['wastewater_source'] ?? null,
                'sampling_point' => $data['sampling_point'] ?? null,
                'other_tests' => $data['other_tests'] ?? null,
                'status' => JobOrderStatus::DraftSubmitted,
                'total_cost' => 0,
            ]);

            /** @var array<int, array<string, mixed>> $samples */
            $samples = $data['samples'];

            foreach ($samples as $index => $sample) {
                $jobOrder->samples()->create([
                    'sample_code' => $sample['sample_code'] ?? null,
                    'description' => $sample['description'],
                    'matrix' => $sample['matrix'] ?? null,
                    'quantity' => $sample['quantity'] ?? null,
                    'unit' => $sample['unit'] ?? null,
                    'remarks' => $sample['remarks'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            /** @var list<int> $requestedTypeIds */
            $requestedTypeIds = array_values(array_unique(array_map(
                'intval',
                $data['analysis_type_ids'] ?? [],
            )));

            /** @var list<int> $packageIds */
            $packageIds = array_values(array_unique(array_map(
                'intval',
                $data['package_ids'] ?? [],
            )));

            $packages = AnalysisPackage::query()
                ->whereIn('id', $packageIds)
                ->where('is_active', true)
                ->with('analysisTypes')
                ->get();

            $typesExplicitlyListed = array_key_exists('analysis_type_ids', $data);
            $analysisTypeIds = $requestedTypeIds;
            $waivedAll = [];

            foreach ($packages as $package) {
                $memberIds = $package->orderedTypeIds();
                $selected = $typesExplicitlyListed
                    ? array_values(array_intersect($memberIds, $requestedTypeIds))
                    : $memberIds;

                if ($selected === []) {
                    throw ValidationException::withMessages([
                        'package_ids' => "Select at least one test in package \"{$package->name}\".",
                    ]);
                }

                $waived = array_values(array_diff($memberIds, $selected));
                foreach ($waived as $waivedId) {
                    $waivedAll[] = $waivedId;
                }

                $jobOrder->packages()->syncWithoutDetaching([
                    $package->id => [
                        'selected_type_ids' => $selected,
                        'waived_type_ids' => $waived,
                    ],
                ]);

                foreach ($selected as $typeId) {
                    $analysisTypeIds[] = $typeId;
                }
            }

            $waivedAll = array_values(array_unique($waivedAll));
            $analysisTypeIds = array_values(array_unique(array_filter(
                $analysisTypeIds,
                fn (int $id): bool => ! in_array($id, $waivedAll, true),
            )));

            $types = AnalysisType::query()
                ->whereIn('id', $analysisTypeIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            foreach ($analysisTypeIds as $typeId) {
                $type = $types->get($typeId);
                if (! $type) {
                    continue;
                }

                $type->loadMissing('category');

                $jobOrder->analyses()->create([
                    'analysis_type_id' => $type->id,
                    'name' => $type->name,
                    'category' => $type->category?->slug,
                    'category_label' => $type->category?->name,
                    'quantity' => 1,
                    'unit_price' => $type->default_price,
                    'total_cost' => $type->default_price,
                    'status' => JobOrderAnalysisStatus::Pending,
                ]);
            }

            if (! empty($data['other_tests'])) {
                $otherCategory = AnalysisCategory::query()->where('slug', 'other')->first();

                $jobOrder->analyses()->create([
                    'analysis_type_id' => null,
                    'name' => $data['other_tests'],
                    'category' => $otherCategory?->slug ?? 'other',
                    'category_label' => $otherCategory?->name ?? 'Other',
                    'quantity' => 1,
                    'unit_price' => 0,
                    'total_cost' => 0,
                    'status' => JobOrderAnalysisStatus::Pending,
                ]);
            }

            $jobOrder->recalculateTotal();

            User::role(['receiving', 'admin'])->get()->each(
                fn (User $user) => $user->notify(new JobOrderSubmitted($jobOrder))
            );

            return $jobOrder->fresh(['samples', 'analyses']) ?? $jobOrder;
        });
    }

    /**
     * @param  array<int, array{id: int, unit_price: float|int|string, quantity?: int}>  $lines
     */
    public function updatePricing(JobOrder $jobOrder, array $lines): JobOrder
    {
        return DB::transaction(function () use ($jobOrder, $lines) {
            foreach ($lines as $line) {
                $analysis = $jobOrder->analyses()->whereKey($line['id'])->firstOrFail();
                $quantity = (int) ($line['quantity'] ?? $analysis->quantity);
                $unitPrice = (float) $line['unit_price'];

                $analysis->update([
                    'quantity' => max(1, $quantity),
                    'unit_price' => $unitPrice,
                    'total_cost' => max(1, $quantity) * $unitPrice,
                ]);
            }

            $jobOrder->update(['status' => JobOrderStatus::Priced]);
            $jobOrder->recalculateTotal();

            return $jobOrder->fresh(['samples', 'analyses.analysisType', 'analyses.assignee']) ?? $jobOrder;
        });
    }

    public function receive(JobOrder $jobOrder, User $receiver): JobOrder
    {
        if ($jobOrder->status !== JobOrderStatus::Priced) {
            throw ValidationException::withMessages([
                'job_order' => 'Save and finalize pricing before marking this job order as received.',
            ]);
        }

        return DB::transaction(function () use ($jobOrder, $receiver) {
            $jobOrder->load('analyses.analysisType.analysts');
            $loads = $this->assignments->openLoads();

            foreach ($jobOrder->analyses as $analysis) {
                $analyst = $this->assignments->pick($analysis->analysisType, $loads);

                $analysis->update([
                    'assigned_to' => $analyst?->id,
                    'status' => $analyst
                        ? JobOrderAnalysisStatus::Assigned
                        : JobOrderAnalysisStatus::Pending,
                ]);

                if ($analyst instanceof User) {
                    $analyst->notify(new TaskAssigned($jobOrder, $analysis));
                }
            }

            $jobOrder->update([
                'status' => JobOrderStatus::InAnalysis,
                'received_by' => $receiver->id,
                'received_at' => now(),
            ]);

            return $jobOrder->fresh(['samples', 'analyses.assignee', 'analyses.analysisType']) ?? $jobOrder;
        });
    }

    /**
     * @param  array{result_value?: ?string, result_measurement?: ?string, result_unit?: ?string, result_remarks?: ?string}  $data
     */
    public function saveAnalysisDraft(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);
        $analysis->loadMissing('analysisType');

        $value = $this->normalizedResultValue($analysis, $data['result_value'] ?? null);
        $measurement = $this->optionalText($data['result_measurement'] ?? null);
        $unit = $this->optionalText($data['result_unit'] ?? null);

        if ($analysis->analysisType?->isPassFail() && $value !== null && ! in_array($value, ['Passed', 'Failed'], true)) {
            throw ValidationException::withMessages([
                'result_value' => 'Select Passed or Failed.',
            ]);
        }

        if (in_array($analysis->status, [
            JobOrderAnalysisStatus::Completed,
        ], true)) {
            throw ValidationException::withMessages([
                'analysis' => 'Completed analyses cannot be edited here.',
            ]);
        }

        $analysis->update([
            'assigned_to' => $analysis->assigned_to ?? $analyst->id,
            'result_value' => $value,
            'result_measurement' => $measurement,
            'result_unit' => $unit,
            'result_remarks' => $data['result_remarks'] ?? null,
            'status' => $analysis->status === JobOrderAnalysisStatus::Returned
                ? JobOrderAnalysisStatus::Returned
                : JobOrderAnalysisStatus::InProgress,
            'completed_at' => null,
        ]);

        return $analysis->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $analysis;
    }

    /**
     * @param  array{result_value: string, result_measurement?: ?string, result_unit?: ?string, result_remarks?: ?string}  $data
     */
    public function completeAnalysis(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);
        $analysis->loadMissing('analysisType');

        $value = $this->normalizedResultValue($analysis, $data['result_value'] ?? null);
        $measurement = $this->optionalText($data['result_measurement'] ?? null);
        $unit = $this->optionalText($data['result_unit'] ?? null);

        if (blank($value)) {
            throw ValidationException::withMessages([
                'result_value' => $analysis->analysisType?->isPassFail()
                    ? 'Select Passed or Failed to complete this analysis.'
                    : 'A result value is required to complete this analysis.',
            ]);
        }

        if ($analysis->analysisType?->isPassFail() && ! in_array($value, ['Passed', 'Failed'], true)) {
            throw ValidationException::withMessages([
                'result_value' => 'Select Passed or Failed.',
            ]);
        }

        return DB::transaction(function () use ($analysis, $data, $analyst, $value, $measurement, $unit) {
            $analysis->update([
                'assigned_to' => $analysis->assigned_to ?? $analyst->id,
                'result_value' => $value,
                'result_measurement' => $measurement,
                'result_unit' => $unit,
                'result_remarks' => $data['result_remarks'] ?? null,
                'status' => JobOrderAnalysisStatus::Completed,
                'completed_at' => now(),
            ]);

            return $analysis->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $analysis;
        });
    }

    public function submitForReview(JobOrder $jobOrder, User $analyst): JobOrder
    {
        if ($jobOrder->status !== JobOrderStatus::InAnalysis) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs still in analysis can be sent to Head.',
            ]);
        }

        $jobOrder->load(['analyses.assignee', 'packages']);
        $this->assertUserCanSubmitForReview($jobOrder, $analyst);

        $incomplete = $jobOrder->analyses->reject(
            fn (JobOrderAnalysis $line) => $line->status === JobOrderAnalysisStatus::Completed
                && filled($line->result_value)
        );

        if ($incomplete->isNotEmpty()) {
            $names = $incomplete->pluck('name')->filter()->implode(', ');

            throw ValidationException::withMessages([
                'job_order' => 'Encode all results before sending to Head'
                    .($names !== '' ? ': '.$names : '.'),
            ]);
        }

        $jobOrder->update(['status' => JobOrderStatus::PendingReview]);

        User::role(['head_analysis', 'admin'])->get()->each(
            fn (User $user) => $user->notify(new JobOrderPendingReview($jobOrder))
        );

        return $jobOrder->fresh(['analyses.assignee', 'packages']) ?? $jobOrder;
    }

    public function userCanSubmitForReview(JobOrder $jobOrder, User $user): bool
    {
        try {
            $this->assertUserCanSubmitForReview($jobOrder, $user);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function assertUserCanSubmitForReview(JobOrder $jobOrder, User $user): void
    {
        $jobOrder->loadMissing(['packages', 'analyses']);

        if ($user->hasRole('admin')) {
            return;
        }

        if ($jobOrder->packages->isNotEmpty()) {
            $signatories = $jobOrder->packages
                ->pluck('signatory_user_id')
                ->filter()
                ->unique()
                ->values();

            if ($signatories->isEmpty()) {
                throw ValidationException::withMessages([
                    'job_order' => 'This package has no designated analyst. Ask Admin to set one on the package.',
                ]);
            }

            if ($signatories->count() > 1) {
                throw ValidationException::withMessages([
                    'job_order' => 'This job has packages with different designated analysts. An admin must send it to Head.',
                ]);
            }

            if ((int) $signatories->first() !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'job_order' => 'Only the designated package analyst can send this job to Head.',
                ]);
            }

            return;
        }

        $assignees = $jobOrder->analyses->pluck('assigned_to')->filter()->unique();

        if (! $assignees->contains($user->id)) {
            throw ValidationException::withMessages([
                'job_order' => 'You are not assigned to this job.',
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function consolidationsFor(User $user): array
    {
        $jobs = JobOrder::query()
            ->where('status', JobOrderStatus::InAnalysis)
            ->with(['analyses.assignee', 'packages.signatory'])
            ->latest('id')
            ->limit(40)
            ->get()
            ->filter(fn (JobOrder $job) => $this->userCanSubmitForReview($job, $user))
            ->values();

        return $jobs->map(function (JobOrder $job) use ($user) {
            $incomplete = $job->analyses->reject(
                fn (JobOrderAnalysis $line) => $line->status === JobOrderAnalysisStatus::Completed
                    && filled($line->result_value)
            );
            $summary = $this->reports->forJobOrder($job, $user)->summary();
            $previewLine = $job->analyses->first();

            return [
                'id' => $job->id,
                'reference_no' => $job->reference_no,
                'customer_name' => $job->customer_name,
                'can_submit' => $incomplete->isEmpty(),
                'can_preview' => $summary['can_preview'],
                'preview_message' => $summary['message'],
                'preview_url' => $previewLine
                    ? "/analyst/tasks/{$previewLine->id}/report"
                    : null,
                'missing' => $incomplete->pluck('name')->values()->all(),
                'lines' => $job->analyses->map(fn (JobOrderAnalysis $line) => [
                    'id' => $line->id,
                    'name' => $line->name,
                    'assignee_name' => $line->assignee?->name,
                    'status' => $line->status->value,
                    'status_label' => $line->status->label(),
                    'result_value' => $line->result_value,
                    'completed' => $line->status === JobOrderAnalysisStatus::Completed
                        && filled($line->result_value),
                ])->values()->all(),
            ];
        })->all();
    }

    /**
     * Result forms the designated analyst may print after Head release.
     *
     * @return list<array<string, mixed>>
     */
    public function releasedResultPrintsFor(User $user): array
    {
        $jobs = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup)
            ->whereNotNull('reviewed_at')
            ->with(['analyses', 'packages'])
            ->latest('reviewed_at')
            ->limit(40)
            ->get()
            ->filter(function (JobOrder $job) use ($user) {
                if ($user->hasRole('admin')) {
                    return true;
                }

                if ($this->reports->userIsPackageSignatory($job, $user)) {
                    return true;
                }

                return $job->packages->isEmpty()
                    && $job->analyses->contains(
                        fn (JobOrderAnalysis $line) => (int) $line->assigned_to === (int) $user->id,
                    );
            })
            ->values();

        return $jobs->map(function (JobOrder $job) use ($user) {
            $summary = $this->reports->forJobOrder($job, $user)->summary();
            $previewLine = $job->analyses->first();

            return [
                'id' => $job->id,
                'reference_no' => $job->reference_no,
                'customer_name' => $job->customer_name,
                'can_print' => $summary['can_print'],
                'print_url' => $previewLine
                    ? "/analyst/tasks/{$previewLine->id}/report"
                    : null,
            ];
        })->all();
    }

    private function normalizedResultValue(JobOrderAnalysis $analysis, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (! $analysis->analysisType?->isPassFail()) {
            return $text;
        }

        $normalized = mb_strtolower($text);

        return match (true) {
            in_array($normalized, ['passed', 'pass'], true) => 'Passed',
            in_array($normalized, ['failed', 'fail'], true) => 'Failed',
            default => $text,
        };
    }

    private function optionalText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function assertAnalystCanWorkOn(JobOrderAnalysis $analysis, User $analyst): void
    {
        $analysis->loadMissing('jobOrder');

        if ($analysis->jobOrder?->status !== JobOrderStatus::InAnalysis) {
            throw ValidationException::withMessages([
                'analysis' => 'This job is not available for analysis yet. Receiving must finalize pricing and mark it received first.',
            ]);
        }

        if ($analysis->assigned_to && $analysis->assigned_to !== $analyst->id && ! $analyst->hasRole('admin')) {
            throw ValidationException::withMessages([
                'analysis' => 'This task is assigned to another analyst.',
            ]);
        }
    }

    public function sign(JobOrder $jobOrder, User $reviewer, ?string $notes = null): JobOrder
    {
        if ($jobOrder->status !== JobOrderStatus::PendingReview) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs sent by the designated analyst can be signed.',
            ]);
        }

        if ($jobOrder->reviewed_at) {
            throw ValidationException::withMessages([
                'job_order' => 'This job order has already been signed.',
            ]);
        }

        $jobOrder->update([
            'status' => JobOrderStatus::ReadyForPickup,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        if (filled($jobOrder->customer_email)) {
            Mail::to($jobOrder->customer_email)->send(new ResultsReadyMail($jobOrder));
        }

        return $jobOrder->fresh(['samples', 'analyses', 'reviewer']) ?? $jobOrder;
    }

    /**
     * @param  iterable<int, int>  $jobOrderIds
     */
    public function signMany(iterable $jobOrderIds, User $reviewer, ?string $notes = null): int
    {
        $ids = collect($jobOrderIds)->unique()->values()->all();

        $orders = JobOrder::query()
            ->whereIn('id', $ids)
            ->where('status', JobOrderStatus::PendingReview)
            ->whereNull('reviewed_at')
            ->get();

        foreach ($orders as $order) {
            $this->sign($order, $reviewer, $notes);
        }

        return $orders->count();
    }

    /**
     * @param  iterable<int, int>  $analysisIds
     */
    public function returnAnalyses(JobOrder $jobOrder, iterable $analysisIds, User $reviewer, ?string $notes = null): JobOrder
    {
        if (! in_array($jobOrder->status, [
            JobOrderStatus::PendingReview,
            JobOrderStatus::ReadyForPickup,
        ], true)) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs pending Head review can be returned.',
            ]);
        }

        return DB::transaction(function () use ($jobOrder, $analysisIds, $notes) {
            $ids = collect($analysisIds)->unique()->values()->all();

            $analyses = $jobOrder->analyses()->whereIn('id', $ids)->get();

            $analyses->each(function (JobOrderAnalysis $analysis) {
                $analysis->update([
                    'result_value' => null,
                    'result_measurement' => null,
                    'result_unit' => null,
                    'result_remarks' => null,
                    'completed_at' => null,
                    'status' => JobOrderAnalysisStatus::Returned,
                ]);

                if ($analysis->assignee instanceof User) {
                    $analysis->assignee->notify(new TaskAssigned($analysis->jobOrder, $analysis));
                }
            });

            $jobOrder->update([
                'status' => JobOrderStatus::InAnalysis,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => $notes,
            ]);

            return $jobOrder->fresh(['samples', 'analyses.assignee']) ?? $jobOrder;
        });
    }
}
