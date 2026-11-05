<?php

namespace Tests\Feature;

use App\Enums\ControlledFormFieldType;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\ControlledPdfFiller;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use App\Support\ResultSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class MetalsFo28ResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_is_registered_without_a_pdf_or_blueprint_target(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO28')->firstOrFail();

        $this->assertSame('Metal Test Results', $form->name);
        $this->assertSame(2, (int) $form->analyst_signatory_slots);
        $this->assertTrue((bool) $form->analyst_require_prc);
        $this->assertNull($form->analysis_package_id);

        $revision = $form->revisions()->firstOrFail();
        $this->assertSame(ControlledFormRevisionStatus::Draft, $revision->status);
        $this->assertFalse($revision->hasCanonicalPdf());

        $this->assertArrayNotHasKey(
            'LSP-7.8-FO28',
            \App\Console\Commands\ExportControlledFormBlueprintsCommand::TARGETS,
        );
        $this->assertContains('LSP-7.8-FO28', array_column(
            OfficialAnalysisCatalog::resultFormRegistry(),
            'form_code',
        ));
    }

    public function test_blueprint_plots_customer_header_and_matrix(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO28')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();
        $revision->fields()->delete();

        \App\Models\User::factory()->create();
        app(\App\Services\ControlledFormService::class)->importBlueprint($revision->fresh());

        $names = $revision->fresh()->fields()->pluck('name')->all();
        $this->assertContains('results.customer', $names);
        $this->assertContains('results.address', $names);
        $this->assertContains('results.sample_description', $names);
        $this->assertContains('results.ref_no', $names);
        $this->assertContains('results.control_no', $names);
        $this->assertContains('metals_fo28_matrix', $names);
        $this->assertContains('results.metals_mdl', $names);
        $this->assertContains('results.analyst_name', $names);
        $this->assertContains('results.analyst_name_2', $names);

        $matrix = $revision->fields()->where('name', 'metals_fo28_matrix')->firstOrFail();
        $this->assertSame('dynamic_test_matrix', $matrix->field_type->value);
        $this->assertSame(10, (int) ($matrix->table_config['max_rows_per_page'] ?? 0));
        $keys = collect($matrix->table_config['columns'] ?? [])->pluck('key')->all();
        $this->assertSame(['test', 'method', 'result'], $keys);
        $this->assertSame('M', $matrix->table_config['columns'][0]['valign'] ?? null);
        $this->assertSame('M', $matrix->table_config['columns'][1]['valign'] ?? null);
        $this->assertTrue((bool) ($matrix->table_config['test_name_bold'] ?? false));
        $preview = DynamicTestMatrix::previewRowsForForm($form);
        $this->assertCount(10, $preview);
        $this->assertSame('Arsenic (ppm)', $preview[0]['test']);
        $this->assertSame('HVG-Flame – AAS Method', $preview[0]['method']);
        $this->assertSame('Iron (ppm)', $preview[9]['test']);
        $this->assertArrayNotHasKey('test_detail', $preview[0]);
    }

    public function test_methods_and_mdl_cover_only_selected_metals(): void
    {
        $this->assertSame('HVG-Flame – AAS Method', OfficialAnalysisCatalog::methodForCode('MT-02'));
        $this->assertSame('Cold Vapor HVG AAS Method', OfficialAnalysisCatalog::methodForCode('MT-11'));
        $this->assertSame('Flame – AAS Method', OfficialAnalysisCatalog::methodForCode('MT-08'));
        $this->assertSame('Flame – AAS Method', OfficialAnalysisCatalog::methodForCode('MT-01'));
        $this->assertNull(OfficialAnalysisCatalog::methodForCode('MT-17'));

        $text = OfficialAnalysisCatalog::metalsDetectionLimitText([
            'MT-01', 'MT-02', 'MT-08', 'MT-11', 'MT-17',
        ]);

        $this->assertNotNull($text);
        $this->assertStringContainsString('Arsenic = 6.0 x 10-5 ppm', $text);
        $this->assertStringContainsString('Lead = 0.003 ppm', $text);
        $this->assertStringContainsString('Mercury = 2.0 x 10-4 ppm', $text);
        $this->assertStringNotContainsString('Aluminum', $text);
        $this->assertStringNotContainsString('Sample Preparation', $text);
        $this->assertLessThan(
            strpos((string) $text, 'Lead'),
            strpos((string) $text, 'Arsenic'),
        );
    }

    public function test_matrix_rows_skip_sample_preparation_and_suffix_ppm(): void
    {
        $lines = collect([
            $this->line('MT-02', 'Arsenic', '0.10'),
            $this->line('MT-17', 'Sample Preparation', null),
            $this->line('MT-08', 'Lead', '0.20'),
        ])->reject(fn (JobOrderAnalysis $line): bool => $line->isSamplePreparation())->values();

        $rows = DynamicTestMatrix::buildRows($lines);

        $this->assertCount(2, $rows);
        $this->assertSame('Arsenic (ppm)', $rows[0]['test']);
        $this->assertSame('HVG-Flame – AAS Method', $rows[0]['test_method']);
        $this->assertSame('Lead (ppm)', $rows[1]['test']);
        $this->assertSame('Flame – AAS Method', $rows[1]['test_method']);
    }

    public function test_individual_metals_plus_sample_preparation_resolve_fo28(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO28')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();
        $this->assertTrue(
            $revision->fields()->where('field_type', ControlledFormFieldType::DynamicTestMatrix)->exists(),
        );
        $revision->forceFill(['status' => ControlledFormRevisionStatus::Active])->save();
        $form->forceFill(['current_revision_id' => $revision->id])->save();

        $arsenic = AnalysisType::query()->where('code', 'MT-02')->firstOrFail();
        $lead = AnalysisType::query()->where('code', 'MT-08')->firstOrFail();
        $prep = AnalysisType::query()->where('code', 'MT-17')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Metals Individual Customer',
            'customer_email' => 'metals-individual@example.com',
            'classification' => 'Feed',
            'samples' => [
                ['description' => 'Herbanext sample', 'matrix' => 'Solid'],
            ],
            'package_ids' => [],
            'analysis_type_ids' => [$arsenic->id, $lead->id, $prep->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $user = User::query()->where('email', 'receiving@nppc.local')->firstOrFail();
        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $user, withValues: false);

        $this->assertSame('LSP-7.8-FO28', $report->controlledForm?->form_code);
        $this->assertStringNotContainsString('exact set of tests', (string) $report->message);
    }

    public function test_signatory_slots_default_and_stay_editable(): void
    {
        $form = new ControlledForm([
            'form_code' => 'LSP-7.8-FO28',
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => true,
        ]);
        $job = new JobOrder([
            'reference_no' => 'FO28-SIG',
            'customer_name' => 'Herbanext',
            'status' => JobOrderStatus::InAnalysis,
            'total_cost' => 0,
        ]);

        $this->assertSame(['Analyzed by', 'Approved by'], ResultSignatories::slotLabels($form));

        $defaults = ResultSignatories::bagValues($job, $form);
        $this->assertSame('Ma. Antoniela C. Lanaca, RChT', $defaults['name']);
        $this->assertSame('0000761', $defaults['prc']);
        $this->assertSame('Wendy A. Tayson, RCh, MEnE', $defaults['name_2']);
        $this->assertSame('15131', $defaults['prc_2']);

        $job->result_signatories = [
            'by_form' => [
                'LSP-7.8-FO28' => [
                    ['name' => 'Custom Analyst', 'prc_id' => '111'],
                    ['name' => 'Custom Head', 'prc_id' => '222'],
                ],
            ],
        ];

        $edited = ResultSignatories::bagValues($job, $form);
        $this->assertSame('Custom Analyst', $edited['name']);
        $this->assertSame('111', $edited['prc']);
        $this->assertSame('Custom Head', $edited['name_2']);
        $this->assertSame('222', $edited['prc_2']);
    }

    public function test_ten_metals_stay_on_one_page(): void
    {
        $rows = $this->metalRows(10);
        $pdf = $this->fillMetals($rows);

        $this->assertSame(1, $this->pdfPageCount($pdf));
        $this->assertSame(1, substr_count($pdf, 'HERBANEXTMETALS'));
        $this->assertSame(1, substr_count($pdf, 'LANACASLOT'));
        $this->assertSame(1, substr_count($pdf, 'TAYSONSLOT'));
        $this->assertStringContainsString('METALROW10', $pdf);
        $this->assertStringNotContainsString('METALROW11', $pdf);
        $this->assertStringContainsString('Arsenic = 6.0 x 10-5 ppm', $pdf);
    }

    public function test_short_matrix_drops_signatories_into_the_space_below(): void
    {
        $six = $this->fillAnchoredMetals($this->metalRows(6));
        $ten = $this->fillAnchoredMetals($this->metalRows(10));

        $this->assertStringContainsString('LANACASLOT', $six);
        $this->assertStringContainsString('TAYSONSLOT', $six);
        $this->assertStringContainsString('LANACASLOT', $ten);
        $this->assertStringContainsString('TAYSONSLOT', $ten);

        $sixNotes = $this->textBaselineFromBottom($six, 'FO28NOTES');
        $tenNotes = $this->textBaselineFromBottom($ten, 'FO28NOTES');
        $this->assertGreaterThan(
            $tenNotes + 30,
            $sixNotes,
            'Notes stay under the last test, so six metals sit higher than ten.',
        );

        $sixY = $this->textBaselineFromBottom($six, 'LANACASLOT');
        $tenY = $this->textBaselineFromBottom($ten, 'LANACASLOT');
        $this->assertLessThan(
            20,
            abs($sixY - $tenY),
            'Six metals should keep Analyzed by in the same lower band as ten metals.',
        );
        $this->assertGreaterThan(
            40,
            $sixNotes - $sixY,
            'Analyzed by should sit well below the notes on a short sheet.',
        );
    }

    public function test_eleventh_metal_continues_on_page_two_with_header_and_signatories(): void
    {
        $rows = $this->metalRows(11);
        $pdf = $this->fillMetals($rows);

        $this->assertSame(2, $this->pdfPageCount($pdf));
        $this->assertSame(2, substr_count($pdf, 'HERBANEXTMETALS'));
        $this->assertSame(2, substr_count($pdf, 'LANACASLOT'));
        $this->assertSame(2, substr_count($pdf, 'TAYSONSLOT'));
        $this->assertStringContainsString('- page 1/2 -', $pdf);
        $this->assertStringContainsString('- page 2/2 -', $pdf);

        $pageTwo = $this->pageBinary($pdf, 2);
        $this->assertStringContainsString('HERBANEXTMETALS', $pageTwo);
        $this->assertStringContainsString('LANACASLOT', $pageTwo);
        $this->assertStringContainsString('TAYSONSLOT', $pageTwo);
        $this->assertStringContainsString('METALROW11', $pageTwo);
        $this->assertStringNotContainsString('METALROW1 ', $pageTwo);
        $this->assertStringNotContainsString('METALROW10', $pageTwo);
    }

    /**
     * @return list<array{test: string, test_method: string, result: string}>
     */
    private function metalRows(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'test' => 'METALROW'.$i,
                'test_method' => 'Flame – AAS Method',
                'result' => (string) $i,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{test: string, test_method: string, result: string}>  $rows
     */
    private function fillMetals(array $rows): string
    {
        Storage::fake('local');

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO28')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();

        $blank = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $blank->setPrintHeader(false);
        $blank->setPrintFooter(false);
        $blank->AddPage();
        $path = 'controlled-forms/fo28-test.pdf';
        Storage::disk('local')->put($path, $blank->Output('', 'S'));
        $revision->update([
            'canonical_pdf_path' => $path,
            'original_path' => $path,
            'page_width_mm' => 215.9,
            'page_height_mm' => 279.4,
            'page_count' => 1,
        ]);

        $revision->fields()->delete();

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'results.customer',
            'label' => 'Customer',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 20,
            'y' => 20,
            'width' => 120,
            'height' => 6,
            'font_size' => 10,
            'font_family' => 'helvetica',
            'z_order' => 1,
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'metals_matrix',
            'label' => 'Metals',
            'field_type' => ControlledFormFieldType::DynamicTestMatrix,
            'page_number' => 1,
            'x' => 15,
            'y' => 40,
            'width' => 180,
            'height' => 160,
            'font_size' => 8,
            'font_family' => 'helvetica',
            'z_order' => 2,
            'table_config' => [
                'columns' => [
                    ['key' => 'test', 'label' => 'Test', 'width_pct' => 40],
                    ['key' => 'test_method', 'label' => 'Method', 'width_pct' => 40],
                    ['key' => 'result', 'label' => 'Result', 'width_pct' => 20],
                ],
                'row_height_mm' => 6,
                'header_row' => true,
                'border' => true,
                'stretch_body' => false,
                'header_bold' => false,
                'test_name_bold' => false,
            ],
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'results.metals_mdl',
            'label' => 'MDL',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 15,
            'y' => 210,
            'width' => 180,
            'height' => 8,
            'font_size' => 7,
            'font_family' => 'helvetica',
            'z_order' => 3,
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'results.analyst_name',
            'label' => 'Analyzed by',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 20,
            'y' => 230,
            'width' => 80,
            'height' => 6,
            'font_size' => 9,
            'font_family' => 'helvetica',
            'z_order' => 4,
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'results.analyst_name_2',
            'label' => 'Approved by',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 110,
            'y' => 230,
            'width' => 80,
            'height' => 6,
            'font_size' => 9,
            'font_family' => 'helvetica',
            'z_order' => 5,
        ]);

        return app(ControlledPdfFiller::class)->fill($revision->fresh(['fields', 'form']), [
            'results.customer' => 'HERBANEXTMETALS',
            'metals_matrix' => $rows,
            'results.metals_mdl' => 'Method Detection Limit: Arsenic = 6.0 x 10-5 ppm',
            'results.analyst_name' => 'LANACASLOT',
            'results.analyst_name_2' => 'TAYSONSLOT',
        ]);
    }

    /**
     * Saved plot says stretch-to-fill. A short FO28 table still ends after the last
     * test; notes follow that row, and the signatories drop into the open space.
     *
     * @param  list<array{test: string, test_method: string, result: string}>  $rows
     */
    private function fillAnchoredMetals(array $rows): string
    {
        Storage::fake('local');

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO28')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();

        $blank = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $blank->setPrintHeader(false);
        $blank->setPrintFooter(false);
        $blank->AddPage();
        $path = 'controlled-forms/fo28-anchor-test.pdf';
        Storage::disk('local')->put($path, $blank->Output('', 'S'));
        $revision->update([
            'canonical_pdf_path' => $path,
            'original_path' => $path,
            'page_width_mm' => 215.9,
            'page_height_mm' => 279.4,
            'page_count' => 1,
        ]);

        $revision->fields()->delete();

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'metals_fo28_matrix',
            'label' => 'Metals',
            'field_type' => ControlledFormFieldType::DynamicTestMatrix,
            'page_number' => 1,
            'x' => 15,
            'y' => 40,
            'width' => 180,
            'height' => 160,
            'font_size' => 8,
            'font_family' => 'helvetica',
            'z_order' => 1,
            'table_config' => [
                'columns' => [
                    ['key' => 'test', 'label' => 'Test', 'width_pct' => 40],
                    ['key' => 'method', 'label' => 'Method', 'width_pct' => 40],
                    ['key' => 'result', 'label' => 'Result', 'width_pct' => 20],
                ],
                'row_height_mm' => 6,
                'header_row' => true,
                'border' => true,
                'stretch_body' => true,
                'header_bold' => false,
                'test_name_bold' => false,
            ],
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'fo28_notes',
            'label' => 'Notes',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 15,
            'y' => 210,
            'width' => 160,
            'height' => 6,
            'font_size' => 8,
            'font_family' => 'helvetica',
            'z_order' => 2,
            'options' => [
                'prefer_static' => true,
                'static_text' => 'FO28NOTES',
                'anchor' => [
                    'below' => 'metals_fo28_matrix',
                    'offset_mm' => 21,
                ],
            ],
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'results.analyst_name',
            'label' => 'Analyzed by',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 20,
            'y' => 230,
            'width' => 80,
            'height' => 6,
            'font_size' => 9,
            'font_family' => 'helvetica',
            'z_order' => 3,
            'options' => [
                'anchor' => [
                    'below' => 'metals_fo28_matrix',
                    'offset_mm' => 39,
                ],
            ],
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'results.analyst_name_2',
            'label' => 'Approved by',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 110,
            'y' => 230,
            'width' => 80,
            'height' => 6,
            'font_size' => 9,
            'font_family' => 'helvetica',
            'z_order' => 4,
            'options' => [
                'anchor' => [
                    'below' => 'metals_fo28_matrix',
                    'offset_mm' => 39,
                ],
            ],
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'fo28_form_meta',
            'label' => 'Form control block',
            'field_type' => ControlledFormFieldType::Text,
            'page_number' => 1,
            'x' => 15,
            'y' => 252,
            'width' => 70,
            'height' => 16,
            'font_size' => 8,
            'font_family' => 'helvetica',
            'z_order' => 5,
            'options' => [
                'prefer_static' => true,
                'static_text' => 'FO28META',
                'anchor' => [
                    'below' => 'metals_fo28_matrix',
                    'offset_mm' => 62,
                ],
            ],
        ]);

        return app(ControlledPdfFiller::class)->fill($revision->fresh(['fields', 'form']), [
            'metals_fo28_matrix' => $rows,
            'results.analyst_name' => 'LANACASLOT',
            'results.analyst_name_2' => 'TAYSONSLOT',
        ]);
    }

    private function textBaselineFromBottom(string $pdf, string $needle): float
    {
        $quoted = preg_quote($needle, '/');
        $matched = preg_match(
            '/([0-9]+(?:\.[0-9]+)?) ([0-9]+(?:\.[0-9]+)?) Td \[\('.$quoted.'\)\] TJ/',
            $pdf,
            $matches,
        );

        $this->assertSame(1, $matched, "Could not locate {$needle} on the page.");

        return (float) $matches[2];
    }

    private function line(string $code, string $name, ?string $value): JobOrderAnalysis
    {
        $type = new AnalysisType(['code' => $code, 'name' => $name]);
        $line = new JobOrderAnalysis([
            'name' => $name,
            'result_value' => $value,
        ]);
        $line->setRelation('analysisType', $type);

        return $line;
    }

    private function pdfPageCount(string $binary): int
    {
        $path = tempnam(sys_get_temp_dir(), 'fo28');
        file_put_contents($path, $binary);

        try {
            return (new Fpdi)->setSourceFile($path);
        } finally {
            @unlink($path);
        }
    }

    private function pageBinary(string $binary, int $pageNo): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fo28');
        file_put_contents($path, $binary);

        try {
            $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setCompression(false);
            $count = $pdf->setSourceFile($path);
            $this->assertGreaterThanOrEqual($pageNo, $count);
            $template = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            return $pdf->Output('', 'S');
        } finally {
            @unlink($path);
        }
    }
}
