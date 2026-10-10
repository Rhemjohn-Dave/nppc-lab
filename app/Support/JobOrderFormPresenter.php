<?php

namespace App\Support;

use App\Enums\JobOrderStatus;
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
            'can_receive' => $jobOrder->status->canReceiveSamples(),
            'needs_jo_approval' => $jobOrder->jo_approved_at === null
                && in_array($jobOrder->status, [
                    JobOrderStatus::PendingJoApproval,
                    JobOrderStatus::Priced,
                ], true),
            'samples' => $jobOrder->samples->values()->map(function ($sample, int $index) use ($jobOrder) {
                return [
                    'id' => $sample->id,
                    'sample_code' => $sample->sample_code,
                    'description' => $sample->description,
                    'matrix' => $sample->matrix,
                    'quantity' => $sample->quantity,
                    'unit' => $sample->unit,
                    'remarks' => $sample->remarks,
                    'control_number' => SampleControlNumber::forIndex(
                        (string) $jobOrder->reference_no,
                        $index,
                        $jobOrder->samples->count(),
                    ),
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
}
