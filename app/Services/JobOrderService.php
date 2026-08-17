<?php

namespace App\Services;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Mail\ResultsReadyMail;
use App\Models\AnalysisCategory;
use App\Models\AnalysisType;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromIntake(array $data): JobOrder
    {
        return DB::transaction(function () use ($data) {
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

            /** @var list<int> $analysisTypeIds */
            $analysisTypeIds = array_values(array_unique(array_map(
                'intval',
                $data['analysis_type_ids'] ?? [],
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

            foreach ($jobOrder->analyses as $analysis) {
                $analyst = $analysis->analysisType?->analysts->first();

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
     * @param  array{result_value?: ?string, result_unit?: ?string, result_remarks?: ?string}  $data
     */
    public function saveAnalysisDraft(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);

        if (in_array($analysis->status, [
            JobOrderAnalysisStatus::Completed,
        ], true)) {
            throw ValidationException::withMessages([
                'analysis' => 'Completed analyses cannot be edited here.',
            ]);
        }

        $analysis->update([
            'assigned_to' => $analysis->assigned_to ?? $analyst->id,
            'result_value' => $data['result_value'] ?? null,
            'result_unit' => $data['result_unit'] ?? null,
            'result_remarks' => $data['result_remarks'] ?? null,
            'status' => $analysis->status === JobOrderAnalysisStatus::Returned
                ? JobOrderAnalysisStatus::Returned
                : JobOrderAnalysisStatus::InProgress,
            'completed_at' => null,
        ]);

        return $analysis->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $analysis;
    }

    /**
     * @param  array{result_value: string, result_unit?: ?string, result_remarks?: ?string}  $data
     */
    public function completeAnalysis(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);

        if (blank($data['result_value'])) {
            throw ValidationException::withMessages([
                'result_value' => 'A result value is required to complete this analysis.',
            ]);
        }

        return DB::transaction(function () use ($analysis, $data, $analyst) {
            $analysis->update([
                'assigned_to' => $analysis->assigned_to ?? $analyst->id,
                'result_value' => $data['result_value'],
                'result_unit' => $data['result_unit'] ?? null,
                'result_remarks' => $data['result_remarks'] ?? null,
                'status' => JobOrderAnalysisStatus::Completed,
                'completed_at' => now(),
            ]);

            $jobOrder = $analysis->jobOrder()->with('analyses')->firstOrFail();
            $allDone = $jobOrder->analyses->every(
                fn (JobOrderAnalysis $line) => $line->status === JobOrderAnalysisStatus::Completed
            );

            if ($allDone) {
                $becomingReady = $jobOrder->status !== JobOrderStatus::ReadyForPickup;

                $jobOrder->update(['status' => JobOrderStatus::ReadyForPickup]);

                if ($becomingReady && filled($jobOrder->customer_email)) {
                    Mail::to($jobOrder->customer_email)->send(new ResultsReadyMail($jobOrder));
                }

                if ($becomingReady) {
                    User::role(['head_analysis', 'admin'])->get()->each(
                        fn (User $user) => $user->notify(new JobOrderPendingReview($jobOrder))
                    );
                }
            }

            return $analysis->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $analysis;
        });
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
        if ($jobOrder->status !== JobOrderStatus::ReadyForPickup) {
            throw ValidationException::withMessages([
                'job_order' => 'Only finished jobs ready for pickup can be signed.',
            ]);
        }

        if ($jobOrder->reviewed_at) {
            throw ValidationException::withMessages([
                'job_order' => 'This job order has already been signed.',
            ]);
        }

        $jobOrder->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

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
            ->where('status', JobOrderStatus::ReadyForPickup)
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
        return DB::transaction(function () use ($jobOrder, $analysisIds, $reviewer, $notes) {
            $ids = collect($analysisIds)->unique()->values()->all();

            $analyses = $jobOrder->analyses()->whereIn('id', $ids)->get();

            $analyses->each(function (JobOrderAnalysis $analysis) {
                $analysis->update([
                    'result_value' => null,
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
