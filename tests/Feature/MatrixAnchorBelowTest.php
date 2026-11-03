<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormFieldType;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledPdfFiller;
use App\Support\DynamicTestMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class MatrixAnchorBelowTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_anchor_below_rewrites_y_from_matrix_bottom_plus_offset(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'applyAnchorBelow');
        $method->setAccessible(true);

        $field = [
            'name' => 'results.analyst_name',
            'type' => 'text',
            'x' => 12.0,
            'y' => 222.0,
            'w' => 70.0,
            'h' => 5.0,
            'options' => [
                'anchor' => [
                    'below' => 'dw_old_fo3_matrix',
                    'offset_mm' => 3.0,
                ],
            ],
        ];

        $resolved = $method->invoke($filler, $field, ['dw_old_fo3_matrix' => 80.5]);

        $this->assertEqualsWithDelta(83.5, $resolved['y'], 0.01);
        $this->assertLessThan(222.0, $resolved['y']);
    }

    public function test_apply_anchor_below_keeps_stored_y_when_matrix_missing(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'applyAnchorBelow');
        $method->setAccessible(true);

        $field = [
            'name' => 'notes',
            'y' => 180.0,
            'options' => [
                'anchor' => [
                    'below' => 'missing_matrix',
                    'offset_mm' => 5.0,
                ],
            ],
        ];

        $resolved = $method->invoke($filler, $field, ['other_matrix' => 90.0]);

        $this->assertEqualsWithDelta(180.0, $resolved['y'], 0.01);
    }

    public function test_apply_anchor_follows_notes_and_clamps_above_form_meta(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'applyAnchorBelow');
        $method->setAccessible(true);

        $field = [
            'name' => 'fo2_label_reviewed',
            'y' => 100.0,
            'options' => [
                'anchor' => [
                    'below' => 'ww_fo2_matrix',
                    'offset_mm' => 45.0,
                    'above' => 'fo2_form_meta',
                    'above_offset_mm' => 45.0,
                ],
            ],
        ];

        // Short table (spare 258-45-145=68 >= 24): nudge under notes.
        $short = $method->invoke(
            $filler,
            $field,
            ['ww_fo2_matrix' => 100.0],
            ['fo2_form_meta' => 258.0],
        );
        $this->assertEqualsWithDelta(155.0, $short['y'], 0.01);

        // Medium/tall with little spare (213-190=23 < 24): no nudge, normal flow.
        $normal = $method->invoke(
            $filler,
            $field,
            ['ww_fo2_matrix' => 145.0],
            ['fo2_form_meta' => 258.0],
        );
        $this->assertEqualsWithDelta(190.0, $normal['y'], 0.01);

        // Tall table: clamp just above form-meta (225 > 213).
        $tall = $method->invoke(
            $filler,
            $field,
            ['ww_fo2_matrix' => 180.0],
            ['fo2_form_meta' => 258.0],
        );
        $this->assertEqualsWithDelta(213.0, $tall['y'], 0.01);
    }

    public function test_form_meta_ignores_stale_matrix_anchor(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'applyAnchorBelow');
        $method->setAccessible(true);

        $field = [
            'name' => 'fo2_form_meta',
            'y' => 262.0,
            'options' => [
                'anchor' => [
                    'below' => 'ww_fo2a_matrix',
                    'offset_mm' => 79.0,
                ],
            ],
        ];

        $resolved = $method->invoke(
            $filler,
            $field,
            ['ww_fo2a_matrix' => 120.0],
            [],
        );

        $this->assertEqualsWithDelta(262.0, $resolved['y'], 0.01);
    }

    public function test_fo2_blueprint_pins_meta_footer_and_signatories(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $meta = $revision->fields->firstWhere('name', 'fo2_form_meta');
        $this->assertNotNull($meta);
        $metaAnchor = is_array($meta->options['anchor'] ?? null) ? $meta->options['anchor'] : [];
        $this->assertSame('ww_fo2_matrix', $metaAnchor['below'] ?? null);
        $this->assertEqualsWithDelta(90.0, (float) ($metaAnchor['offset_mm'] ?? 0), 0.01);

        $notes = $revision->fields->firstWhere('name', 'fo2_notes');
        $this->assertNotNull($notes);
        $notesAnchor = is_array($notes->options['anchor'] ?? null) ? $notes->options['anchor'] : [];
        $this->assertSame('ww_fo2_matrix', $notesAnchor['below'] ?? null);
        $this->assertArrayNotHasKey('above', $notesAnchor);

        $reviewed = $revision->fields->firstWhere('name', 'fo2_label_reviewed');
        $this->assertNotNull($reviewed);
        $reviewedAnchor = is_array($reviewed->options['anchor'] ?? null) ? $reviewed->options['anchor'] : [];
        $this->assertSame('ww_fo2_matrix', $reviewedAnchor['below'] ?? null);
        $this->assertSame('fo2_form_meta', $reviewedAnchor['above'] ?? null);
        $this->assertGreaterThan(0, (float) ($reviewedAnchor['above_offset_mm'] ?? 0));
    }

    public function test_form_meta_is_pinned_above_page_stamp(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'formMetaYAbovePageStamp');
        $method->setAccessible(true);
        $stamp = new ReflectionMethod(ControlledPdfFiller::class, 'pageStampY');
        $stamp->setAccessible(true);
        $belowMeta = new ReflectionMethod(ControlledPdfFiller::class, 'pageStampYBelowFormMeta');
        $belowMeta->setAccessible(true);

        $pageHeight = 279.4;
        $metaY = $method->invoke($filler, 18.0, 7.0, $pageHeight);
        $stampY = $stamp->invoke($filler, $pageHeight);
        $contentBottom = $metaY + (7.0 * 0.42 * 5.0);

        $this->assertLessThan($stampY, $contentBottom);
        $this->assertGreaterThanOrEqual(2.0, $stampY - $contentBottom);

        // Tall meta: page stamp follows just under Test Report.
        $tallMetaY = 250.0;
        $followed = $belowMeta->invoke($filler, $tallMetaY, 18.0, 7.0, $pageHeight);
        $this->assertGreaterThan($tallMetaY + (7.0 * 0.42 * 5.0), $followed - 0.01);
        $this->assertGreaterThanOrEqual($stampY, $followed - 0.01);
    }

    public function test_form_meta_follows_tall_matrix_and_parks_on_short(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'resolveFormMetaY');
        $method->setAccessible(true);

        $field = [
            'name' => 'fo2_form_meta',
            'h' => 18.0,
            'font_size' => 7.0,
            'options' => [
                'anchor' => [
                    'below' => 'ww_fo2_matrix',
                    'offset_mm' => 90.0,
                ],
            ],
        ];

        $pageHeight = 279.4;
        $pinned = (new ReflectionMethod(ControlledPdfFiller::class, 'formMetaYAbovePageStamp'))
            ->invoke($filler, 18.0, 7.0, $pageHeight);

        $short = $method->invoke($filler, $field, ['ww_fo2_matrix' => 100.0], $pageHeight);
        $this->assertEqualsWithDelta($pinned, $short, 0.01);

        $tall = $method->invoke($filler, $field, ['ww_fo2_matrix' => 180.0], $pageHeight);
        $this->assertEqualsWithDelta(270.0, $tall, 0.01);
        $this->assertGreaterThan($pinned, $tall);
    }

    public function test_short_matrix_content_bottom_is_below_designer_box_and_anchors_follow(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $y = 54.0;
        $designerBoxH = 181.0;
        $field = [
            'name' => 'dw_old_fo3_matrix',
            'type' => ControlledFormFieldType::DynamicTestMatrix->value,
            'page' => 1,
            'x' => 25.0,
            'y' => $y,
            'w' => 170.0,
            'h' => $designerBoxH,
            'font_size' => 8.0,
            'align' => 'L',
            'font_family' => 'helvetica',
            'font_color' => '#000000',
            'table_config' => [
                'columns' => DynamicTestMatrix::defaultConfig()['columns'],
                'row_height_mm' => 0,
                'header_row_height_mm' => 0,
                'header_row' => true,
                'border' => true,
                'header_bold' => true,
                'test_name_bold' => true,
                'method_font_size' => 7,
                'stretch_body' => false,
            ],
        ];

        $rows = [
            ['test' => 'pH', 'test_method' => 'Electrometric', 'result' => '7.1'],
        ];

        $write = new ReflectionMethod($filler, 'writeDynamicTestMatrix');
        $write->setAccessible(true);
        $bottomY = $write->invoke($filler, $pdf, $field, $rows, []);

        $this->assertIsFloat($bottomY);
        $this->assertGreaterThan($y, $bottomY);
        $this->assertLessThan($y + $designerBoxH, $bottomY);

        $apply = new ReflectionMethod($filler, 'applyAnchorBelow');
        $apply->setAccessible(true);
        $anchored = $apply->invoke($filler, [
            'name' => 'fo3_footer_notes',
            'y' => 180.0,
            'options' => [
                'anchor' => [
                    'below' => 'dw_old_fo3_matrix',
                    'offset_mm' => 3.0,
                ],
            ],
        ], ['dw_old_fo3_matrix' => $bottomY]);

        $this->assertEqualsWithDelta($bottomY + 3.0, $anchored['y'], 0.01);
        $this->assertLessThan(180.0, $anchored['y']);
        $this->assertStringStartsWith('%PDF', $pdf->Output('', 'S'));
    }

    public function test_fill_draws_static_text_and_anchored_field_for_short_fo3_like_matrix(): void
    {
        $this->seed();
        \Illuminate\Support\Facades\Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $form = ControlledForm::query()->create([
            'form_code' => 'ANCHOR-FO3-PILOT',
            'name' => 'Anchor below matrix pilot',
            'department' => 'Laboratory',
            'category' => ControlledFormCategory::AnalysisResult,
        ]);

        $revision = ControlledFormRevision::query()->create([
            'controlled_form_id' => $form->id,
            'revision' => '01',
            'status' => ControlledFormRevisionStatus::Active,
            'created_by' => $admin->id,
            'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
            'page_width_mm' => 215.9,
            'page_height_mm' => 279.4,
            'page_count' => 1,
        ]);

        // Minimal blank PDF as canonical.
        $blank = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $blank->setPrintHeader(false);
        $blank->setPrintFooter(false);
        $blank->SetMargins(0, 0, 0);
        $blank->SetAutoPageBreak(false, 0);
        $blank->AddPage();
        $bytes = $blank->Output('', 'S');
        $path = 'controlled-forms/'.$form->id.'/anchor-pilot.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $bytes);
        $revision->update([
            'canonical_pdf_path' => $path,
            'original_path' => $path,
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'pilot_matrix',
            'label' => 'Matrix',
            'field_type' => ControlledFormFieldType::DynamicTestMatrix,
            'page_number' => 1,
            'x' => 20,
            'y' => 50,
            'width' => 170,
            'height' => 180,
            'font_size' => 8,
            'z_order' => 1,
            'table_config' => [
                'columns' => DynamicTestMatrix::defaultConfig()['columns'],
                'header_row' => true,
                'border' => true,
                'stretch_body' => false,
            ],
        ]);

        ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'pilot_notes',
            'label' => 'Notes',
            'field_type' => ControlledFormFieldType::Multiline,
            'page_number' => 1,
            'x' => 20,
            'y' => 200,
            'width' => 170,
            'height' => 20,
            'font_size' => 8,
            'z_order' => 2,
            'options' => [
                'anchor' => [
                    'below' => 'pilot_matrix',
                    'offset_mm' => 3.0,
                ],
                'static_text' => 'Static notes under the table.',
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
            'width' => 70,
            'height' => 5,
            'font_size' => 10,
            'z_order' => 3,
            'options' => [
                'anchor' => [
                    'below' => 'pilot_matrix',
                    'offset_mm' => 28.0,
                ],
            ],
        ]);

        $pdf = app(ControlledPdfFiller::class)->fill($revision->fresh(['fields']), [
            'pilot_matrix' => [
                ['test' => 'Color', 'test_method' => 'Colorimetric', 'result' => '5'],
            ],
            'results.analyst_name' => 'Test Analyst',
        ]);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(800, strlen($pdf));
    }

    public function test_fo3_blueprint_includes_split_footer_and_signature_chrome(): void
    {
        $fields = collect(config('result_dw_old_fo3_form_fields.fields', []));

        foreach (['fo3_lab_conditions', 'fo3_references', 'fo3_note_left', 'fo3_note_right'] as $name) {
            $field = $fields->first(
                fn ($item): bool => is_array($item) && ($item['name'] ?? '') === $name,
            );
            $this->assertIsArray($field, $name);
            $this->assertSame('multiline', $field['type'] ?? null);
            $this->assertSame('dw_old_fo3_matrix', $field['options']['anchor']['below'] ?? null);
            $this->assertNotSame('', trim((string) ($field['options']['static_text'] ?? '')));
        }

        foreach (['fo3_lic_prefix_1', 'fo3_lic_prefix_2', 'fo3_label_analyzed_by', 'fo3_label_certified'] as $name) {
            $field = $fields->first(
                fn ($item): bool => is_array($item) && ($item['name'] ?? '') === $name,
            );
            $this->assertIsArray($field, $name);
            $this->assertSame('dw_old_fo3_matrix', $field['options']['anchor']['below'] ?? null);
            $this->assertNotSame('', trim((string) ($field['options']['static_text'] ?? '')));
        }

        $noted = $fields->first(
            fn ($item): bool => is_array($item) && ($item['name'] ?? '') === 'fo3_noted_by_name',
        );
        $this->assertIsArray($noted);
        $this->assertSame('fo3_noted_by_name', $noted['data_source_key'] ?? null);
        $this->assertSame('dw_old_fo3_matrix', $noted['options']['anchor']['below'] ?? null);
        $this->assertStringContainsString('WENDY', (string) ($noted['options']['static_text'] ?? ''));

        $meta = $fields->first(
            fn ($item): bool => is_array($item) && ($item['name'] ?? '') === 'fo3_form_meta',
        );
        $this->assertIsArray($meta);
        $this->assertStringContainsString('LSP 7.8 FO3', (string) ($meta['options']['static_text'] ?? ''));

        $analyst = $fields->first(
            fn ($item): bool => is_array($item) && ($item['name'] ?? '') === 'results.analyst_name',
        );
        $this->assertIsArray($analyst);
        $this->assertSame('dw_old_fo3_matrix', $analyst['options']['anchor']['below'] ?? null);
        $this->assertGreaterThan(0, (float) ($analyst['options']['anchor']['offset_mm'] ?? 0));
    }

    public function test_fo3_footer_chrome_heal_imports_blueprint_when_chrome_missing(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();

        // Simulate legacy field list: matrix + names only (no chrome labels).
        ControlledFormField::query()
            ->where('controlled_form_revision_id', $revision->id)
            ->whereIn('name', [
                'fo3_label_analyzed_by',
                'fo3_lic_prefix_1',
                'fo3_lab_conditions',
                'fo3_note_left',
                'fo3_footer_notes',
            ])
            ->delete();

        $this->assertFalse(
            $revision->fields()->where('name', 'fo3_label_analyzed_by')->exists(),
        );

        $migration = require database_path('migrations/2026_11_03_130000_heal_fo_dynamic_form_meta.php');
        $migration->up();

        $revision->refresh();
        $this->assertTrue(
            $revision->fields()->where('name', 'fo3_label_analyzed_by')->exists(),
        );
        $this->assertTrue(
            $revision->fields()->where('name', 'fo3_lic_prefix_1')->exists(),
        );
        $this->assertTrue(
            $revision->fields()->where('name', 'fo3_note_left')->exists(),
        );
        $noted = $revision->fields()->where('name', 'fo3_noted_by_name')->first();
        $this->assertNotNull($noted);
        $this->assertStringContainsString('WENDY', (string) (($noted->options['static_text'] ?? '')));
        $this->assertFalse(
            $revision->fields()->where('name', 'job_orders.reviewed_by_name')->exists(),
        );

        $meta = $revision->fields()->where('name', 'fo3_form_meta')->first();
        $this->assertNotNull($meta);
        $this->assertSame('dw_old_fo3_matrix', $meta->options['anchor']['below'] ?? null);

        $analyzed = $revision->fields()->where('name', 'fo3_label_analyzed_by')->first();
        $this->assertNotNull($analyzed);
        $this->assertSame('fo3_form_meta', $analyzed->options['anchor']['above'] ?? null);
    }
}
