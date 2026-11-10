<?php

namespace App\Support;

use App\Enums\JobOrderStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Support\OfficialAnalysisCatalog;
use App\Support\SampleControlNumber;

class JobOrderFormPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(JobOrder $jobOrder, bool $withResults = true): array
    {
        $jobOrder->loadMissing([
            'samples',
            'analyses.assignee',
            'packageLinks.analysisPackage.analysisTypes',
            'receiver',
            'reviewer',
            'joApprover',
        ]);

        $selectedTypeIds = $jobOrder->packageLinks
            ->flatMap(fn ($link) => $link->selected_type_ids ?? [])
            ->unique()
            ->filter()
            ->values()
            ->all();

        $typesById = $selectedTypeIds === []
            ? collect()
            : AnalysisType::query()
                ->whereIn('id', $selectedTypeIds)
                ->get()
                ->keyBy('id');

        return [
            'id' => $jobOrder->id,
            'reference_no' => $jobOrder->reference_no,
            'customer_name' => $jobOrder->customer_name,
            'customer_email' => $jobOrder->customer_email,
            'customer_contact' => $jobOrder->customer_contact,
            'customer_address' => $jobOrder->customer_address,
            'company_name' => $jobOrder->company_name,
            'ownership_type' => $jobOrder->ownership_type,
            'classification' => $jobOrder->classification,
            'sampling_date' => $jobOrder->sampling_date?->format('Y-m-d'),
            'sampling_time' => $jobOrder->sampling_time,
            'sample_collected_by' => $jobOrder->sample_collected_by,
            'field_data' => $jobOrder->field_data,
            'sample_storage_temp' => $jobOrder->sample_storage_temp,
            'wastewater_source' => $jobOrder->wastewater_source,
            'sampling_point' => $jobOrder->sampling_point,
            'sampling_site' => $jobOrder->sampling_site,
            'specimen' => $jobOrder->specimen,
            'payment_mode' => $jobOrder->payment_mode?->value,
            'payment_mode_label' => $jobOrder->payment_mode?->label(),
            'payment_terms' => $jobOrder->payment_terms?->value,
            'payment_terms_label' => $jobOrder->payment_terms?->label(),
            'other_tests' => $jobOrder->other_tests,
            'attachment_path' => $jobOrder->attachment_path,
            'attachment_original_name' => $jobOrder->attachment_original_name,
            'has_attachment' => filled($jobOrder->attachment_path),
            'attachment_url' => filled($jobOrder->attachment_path)
                ? "/receiving/{$jobOrder->id}/attachment"
                : null,
            'is_bulk' => (bool) $jobOrder->is_receiving_bulk,
            'is_laboratory_services' => (bool) $jobOrder->is_laboratory_services,
            'can_edit_lab_services' => (bool) $jobOrder->is_laboratory_services
                && in_array($jobOrder->status, [
                    JobOrderStatus::DraftSubmitted,
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                ], true),
            'can_edit_analyses' => ! (bool) $jobOrder->is_laboratory_services
                && in_array($jobOrder->status, [
                    JobOrderStatus::DraftSubmitted,
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                ], true),
            'can_mark_lab_services_ready' => (bool) $jobOrder->is_laboratory_services
                && $jobOrder->status === JobOrderStatus::JoApproved
                && $jobOrder->analyses->isNotEmpty(),
            'status' => $jobOrder->status->value,
            'status_label' => $jobOrder->status->label(),
            'total_cost' => $jobOrder->total_cost,
            'discount_amount' => $jobOrder->discount_amount,
            'discount_percent' => $jobOrder->discount_percent,
            'subtotal' => number_format(
                (float) $jobOrder->analyses->sum(fn ($line) => (float) $line->total_cost),
                2,
                '.',
                '',
            ),
            'created_at' => $jobOrder->created_at?->format('m/d/Y h:i A'),
            'received_at' => $jobOrder->received_at?->format('m/d/Y'),
            'jo_approved_at' => $jobOrder->jo_approved_at?->format('m/d/Y'),
            'jo_approver_name' => $jobOrder->joApprover?->name,
            'reviewed_at' => $jobOrder->reviewed_at?->format('m/d/Y'),
            'receiver_name' => $jobOrder->receiver?->name,
            'reviewer_name' => $jobOrder->reviewer?->name,
            'can_print_rfa' => $jobOrder->status->canPrintRfa(),
            'can_receive' => ! $jobOrder->is_laboratory_services
                && $jobOrder->status->canReceiveSamples(),
            'needs_jo_approval' => $jobOrder->jo_approved_at === null
                && in_array($jobOrder->status, [
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                ], true),
            'samples_count' => $jobOrder->samples->count(),
            'samples_named_count' => $jobOrder->samples
                ->filter(fn ($sample) => filled(trim((string) ($sample->description ?? ''))))
                ->count(),
            'can_encode_samples' => (bool) $jobOrder->is_receiving_bulk
                && ! $jobOrder->is_bulk_draft
                && $jobOrder->samples->count() >= 2
                && in_array($jobOrder->status, [
                    JobOrderStatus::DraftSubmitted,
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                    JobOrderStatus::JoApproved,
                ], true),
            'samples_encode_complete' => ! $jobOrder->is_receiving_bulk
                || $jobOrder->samples->count() < 2
                || $jobOrder->samples->every(
                    fn ($sample) => filled(trim((string) ($sample->description ?? ''))),
                ),
            'samples_encode_url' => (
                $jobOrder->is_receiving_bulk
                && ! $jobOrder->is_bulk_draft
                && $jobOrder->samples->count() >= 2
            )
                ? "/receiving/{$jobOrder->id}/samples"
                : null,
            'samples' => $jobOrder->samples->values()->map(function ($sample, int $index) use ($jobOrder) {
                return [
                    'id' => $sample->id,
                    'sample_code' => $sample->sample_code,
                    'description' => $sample->description,
                    'classification' => $sample->classification,
                    'matrix' => $sample->matrix,
                    'quantity' => $sample->quantity,
                    'unit' => $sample->unit,
                    'remarks' => $sample->remarks,
                    'control_number' => SampleControlNumber::forIndex(
                        (string) $jobOrder->reference_no,
                        $index,
                        $jobOrder->samples->count(),
                    ),
                    'submitted_for_review' => $sample->submitted_for_review_at !== null,
                    'submitted_for_review_at' => $sample->submitted_for_review_at?->toIso8601String(),
                    'released' => $sample->released_at !== null,
                    'released_at' => $sample->released_at?->toIso8601String(),
                ];
            })->values(),
            'analyses' => $jobOrder->analyses->map(function ($line) use ($jobOrder, $withResults) {
                $sampleIndex = $jobOrder->samples->search(
                    fn ($sample) => (int) $sample->id === (int) $line->sample_id,
                );
                $controlNumber = $sampleIndex === false
                    ? null
                    : SampleControlNumber::forIndex(
                        (string) $jobOrder->reference_no,
                        (int) $sampleIndex,
                        $jobOrder->samples->count(),
                    );

                return [
                    'id' => $line->id,
                    'sample_id' => $line->sample_id,
                    'control_number' => $controlNumber,
                    'analysis_type_id' => $line->analysis_type_id,
                    'name' => $line->name,
                    'category' => $line->category,
                    'category_label' => $line->resolvedCategoryLabel(),
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_cost' => $line->total_cost,
                    'status' => $line->status->value,
                    'status_label' => $line->status->label(),
                    'assigned_to_name' => $line->assignee?->name,
                    'result_value' => $withResults ? $line->result_value : null,
                    'result_measurement' => $withResults ? $line->result_measurement : null,
                    'result_unit' => $withResults ? $line->result_unit : null,
                    'result_remarks' => $withResults ? $line->result_remarks : null,
                ];
            })->values(),
            'packages' => $jobOrder->packageLinks->values()->map(function ($link) use ($typesById) {
                $selectedIds = array_values(array_map(
                    'intval',
                    $link->selected_type_ids ?? [],
                ));
                $package = $link->analysisPackage;
                $memberNames = [];
                foreach ($selectedIds as $typeId) {
                    $type = $typesById->get($typeId) ?? $package?->analysisTypes->firstWhere('id', $typeId);
                    if ($type) {
                        $memberNames[] = $type->name;
                    }
                }

                return [
                    'id' => $link->id,
                    'sample_id' => $link->sample_id,
                    'analysis_package_id' => $link->analysis_package_id,
                    'name' => $package?->name ?? 'Package',
                    'code' => $package?->code,
                    'default_price' => $package?->default_price,
                    'selected_type_ids' => $selectedIds,
                    'member_names' => $memberNames,
                ];
            })->values(),
            'catalog' => self::catalog(),
            'document_control' => OfficialAnalysisCatalog::documentControlForJobOrder($jobOrder),
        ];
    }

    /**
     * @return list<array{id: int, code: string, name: string, category: string}>
     */
    public static function catalog(): array
    {
        return array_values(AnalysisType::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AnalysisType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category' => $type->category?->slug,
                'category_label' => $type->category?->name,
            ])
            ->all());
    }

    /**
     * Catalog for Receiving add-package / add-test pickers.
     *
     * @return array{
     *     packages: list<array{id: int, name: string, code: string|null, default_price: float|int|string|null, analysis_type_ids: list<int>, classifications: list<string>}>,
     *     analysis_types: list<array{id: int, code: string, name: string, default_price: float|int|string|null, category: string|null, category_label: string|null}>
     * }
     */
    public static function analysisEditCatalog(): array
    {
        $packages = AnalysisPackage::query()
            ->where('is_active', true)
            ->with('analysisTypes')
            ->orderBy('name')
            ->get()
            ->map(fn (AnalysisPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'code' => $package->code,
                'default_price' => $package->default_price,
                'analysis_type_ids' => $package->orderedTypeIds(),
                'classifications' => array_values(array_map('strval', $package->classifications ?? [])),
            ])
            ->values()
            ->all();

        $analysisTypes = AnalysisType::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AnalysisType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'default_price' => $type->default_price,
                'category' => $type->category?->slug,
                'category_label' => $type->category?->name,
            ])
            ->values()
            ->all();

        return [
            'packages' => $packages,
            'analysis_types' => $analysisTypes,
        ];
    }
}
