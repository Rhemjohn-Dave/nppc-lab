<?php

namespace App\Services;

use App\Enums\ControlledFormFieldType;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Support\DynamicTestMatrix;
use App\Support\ResultSignatories;
use App\Support\SampleControlNumber;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FieldValueResolver
{
    /** General JO dual-column billing: slots 1–10 left, 11–20 right. */
    public const RFA_BILLING_LEFT_SLOTS = 10;

    public const RFA_BILLING_TOTAL_SLOTS = 20;

    /** General JO dual-column samples: slots 1–8 left, 9–16 right. */
    public const RFA_SAMPLE_LEFT_SLOTS = 8;

    public const RFA_SAMPLE_TOTAL_SLOTS = 16;

    /**
     * @return array<string, mixed>
     */
    public function forJobOrder(ControlledFormRevision $revision, JobOrder $jobOrder, bool $showResults = false): array
    {
        $bag = $this->jobOrderBag($jobOrder, $showResults);

        return $this->mapFields($revision, $bag, $jobOrder);
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>|null  $orderedAnalyses
     * @return array<string, mixed>
     */
    public function forResult(
        ControlledFormRevision $revision,
        JobOrder $jobOrder,
        ?Collection $orderedAnalyses = null,
        ?ControlledForm $form = null,
    ): array {
        $bag = $this->jobOrderBag($jobOrder, true);
        $form ??= $revision->form;
        $form?->loadMissing('analysisPackage');

        // FO4 Sample Description prints the job classification (e.g. Wastewater), not FO5 sterile-bottle text.
        if (($form?->form_code ?? null) === 'LSP-7.8-FO4') {
            $bag['results.sample_description'] = $this->listedOrSpecified($jobOrder->classification);
        }

        if ($orderedAnalyses !== null) {
            $ordered = $orderedAnalyses->values();
        } elseif ($form && DynamicTestMatrix::formUsesMatrix($form, $revision)) {
            $ordered = DynamicTestMatrix::orderedSelectedAnalysesForForm($jobOrder, $form);
        } elseif ($form?->analysisPackage && DynamicTestMatrix::packageUsesMatrix($form->analysisPackage)) {
            $ordered = DynamicTestMatrix::orderedSelectedAnalyses($jobOrder, $form->analysisPackage);
        } else {
            $ordered = $jobOrder->analyses->values();
        }

        $bag['results.issued_date'] = $this->officialDate($jobOrder->reviewed_at);
        $bag['results.release_date'] = $bag['results.issued_date'];
        $bag['results.report_date'] = $bag['results.release_date'];
        $this->putResultAnalystBag($bag, $jobOrder, $form, $ordered);
        $bag['results.test_requested'] = $this->testRequestedNames($jobOrder, $form);
        $bag['results.test_methods_references'] = $this->testMethodsReferencesBlock($ordered);
        $bag['issued_date'] = $bag['results.issued_date'];
        $bag['analyst_name'] = $bag['results.analyst_name'];

        $revision->loadMissing('fields');
        $usesMatrix = DynamicTestMatrix::revisionUsesMatrix($revision)
            || DynamicTestMatrix::packageUsesMatrix($form?->analysisPackage)
            || DynamicTestMatrix::formUsesMatrix($form, $revision);

        $sampleCount = (int) config('analysis_result_form_fields.sample_count', 9);
        $samples = $jobOrder->samples->values();
        for ($i = 1; $i <= $sampleCount; $i++) {
            $sample = $samples->get($i - 1);
            $bag["sample_code_{$i}"] = $this->rfaSampleCodeDescription($sample);
            $bag["sample_description_{$i}"] = $sample?->description;
            $bag["sample_matrix_{$i}"] = $sample?->matrix;
            $bag["samples.{$i}.sample_code"] = $sample?->sample_code;
            $bag["samples.{$i}.description"] = $sample?->description;
            $bag["samples.{$i}.matrix"] = $sample?->matrix;
        }

        // Food matrix sheets (Proximate / Milk / Water Activity / CAP / FO26 / FO27) print the first sample
        // description in the result-column header — do not overwrite FO4 / sterile-bottle text.
        if (
            $usesMatrix
            && ($form?->form_code ?? null) !== 'LSP-7.8-FO4'
            && empty($bag['results.sample_description'])
        ) {
            $firstDescription = trim((string) ($samples->first()?->description ?? ''));
            if ($firstDescription !== '') {
                $bag['results.sample_description'] = $firstDescription;
            }
        }

        if (! $usesMatrix) {
            $this->fillResultTestSlots($bag, $jobOrder, $ordered, $form);
        } else {
            foreach ($revision->fields as $field) {
                if ($field->field_type !== ControlledFormFieldType::DynamicTestMatrix) {
                    continue;
                }

                $rows = DynamicTestMatrix::buildRows($ordered);

                $formCode = $form?->form_code ?? null;
                $isNo2Matrix = $formCode === 'LSP-7.8-F016-NO2'
                    || $field->name === 'nitrite_f016_matrix';

                // F016-NO2: one body row per JO sample (per-sample control numbers).
                if ($isNo2Matrix) {
                    $bag[$field->name] = DynamicTestMatrix::buildSampleResidueRows($jobOrder, $ordered);

                    continue;
                }

                // F016-WA / F016-CAP: Control Number | Sample Description | result — body cells.
                if (
                    in_array($formCode, ['LSP-7.8-F016-WA', 'LSP-7.8-F016-CAP'], true)
                    || in_array($field->name, [
                        'water_activity_f016_matrix',
                        'chloramphenicol_f016_matrix',
                    ], true)
                ) {
                    $controlNo = trim((string) ($bag['results.control_no'] ?? ''));
                    $sampleDescription = trim((string) ($bag['results.sample_description'] ?? ''));
                    $rows = array_map(static function (array $row) use ($controlNo, $sampleDescription): array {
                        $row['control_no'] = $controlNo;
                        $row['sample_description'] = $sampleDescription;

                        return $row;
                    }, $rows);
                }

                $bag[$field->name] = $rows;
            }
        }

        if ($revision->fields->isEmpty()) {
            return $bag;
        }

        return $this->mapFields($revision, $bag, $jobOrder);
    }

    /**
     * @param  array<string, mixed>  $bag
     * @param  Collection<int, JobOrderAnalysis>  $ordered
     */
    private function fillResultTestSlots(
        array &$bag,
        JobOrder $jobOrder,
        Collection $ordered,
        ?ControlledForm $form,
    ): void {
        $slotTypeIds = $form?->orderedTypeIds() ?? [];
        if ($slotTypeIds === [] && $form?->analysisPackage) {
            $slotTypeIds = $form->analysisPackage->orderedTypeIds();
        }
        $waived = $jobOrder->waivedTypeIds();
        $byType = $jobOrder->analyses
            ->filter(fn (JobOrderAnalysis $line): bool => $line->analysis_type_id !== null)
            ->keyBy(fn (JobOrderAnalysis $line): int => (int) $line->analysis_type_id);

        if ($slotTypeIds !== []) {
            $form?->loadMissing(['analysisTypes', 'analysisPackage.analysisTypes']);

            foreach ($slotTypeIds as $index => $typeId) {
                $slot = $index + 1;
                $typeId = (int) $typeId;
                $typeMeta = $form?->analysisTypes->firstWhere('id', $typeId)
                    ?? $form?->analysisPackage?->analysisTypes->firstWhere('id', $typeId);

                if (in_array($typeId, $waived, true) || ! $byType->has($typeId)) {
                    $bag["test_{$slot}_name"] = $typeMeta?->name;
                    $bag["test_{$slot}_code"] = $typeMeta?->code;
                    $bag["test_{$slot}_result"] = '-';
                    $bag["test_{$slot}_measurement"] = '-';
                    $bag["test_{$slot}_pass_fail"] = '-';
                    $bag["test_{$slot}_method"] = '-';
                    $bag["test_{$slot}_unit"] = '-';
                    $bag["test_{$slot}_remarks"] = '-';
                    $bag["test_{$slot}_analyst"] = null;
                    $bag["test_{$slot}_completed_at"] = null;

                    continue;
                }

                $line = $byType->get($typeId);
                $this->putTestSlot($bag, $slot, $line);
            }

            return;
        }

        foreach ($ordered as $index => $line) {
            $this->putTestSlot($bag, $index + 1, $line);
        }
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function putTestSlot(array &$bag, int $slot, JobOrderAnalysis $line): void
    {
        $measured = filled($line->result_value) ? (string) $line->result_value : null;
        $passFail = filled($line->result_pass_fail) ? (string) $line->result_pass_fail : null;
        $method = filled($line->result_method)
            ? (string) $line->result_method
            : DynamicTestMatrix::methodForAnalysisType($line->analysisType);

        $bag["test_{$slot}_name"] = $line->name;
        $bag["test_{$slot}_code"] = $line->analysisType?->code;
        // FO4/FO5 Form Designer contract:
        // - test_N_measurement → Results of Analysis (measured value)
        // - test_N_result → Interpretation (Passed/Failed when set)
        // Combined "value (Passed)" is for dynamic matrix only, not fixed slots.
        $bag["test_{$slot}_result"] = $passFail ?: $measured;
        $bag["test_{$slot}_measurement"] = $measured;
        $bag["test_{$slot}_pass_fail"] = $passFail;
        $bag["test_{$slot}_method"] = $method;
        $bag["test_{$slot}_unit"] = $line->result_unit;
        $bag["test_{$slot}_remarks"] = $line->result_remarks;
        $bag["test_{$slot}_analyst"] = $line->assignee?->name;
        $bag["test_{$slot}_completed_at"] = $line->completed_at?->format('m/d/Y');
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleValues(ControlledFormRevision $revision): array
    {
        $bag = [
            'job_orders.reference_no' => 'JO-2026-00125',
            'job_orders.customer_name' => 'ABC Corporation',
            'job_orders.customer_address' => 'Bacolod City',
            'job_orders.customer_contact' => '09171234567',
            'job_orders.company_name' => 'ABC Corporation',
            'job_orders.sampling_date' => now()->format('m/d/Y'),
            'job_orders.sampling_time' => '09:00 AM',
            'job_orders.sample_collected_by' => 'Sample Collector',
            'job_orders.sampling_site' => 'Plant gate — Tank A',
            'job_orders.specimen' => 'Dried fish',
            'job_orders.payment_mode' => 'Billing/Partial',
            'job_orders.payment_mode:cash' => false,
            'job_orders.payment_mode:billing_partial' => true,
            'job_orders.payment_mode:check' => false,
            'job_orders.payment_terms' => '30 days',
            'job_orders.payment_terms:15_days' => false,
            'job_orders.payment_terms:30_days' => true,
            'job_orders.classification' => 'Potability',
            'job_orders.classification:potability' => true,
            'job_orders.ownership_type' => 'Private',
            'job_orders.ownership_type:private' => true,
            'job_orders.total_cost' => '1,500.00',
            'job_orders.created_at' => now()->format('m/d/Y h:i A'),
            'job_orders.received_at' => now()->format('m/d/Y'),
            'job_orders.jo_approved_at' => now()->format('m/d/Y'),
            'job_orders.reviewed_at' => now()->format('m/d/Y'),
            'job_orders.received_by_name' => 'Maria Santos',
            'job_orders.reviewed_by_name' => 'John Cruz',
            'conforme_name' => 'ABC Corporation',
            'conforme_date' => now()->format('m/d/Y'),
            'received_date' => now()->format('m/d/Y'),
            'reviewed_date' => now()->format('m/d/Y'),
            'sampling_site' => 'Plant gate — Tank A',
            'specimen' => 'Dried fish',
            'payment_cash' => false,
            'payment_billing_partial' => true,
            'payment_check' => false,
            'payment_terms_15' => false,
            'payment_terms_30' => true,
            'results.customer' => 'ABC Corporation',
            'results.address' => 'Bacolod City',
            'results.ref_no' => 'JO-2026-00125',
            'results.control_no' => 'JO-2026-00125',
            'results.sample_received_at' => 'July 29, 2026 (3:00PM)',
            'results.receipt_at' => 'July 29, 2026 (9:00AM)',
            'results.sample_description' => 'Water in sterile bottle',
            'results.specimen' => 'Dried fish',
            'results.sample_code' => 'SMP-2026-00452',
            'results.sampling_datetime' => 'July 20, 2026 (9:30AM)',
            'results.collection_datetime' => 'July 20, 2026 (9:30AM)',
            'results.analysis_datetime' => 'July 29, 2026 (3:00PM)',
            'results.examination_datetime' => 'July 29, 2026 (3:00PM)',
            'results.release_date' => 'July 30, 2026',
            'results.report_date' => 'July 30, 2026',
            'results.collected_by' => 'Sample Collector',
            'results.water_supply' => 'Faucet',
            'results.sampling_point' => 'Faucet',
            'results.classification' => 'Potability',
            'results.issued_date' => 'July 30, 2026',
            'results.analyst_name' => 'Ana Analyst',
            'results.analyst_name_2' => 'Ben Analyst',
            'results.analyst_name_3' => 'Cara Analyst',
            'results.analyst_name_4' => 'Dan Analyst',
            'results.analyst_prc' => '1234567',
            'results.analyst_prc_2' => '7654321',
            'results.analyst_prc_3' => '1111111',
            'results.analyst_prc_4' => '2222222',
            'results.test_requested' => 'Water Activity',
            'test_1_result' => 'Passed',
            'test_1_measurement' => '<1.1',
            'test_1_name' => 'Water Activity',
            'test_2_result' => 'Failed',
            'test_2_measurement' => '16',
            'test_3_result' => 'Passed',
            'test_3_measurement' => '12',
            'reference_no' => 'JO-2026-00125',
            'customer_name' => 'ABC Corporation',
            'address' => 'Bacolod City',
            'contact_no' => '09171234567',
            'samples.sample_code' => 'SMP-2026-00452',
            'samples.description' => 'Tap water',
            'sample_code_1' => 'SMP-2026-00452 / Pond water',
            'sample_code_2' => 'SMP-2026-00453 / Canal water',
            'control_number_1' => 'JO-2026-00125A',
            'control_number_2' => 'JO-2026-00125B',
            'bill_param_1' => 'pH',
            'bill_price_1' => '250.00',
            'bill_total_1' => '250.00',
            'billing_total' => '1,500.00',
            'billing_total_right' => null,
            'samples[]' => [
                [
                    'sample_code' => 'SMP-2026-00452',
                    'description' => 'Pond water',
                    'control_number' => 'JO-2026-00125A',
                    'matrix' => 'Liquid',
                    'quantity' => '1',
                    'unit' => 'L',
                ],
                [
                    'sample_code' => 'SMP-2026-00453',
                    'description' => 'Canal water',
                    'control_number' => 'JO-2026-00125B',
                    'matrix' => 'Liquid',
                    'quantity' => '1',
                    'unit' => 'L',
                ],
            ],
            'analyses[]' => [
                ['name' => 'pH', 'category' => 'Physico-Chemical', 'unit_price' => '250.00', 'total_cost' => '250.00', 'result_value' => '7.2', 'result_unit' => ''],
            ],
        ];

        $revision->loadMissing(['fields', 'form.analysisPackage.analysisTypes', 'form.analysisTypes']);

        $sampleTestRequested = $this->sampleTestRequestedLabel($revision->form);
        if ($sampleTestRequested !== null) {
            $bag['results.test_requested'] = $sampleTestRequested;
        }

        $sampleMethods = $this->sampleTestMethodsReferencesBlock($revision->form);
        if ($sampleMethods !== null) {
            $bag['results.test_methods_references'] = $sampleMethods;
        }

        $revision->loadMissing('form');
        if (($revision->form?->form_code ?? null) === 'LSP-7.8-FO4') {
            $bag['job_orders.classification'] = 'Wastewater';
            $bag['job_orders.classification:potability'] = false;
            $bag['job_orders.classification:wastewater'] = true;
            $bag['results.classification'] = 'Wastewater';
            $bag['results.sample_description'] = 'Wastewater';
            $bag['test_1_result'] = '<1.1';
            $bag['test_2_result'] = '16';
        }

        foreach ($revision->fields as $field) {
            if ($field->field_type === ControlledFormFieldType::DynamicTestMatrix) {
                $bag[$field->name] = DynamicTestMatrix::previewRowsForForm($revision->form);
            }
        }

        return $this->mapFields($revision, $bag, null);
    }

    /**
     * @param  array<string, mixed>  $bag
     * @return array<string, mixed>
     */
    private function mapFields(ControlledFormRevision $revision, array $bag, ?JobOrder $jobOrder): array
    {
        $values = [];

        foreach ($revision->fields as $field) {
            $values[$field->name] = $this->valueForField($field, $bag, $jobOrder);

            if ($field->field_type !== ControlledFormFieldType::DynamicTestMatrix) {
                continue;
            }

            $columns = is_array($field->table_config['columns'] ?? null)
                ? $field->table_config['columns']
                : [];

            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }

                foreach (['label_data_source', 'sublabel_data_source'] as $sourceKey) {
                    $key = isset($column[$sourceKey]) ? trim((string) $column[$sourceKey]) : '';
                    if ($key === '' || ! array_key_exists($key, $bag) || array_key_exists($key, $values)) {
                        continue;
                    }

                    $values[$key] = $bag[$key];
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    private function valueForField(ControlledFormField $field, array $bag, ?JobOrder $jobOrder): mixed
    {
        $key = $field->data_source_key ?: $field->name;

        if ($field->field_type === ControlledFormFieldType::Table) {
            return $bag[$key] ?? [];
        }

        if ($field->field_type === ControlledFormFieldType::DynamicTestMatrix) {
            return $bag[$field->name] ?? DynamicTestMatrix::sampleRows();
        }

        if ($field->field_type === ControlledFormFieldType::Checkbox) {
            if (str_starts_with($key, 'analyses.selected')) {
                $code = $field->checkbox_true_value
                    ?: (str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : '');

                return $jobOrder
                    ? $this->analysisSelected($jobOrder, $code)
                    : (bool) ($bag[$key] ?? $bag['analyses.selected:'.strtoupper($code)] ?? false);
            }

            if (array_key_exists($key, $bag)) {
                return (bool) $bag[$key];
            }

            if ($field->checkbox_true_value && array_key_exists(strtok($key, ':') ?: $key, $bag)) {
                $haystack = mb_strtolower((string) $bag[strtok($key, ':')]);

                return str_contains($haystack, mb_strtolower($field->checkbox_true_value));
            }
        }

        if (array_key_exists($key, $bag)) {
            return $this->formatScalar($field, $bag[$key]);
        }

        if (array_key_exists($field->name, $bag)) {
            return $this->formatScalar($field, $bag[$field->name]);
        }

        if (preg_match('/^(samples|analyses)\.(\d+)\.(.+)$/', $key, $matches) === 1) {
            $rows = $bag[$matches[1].'[]'] ?? [];
            $row = $rows[(int) $matches[2] - 1] ?? null;

            return is_array($row) ? ($row[$matches[3]] ?? null) : null;
        }

        return null;
    }

    private function formatScalar(ControlledFormField $field, mixed $value): mixed
    {
        if ($value === null || $value === '' || is_bool($value) || is_array($value)) {
            return $value;
        }

        if ($field->field_type === ControlledFormFieldType::Currency && is_numeric($value)) {
            return number_format((float) $value, 2);
        }

        if ($field->field_type === ControlledFormFieldType::Number && is_numeric($value)) {
            return (string) $value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function analysisSelected(JobOrder $jobOrder, string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        $jobOrder->loadMissing('analyses.analysisType');

        return $jobOrder->analyses->contains(
            function (JobOrderAnalysis $line) use ($code): bool {
                $lineCode = strtoupper((string) ($line->analysisType?->code ?? ''));

                return $lineCode === $code || str_replace('_', '-', $lineCode) === str_replace('_', '-', $code);
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jobOrderBag(JobOrder $jobOrder, bool $showResults = false): array
    {
        $jobOrder->loadMissing(['samples', 'analyses.analysisType', 'analyses.assignee', 'receiver', 'reviewer', 'packages.signatory']);

        $bag = [
            'job_orders.reference_no' => $jobOrder->reference_no,
            'job_orders.customer_name' => $jobOrder->customer_name,
            'job_orders.customer_address' => $jobOrder->customer_address,
            'job_orders.customer_contact' => $jobOrder->customer_contact,
            'job_orders.customer_email' => $jobOrder->customer_email,
            'job_orders.company_name' => $jobOrder->company_name,
            'job_orders.sampling_date' => $jobOrder->sampling_date?->format('m/d/Y'),
            'job_orders.sampling_time' => $jobOrder->sampling_time,
            'job_orders.sample_collected_by' => $jobOrder->sample_collected_by,
            'job_orders.classification' => $jobOrder->classification,
            'job_orders.ownership_type' => $jobOrder->ownership_type,
            'job_orders.field_data' => is_string($jobOrder->field_data) ? $jobOrder->field_data : null,
            'job_orders.sample_storage_temp' => $jobOrder->sample_storage_temp,
            'job_orders.wastewater_source' => $jobOrder->wastewater_source,
            'job_orders.sampling_point' => $jobOrder->sampling_point,
            'job_orders.sampling_site' => $jobOrder->sampling_site,
            'job_orders.specimen' => $jobOrder->specimen,
            'job_orders.payment_mode' => $jobOrder->payment_mode?->label(),
            'job_orders.payment_terms' => $jobOrder->payment_terms?->label(),
            'job_orders.other_tests' => $jobOrder->other_tests,
            'job_orders.total_cost' => $jobOrder->total_cost !== null
                ? number_format((float) $jobOrder->total_cost, 2)
                : null,
            'job_orders.created_at' => $jobOrder->created_at?->format('m/d/Y h:i A'),
            'job_orders.received_at' => $jobOrder->received_at?->format('m/d/Y'),
            'job_orders.reviewed_at' => $jobOrder->reviewed_at?->format('m/d/Y'),
            'job_orders.jo_approved_at' => $jobOrder->jo_approved_at?->format('m/d/Y'),
            'job_orders.received_by_name' => $jobOrder->receiver?->name,
            'job_orders.reviewed_by_name' => $jobOrder->reviewer?->name,
            'reference_no' => $jobOrder->reference_no,
            'customer_name' => $jobOrder->customer_name,
            'address' => $jobOrder->customer_address,
            'contact_no' => $jobOrder->customer_contact,
            'time_date_submitted' => $jobOrder->created_at?->format('m/d/Y h:i A'),
            'sampling_date' => $jobOrder->sampling_date?->format('m/d/Y'),
            'sampling_time' => $jobOrder->sampling_time,
            'sample_collected_by' => $jobOrder->sample_collected_by,
            'sample_storage_temp' => $jobOrder->sample_storage_temp,
            'sampling_site' => $jobOrder->sampling_site,
            'specimen' => $jobOrder->specimen,
            'other_tests' => $jobOrder->other_tests,
            'conforme_name' => $jobOrder->customer_name,
            'conforme_date' => $jobOrder->created_at?->format('m/d/Y'),
            'received_date' => $jobOrder->received_at?->format('m/d/Y'),
            'reviewed_date' => $jobOrder->jo_approved_at?->format('m/d/Y'),
        ];

        // billing_total / billing_total_right filled after analysis lines below.
        $ownership = mb_strtolower((string) $jobOrder->ownership_type);
        $bag['job_orders.ownership_type:private'] = $ownership === 'private';
        $bag['job_orders.ownership_type:commercial'] = $ownership === 'commercial';
        $bag['job_orders.ownership_type:public'] = $ownership === 'public';
        $bag['ownership_private'] = $bag['job_orders.ownership_type:private'];
        $bag['ownership_commercial'] = $bag['job_orders.ownership_type:commercial'];
        $bag['ownership_public'] = $bag['job_orders.ownership_type:public'];

        $paymentMode = $jobOrder->payment_mode?->value;
        $bag['job_orders.payment_mode:cash'] = $paymentMode === 'cash';
        $bag['job_orders.payment_mode:billing_partial'] = $paymentMode === 'billing_partial';
        $bag['job_orders.payment_mode:check'] = $paymentMode === 'check';
        $bag['payment_cash'] = $bag['job_orders.payment_mode:cash'];
        $bag['payment_billing_partial'] = $bag['job_orders.payment_mode:billing_partial'];
        $bag['payment_check'] = $bag['job_orders.payment_mode:check'];

        $paymentTerms = $jobOrder->payment_terms?->value;
        $bag['job_orders.payment_terms:15_days'] = $paymentTerms === '15_days';
        $bag['job_orders.payment_terms:30_days'] = $paymentTerms === '30_days';
        $bag['payment_terms_15'] = $bag['job_orders.payment_terms:15_days'];
        $bag['payment_terms_30'] = $bag['job_orders.payment_terms:30_days'];

        $classification = mb_strtolower((string) $jobOrder->classification);
        foreach (['aqua', 'potability', 'wastewater', 'agriculture', 'academic', 'other'] as $token) {
            $matches = $token === 'academic'
                ? (str_contains($classification, 'academic') || str_contains($classification, 'research'))
                : str_contains($classification, $token);
            $bag["job_orders.classification:{$token}"] = $matches;
            $bag['class_'.$token.($token === 'other' ? 's' : '')] = $matches;
        }
        $bag['class_aqua'] = $bag['job_orders.classification:aqua'];
        $bag['class_potability'] = $bag['job_orders.classification:potability'];
        $bag['class_wastewater'] = $bag['job_orders.classification:wastewater'];
        $bag['class_agriculture'] = $bag['job_orders.classification:agriculture'];
        $bag['class_academic'] = $bag['job_orders.classification:academic'];
        $bag['class_others'] = $bag['job_orders.classification:other'];
        $bag['class_others_text'] = $bag['class_others'] ? (string) $jobOrder->classification : null;

        $isSterileBottle = $this->isSterileBottle($jobOrder->field_data);
        $fieldData = is_array($jobOrder->field_data) ? $jobOrder->field_data : [];
        $bag['job_orders.field_data:sterile_bottle'] = $isSterileBottle
            || (bool) ($fieldData['sterile_bottle'] ?? $fieldData['water_in_sterile_bottle'] ?? false);
        $bag['potability_sterile'] = $bag['job_orders.field_data:sterile_bottle'];

        $ww = mb_strtolower((string) $jobOrder->wastewater_source);
        $bag['job_orders.wastewater_source:district'] = str_contains($ww, 'district');
        $bag['job_orders.wastewater_source:faucet'] = str_contains($ww, 'faucet');
        $bag['job_orders.wastewater_source:tank'] = str_contains($ww, 'tank');
        $bag['job_orders.wastewater_source:deepwell'] = str_contains($ww, 'deepwell') || str_contains($ww, 'deep well');
        $bag['job_orders.wastewater_source:sea'] = str_contains($ww, 'sea');
        $bag['job_orders.wastewater_source:brackish'] = str_contains($ww, 'brackish');
        $bag['job_orders.wastewater_source:river'] = str_contains($ww, 'river');
        $bag['ww_local_district'] = $bag['job_orders.wastewater_source:district'];
        $bag['ww_faucet'] = $bag['job_orders.wastewater_source:faucet'];
        $bag['ww_tank'] = $bag['job_orders.wastewater_source:tank'];
        $bag['ww_deepwell'] = $bag['job_orders.wastewater_source:deepwell'];
        $bag['aqua_sea'] = $bag['job_orders.wastewater_source:sea'];
        $bag['aqua_brackish'] = $bag['job_orders.wastewater_source:brackish'];
        $bag['aqua_river'] = $bag['job_orders.wastewater_source:river'];
        if (str_contains(mb_strtolower((string) $jobOrder->sampling_point), 'faucet')) {
            $bag['job_orders.wastewater_source:faucet'] = true;
            $bag['ww_faucet'] = true;
        }
        $bag['ww_others'] = $ww !== '' && ! (
            $bag['ww_local_district']
            || $bag['ww_faucet']
            || $bag['ww_tank']
            || $bag['ww_deepwell']
            || $bag['aqua_sea']
            || $bag['aqua_brackish']
            || $bag['aqua_river']
        );
        $bag['job_orders.wastewater_source:other'] = $bag['ww_others'];
        $bag['ww_others_text'] = $bag['ww_others'] ? (string) $jobOrder->wastewater_source : null;

        $samples = $jobOrder->samples->values();
        $sampleCount = $samples->count();
        $bag['samples[]'] = $samples->values()->map(function ($sample, int $index) use ($jobOrder, $sampleCount) {
            return [
                'sample_code' => $sample->sample_code,
                'description' => $sample->description,
                'control_number' => SampleControlNumber::forIndex(
                    (string) $jobOrder->reference_no,
                    $index,
                    $sampleCount,
                ),
                'matrix' => $sample->matrix,
                'quantity' => $sample->quantity,
                'unit' => $sample->unit,
                'remarks' => $sample->remarks,
            ];
        })->all();
        $firstSample = $samples->first();
        $bag['samples.sample_code'] = $firstSample?->sample_code;
        $bag['samples.description'] = $firstSample?->description;

        $bag['results.customer'] = $jobOrder->customer_name;
        $bag['results.address'] = $jobOrder->customer_address;
        $bag['results.ref_no'] = $jobOrder->reference_no;
        $bag['results.control_no'] = $jobOrder->reference_no;
        $bag['results.sample_received_at'] = $this->officialDateTime($jobOrder->created_at);
        $bag['results.receipt_at'] = $this->officialDateTime($jobOrder->received_at);
        // FO5 "Sample Code" is the RFA sample code only — not code + description joined.
        // If intake left code blank (common on FO4 wastewater), fall back to description.
        $bag['results.sample_code'] = $this->rfaSampleCodeOnly($firstSample);
        $bag['results.sample_description'] = $bag['potability_sterile']
            ? 'Water in sterile bottle'
            : null;
        $bag['results.specimen'] = $jobOrder->specimen;
        $bag['results.sampling_datetime'] = $this->officialSamplingDateTime(
            $jobOrder->sampling_date,
            $jobOrder->sampling_time,
        );
        $bag['results.collection_datetime'] = $bag['results.sampling_datetime'];
        $bag['results.analysis_datetime'] = $this->officialAnalysisDateTime($jobOrder);
        $bag['results.examination_datetime'] = $bag['results.analysis_datetime'];
        $bag['results.release_date'] = $this->officialDate($jobOrder->reviewed_at);
        $bag['results.report_date'] = $bag['results.release_date'];
        $bag['results.collected_by'] = $jobOrder->sample_collected_by;
        $bag['results.water_supply'] = $this->listedOrSpecified($jobOrder->wastewater_source);
        $bag['results.sampling_point'] = $this->listedOrSpecified($jobOrder->wastewater_source);
        $bag['results.classification'] = $this->listedOrSpecified($jobOrder->classification);
        $bag['results.issued_date'] = $bag['results.release_date'];
        $this->putResultAnalystBag($bag, $jobOrder);

        for ($i = 1; $i <= self::RFA_SAMPLE_TOTAL_SLOTS; $i++) {
            $sample = $samples->get($i - 1);
            $bag["sample_code_{$i}"] = $this->rfaSampleCodeDescription($sample);
            $bag["control_number_{$i}"] = SampleControlNumber::forIndex(
                (string) $jobOrder->reference_no,
                $i - 1,
                $sampleCount,
            );
        }

        $codeByTypeId = AnalysisType::query()
            ->whereNotNull('code')
            ->pluck('code', 'id')
            ->all();

        $selectedCodes = [];
        foreach ($jobOrder->analyses as $line) {
            $code = null;
            if ($line->analysis_type_id && isset($codeByTypeId[$line->analysis_type_id])) {
                $code = $codeByTypeId[$line->analysis_type_id];
            }
            if (is_string($code) && $code !== '') {
                $selectedCodes[strtoupper($code)] = true;
                $bag['analyses.selected:'.strtoupper($code)] = true;
                $bag['chk_'.strtolower(str_replace('-', '_', $code))] = true;
            }
        }

        $analyses = $jobOrder->analyses->values();
        $bag['analyses[]'] = $analyses->map(fn (JobOrderAnalysis $line) => [
            'name' => $line->name,
            'category' => $line->category,
            'unit_price' => number_format((float) $line->unit_price, 2),
            'total_cost' => number_format((float) $line->total_cost, 2),
            'result_value' => $showResults ? $line->result_value : null,
            'result_unit' => $showResults ? $line->result_unit : null,
        ])->all();

        for ($i = 1; $i <= self::RFA_BILLING_TOTAL_SLOTS; $i++) {
            $line = $analyses->get($i - 1);
            if (! $line) {
                $bag["bill_param_{$i}"] = null;
                $bag["bill_price_{$i}"] = null;
                $bag["bill_total_{$i}"] = null;

                continue;
            }

            $param = (string) $line->name;
            if ($showResults && filled($line->result_value)) {
                $param .= ' = '.$line->result_value.($line->result_unit ? ' '.$line->result_unit : '');
            }

            $bag["bill_param_{$i}"] = $param;
            $bag["bill_price_{$i}"] = number_format((float) $line->unit_price, 2);
            $bag["bill_total_{$i}"] = number_format((float) $line->total_cost, 2);
        }

        $this->putBillingTotals($bag, $jobOrder, $analyses->count());

        return $bag;
    }

    /**
     * Place the grand total under the left column when lines fit there; under the
     * right column when the dual-column grid spills past the left slots.
     *
     * @param  array<string, mixed>  $bag
     */
    private function putBillingTotals(array &$bag, JobOrder $jobOrder, int $lineCount): void
    {
        $formatted = $jobOrder->total_cost !== null
            ? number_format((float) $jobOrder->total_cost, 2)
            : null;

        if ($formatted === null) {
            $bag['billing_total'] = null;
            $bag['billing_total_right'] = null;

            return;
        }

        $spillsRight = $lineCount > self::RFA_BILLING_LEFT_SLOTS;

        // Clear left amount (space) when spilling right; totals stay transparent (no cover fill).
        $bag['billing_total'] = $spillsRight ? ' ' : $formatted;
        $bag['billing_total_right'] = $spillsRight ? $formatted : null;
    }

    /**
     * @param  array<string, mixed>  $bag
     * @param  Collection<int, JobOrderAnalysis>|null  $orderedAnalyses
     */
    private function putResultAnalystBag(
        array &$bag,
        JobOrder $jobOrder,
        ?ControlledForm $form = null,
        ?Collection $orderedAnalyses = null,
    ): void {
        $values = ResultSignatories::bagValues($jobOrder, $form, $orderedAnalyses);

        // Name and PRC are separate overlay fields — do not append PRC to the name.
        $bag['results.analyst_name'] = $values['name'] ?? $values['line'];
        $bag['results.analyst_name_2'] = $values['name_2'] ?? $values['line_2'];
        $bag['results.analyst_name_3'] = $values['name_3'] ?? $values['line_3'];
        $bag['results.analyst_name_4'] = $values['name_4'] ?? $values['line_4'];
        $bag['results.analyst_prc'] = $values['prc'];
        $bag['results.analyst_prc_2'] = $values['prc_2'];
        $bag['results.analyst_prc_3'] = $values['prc_3'];
        $bag['results.analyst_prc_4'] = $values['prc_4'];
        $bag['analyst_name'] = $bag['results.analyst_name'];
    }

    private function resultAnalystName(JobOrder $jobOrder, ?Collection $orderedAnalyses = null): ?string
    {
        $names = ResultSignatories::candidateNames($jobOrder, $orderedAnalyses);

        if ($names === []) {
            return null;
        }

        return count($names) === 1 ? $names[0] : implode(', ', $names);
    }

    private function officialDateTime(?DateTimeInterface $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::instance($dateTime)
            ->timezone($this->labTimezone())
            ->format('F j, Y (g:iA)');
    }

    private function officialDate(?DateTimeInterface $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Carbon::instance($date)
            ->timezone($this->labTimezone())
            ->format('F j, Y');
    }

    private function listedOrSpecified(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        if (preg_match('/^others\s*:\s*(.+)$/i', $trimmed, $matches) === 1) {
            return trim($matches[1]);
        }

        return $trimmed;
    }

    private function rfaSampleCodeDescription(mixed $sample): ?string
    {
        if ($sample === null) {
            return null;
        }

        $label = trim(implode(' / ', array_filter([
            trim((string) ($sample->sample_code ?? '')),
            trim((string) ($sample->description ?? '')),
        ])));

        return $label === '' ? null : $label;
    }

    /**
     * Result-sheet Sample Code: prefer the RFA sample code; if blank, use description.
     * Never joins both with " / " (that combined label is only for RFA sample_code_N slots).
     */
    private function rfaSampleCodeOnly(mixed $sample): ?string
    {
        if ($sample === null) {
            return null;
        }

        $code = trim((string) ($sample->sample_code ?? ''));
        if ($code !== '') {
            return $code;
        }

        $description = trim((string) ($sample->description ?? ''));

        return $description === '' ? null : $description;
    }

    private function isSterileBottle(mixed $fieldData): bool
    {
        if (is_array($fieldData)) {
            return (bool) ($fieldData['sterile_bottle'] ?? $fieldData['water_in_sterile_bottle'] ?? false);
        }

        return str_contains(mb_strtolower((string) $fieldData), 'sterile bottle');
    }

    private function officialSamplingDateTime(mixed $date, ?string $time): ?string
    {
        if (! $date instanceof DateTimeInterface) {
            return null;
        }

        $time = trim((string) $time);
        if ($time === '') {
            return $this->officialDate($date);
        }

        try {
            $combined = Carbon::parse(
                $date->format('Y-m-d').' '.$time,
                $this->labTimezone(),
            );
        } catch (\Throwable) {
            return $this->officialDate($date);
        }

        return $combined->timezone($this->labTimezone())->format('F j, Y (g:iA)');
    }

    private function labTimezone(): string
    {
        return (string) config('app.lab_timezone', 'Asia/Manila');
    }

    private function officialAnalysisDateTime(JobOrder $jobOrder): ?string
    {
        $completed = $jobOrder->analyses
            ->map(fn (JobOrderAnalysis $line) => $line->completed_at)
            ->filter()
            ->sort()
            ->first();

        return $completed instanceof DateTimeInterface
            ? $this->officialDateTime($completed)
            : null;
    }

    /**
     * @return list<array{key: string, label: string, type: string, group: string, hint?: string|null, focused?: bool}>
     */
    public static function catalog(?string $category = null, ?ControlledForm $form = null): array
    {
        $groups = config('controlled_form_sources.groups', []);
        $flat = [];
        $packageSources = self::packageResultSources($form);
        $focusPackage = (bool) $form?->analysis_package_id;

        if ($packageSources !== []) {
            $flat = $packageSources;
        }

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $categories = $group['categories'] ?? [];
            if ($category && $categories !== [] && ! in_array($category, $categories, true)) {
                continue;
            }

            foreach ($group['sources'] ?? [] as $source) {
                if (! is_array($source) || ! isset($source['key'])) {
                    continue;
                }

                $key = (string) $source['key'];
                $flat[] = [
                    'key' => $key,
                    'label' => (string) ($source['label'] ?? $key),
                    'type' => (string) ($source['type'] ?? 'text'),
                    'group' => (string) ($group['label'] ?? 'Fields'),
                    'hint' => isset($source['hint']) ? (string) $source['hint'] : null,
                    'focused' => $focusPackage && self::isFocusedSourceKey($key),
                ];
            }
        }

        $types = AnalysisType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name']);

        foreach ($types as $type) {
            $code = strtoupper((string) $type->code);
            $flat[] = [
                'key' => 'analyses.selected:'.$code,
                'label' => 'Selected: '.$type->name.' ('.$code.')',
                'type' => 'checkbox',
                'group' => 'Analysis selected',
                'hint' => null,
                'focused' => false,
            ];
        }

        return $flat;
    }

    /**
     * @return list<array{key: string, label: string, type: string, group: string, hint?: string|null, focused?: bool}>
     */
    private static function packageResultSources(?ControlledForm $form): array
    {
        if (! $form) {
            return [];
        }

        $form->loadMissing(['analysisTypes', 'analysisPackage.analysisTypes']);

        $types = $form->analysisPackage
            ? $form->analysisPackage->analysisTypes
            : $form->analysisTypes;

        if ($types->isEmpty()) {
            return [];
        }

        $focus = (bool) $form->analysis_package_id;
        $sources = [];

        foreach ($types->values() as $index => $type) {
            $slot = $index + 1;
            $name = (string) $type->name;
            $sources[] = [
                'key' => "test_{$slot}_name",
                'label' => $name.' name',
                'type' => 'text',
                'group' => 'This package',
                'hint' => 'Printed test name for '.$name.' (test requested on this sheet).',
                'focused' => $focus,
            ];
            $sources[] = [
                'key' => "test_{$slot}_result",
                'label' => $name.' result',
                'type' => 'text',
                'group' => 'This package',
                'hint' => 'For Pass/Fail procedures this prints Passed/Failed; otherwise the measured result for '.$name.'.',
                'focused' => $focus,
            ];
            $sources[] = [
                'key' => "test_{$slot}_measurement",
                'label' => $name.' measured value',
                'type' => 'text',
                'group' => 'This package',
                'hint' => 'Measured value for '.$name.' (also used when Pass/Fail is printed in the result slot).',
                'focused' => $focus,
            ];
            $sources[] = [
                'key' => "test_{$slot}_pass_fail",
                'label' => $name.' pass/fail',
                'type' => 'text',
                'group' => 'This package',
                'hint' => 'Passed or Failed judgment for '.$name.'.',
                'focused' => $focus,
            ];
            $sources[] = [
                'key' => "test_{$slot}_method",
                'label' => $name.' method',
                'type' => 'text',
                'group' => 'This package',
                'hint' => 'Method used for '.$name.' (defaults from Procedures; analyst may override).',
                'focused' => $focus,
            ];
            $sources[] = [
                'key' => "test_{$slot}_unit",
                'label' => $name.' unit',
                'type' => 'text',
                'group' => 'This package',
                'hint' => 'Unit for '.$name.' when the printed sheet leaves it blank.',
                'focused' => $focus,
            ];
        }

        return $sources;
    }

    /**
     * Comma-separated names of this form's package (or bound) tests that are selected on the job.
     * Multi-test food micro panels (FO26 / FO27) print the panel title instead.
     */
    private function testRequestedNames(JobOrder $jobOrder, ?ControlledForm $form): ?string
    {
        if (! $form) {
            return null;
        }

        $panelTitle = $this->panelTestRequestedLabel($form);
        if ($panelTitle !== null) {
            return $panelTitle;
        }

        $form->loadMissing(['analysisPackage.analysisTypes', 'analysisTypes']);
        $jobOrder->loadMissing(['analyses.analysisType']);

        $typeIds = $form->analysisPackage
            ? $form->analysisPackage->orderedTypeIds()
            : $form->orderedTypeIds();

        if ($typeIds === []) {
            return null;
        }

        $waived = $jobOrder->waivedTypeIds();
        $byType = $jobOrder->analyses
            ->filter(fn (JobOrderAnalysis $line): bool => $line->analysis_type_id !== null)
            ->keyBy(fn (JobOrderAnalysis $line): int => (int) $line->analysis_type_id);

        $typesById = ($form->analysisPackage?->analysisTypes ?? $form->analysisTypes)
            ->keyBy(fn (AnalysisType $type): int => (int) $type->id);

        $names = [];
        foreach ($typeIds as $typeId) {
            $typeId = (int) $typeId;
            if (in_array($typeId, $waived, true) || ! $byType->has($typeId)) {
                continue;
            }

            $line = $byType->get($typeId);
            $name = trim((string) ($line->name ?: $typesById->get($typeId)?->name ?: ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names === [] ? null : implode(', ', $names);
    }

    private function sampleTestRequestedLabel(?ControlledForm $form): ?string
    {
        if (! $form) {
            return null;
        }

        $panelTitle = $this->panelTestRequestedLabel($form);
        if ($panelTitle !== null) {
            return $panelTitle;
        }

        $form->loadMissing(['analysisPackage.analysisTypes', 'analysisTypes']);

        $types = $form->analysisPackage
            ? $form->analysisPackage->analysisTypes
            : $form->analysisTypes;

        $names = $types
            ->map(fn (AnalysisType $type): string => trim((string) $type->name))
            ->filter()
            ->values()
            ->all();

        return $names === [] ? null : implode(', ', $names);
    }

    /**
     * Multiline “Test Methods and References” block for selected analyses only.
     * Same method resolution as nested matrix rows: result_method → Procedures → catalog.
     *
     * @param  Collection<int, JobOrderAnalysis>  $ordered
     */
    private function testMethodsReferencesBlock(Collection $ordered): ?string
    {
        $lines = [];
        foreach ($ordered as $line) {
            if (! $line instanceof JobOrderAnalysis) {
                continue;
            }

            $name = trim((string) ($line->name ?: $line->analysisType?->name ?: ''));
            if ($name === '') {
                continue;
            }

            $method = filled($line->result_method)
                ? trim((string) $line->result_method)
                : trim((string) (DynamicTestMatrix::methodForAnalysisType($line->analysisType) ?? ''));

            $lines[] = $method !== '' ? $name.' — '.$method : $name;
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function sampleTestMethodsReferencesBlock(?ControlledForm $form): ?string
    {
        if (! $form) {
            return null;
        }

        $form->loadMissing(['analysisPackage.analysisTypes', 'analysisTypes']);

        $types = $form->analysisPackage
            ? collect($form->analysisPackage->orderedTypeIds())
                ->map(fn (int $id) => $form->analysisPackage?->analysisTypes->firstWhere('id', $id))
                ->filter()
                ->values()
            : collect($form->orderedTypeIds())
                ->map(fn (int $id) => $form->analysisTypes->firstWhere('id', $id))
                ->filter()
                ->values();

        if ($types->isEmpty()) {
            $types = ($form->analysisPackage?->analysisTypes ?? $form->analysisTypes)->values();
        }

        $lines = [];
        foreach ($types as $type) {
            if (! $type instanceof AnalysisType) {
                continue;
            }

            $name = trim((string) $type->name);
            if ($name === '') {
                continue;
            }

            $method = trim((string) (DynamicTestMatrix::methodForAnalysisType($type) ?? ''));
            $lines[] = $method !== '' ? $name.' — '.$method : $name;
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * Panel title for multi-test food micro result sheets (Test Requested header).
     */
    private function panelTestRequestedLabel(?ControlledForm $form): ?string
    {
        return match ($form?->form_code) {
            'LSP-7.8-FO26' => 'Microbiological Food Test',
            'LSP-7.8-FO27' => 'Food Micro Sugar Test',
            'LSP-7.8-F016-CAP' => 'Chloramphenicol (chemical residue)',
            'LSP-7.8-F016-NO2' => 'Nitrite Content',
            default => null,
        };
    }

    private static function isFocusedSourceKey(string $key): bool
    {
        return in_array($key, [
            'results.customer',
            'results.address',
            'results.ref_no',
            'results.control_no',
            'results.collected_by',
            'results.water_supply',
            'results.sampling_point',
            'results.classification',
            'results.test_requested',
            'results.test_methods_references',
            'results.collection_datetime',
            'results.receipt_at',
            'results.examination_datetime',
            'results.report_date',
            'results.release_date',
            'results.sample_description',
            'results.specimen',
            'results.sample_code',
            'results.sample_received_at',
            'results.sampling_datetime',
            'results.analysis_datetime',
            'results.analyst_name',
            'results.analyst_name_2',
            'results.analyst_name_3',
            'results.analyst_name_4',
            'results.analyst_prc',
            'results.analyst_prc_2',
            'results.analyst_prc_3',
            'results.analyst_prc_4',
            'job_orders.reference_no',
            'samples.sample_code',
        ], true);
    }

    public static function isAllowedKey(string $key): bool
    {
        if ($key === '' || strlen($key) > 120) {
            return false;
        }

        // Resolver only looks up a PHP value bag — never SQL — so aliases from
        // imported blueprints (sample_code_1, chk_mb_01, …) are safe.
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_.:\[\]-]*$/', $key);
    }
}
