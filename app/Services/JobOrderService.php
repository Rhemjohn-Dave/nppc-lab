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
use App\Support\OfficialAnalysisCatalog;
use App\Support\ResultFooter;
use App\Support\ResultSignatories;
use App\Support\SampleControlNumber;
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
     * Receiving bulk wizard: save customer + samples without tests (resume later).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{sample_code?: mixed, description?: mixed, matrix?: mixed, quantity?: mixed, unit?: mixed, remarks?: mixed}>  $samples
     */
    public function saveBulkDraft(array $data, array $samples, ?JobOrder $existing = null): JobOrder
    {
        return DB::transaction(function () use ($data, $samples, $existing) {
            Customer::rememberFromIntake($data);

            if ($existing !== null) {
                abort_unless($existing->is_bulk_draft, 403);
                abort_unless($existing->status === JobOrderStatus::DraftSubmitted, 422);

                $existing->update([
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
                    'sampling_site' => $data['sampling_site'] ?? null,
                    'specimen' => $data['specimen'] ?? null,
                    'payment_mode' => $data['payment_mode'] ?? null,
                    'payment_terms' => $data['payment_terms'] ?? null,
                    'other_tests' => $data['other_tests'] ?? null,
                    'see_attached_file' => (bool) ($data['see_attached_file'] ?? false),
                    'is_bulk_draft' => true,
                ]);

                $existing->analyses()->delete();
                $existing->packageLinks()->delete();
                $existing->samples()->delete();

                foreach ($samples as $index => $sample) {
                    $existing->samples()->create([
                        'sample_code' => $sample['sample_code'] ?? null,
                        'description' => $sample['description'] ?? null,
                        'matrix' => $sample['matrix'] ?? null,
                        'quantity' => $sample['quantity'] ?? null,
                        'unit' => $sample['unit'] ?? null,
                        'remarks' => $sample['remarks'] ?? null,
                        'sort_order' => $index,
                    ]);
                }

                $existing->recalculateTotal();
                LabQueueUpdated::notify([LabQueueUpdated::SCOPE_RECEIVING], $existing->id);

                return $existing->fresh(['samples', 'analyses']) ?? $existing;
            }

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
                'sampling_site' => $data['sampling_site'] ?? null,
                'specimen' => $data['specimen'] ?? null,
                'payment_mode' => $data['payment_mode'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'other_tests' => $data['other_tests'] ?? null,
                'see_attached_file' => (bool) ($data['see_attached_file'] ?? false),
                'status' => JobOrderStatus::DraftSubmitted,
                'is_bulk_draft' => true,
                'total_cost' => 0,
            ]);

            foreach ($samples as $index => $sample) {
                $jobOrder->samples()->create([
                    'sample_code' => $sample['sample_code'] ?? null,
                    'description' => $sample['description'] ?? null,
                    'matrix' => $sample['matrix'] ?? null,
                    'quantity' => $sample['quantity'] ?? null,
                    'unit' => $sample['unit'] ?? null,
                    'remarks' => $sample['remarks'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            LabQueueUpdated::notify([LabQueueUpdated::SCOPE_RECEIVING], $jobOrder->id);

            return $jobOrder->fresh(['samples', 'analyses']) ?? $jobOrder;
        });
    }

    /**
     * Finish a bulk draft: replace samples and attach shared tests.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{sample_code?: mixed, description?: mixed, matrix?: mixed, quantity?: mixed, unit?: mixed, remarks?: mixed, package_ids?: list<int>, analysis_type_ids?: list<int>}>  $samples
     */
    public function finalizeBulkDraft(JobOrder $jobOrder, array $data, array $samples): JobOrder
    {
        abort_unless($jobOrder->is_bulk_draft, 403);
        abort_unless($jobOrder->status === JobOrderStatus::DraftSubmitted, 422);

        return DB::transaction(function () use ($jobOrder, $data, $samples) {
            Customer::rememberFromIntake($data);

            $jobOrder->update([
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
                'sampling_site' => $data['sampling_site'] ?? null,
                'specimen' => $data['specimen'] ?? null,
                'payment_mode' => $data['payment_mode'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'other_tests' => $data['other_tests'] ?? null,
                'see_attached_file' => (bool) ($data['see_attached_file'] ?? false),
                'is_bulk_draft' => false,
            ]);

            $jobOrder->analyses()->delete();
            $jobOrder->packageLinks()->delete();
            $jobOrder->samples()->delete();

            $createdSamples = [];
            foreach ($samples as $index => $sample) {
                $createdSamples[] = $jobOrder->samples()->create([
                    'sample_code' => $sample['sample_code'] ?? null,
                    'description' => $sample['description'] ?? null,
                    'matrix' => $sample['matrix'] ?? null,
                    'quantity' => $sample['quantity'] ?? null,
                    'unit' => $sample['unit'] ?? null,
                    'remarks' => $sample['remarks'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            $perSampleSelections = [];
            foreach ($createdSamples as $index => $_) {
                $perSampleSelections[] = [
                    'package_ids' => $samples[$index]['package_ids'] ?? [],
                    'analysis_type_ids' => $samples[$index]['analysis_type_ids'] ?? [],
                ];
            }

            $this->attachAnalysesForSamples(
                $jobOrder,
                $createdSamples,
                $perSampleSelections,
                $data['other_tests'] ?? null,
                true,
            );

            $jobOrder->recalculateTotal();

            User::role(['receiving', 'admin'])->get()->each(
                fn (User $user) => $user->notify(new JobOrderSubmitted($jobOrder))
            );

            LabQueueUpdated::notify([LabQueueUpdated::SCOPE_RECEIVING], $jobOrder->id);
            $this->customerEmails->queueRequestReceived($jobOrder);

            return $jobOrder->fresh(['samples', 'analyses']) ?? $jobOrder;
        });
    }

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
                'see_attached_file' => (bool) ($data['see_attached_file'] ?? false),
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
                    'accreditation_footnote' => OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier(
                        $type->code,
                    ),
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

    /**
     * Multi-sample (bulk) JOs may fill sample names/details until Send to analysts.
     */
    public function canEncodeSampleDetails(JobOrder $jobOrder): bool
    {
        if ($jobOrder->is_bulk_draft) {
            return false;
        }

        $jobOrder->loadMissing('samples');

        if ($jobOrder->samples->count() < 2) {
            return false;
        }

        return in_array($jobOrder->status, [
            JobOrderStatus::DraftSubmitted,
            JobOrderStatus::PendingJoApproval,
            JobOrderStatus::Priced,
            JobOrderStatus::JoApproved,
        ], true);
    }

    public function samplesNamedCount(JobOrder $jobOrder): int
    {
        $jobOrder->loadMissing('samples');

        return $jobOrder->samples
            ->filter(fn ($sample) => filled(trim((string) ($sample->description ?? ''))))
            ->count();
    }

    public function samplesEncodeComplete(JobOrder $jobOrder): bool
    {
        $jobOrder->loadMissing('samples');
        $count = $jobOrder->samples->count();
        if ($count < 2) {
            return true;
        }

        return $this->samplesNamedCount($jobOrder) === $count;
    }

    /**
     * Update sample metadata only (no add/remove, no test changes).
     *
     * @param  list<array{id: int, sample_code?: mixed, description?: mixed, matrix?: mixed, quantity?: mixed, unit?: mixed, location?: mixed, sample_point?: mixed, collected_time?: mixed, remarks?: mixed}>  $rows
     */
    public function updateBulkSampleDetails(JobOrder $jobOrder, array $rows): JobOrder
    {
        if (! $this->canEncodeSampleDetails($jobOrder)) {
            throw ValidationException::withMessages([
                'job_order' => 'Sample details can only be encoded on multi-sample job orders before Send to analysts.',
            ]);
        }

        $jobOrder->loadMissing('samples');
        $byId = $jobOrder->samples->keyBy(fn ($sample) => (int) $sample->id);

        if (count($rows) !== $byId->count()) {
            throw ValidationException::withMessages([
                'samples' => 'Submit every sample on this job order. Sample count cannot change after create.',
            ]);
        }

        $seen = [];
        foreach ($rows as $index => $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || ! $byId->has($id)) {
                throw ValidationException::withMessages([
                    "samples.{$index}.id" => 'Unknown sample on this job order.',
                ]);
            }
            if (isset($seen[$id])) {
                throw ValidationException::withMessages([
                    "samples.{$index}.id" => 'Duplicate sample id.',
                ]);
            }
            $seen[$id] = true;
        }

        return DB::transaction(function () use ($jobOrder, $rows, $byId) {
            foreach ($rows as $row) {
                $sample = $byId->get((int) $row['id']);
                $description = trim((string) ($row['description'] ?? ''));
                $code = trim((string) ($row['sample_code'] ?? ''));
                $matrix = trim((string) ($row['matrix'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                $quantity = $row['quantity'] ?? null;

                $sample->update([
                    'sample_code' => $code !== '' ? $code : null,
                    'description' => $description !== '' ? $description : null,
                    'matrix' => $matrix !== '' ? $matrix : $sample->matrix,
                    'quantity' => $quantity !== null && $quantity !== ''
                        ? $quantity
                        : $sample->quantity,
                    'unit' => $unit !== '' ? $unit : $sample->unit,
                    'remarks' => $this->composeBulkSampleRemarks($row),
                ]);
            }

            LabQueueUpdated::notify([LabQueueUpdated::SCOPE_RECEIVING], $jobOrder->id);

            return $jobOrder->fresh(['samples', 'analyses']) ?? $jobOrder;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function composeBulkSampleRemarks(array $row): ?string
    {
        $parts = [];
        $location = trim((string) ($row['location'] ?? ''));
        $point = trim((string) ($row['sample_point'] ?? ''));
        $time = trim((string) ($row['collected_time'] ?? ''));

        if ($location !== '') {
            $parts[] = 'Location: '.$location;
        }
        if ($point !== '') {
            $parts[] = 'Sample point: '.$point;
        }
        if ($time !== '') {
            $parts[] = 'Collected: '.$time;
        }

        return $parts === [] ? null : implode(' | ', $parts);
    }

    public function receive(JobOrder $jobOrder, User $receiver): JobOrder
    {
        if (! $jobOrder->status->canReceiveSamples()) {
            throw ValidationException::withMessages([
                'job_order' => 'Head must approve this Job Order before it can be sent to analysts.',
            ]);
        }

        $jobOrder->loadMissing('samples');
        if ($jobOrder->samples->count() >= 2 && ! $this->samplesEncodeComplete($jobOrder)) {
            $missing = $jobOrder->samples
                ->filter(fn ($sample) => blank(trim((string) ($sample->description ?? ''))))
                ->count();
            throw ValidationException::withMessages([
                'samples' => "Encode a Name for every sample before Send to analysts ({$missing} still blank).",
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
     * @param  array{result_value?: ?string, result_raw?: ?string, result_finish?: ?string, result_pass_fail?: ?string, result_measurement?: ?string, result_unit?: ?string, result_remarks?: ?string, result_method?: ?string, performed_at?: mixed}  $data
     */
    public function saveAnalysisDraft(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);
        $analysis->loadMissing('analysisType');

        $code = $analysis->analysisType?->code;
        $rawFinish = OfficialAnalysisCatalog::usesRawFinishResults($code);
        $residualCl = OfficialAnalysisCatalog::usesResidualChlorineResults($code);

        if ($residualCl) {
            [$raw, $finish, $chlorinated] = OfficialAnalysisCatalog::normalizeResidualChlorineEncode(
                $this->optionalText($data['result_raw'] ?? null),
                $this->optionalText($data['result_finish'] ?? null),
                $this->optionalText($data['result_chlorinated'] ?? null),
            );
            $value = OfficialAnalysisCatalog::summarizeResidualChlorineResults($finish, $chlorinated, $raw);
            $remarksRaw = null;
            $remarksFinish = $finish !== null
                ? $this->optionalText($data['result_remarks_finish'] ?? null)
                : null;
            $remarksChlorinated = $chlorinated !== null
                ? $this->optionalText($data['result_remarks_chlorinated'] ?? null)
                : null;
            $remarks = OfficialAnalysisCatalog::summarizeResidualChlorineResults(
                $remarksFinish,
                $remarksChlorinated,
            );
        } else {
            $raw = $rawFinish ? $this->optionalText($data['result_raw'] ?? null) : null;
            $finish = $rawFinish ? $this->optionalText($data['result_finish'] ?? null) : null;
            $chlorinated = null;
            $value = $rawFinish
                ? OfficialAnalysisCatalog::summarizeRawFinishResults($raw, $finish)
                : $this->optionalText($data['result_value'] ?? null);
            $remarksRaw = $rawFinish ? $this->optionalText($data['result_remarks_raw'] ?? null) : null;
            $remarksFinish = $rawFinish
                ? $this->optionalText($data['result_remarks_finish'] ?? null)
                : null;
            $remarksChlorinated = null;
            $remarks = $rawFinish
                ? OfficialAnalysisCatalog::summarizeRawFinishResults($remarksRaw, $remarksFinish)
                : ($data['result_remarks'] ?? null);
        }
        $requiresPassFail = (bool) ($analysis->analysisType?->requires_pass_fail);
        $passFail = $requiresPassFail
            ? $this->normalizedPassFail($data['result_pass_fail'] ?? null)
            : null;
        $measurement = $this->optionalText($data['result_measurement'] ?? null);
        $unit = $this->optionalText($data['result_unit'] ?? null);
        $method = $this->optionalText($data['result_method'] ?? null);
        $accreditationFootnote = $this->normalizedAccreditationFootnote(
            $data['accreditation_footnote'] ?? null,
            $code,
            $analysis->accreditation_footnote,
        );
        $performedAt = $this->parsePerformedAt($data['performed_at'] ?? null)
            ?? $analysis->performed_at;

        if ($requiresPassFail
            && ($data['result_pass_fail'] ?? null) !== null
            && trim((string) $data['result_pass_fail']) !== ''
            && $passFail === null) {
            throw ValidationException::withMessages([
                'result_pass_fail' => 'Select Passed or Failed.',
            ]);
        }

        $hasResultContent = $value !== null
            || $raw !== null
            || $finish !== null
            || $chlorinated !== null
            || $passFail !== null
            || $measurement !== null
            || $unit !== null
            || $this->optionalText(is_string($remarks) ? $remarks : null) !== null
            || $remarksRaw !== null
            || $remarksFinish !== null
            || $remarksChlorinated !== null
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
            'result_raw' => ($rawFinish || $residualCl) ? $raw : null,
            'result_finish' => ($rawFinish || $residualCl) ? $finish : null,
            'result_chlorinated' => $residualCl ? $chlorinated : null,
            'result_pass_fail' => $passFail,
            'result_measurement' => $measurement,
            'result_unit' => $unit,
            'result_remarks' => $remarks,
            'result_remarks_raw' => $rawFinish ? $remarksRaw : null,
            'result_remarks_finish' => ($rawFinish || $residualCl) ? $remarksFinish : null,
            'result_remarks_chlorinated' => $residualCl ? $remarksChlorinated : null,
            'result_method' => $method,
            'accreditation_footnote' => $accreditationFootnote,
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
     * @param  array{result_value?: string, result_raw?: ?string, result_finish?: ?string, result_pass_fail?: ?string, result_measurement?: ?string, result_unit?: ?string, result_remarks?: ?string, result_method?: ?string, performed_at?: mixed}  $data
     */
    public function completeAnalysis(JobOrderAnalysis $analysis, array $data, User $analyst): JobOrderAnalysis
    {
        $this->assertAnalystCanWorkOn($analysis, $analyst);
        $analysis->loadMissing(['analysisType', 'jobOrder.packages', 'jobOrder.analyses', 'jobOrder.samples']);

        $jobOrder = $analysis->jobOrder;
        if ($jobOrder instanceof JobOrder) {
            $resultForm = $this->reports->matchingControlledForm($jobOrder);
            if (ResultFooter::formRequiresLabAtmosphere($resultForm)
                && ! ResultFooter::isAtmosphereComplete($jobOrder, $resultForm)) {
                throw ValidationException::withMessages([
                    'result_footer' => 'Enter laboratory temperature and humidity before encoding results.',
                ]);
            }
        }

        $code = $analysis->analysisType?->code;
        $rawFinish = OfficialAnalysisCatalog::usesRawFinishResults($code);
        $residualCl = OfficialAnalysisCatalog::usesResidualChlorineResults($code);

        if ($residualCl) {
            [$raw, $finish, $chlorinated] = OfficialAnalysisCatalog::normalizeResidualChlorineEncode(
                $this->optionalText($data['result_raw'] ?? null),
                $this->optionalText($data['result_finish'] ?? null),
                $this->optionalText($data['result_chlorinated'] ?? null),
            );
            $value = OfficialAnalysisCatalog::summarizeResidualChlorineResults($finish, $chlorinated, $raw);
            $remarksRaw = null;
            $remarksFinish = $finish !== null
                ? $this->optionalText($data['result_remarks_finish'] ?? null)
                : null;
            $remarksChlorinated = $chlorinated !== null
                ? $this->optionalText($data['result_remarks_chlorinated'] ?? null)
                : null;
            $remarks = OfficialAnalysisCatalog::summarizeResidualChlorineResults(
                $remarksFinish,
                $remarksChlorinated,
            );
        } else {
            $raw = $rawFinish ? $this->optionalText($data['result_raw'] ?? null) : null;
            $finish = $rawFinish ? $this->optionalText($data['result_finish'] ?? null) : null;
            $chlorinated = null;
            $value = $rawFinish
                ? OfficialAnalysisCatalog::summarizeRawFinishResults($raw, $finish)
                : $this->optionalText($data['result_value'] ?? null);
            $remarksRaw = $rawFinish ? $this->optionalText($data['result_remarks_raw'] ?? null) : null;
            $remarksFinish = $rawFinish
                ? $this->optionalText($data['result_remarks_finish'] ?? null)
                : null;
            $remarksChlorinated = null;
            $remarks = $rawFinish
                ? OfficialAnalysisCatalog::summarizeRawFinishResults($remarksRaw, $remarksFinish)
                : ($data['result_remarks'] ?? null);
        }
        $requiresPassFail = (bool) ($analysis->analysisType?->requires_pass_fail);
        $passFail = $requiresPassFail
            ? $this->normalizedPassFail($data['result_pass_fail'] ?? null)
            : null;
        $measurement = $this->optionalText($data['result_measurement'] ?? null);
        $unit = $this->optionalText($data['result_unit'] ?? null);
        $method = $this->optionalText($data['result_method'] ?? null);
        $accreditationFootnote = $this->normalizedAccreditationFootnote(
            $data['accreditation_footnote'] ?? null,
            $code,
            $analysis->accreditation_footnote,
        );
        $performedAt = $this->parsePerformedAt($data['performed_at'] ?? null)
            ?? $analysis->performed_at;

        $isPrep = $analysis->isSamplePreparation();
        $errors = [];
        if ($performedAt === null && ! $isPrep) {
            $errors['performed_at'] = 'Enter the date and time this test was performed.';
        }
        if ($rawFinish) {
            if (blank($raw) && blank($finish) && ! $isPrep) {
                $errors['result_raw'] = 'Enter Raw or Finish.';
                $errors['result_finish'] = 'Enter Raw or Finish.';
            }
        } elseif ($residualCl) {
            if (blank($raw) && blank($finish) && blank($chlorinated) && ! $isPrep) {
                $errors['result_raw'] = 'Select Finish, Chlorinated, or Raw.';
                $errors['result_finish'] = 'Select Finish, Chlorinated, or Raw.';
                $errors['result_chlorinated'] = 'Select Finish, Chlorinated, or Raw.';
            }
        } elseif (blank($value) && ! $isPrep) {
            $errors['result_value'] = 'A result value is required to complete this analysis.';
        }
        if ($requiresPassFail && $passFail === null) {
            $errors['result_pass_fail'] = 'Select Passed or Failed to complete this analysis.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $wasCompleted = $analysis->status === JobOrderAnalysisStatus::Completed;

        $fresh = DB::transaction(function () use ($analysis, $analyst, $value, $raw, $finish, $chlorinated, $rawFinish, $residualCl, $remarks, $remarksRaw, $remarksFinish, $remarksChlorinated, $passFail, $measurement, $unit, $method, $accreditationFootnote, $performedAt, $wasCompleted) {
            $payload = [
                'result_value' => $value,
                'result_raw' => ($rawFinish || $residualCl) ? $raw : null,
                'result_finish' => ($rawFinish || $residualCl) ? $finish : null,
                'result_chlorinated' => $residualCl ? $chlorinated : null,
                'result_pass_fail' => $passFail,
                'result_measurement' => $measurement,
                'result_unit' => $unit,
                'result_remarks' => $remarks,
                'result_remarks_raw' => $rawFinish ? $remarksRaw : null,
                'result_remarks_finish' => ($rawFinish || $residualCl) ? $remarksFinish : null,
                'result_remarks_chlorinated' => $residualCl ? $remarksChlorinated : null,
                'result_method' => $method,
                'accreditation_footnote' => $accreditationFootnote,
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

            if ($line->isSamplePreparation()) {
                continue;
            }

            if (OfficialAnalysisCatalog::usesRawFinishResults($line->analysisType?->code)) {
                if (blank($line->result_raw) && blank($line->result_finish)) {
                    $missing[] = $line->name.' (Raw or Finish)';
                    continue;
                }
            } elseif (OfficialAnalysisCatalog::usesResidualChlorineResults($line->analysisType?->code)) {
                if (blank($line->result_finish) && blank($line->result_chlorinated) && blank($line->result_raw)) {
                    $missing[] = $line->name.' (Finish, Chlorinated, or Raw)';
                    continue;
                }
            } elseif (blank($line->result_value)) {
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
     * Ready for Head: completed with a result, or a Sample Preparation line (no result is encoded).
     */
    private function lineHasResult(JobOrderAnalysis $line): bool
    {
        if ($line->isSamplePreparation()) {
            return true;
        }

        return $line->status === JobOrderAnalysisStatus::Completed
            && filled($line->result_value);
    }

    /**
     * @param  list<array{name?: mixed, prc_id?: mixed}>|null  $signatories
     */
    public function submitForReview(
        JobOrder $jobOrder,
        User $analyst,
        ?array $signatories = null,
        ?int $sampleId = null,
    ): JobOrder {
        if ($sampleId !== null) {
            return $this->submitSampleForReview($jobOrder, $analyst, $sampleId, $signatories);
        }

        if ($jobOrder->status !== JobOrderStatus::InAnalysis) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs still in analysis can be sent to Head.',
            ]);
        }

        $jobOrder->load(['analyses.assignee', 'analyses.analysisType', 'packages', 'samples']);
        $this->assertUserCanSubmitForReview($jobOrder, $analyst);

        $incomplete = $jobOrder->analyses->reject(
            fn (JobOrderAnalysis $line) => $this->lineHasResult($line)
        );

        if ($incomplete->isNotEmpty()) {
            $names = $incomplete->pluck('name')->filter()->implode(', ');

            throw ValidationException::withMessages([
                'job_order' => 'Encode all results before sending to Head'
                    .($names !== '' ? ': '.$names : '.'),
            ]);
        }

        foreach ($jobOrder->analyses as $line) {
            if ($line->isSamplePreparation() && $line->status !== JobOrderAnalysisStatus::Completed) {
                $line->update([
                    'status' => JobOrderAnalysisStatus::Completed,
                    'completed_at' => now(),
                    'assigned_to' => $line->assigned_to ?? $analyst->id,
                ]);
            }
        }

        $updates = $this->applySubmitSignatories(
            $jobOrder,
            $analyst,
            $signatories,
            sampleId: null,
        );
        $updates['status'] = JobOrderStatus::PendingReview;

        $jobOrder->update($updates);

        $now = now();
        foreach ($jobOrder->samples as $sample) {
            if ($sample->submitted_for_review_at === null) {
                $sample->update(['submitted_for_review_at' => $now]);
            }
        }

        User::role(['head_analysis', 'admin'])->get()->each(
            fn (User $user) => $user->notify(new JobOrderPendingReview($jobOrder))
        );

        $fresh = $jobOrder->fresh(['analyses.assignee', 'packages', 'samples']) ?? $jobOrder;
        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_HEAD,
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->id);

        return $fresh;
    }

    /**
     * Send one sample's completed assays to Head while other samples stay encodable.
     *
     * @param  list<array{name?: mixed, prc_id?: mixed}>|null  $signatories
     */
    public function submitSampleForReview(
        JobOrder $jobOrder,
        User $analyst,
        int $sampleId,
        ?array $signatories = null,
    ): JobOrder {
        if ($jobOrder->status !== JobOrderStatus::InAnalysis) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs still in analysis can be sent to Head.',
            ]);
        }

        $jobOrder->load(['analyses.assignee', 'analyses.analysisType', 'packages', 'samples']);
        $this->assertUserCanSubmitForReview($jobOrder, $analyst);

        $sample = $jobOrder->samples->firstWhere('id', $sampleId);
        if ($sample === null) {
            throw ValidationException::withMessages([
                'sample_id' => 'Sample not found on this job order.',
            ]);
        }

        if ($sample->submitted_for_review_at !== null) {
            throw ValidationException::withMessages([
                'sample_id' => 'This sample was already sent to Head.',
            ]);
        }

        $lines = $jobOrder->analyses
            ->filter(fn (JobOrderAnalysis $line) => (int) $line->sample_id === $sampleId)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'sample_id' => 'No assays found for this sample.',
            ]);
        }

        $incomplete = $lines->reject(fn (JobOrderAnalysis $line) => $this->lineHasResult($line));
        if ($incomplete->isNotEmpty()) {
            $names = $incomplete->pluck('name')->filter()->implode(', ');

            throw ValidationException::withMessages([
                'job_order' => 'Encode all results for this sample before sending to Head'
                    .($names !== '' ? ': '.$names : '.'),
            ]);
        }

        foreach ($lines as $line) {
            if ($line->isSamplePreparation() && $line->status !== JobOrderAnalysisStatus::Completed) {
                $line->update([
                    'status' => JobOrderAnalysisStatus::Completed,
                    'completed_at' => now(),
                    'assigned_to' => $line->assigned_to ?? $analyst->id,
                ]);
            }
        }

        $updates = $this->applySubmitSignatories(
            $jobOrder,
            $analyst,
            $signatories,
            sampleId: $sampleId,
        );

        $sample->update(['submitted_for_review_at' => now()]);
        $jobOrder->unsetRelation('samples');
        $jobOrder->load('samples');

        if ($jobOrder->samples->every(fn ($row) => $row->submitted_for_review_at !== null)) {
            $updates['status'] = JobOrderStatus::PendingReview;
        }

        if ($updates !== []) {
            $jobOrder->update($updates);
        }

        User::role(['head_analysis', 'admin'])->get()->each(
            fn (User $user) => $user->notify(new JobOrderPendingReview($jobOrder))
        );

        $fresh = $jobOrder->fresh(['analyses.assignee', 'packages', 'samples']) ?? $jobOrder;
        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_HEAD,
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->id);

        return $fresh;
    }

    /**
     * @param  list<array{name?: mixed, prc_id?: mixed}>|null  $signatories
     * @return array<string, mixed>
     */
    private function applySubmitSignatories(
        JobOrder $jobOrder,
        User $analyst,
        ?array $signatories,
        ?int $sampleId,
    ): array {
        $updates = [];
        $resultForm = $this->reports->matchingControlledForm($jobOrder);
        if ($resultForm === null) {
            return $updates;
        }

        $stored = is_array($jobOrder->result_signatories) ? $jobOrder->result_signatories : null;

        if ($signatories !== null && $signatories !== []) {
            $normalized = ResultSignatories::normalizeForStorage(
                $signatories,
                $resultForm,
                $jobOrder,
            );
            $stored = ResultSignatories::mergeIntoStorage(
                $stored,
                $resultForm,
                $normalized,
            );
            $updates['result_signatories'] = $stored;
        }

        $requiredForms = $this->reports
            ->reportsForJobOrder($jobOrder, $analyst, withValues: false)
            ->when(
                $sampleId !== null,
                fn ($reports) => $reports->filter(
                    fn ($report) => (int) ($report->sampleId ?? 0) === $sampleId
                ),
            )
            ->map(fn ($report) => $report->controlledForm)
            ->filter()
            ->unique(fn ($form) => $form->form_code)
            ->values();

        $missing = ResultSignatories::missingFormCodes(
            $updates['result_signatories'] ?? $stored,
            $requiredForms,
        );

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'signatories' => 'Confirm analyst signatories for every result sheet before sending to Head'
                    .(count($missing) > 1 ? ' ('.implode(', ', $missing).').' : '.'),
            ]);
        }

        return $updates;
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
            ->with(['analyses.assignee', 'analyses.analysisType', 'packages.signatory', 'samples'])
            ->latest('id')
            ->limit(40)
            ->get()
            ->filter(fn (JobOrder $job) => $this->userCanSubmitForReview($job, $user))
            ->values();

        return $jobs->map(function (JobOrder $job) use ($user) {
            $sampleCount = $job->samples->count();
            $sampleRows = $job->samples->values()->map(function ($sample, int $index) use ($job, $sampleCount) {
                $lines = $job->analyses
                    ->filter(fn (JobOrderAnalysis $line) => (int) $line->sample_id === (int) $sample->id)
                    ->values();
                $incomplete = $lines->reject(fn (JobOrderAnalysis $line) => $this->lineHasResult($line));
                $submitted = $sample->submitted_for_review_at !== null;
                $released = $sample->released_at !== null;
                $sampleComplete = $lines->isNotEmpty() && $incomplete->isEmpty();
                // Preview stays available after Send to Head as long as this sample's assays are complete.
                $canPreviewSample = $sampleComplete;

                return [
                    'id' => $sample->id,
                    'sample_code' => $sample->sample_code,
                    'description' => $sample->description,
                    'control_number' => SampleControlNumber::forIndex(
                        (string) $job->reference_no,
                        $index,
                        $sampleCount,
                    ),
                    'can_submit' => ! $submitted && $sampleComplete,
                    'can_preview' => $canPreviewSample,
                    'preview_url' => $canPreviewSample
                        ? "/analyst/job-orders/{$job->id}/result-report?sample_id={$sample->id}"
                        : null,
                    'submitted' => $submitted,
                    'released' => $released,
                    'missing' => $incomplete->pluck('name')->values()->all(),
                ];
            })->values()->all();

            // Legacy / unscoped lines (no sample_id): fall back to whole-JO completeness.
            $unscoped = $job->analyses->filter(fn (JobOrderAnalysis $line) => $line->sample_id === null);
            $unscopedIncomplete = $unscoped->reject(fn (JobOrderAnalysis $line) => $this->lineHasResult($line));
            $anySampleReady = collect($sampleRows)->contains(fn (array $row) => $row['can_submit'] === true);
            $allLinesReady = $job->analyses->every(fn (JobOrderAnalysis $line) => $this->lineHasResult($line));

            $incompleteNames = $job->analyses
                ->reject(fn (JobOrderAnalysis $line) => $this->lineHasResult($line))
                ->pluck('name')
                ->values()
                ->all();

            $summary = $this->reports->forJobOrder($job, $user, withValues: false)->summary();
            $anySamplePreview = collect($sampleRows)->contains(fn (array $row) => $row['can_preview'] === true);
            $previewUrl = "/analyst/job-orders/{$job->id}/result-report";

            return [
                'id' => $job->id,
                'reference_no' => $job->reference_no,
                'customer_name' => $job->customer_name,
                'can_submit' => $anySampleReady || ($sampleRows === [] && $allLinesReady),
                // Allow preview when any sample sheet is ready (not only when every sample is complete).
                'can_preview' => (bool) ($summary['can_preview'] || $anySamplePreview),
                'preview_message' => $summary['message'],
                'preview_url' => $previewUrl,
                'missing' => $incompleteNames,
                'review_notes' => $job->review_notes,
                'signatory' => $this->consolidationSignatoryPayload($job),
                'footer' => $this->consolidationFooterPayload($job),
                'samples' => $sampleRows,
                'unscoped_can_submit' => $unscoped->isNotEmpty() && $unscopedIncomplete->isEmpty(),
                'lines' => $job->analyses->map(fn (JobOrderAnalysis $line) => [
                    'id' => $line->id,
                    'sample_id' => $line->sample_id,
                    'name' => $line->name,
                    'assignee_name' => $line->assignee?->name,
                    'status' => $line->status->value,
                    'status_label' => $line->status->label(),
                    'result_value' => $line->result_value,
                    'completed' => $this->lineHasResult($line),
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
     * @return array<string, mixed>|null
     */
    private function consolidationFooterPayload(JobOrder $job): ?array
    {
        $form = $this->reports->matchingControlledForm($job);
        if ($form === null || ! ResultFooter::formSupportsFooter($form)) {
            return null;
        }

        return ResultFooter::manifestPayload(
            $job,
            $form,
            "/analyst/job-orders/{$job->id}/result-footer",
        );
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
            ->where(function ($query) {
                $query->whereIn('status', [
                    JobOrderStatus::PendingReview,
                    JobOrderStatus::ReadyForPickup,
                ])->orWhere(function ($inner) {
                    $inner->where('status', JobOrderStatus::InAnalysis)
                        ->whereHas('samples', function ($samples) {
                            $samples->whereNotNull('submitted_for_review_at');
                        });
                });
            })
            ->with(['analyses', 'packages', 'samples'])
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
            $reports = $this->reports->reportsForJobOrder($job, $user, withValues: false);
            $summary = $this->reports->forJobOrder($job, $user, withValues: false)->summary();
            $anyPreview = $reports->contains(fn ($report) => $report->canPreview());
            $form = $this->reports->matchingControlledForm($job);
            $previewUrl = "/analyst/job-orders/{$job->id}/result-report";
            $samplePreviews = $job->samples->values()->map(function ($sample) use ($job, $reports) {
                $report = $reports->first(
                    fn ($row) => $row->sampleId !== null && (int) $row->sampleId === (int) $sample->id,
                );
                $canPreview = $report?->canPreview() === true
                    || ($sample->submitted_for_review_at !== null && $job->analyses
                        ->where('sample_id', $sample->id)
                        ->every(fn (JobOrderAnalysis $line) => $this->lineHasResult($line)));

                return [
                    'id' => $sample->id,
                    'can_preview' => $canPreview,
                    'preview_url' => $canPreview
                        ? "/analyst/job-orders/{$job->id}/result-report?sample_id={$sample->id}"
                        : null,
                    'submitted' => $sample->submitted_for_review_at !== null,
                    'released' => $sample->released_at !== null,
                ];
            })->values()->all();

            return [
                'id' => $job->id,
                'reference_no' => $job->reference_no,
                'customer_name' => $job->customer_name,
                'job_status' => $job->status->value,
                'can_print' => (bool) ($summary['can_print'] ?? false),
                'can_preview' => (bool) (($summary['can_preview'] ?? false) || $anyPreview),
                'print_url' => $previewUrl,
                'preview_url' => $previewUrl,
                'samples' => $samplePreviews,
                'signatory' => $form
                    ? ResultSignatories::manifestPayload(
                        $job,
                        $form,
                        "/analyst/job-orders/{$job->id}/result-signatories",
                        $job->analyses,
                    )
                    : null,
                'footer' => $form && ResultFooter::formSupportsFooter($form)
                    ? ResultFooter::manifestPayload(
                        $job,
                        $form,
                        "/analyst/job-orders/{$job->id}/result-footer",
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

    /**
     * Persist FO2/FO2A accreditation mark. Empty string = explicit none; null input keeps current.
     */
    private function normalizedAccreditationFootnote(
        mixed $value,
        ?string $code,
        ?string $current,
    ): ?string {
        if (! OfficialAnalysisCatalog::isWastewaterPhysicoPanelCode($code)) {
            return $current;
        }

        if ($value === null) {
            return $current;
        }

        $text = trim((string) $value);
        if ($text === '' || strcasecmp($text, 'none') === 0) {
            return '';
        }

        if (! in_array($text, ['*', '**', '***'], true)) {
            throw ValidationException::withMessages([
                'accreditation_footnote' => 'Choose None, *, **, or ***.',
            ]);
        }

        return $text;
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

    public function sign(
        JobOrder $jobOrder,
        User $reviewer,
        ?string $notes = null,
        ?int $sampleId = null,
    ): JobOrder {
        if ($jobOrder->reviewed_at) {
            throw ValidationException::withMessages([
                'job_order' => 'This job order has already been signed.',
            ]);
        }

        $jobOrder->loadMissing('samples');

        if ($sampleId !== null) {
            return $this->signSample($jobOrder, $reviewer, $sampleId, $notes);
        }

        $awaitingPartial = $jobOrder->status === JobOrderStatus::InAnalysis
            && $jobOrder->samples->contains(
                fn ($sample) => $sample->submitted_for_review_at !== null && $sample->released_at === null
            );

        if ($jobOrder->status !== JobOrderStatus::PendingReview && ! $awaitingPartial) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs sent by the designated analyst can be signed.',
            ]);
        }

        // Whole-JO release: stamp every submitted sample, then finalize.
        $now = now();
        foreach ($jobOrder->samples as $sample) {
            if ($sample->submitted_for_review_at !== null && $sample->released_at === null) {
                $sample->update(['released_at' => $now]);
            }
        }

        return $this->finalizeJobRelease($jobOrder, $reviewer, $notes);
    }

    public function signSample(
        JobOrder $jobOrder,
        User $reviewer,
        int $sampleId,
        ?string $notes = null,
    ): JobOrder {
        if (! in_array($jobOrder->status, [JobOrderStatus::InAnalysis, JobOrderStatus::PendingReview], true)) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs with results awaiting Head can be signed.',
            ]);
        }

        $jobOrder->loadMissing('samples');
        $sample = $jobOrder->samples->firstWhere('id', $sampleId);
        if ($sample === null) {
            throw ValidationException::withMessages([
                'sample_id' => 'Sample not found on this job order.',
            ]);
        }

        if ($sample->submitted_for_review_at === null) {
            throw ValidationException::withMessages([
                'sample_id' => 'This sample has not been sent to Head yet.',
            ]);
        }

        if ($sample->released_at !== null) {
            throw ValidationException::withMessages([
                'sample_id' => 'This sample was already released.',
            ]);
        }

        $sample->update(['released_at' => now()]);
        $jobOrder->unsetRelation('samples');
        $jobOrder->load('samples');

        $allReleased = $jobOrder->samples->isNotEmpty()
            && $jobOrder->samples->every(fn ($row) => $row->released_at !== null);

        if ($allReleased) {
            return $this->finalizeJobRelease($jobOrder, $reviewer, $notes);
        }

        if ($notes !== null && $notes !== '') {
            $jobOrder->update(['review_notes' => $notes]);
        }

        $fresh = $jobOrder->fresh(['samples', 'analyses.assignee', 'packages.signatory', 'reviewer']) ?? $jobOrder;
        LabQueueUpdated::notify([
            LabQueueUpdated::SCOPE_HEAD,
            LabQueueUpdated::SCOPE_RECEIVING,
            LabQueueUpdated::SCOPE_ANALYST,
        ], $fresh->id);

        return $fresh;
    }

    private function finalizeJobRelease(JobOrder $jobOrder, User $reviewer, ?string $notes = null): JobOrder
    {
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
            ->whereNull('reviewed_at')
            ->where(function ($query) {
                $query->where('status', JobOrderStatus::PendingReview)
                    ->orWhere(function ($inner) {
                        $inner->where('status', JobOrderStatus::InAnalysis)
                            ->whereHas('samples', function ($samples) {
                                $samples->whereNotNull('submitted_for_review_at')
                                    ->whereNull('released_at');
                            });
                    });
            })
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
        if ($jobOrder->reviewed_at !== null) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs awaiting result release can be returned. Released results cannot be returned.',
            ]);
        }

        $awaitingHead = $jobOrder->status === JobOrderStatus::PendingReview
            || (
                $jobOrder->status === JobOrderStatus::InAnalysis
                && $jobOrder->samples()->whereNotNull('submitted_for_review_at')->whereNull('released_at')->exists()
            );

        if (! $awaitingHead) {
            throw ValidationException::withMessages([
                'job_order' => 'Only jobs awaiting result release can be returned. Released results cannot be returned.',
            ]);
        }

        $jobOrder = DB::transaction(function () use ($jobOrder, $analysisIds, $notes) {
            $ids = collect($analysisIds)->unique()->values()->all();

            $analyses = $jobOrder->analyses()->whereIn('id', $ids)->get();
            $touchedSampleIds = $analyses->pluck('sample_id')->filter()->unique()->values();

            $analyses->each(function (JobOrderAnalysis $analysis) {
                $analysis->update([
                    'result_value' => null,
                    'result_pass_fail' => null,
                    'result_measurement' => null,
                    'result_unit' => null,
                    'result_remarks' => null,
                    'result_method' => null,
                    // Keep performed_at so the analyst can re-encode without re-entering date/time.
                    'completed_at' => null,
                    'status' => JobOrderAnalysisStatus::Returned,
                ]);

                if ($analysis->assignee instanceof User) {
                    $analysis->assignee->notify(new AnalysisReturned($analysis->jobOrder, $analysis));
                }
            });

            $jobOrder->load(['samples', 'analyses']);
            foreach ($touchedSampleIds as $sampleId) {
                $sample = $jobOrder->samples->firstWhere('id', (int) $sampleId);
                if ($sample === null || $sample->released_at !== null) {
                    continue;
                }

                $sampleLines = $jobOrder->analyses->where('sample_id', (int) $sampleId);
                $stillComplete = $sampleLines->every(
                    fn (JobOrderAnalysis $line) => $this->lineHasResult($line)
                );
                if (! $stillComplete) {
                    $sample->update(['submitted_for_review_at' => null]);
                }
            }

            $jobOrder->update([
                'status' => JobOrderStatus::InAnalysis,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => $notes,
                // Keep result_signatories so Confirm analyst signatories prefills on re-submit.
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
