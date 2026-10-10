<?php

namespace App\Services;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Events\LabQueueUpdated;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\JobOrderPackage;
use App\Models\User;
use App\Notifications\AnalysisReturned;
use App\Notifications\JobOrderJoApproved;
use App\Notifications\JobOrderPendingReview;
use App\Notifications\JobOrderSubmitted;
use App\Notifications\ResultsReleased;
use App\Support\ResultSignatories;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobOrderService
{
    public function __construct(
        private readonly ReferenceNumberService $referenceNumbers,
        private readonly AnalystAssignmentPicker $assignments,
        private readonly AnalysisResultReportResolver $reports,
        private readonly CustomerEmailService $customerEmails,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromIntake(array $data): JobOrder
    {
        $jobOrder = DB::transaction(function () use ($data) {
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
                'sampling_site' => $data['sampling_site'] ?? null,
                'specimen' => $data['specimen'] ?? null,
                'payment_mode' => $data['payment_mode'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'other_tests' => $data['other_tests'] ?? null,
                'status' => JobOrderStatus::DraftSubmitted,
                'total_cost' => 0,
            ]);

            /** @var array<int, array<string, mixed>> $samples */
            $samples = $data['samples'];

            $createdSamples = [];

            foreach ($samples as $index => $sample) {
                $description = trim((string) ($sample['description'] ?? ''));

                $createdSamples[] = $jobOrder->samples()->create([
                    'sample_code' => $sample['sample_code'] ?? null,
                    'description' => $description,
                    'matrix' => $sample['matrix'] ?? null,
                    'quantity' => $sample['quantity'] ?? null,
                    'unit' => $sample['unit'] ?? null,
                    'remarks' => $sample['remarks'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $usesLegacyJoLevelSelection = array_key_exists('analysis_type_ids', $data)
                || array_key_exists('package_ids', $data);

            if ($usesLegacyJoLevelSelection) {
                $shared = [
                    'package_ids' => $data['package_ids'] ?? [],
                    'analysis_type_ids' => $data['analysis_type_ids'] ?? [],
                ];
                $perSampleSelections = array_fill(0, count($createdSamples), $shared);
                $typesExplicitlyListed = array_key_exists('analysis_type_ids', $data);
            } else {
                $perSampleSelections = [];
                foreach ($createdSamples as $index => $_) {
                    $perSampleSelections[] = [
                        'package_ids' => $samples[$index]['package_ids'] ?? [],
                        'analysis_type_ids' => $samples[$index]['analysis_type_ids'] ?? [],
                    ];
                }
                $typesExplicitlyListed = true;
            }

            $this->attachAnalysesForSamples(
                $jobOrder,
                $createdSamples,
                $perSampleSelections,
                $data['other_tests'] ?? null,
                $typesExplicitlyListed,
            );

            $jobOrder->recalculateTotal();

            User::role(['receiving', 'admin'])->get()->each(
                fn (User $user) => $user->notify(new JobOrderSubmitted($jobOrder))
            );

            return $jobOrder->fresh(['samples', 'analyses']) ?? $jobOrder;
        });

        LabQueueUpdated::notify([LabQueueUpdated::SCOPE_RECEIVING], $jobOrder->id);

        $this->customerEmails->queueRequestReceived($jobOrder);

        return $jobOrder;
    }

    /**
     * Attach packages and analysis lines for each sample (billed per sample).
     *
     * @param  list<\App\Models\Sample>  $createdSamples
     * @param  list<array{package_ids?: list<int|string>, analysis_type_ids?: list<int|string>}>  $perSampleSelections
     */
    private function attachAnalysesForSamples(
        JobOrder $jobOrder,
        array $createdSamples,
        array $perSampleSelections,
        ?string $otherTests,
        bool $typesExplicitlyListed,
    ): void {
        $allPackageIds = [];
        foreach ($perSampleSelections as $selection) {
            foreach (($selection['package_ids'] ?? []) as $id) {
                $allPackageIds[] = (int) $id;
            }
        }

        $packages = AnalysisPackage::query()
            ->whereIn('id', array_values(array_unique($allPackageIds)))
            ->where('is_active', true)
            ->with('analysisTypes')
            ->get()
            ->keyBy('id');

        foreach ($createdSamples as $index => $sampleModel) {
            $selection = $perSampleSelections[$index] ?? [
                'package_ids' => [],
                'analysis_type_ids' => [],
            ];

            /** @var list<int> $requestedTypeIds */
            $requestedTypeIds = array_values(array_unique(array_map(
                'intval',
                $selection['analysis_type_ids'] ?? [],
            )));

            /** @var list<int> $packageIds */
            $packageIds = array_values(array_unique(array_map(
                'intval',
                $selection['package_ids'] ?? [],
            )));

            $sampleTypesExplicit = $typesExplicitlyListed && $requestedTypeIds !== [];
            $analysisTypeIds = $requestedTypeIds;
            $waivedAll = [];
            /** @var list<array{price: float, type_ids: list<int>}> $fullPackages */
            $fullPackages = [];

            foreach ($packageIds as $packageId) {
                $package = $packages->get($packageId);
                if (! $package) {
                    continue;
                }

                $memberIds = $package->orderedTypeIds();
                $selected = $sampleTypesExplicit
                    ? array_values(array_intersect($memberIds, $requestedTypeIds))
                    : $memberIds;

                if ($selected === []) {
                    throw ValidationException::withMessages([
                        "samples.{$index}.package_ids" => "Select at least one test in package \"{$package->name}\" for sample ".($index + 1).'.',
                    ]);
                }

                $waived = array_values(array_diff($memberIds, $selected));
                foreach ($waived as $waivedId) {
                    $waivedAll[] = $waivedId;
                }

                JobOrderPackage::query()->create([
                    'job_order_id' => $jobOrder->id,
                    'sample_id' => $sampleModel->id,
                    'analysis_package_id' => $package->id,
                    'selected_type_ids' => $selected,
                    'waived_type_ids' => $waived,
                ]);

                if ($waived === []) {
                    $fullPackages[] = [
                        'price' => (float) $package->default_price,
                        'type_ids' => array_map('intval', $selected),
                    ];
                }

                foreach ($selected as $typeId) {
                    $analysisTypeIds[] = $typeId;
                }
            }

            $waivedAll = array_values(array_unique($waivedAll));
            $analysisTypeIds = array_values(array_unique(array_filter(
                $analysisTypeIds,
                fn (int $id): bool => ! in_array($id, $waivedAll, true),
            )));

            if ($analysisTypeIds === [] && ($index > 0 || empty($otherTests))) {
                throw ValidationException::withMessages([
                    "samples.{$index}.analysis_type_ids" => 'Select at least one analysis or package for sample '.($index + 1).'.',
                ]);
            }

            $types = AnalysisType::query()
                ->whereIn('id', $analysisTypeIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $packagePrices = $this->packageLinePrices($fullPackages, $types);

            foreach ($analysisTypeIds as $typeId) {
                $type = $types->get($typeId);
                if (! $type) {
                    continue;
                }

                $type->loadMissing('category');
                $price = $packagePrices[(int) $type->id] ?? $type->default_price;

                $jobOrder->analyses()->create([
                    'sample_id' => $sampleModel->id,
                    'analysis_type_id' => $type->id,
                    'name' => $type->name,
                    'category' => $type->category?->slug,
                    'category_label' => $type->category?->name,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'total_cost' => $price,
                    'status' => JobOrderAnalysisStatus::Pending,
                ]);
            }
        }

        if (! empty($otherTests) && $createdSamples !== []) {
            $otherCategory = AnalysisCategory::query()->where('slug', 'other')->first();
            $firstSample = $createdSamples[0];

            $jobOrder->analyses()->create([
                'sample_id' => $firstSample->id,
                'analysis_type_id' => null,
                'name' => $otherTests,
                'category' => $otherCategory?->slug ?? 'other',
                'category_label' => $otherCategory?->name ?? 'Other',
                'quantity' => 1,
                'unit_price' => 0,
                'total_cost' => 0,
                'status' => JobOrderAnalysisStatus::Pending,
            ]);
        }
    }

    /**
     * Split each fully-selected package's price across its member lines, weighted by
     * test list price (same as Receiving's package price edit); last line takes the remainder.
     *
     * @param  list<array{price: float, type_ids: list<int>}>  $fullPackages
     * @param  Collection<int, AnalysisType>  $types
     * @return array<int, float>
     */
    private function packageLinePrices(array $fullPackages, Collection $types): array
    {
        $prices = [];

        foreach ($fullPackages as $package) {
            $memberIds = array_values(array_filter(
                $package['type_ids'],
                fn (int $id): bool => $types->has($id) && ! array_key_exists($id, $prices),
            ));
            if ($memberIds === []) {
                continue;
            }

            $weights = array_map(function (int $id) use ($types): float {
                $listPrice = (float) $types->get($id)->default_price;

                return $listPrice > 0 ? $listPrice : 1.0;
            }, $memberIds);
            $weightSum = array_sum($weights);
            $allocated = 0.0;
            $last = count($memberIds) - 1;

            foreach ($memberIds as $i => $id) {
                if ($i === $last) {
                    $prices[$id] = round($package['price'] - $allocated, 2);

                    continue;
                }

                $share = round($package['price'] * $weights[$i] / $weightSum, 2);
                $allocated += $share;
                $prices[$id] = $share;
            }
        }

        return $prices;
    }

    /**
     * @param  array<int, array{id: int, unit_price: float|int|string, quantity?: int}>  $lines
     * @return array{job: JobOrder, outcome: 'queued_for_approval'|'awaiting_approval'|'pricing_updated'}
     */
    public function updatePricing(
        JobOrder $jobOrder,
        array $lines,
        User $actor,
        float|int|string|null $discountPercent = null,
    ): array {
        $previousStatus = $jobOrder->status;
        $queuedForApproval = false;
        $resolvedPercent = $discountPercent !== null
            ? max(0, min(100, (float) $discountPercent))
            : max(0, min(100, (float) $jobOrder->discount_percent));

        $jobOrder = DB::transaction(function () use (
            $jobOrder,
            $lines,
            $previousStatus,
            $actor,
            $resolvedPercent,
            &$queuedForApproval,
        ) {
            foreach ($lines as $line) {
                $analysis = $jobOrder->analyses()->whereKey($line['id'])->firstOrFail();
                $quantity = (int) ($line['quantity'] ?? $analysis->quantity);
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = max(1, $quantity) * $unitPrice;

                $analysis->update([
                    'quantity' => max(1, $quantity),
                    'unit_price' => $unitPrice,
                    'total_cost' => $lineTotal,
                ]);
            }

            $attributes = [
                'discount_percent' => $resolvedPercent,
            ];

            // Stamp sample-received time when Receiving first handles costing (not at send-to-analysts).
            if ($jobOrder->received_at === null) {
                $attributes['received_at'] = now();
                $attributes['received_by'] = $actor->id;
            }

            // Never regress JO-approved / analysis / review statuses when repricing.
            if (in_array($previousStatus, [
                JobOrderStatus::DraftSubmitted,
                JobOrderStatus::Priced,
            ], true)) {
                $attributes['status'] = JobOrderStatus::PendingJoApproval;
                $queuedForApproval = true;
            }

            $jobOrder->update($attributes);
            $jobOrder->recalculateTotal();

            return $jobOrder->fresh(['samples', 'analyses.analysisType', 'analyses.assignee', 'receiver']) ?? $jobOrder;
        });

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_RECEIVING,
            LabQueueUpdated::SCOPE_HEAD,
        ], $jobOrder->id);

        $outcome = match (true) {
            $queuedForApproval => 'queued_for_approval',
            $jobOrder->status === JobOrderStatus::PendingJoApproval => 'awaiting_approval',
            default => 'pricing_updated',
        };

        return ['job' => $jobOrder, 'outcome' => $outcome];
    }

    /**
     * Repair rows where pricing re-save forced pending_jo_approval while jo_approved_at stayed set.
     */
    public static function healJoApprovalStatusDesync(): int
    {
        // Already assigned to analysts → restore in_analysis.
        $inAnalysisIds = DB::table('job_orders')
            ->whereIn('job_orders.status', [
                JobOrderStatus::PendingJoApproval->value,
                JobOrderStatus::Priced->value,
            ])
            ->whereNotNull('jo_approved_at')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('job_order_analyses')
                    ->whereColumn('job_order_analyses.job_order_id', 'job_orders.id')
                    ->whereNotNull('job_order_analyses.assigned_to');
            })
            ->pluck('id');

        $received = 0;
        if ($inAnalysisIds->isNotEmpty()) {
            $received = DB::table('job_orders')
                ->whereIn('id', $inAnalysisIds)
                ->update(['status' => JobOrderStatus::InAnalysis->value]);
        }

        $approved = DB::table('job_orders')
            ->whereIn('status', [
                JobOrderStatus::PendingJoApproval->value,
                JobOrderStatus::Priced->value,
            ])
            ->whereNotNull('jo_approved_at')
            ->when(
                $inAnalysisIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $inAnalysisIds),
            )
            ->update(['status' => JobOrderStatus::JoApproved->value]);

        return $received + $approved;
    }

    public function approveJobOrder(JobOrder $jobOrder, User $approver): JobOrder
    {
        if (! in_array($jobOrder->status, [
            JobOrderStatus::PendingJoApproval,
            JobOrderStatus::Priced,
        ], true)) {
            throw ValidationException::withMessages([
                'job_order' => 'Only priced job orders waiting for Head approval can be approved.',
            ]);
        }

        if ($jobOrder->jo_approved_at) {
            throw ValidationException::withMessages([
                'job_order' => 'This job order has already been approved.',
            ]);
        }

        $jobOrder->update([
            'status' => JobOrderStatus::JoApproved,
            'jo_approved_by' => $approver->id,
            'jo_approved_at' => now(),
        ]);

        $fresh = $jobOrder->fresh(['samples', 'analyses', 'joApprover']) ?? $jobOrder;
        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_HEAD,
            LabQueueUpdated::SCOPE_RECEIVING,
        ], $fresh->id);

        User::role(['receiving', 'admin'])->get()->each(
            fn (User $user) => $user->notify(new JobOrderJoApproved($fresh))
        );

        return $fresh;
    }

    /**
     * @param  iterable<int, int>  $jobOrderIds
     */
    public function approveJobOrders(iterable $jobOrderIds, User $approver): int
    {
        $ids = collect($jobOrderIds)->unique()->values()->all();

        $orders = JobOrder::query()
            ->whereIn('id', $ids)
            ->whereIn('status', [
                JobOrderStatus::PendingJoApproval,
                JobOrderStatus::Priced,
            ])
            ->whereNull('jo_approved_at')
            ->get();

        foreach ($orders as $order) {
            $this->approveJobOrder($order, $approver);
        }

        return $orders->count();
    }

    public function receive(JobOrder $jobOrder, User $receiver): JobOrder
    {
        if (! $jobOrder->status->canReceiveSamples()) {
            throw ValidationException::withMessages([
                'job_order' => 'Head must approve this Job Order before it can be sent to analysts.',
            ]);
        }

        $jobOrder = DB::transaction(function () use ($jobOrder, $receiver) {
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
            }

            $acceptance = [
                'status' => JobOrderStatus::InAnalysis,
            ];

            // Keep earlier sample-received stamp from first pricing; only fill if missing.
            if ($jobOrder->received_at === null) {
                $acceptance['received_by'] = $receiver->id;
                $acceptance['received_at'] = now();
            }

            $jobOrder->update($acceptance);

            return $jobOrder->fresh(['samples', 'analyses.assignee', 'analyses.analysisType']) ?? $jobOrder;
        });

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_RECEIVING,
            LabQueueUpdated::SCOPE_ANALYST,
        ], $jobOrder->id);

        return $jobOrder;
    }

    /**
     * @param  array{result_value?: ?string, result_pass_fail?: ?string, result_measurement?: ?string, result_unit?: ?string, result_remarks?: ?string, result_method?: ?string, performed_at?: mixed}  $data
     */
    public function saveAnalysisDraft(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);
        $analysis->loadMissing('analysisType');

        $value = $this->optionalText($data['result_value'] ?? null);
        $requiresPassFail = (bool) ($analysis->analysisType?->requires_pass_fail);
        $passFail = $requiresPassFail
            ? $this->normalizedPassFail($data['result_pass_fail'] ?? null)
            : null;
        $measurement = $this->optionalText($data['result_measurement'] ?? null);
        $unit = $this->optionalText($data['result_unit'] ?? null);
        $method = $this->optionalText($data['result_method'] ?? null);
        $performedAt = $this->parsePerformedAt($data['performed_at'] ?? null);

        if ($requiresPassFail
            && ($data['result_pass_fail'] ?? null) !== null
            && trim((string) $data['result_pass_fail']) !== ''
            && $passFail === null) {
            throw ValidationException::withMessages([
                'result_pass_fail' => 'Select Passed or Failed.',
            ]);
        }

        $hasResultContent = $value !== null
            || $passFail !== null
            || $measurement !== null
            || $unit !== null
            || $this->optionalText($data['result_remarks'] ?? null) !== null
            || $method !== null;

        if ($hasResultContent && $performedAt === null) {
            throw ValidationException::withMessages([
                'performed_at' => 'Enter the date and time this test was performed before saving result values.',
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
            'assigned_to' => $analyst->id,
            'result_value' => $value,
            'result_pass_fail' => $passFail,
            'result_measurement' => $measurement,
            'result_unit' => $unit,
            'result_remarks' => $data['result_remarks'] ?? null,
            'result_method' => $method,
            'performed_at' => $performedAt,
            'status' => $analysis->status === JobOrderAnalysisStatus::Returned
                ? JobOrderAnalysisStatus::Returned
                : JobOrderAnalysisStatus::InProgress,
            'completed_at' => null,
        ]);

        $fresh = $analysis->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $analysis;

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->job_order_id);

        return $fresh;
    }

    /**
     * @param  array{result_value: string, result_pass_fail?: ?string, result_measurement?: ?string, result_unit?: ?string, result_remarks?: ?string, result_method?: ?string, performed_at?: mixed}  $data
     */
    public function completeAnalysis(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);
        $analysis->loadMissing(['analysisType', 'jobOrder.packages', 'jobOrder.analyses']);

        $value = $this->optionalText($data['result_value'] ?? null);
        $requiresPassFail = (bool) ($analysis->analysisType?->requires_pass_fail);
        $passFail = $requiresPassFail
            ? $this->normalizedPassFail($data['result_pass_fail'] ?? null)
            : null;
        $measurement = $this->optionalText($data['result_measurement'] ?? null);
        $unit = $this->optionalText($data['result_unit'] ?? null);
        $method = $this->optionalText($data['result_method'] ?? null);
        $performedAt = $this->parsePerformedAt($data['performed_at'] ?? null);

        $errors = [];
        if ($performedAt === null) {
            $errors['performed_at'] = 'Enter the date and time this test was performed.';
        }
        if (blank($value)) {
            $errors['result_value'] = 'A result value is required to complete this analysis.';
        }
        if ($requiresPassFail && $passFail === null) {
            $errors['result_pass_fail'] = 'Select Passed or Failed to complete this analysis.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $wasCompleted = $analysis->status === JobOrderAnalysisStatus::Completed;

        $fresh = DB::transaction(function () use ($analysis, $data, $analyst, $value, $passFail, $measurement, $unit, $method, $performedAt, $wasCompleted) {
            $payload = [
                'result_value' => $value,
                'result_pass_fail' => $passFail,
                'result_measurement' => $measurement,
                'result_unit' => $unit,
                'result_remarks' => $data['result_remarks'] ?? null,
                'result_method' => $method,
                'performed_at' => $performedAt,
                'status' => JobOrderAnalysisStatus::Completed,
                'completed_at' => $wasCompleted
                    ? ($analysis->completed_at ?? now())
                    : now(),
            ];

            // First completion takes ownership; later corrections keep the original assignee.
            if (! $wasCompleted) {
                $payload['assigned_to'] = $analyst->id;
            }

            $analysis->update($payload);

            return $analysis->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $analysis;
        });

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->job_order_id);

        return $fresh;
    }

    /**
     * Mark all assays for a sample (or the whole JO when sample_id is null) as completed
     * using already-drafted result values. Does not submit to Head.
     *
     * @return list<JobOrderAnalysis>
     */
    public function completeSampleAnalyses(JobOrder $jobOrder, User $analyst, ?int $sampleId = null): array
    {
        if ($jobOrder->status !== JobOrderStatus::InAnalysis) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs in analysis can complete sample results.',
            ]);
        }

        $jobOrder->loadMissing(['analyses.analysisType', 'analyses.assignee']);

        if ($sampleId !== null) {
            $lines = $jobOrder->analyses
                ->filter(fn (JobOrderAnalysis $line) => (int) $line->sample_id === $sampleId)
                ->values();
        } else {
            // Null sample_id: complete unscoped lines if any exist; otherwise the full JO.
            $unscoped = $jobOrder->analyses
                ->filter(fn (JobOrderAnalysis $line) => $line->sample_id === null)
                ->values();
            $lines = $unscoped->isNotEmpty() ? $unscoped : $jobOrder->analyses->values();
        }

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'sample_id' => 'No assays found for this sample.',
            ]);
        }

        $missing = [];
        foreach ($lines as $line) {
            $this->assertAnalystCanWorkOn($line, $analyst);

            if (blank($line->result_value)) {
                $missing[] = $line->name;
                continue;
            }

            if ($line->performed_at === null) {
                $missing[] = $line->name.' (date/time performed)';
                continue;
            }

            $requiresPassFail = (bool) ($line->analysisType?->requires_pass_fail);
            if ($requiresPassFail && $this->normalizedPassFail($line->result_pass_fail) === null) {
                $missing[] = $line->name.' (Pass/Fail)';
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'assays' => ($sampleId === null
                    ? 'Encode all results before completing this job order: '
                    : 'Encode all results before completing this sample: ').implode(', ', $missing).'.',
            ]);
        }

        $completed = DB::transaction(function () use ($lines, $analyst) {
            $freshLines = [];

            foreach ($lines as $line) {
                $requiresPassFail = (bool) ($line->analysisType?->requires_pass_fail);
                $passFail = $requiresPassFail
                    ? $this->normalizedPassFail($line->result_pass_fail)
                    : null;
                $wasCompleted = $line->status === JobOrderAnalysisStatus::Completed;

                $payload = [
                    'result_pass_fail' => $passFail,
                    'status' => JobOrderAnalysisStatus::Completed,
                    'completed_at' => $wasCompleted
                        ? ($line->completed_at ?? now())
                        : now(),
                ];

                if (! $wasCompleted) {
                    $payload['assigned_to'] = $analyst->id;
                }

                $line->update($payload);
                $freshLines[] = $line->fresh(['jobOrder', 'analysisType', 'assignee']) ?? $line;
            }

            return $freshLines;
        });

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_ANALYST,
        ], $jobOrder->id);

        return $completed;
    }

    /**
     * @param  list<array{name?: mixed, prc_id?: mixed}>|null  $signatories
     */
    public function submitForReview(JobOrder $jobOrder, User $analyst, ?array $signatories = null): JobOrder
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

        $resultForm = $this->reports->matchingControlledForm($jobOrder);
        $updates = ['status' => JobOrderStatus::PendingReview];

        if ($resultForm !== null) {
            if ($signatories === null || $signatories === []) {
                throw ValidationException::withMessages([
                    'signatories' => 'Enter the analyst name and PRC before sending to Head.',
                ]);
            }

            $updates['result_signatories'] = ResultSignatories::normalizeForStorage(
                $signatories,
                $resultForm,
            );
        }

        $jobOrder->update($updates);

        User::role(['head_analysis', 'admin'])->get()->each(
            fn (User $user) => $user->notify(new JobOrderPendingReview($jobOrder))
        );

        $fresh = $jobOrder->fresh(['analyses.assignee', 'packages']) ?? $jobOrder;
        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_HEAD,
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->id);

        return $fresh;
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
            $summary = $this->reports->forJobOrder($job, $user, withValues: false)->summary();
            $previewUrl = "/analyst/job-orders/{$job->id}/result-report";

            return [
                'id' => $job->id,
                'reference_no' => $job->reference_no,
                'customer_name' => $job->customer_name,
                'can_submit' => $incomplete->isEmpty(),
                'can_preview' => $summary['can_preview'],
                'preview_message' => $summary['message'],
                'preview_url' => $previewUrl,
                'missing' => $incomplete->pluck('name')->values()->all(),
                'review_notes' => $job->review_notes,
                'signatory' => $this->consolidationSignatoryPayload($job),
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
     * @return array<string, mixed>|null
     */
    private function consolidationSignatoryPayload(JobOrder $job): ?array
    {
        $form = $this->reports->matchingControlledForm($job);
        if ($form === null) {
            return null;
        }

        $payload = ResultSignatories::manifestPayload(
            $job,
            $form,
            "/analyst/job-orders/{$job->id}/result-signatories",
            $job->analyses,
        );

        // Submit posts signatories with send-to-head; save_url is only for later edits.
        return $payload;
    }

    /**
     * Result forms the designated analyst may preview/print and edit signatories
     * after Send to Head (pending review) or after Head release.
     *
     * @return list<array<string, mixed>>
     */
    public function releasedResultPrintsFor(User $user): array
    {
        $jobs = JobOrder::query()
            ->whereIn('status', [
                JobOrderStatus::PendingReview,
                JobOrderStatus::ReadyForPickup,
            ])
            ->with(['analyses', 'packages'])
            ->latest('id')
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
            $summary = $this->reports->forJobOrder($job, $user, withValues: false)->summary();
            $form = $this->reports->matchingControlledForm($job);
            $previewUrl = "/analyst/job-orders/{$job->id}/result-report";

            return [
                'id' => $job->id,
                'reference_no' => $job->reference_no,
                'customer_name' => $job->customer_name,
                'job_status' => $job->status->value,
                'can_print' => (bool) ($summary['can_print'] ?? false),
                'can_preview' => (bool) ($summary['can_preview'] ?? false),
                'print_url' => $previewUrl,
                'preview_url' => $previewUrl,
                'signatory' => $form
                    ? ResultSignatories::manifestPayload(
                        $job,
                        $form,
                        "/analyst/job-orders/{$job->id}/result-signatories",
                        $job->analyses,
                    )
                    : null,
            ];
        })->all();
    }

    private function normalizedPassFail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $normalized = mb_strtolower($text);

        return match (true) {
            in_array($normalized, ['passed', 'pass'], true) => 'Passed',
            in_array($normalized, ['failed', 'fail'], true) => 'Failed',
            default => null,
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

    private function parsePerformedAt(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        // datetime-local sends "Y-m-d\TH:i" without a zone — interpret in lab time.
        $normalized = str_replace(' ', 'T', $text);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $normalized) === 1) {
            $normalized = substr($normalized, 0, 16);
        }

        $tz = (string) config('app.lab_timezone', config('app.timezone', 'Asia/Manila'));

        try {
            // Persist in app timezone (UTC): wall clock from datetime-local is lab-local.
            return Carbon::createFromFormat('Y-m-d\TH:i', $normalized, $tz)->utc();
        } catch (\Throwable) {
            try {
                return Carbon::parse($text, $tz)->utc();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'performed_at' => 'Enter a valid date and time for when this test was performed.',
                ]);
            }
        }
    }

    private function assertAnalystCanWorkOn(JobOrderAnalysis $analysis, User $analyst): void
    {
        $analysis->loadMissing(['jobOrder', 'analysisType']);

        $jobStatus = $analysis->jobOrder?->status;
        $allowed = [
            JobOrderStatus::InAnalysis,
            JobOrderStatus::PendingReview,
            JobOrderStatus::ReadyForPickup,
        ];

        if (! in_array($jobStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'analysis' => 'This job is not available for analysis encoding. Receiving must finalize pricing and mark it received first.',
            ]);
        }

        if ($analyst->hasRole('admin')) {
            return;
        }

        if ((int) $analysis->assigned_to === (int) $analyst->id) {
            return;
        }

        $typeId = $analysis->analysis_type_id;
        if ($typeId && $analyst->analysisTypes()->where('analysis_types.id', $typeId)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'analysis' => 'You are not qualified for this analysis type. Ask an admin to update Assignments.',
        ]);
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

        $fresh = $jobOrder->fresh(['samples', 'analyses.assignee', 'packages.signatory', 'reviewer']) ?? $jobOrder;

        $this->customerEmails->queueResultsReady($fresh, $reviewer);

        $this->notifyResultsReleased($fresh);

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_HEAD,
            LabQueueUpdated::SCOPE_RECEIVING,
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->id);

        return $fresh;
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
        if ($jobOrder->status !== JobOrderStatus::PendingReview || $jobOrder->reviewed_at !== null) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs awaiting result release can be returned. Released results cannot be returned.',
            ]);
        }

        $jobOrder = DB::transaction(function () use ($jobOrder, $analysisIds, $notes) {
            $ids = collect($analysisIds)->unique()->values()->all();

            $analyses = $jobOrder->analyses()->whereIn('id', $ids)->get();

            $analyses->each(function (JobOrderAnalysis $analysis) {
                $analysis->update([
                    'result_value' => null,
                    'result_pass_fail' => null,
                    'result_measurement' => null,
                    'result_unit' => null,
                    'result_remarks' => null,
                    'result_method' => null,
                    'performed_at' => null,
                    'completed_at' => null,
                    'status' => JobOrderAnalysisStatus::Returned,
                ]);

                if ($analysis->assignee instanceof User) {
                    $analysis->assignee->notify(new AnalysisReturned($analysis->jobOrder, $analysis));
                }
            });

            $jobOrder->update([
                'status' => JobOrderStatus::InAnalysis,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => $notes,
                'result_signatories' => null,
            ]);

            return $jobOrder->fresh(['samples', 'analyses.assignee']) ?? $jobOrder;
        });

        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_ANALYST,
            LabQueueUpdated::SCOPE_HEAD,
        ], $jobOrder->id);

        return $jobOrder;
    }

    private function notifyResultsReleased(JobOrder $jobOrder): void
    {
        $jobOrder->loadMissing(['analyses', 'packages']);

        $signatoryIds = $jobOrder->packages
            ->pluck('signatory_user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $analystIds = $signatoryIds->isNotEmpty()
            ? $signatoryIds
            : $jobOrder->analyses
                ->pluck('assigned_to')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

        $notifiedIds = [];

        if ($analystIds->isNotEmpty()) {
            User::query()
                ->whereIn('id', $analystIds->all())
                ->get()
                ->each(function (User $user) use ($jobOrder, &$notifiedIds) {
                    $user->notify(new ResultsReleased($jobOrder, ResultsReleased::AUDIENCE_ANALYST));
                    $notifiedIds[(int) $user->id] = true;
                });
        }

        User::role(['receiving', 'admin'])
            ->get()
            ->each(function (User $user) use ($jobOrder, $notifiedIds) {
                if (isset($notifiedIds[(int) $user->id])) {
                    return;
                }

                $user->notify(new ResultsReleased($jobOrder, ResultsReleased::AUDIENCE_RECEIVING));
            });
    }
}
