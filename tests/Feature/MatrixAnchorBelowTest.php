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
            'name' => 'fo2_signatory_reviewed',
            'y' => 100.0,
            'options' => [
                'anchor' => [
                    'below' => 'fo2_notes',
                    'offset_mm' => 6.0,
                    'above' => 'fo2_form_meta',
                    'above_offset_mm' => 46.0,
                ],
            ],
        ];

        // NOTES-ink bottom + 6mm; no 20mm short-sheet nudge for fo2_notes anchors.
        $short = $method->invoke(
            $filler,
            $field,
            [],
            ['fo2_form_meta' => 258.0],
            [],
            ['fo2_notes' => 140.0],
        );
        $this->assertEqualsWithDelta(146.0, $short['y'], 0.01);

        // Longer NOTES ink pushes the signatory down.
        $longerNotes = $method->invoke(
            $filler,
            $field,
            [],
            ['fo2_form_meta' => 258.0],
            [],
            ['fo2_notes' => 160.0],
        );
        $this->assertEqualsWithDelta(166.0, $longerNotes['y'], 0.01);

        // Mid sheet: meta parked at stamp pin must NOT collapse the 6mm NOTES gap
        // via stale above_offset_mm (46) — that was pulling labels into the notes.
        $midPinnedMeta = $method->invoke(
            $filler,
            array_merge($field, ['h' => 24.0]),
            [],
            ['fo2_form_meta' => 235.0],
            [],
            ['fo2_notes' => 189.0],
        );
        $this->assertEqualsWithDelta(195.0, $midPinnedMeta['y'], 0.01);

        // NOTES stay under the matrix; short sheets also get the same 4mm drop as the matrix.
        $notesShort = $method->invoke(
            $filler,
            [
                'name' => 'fo2_notes',
                'y' => 192.0,
                'options' => [
                    'anchor' => [
                        'below' => 'ww_fo2_matrix',
                        'offset_mm' => 17.0,
                    ],
                ],
            ],
            ['ww_fo2_matrix' => 100.0],
            ['fo2_form_meta' => 258.0],
            ['ww_fo2_matrix' => 4.0],
        );
        $this->assertEqualsWithDelta(121.0, $notesShort['y'], 0.01);

        $notesFull = $method->invoke(
            $filler,
            [
                'name' => 'fo2_notes',
                'y' => 192.0,
                'options' => [
                    'anchor' => [
                        'below' => 'ww_fo2_matrix',
                        'offset_mm' => 17.0,
                    ],
                ],
            ],
            ['ww_fo2_matrix' => 100.0],
            ['fo2_form_meta' => 258.0],
        );
        $this->assertEqualsWithDelta(117.0, $notesFull['y'], 0.01);

        // Tall NOTES: still follow ink + 6mm (meta follows the stack afterward).
        $tall = $method->invoke(
            $filler,
            array_merge($field, ['h' => 24.0]),
            [],
            ['fo2_form_meta' => 258.0],
            [],
            ['fo2_notes' => 220.0],
        );
        $this->assertEqualsWithDelta(226.0, $tall['y'], 0.01);

        // Even when meta is tight, never pull signatories up into the NOTES band.
        $overflow = $method->invoke(
            $filler,
            array_merge($field, ['h' => 24.0]),
            [],
            ['fo2_form_meta' => 230.0],
            [],
            ['fo2_notes' => 220.0],
        );
        $this->assertEqualsWithDelta(226.0, $overflow['y'], 0.01);
    }

    public function test_fo3_keeps_twenty_mm_short_sheet_signatory_nudge(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'applyAnchorBelow');
        $method->setAccessible(true);

        $field = [
            'name' => 'fo3_label_analyzed',
            'y' => 100.0,
            'options' => [
                'anchor' => [
                    'below' => 'dw_old_fo3_matrix',
                    'offset_mm' => 45.0,
                    'above' => 'fo3_form_meta',
                    'above_offset_mm' => 45.0,
                ],
            ],
        ];

        $short = $method->invoke(
            $filler,
            $field,
            ['dw_old_fo3_matrix' => 100.0],
            ['fo3_form_meta' => 258.0],
        );
        // fromBelow 145 + 20 nudge = 165, clamp above 213.
        $this->assertEqualsWithDelta(165.0, $short['y'], 0.01);
    }

    public function test_fo2_short_sheet_matrix_scale_bumps_row_height_and_fonts(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'applyFo2ShortSheetMatrixScale');
        $method->setAccessible(true);

        $scaled = $method->invoke($filler, [
            'name' => 'ww_fo2b_matrix',
            'font_size' => 6.5,
            'table_config' => [
                'row_height_mm' => 5.0,
                'method_font_size' => 6.0,
                'columns' => [
                    ['key' => 'test', 'font_size' => 8],
                    ['key' => 'method', 'font_size' => 8],
                    ['key' => 'result', 'font_size' => 8],
                ],
            ],
        ]);

        $this->assertEqualsWithDelta(7.5, $scaled['font_size'], 0.01);
        $this->assertEqualsWithDelta(7.0, $scaled['table_config']['row_height_mm'], 0.01);
        $this->assertEqualsWithDelta(7.0, $scaled['table_config']['method_font_size'], 0.01);
        $this->assertEqualsWithDelta(9.0, $scaled['table_config']['columns'][0]['font_size'], 0.01);
        $this->assertEqualsWithDelta(9.0, $scaled['table_config']['columns'][1]['font_size'], 0.01);
        // Result column stays at the designer size.
        $this->assertEqualsWithDelta(8.0, $scaled['table_config']['columns'][2]['font_size'], 0.01);
    }

    public function test_fo2_short_sheet_chrome_font_targets_notes_refs_signatories_and_meta(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'shouldBoostFo2ShortSheetChromeFont');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($filler, 'fo2_notes', []));
        $this->assertTrue($method->invoke($filler, 'fo2_references', []));
        $this->assertTrue($method->invoke($filler, 'fo2_form_meta', []));
        $this->assertTrue($method->invoke($filler, 'fo2_signatory_reviewed', []));
        $this->assertTrue($method->invoke($filler, 'fo2_signatory_certified', []));
        $this->assertTrue($method->invoke($filler, 'fo2_signatory_noted', []));
        $this->assertTrue($method->invoke($filler, 'fo2_label_reviewed', []));
        $this->assertTrue($method->invoke($filler, 'results.analyst_name', [
            'options' => ['anchor' => ['above' => 'fo2_form_meta']],
        ]));

        $this->assertFalse($method->invoke($filler, 'results.sample_code', []));
        $this->assertFalse($method->invoke($filler, 'fo2_lab_conditions', []));
        $this->assertFalse($method->invoke($filler, 'results.analyst_name', [
            'options' => ['anchor' => ['above' => 'fo3_form_meta']],
        ]));
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

    public function test_fo2_sync_above_offset_heals_stale_signatory_block(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $sync = new ReflectionMethod(ControlledPdfFiller::class, 'syncAboveOffsetFromMetaOffset');
        $sync->setAccessible(true);

        $meta = new ControlledFormField([
            'name' => 'fo2_form_meta',
            'options' => [
                'anchor' => [
                    'below' => 'fo2_notes',
                    'offset_mm' => 52.0,
                ],
            ],
        ]);
        $pageFields = collect([$meta]);

        $block = [
            'name' => 'fo2_signatory_reviewed',
            'y' => 100.0,
            'options' => [
                'anchor' => [
                    'below' => 'fo2_notes',
                    'offset_mm' => 6.0,
                    'above' => 'fo2_form_meta',
                    'above_offset_mm' => 30.0,
                ],
            ],
        ];

        $synced = $sync->invoke($filler, $block, $pageFields);
        $this->assertEqualsWithDelta(
            46.0,
            $synced['options']['anchor']['above_offset_mm'],
            0.01,
        );
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
        $this->assertSame('fo2_notes', $metaAnchor['below'] ?? null);
        $this->assertEqualsWithDelta(52.0, (float) ($metaAnchor['offset_mm'] ?? 0), 0.01);

        $notes = $revision->fields->firstWhere('name', 'fo2_notes');
        $this->assertNotNull($notes);
        $notesAnchor = is_array($notes->options['anchor'] ?? null) ? $notes->options['anchor'] : [];
        $this->assertSame('ww_fo2_matrix', $notesAnchor['below'] ?? null);
        $this->assertArrayNotHasKey('above', $notesAnchor);

        $reviewed = $revision->fields->firstWhere('name', 'fo2_signatory_reviewed');
        $this->assertNotNull($reviewed);
        $reviewedAnchor = is_array($reviewed->options['anchor'] ?? null) ? $reviewed->options['anchor'] : [];
        $this->assertSame('fo2_notes', $reviewedAnchor['below'] ?? null);
        $this->assertSame('fo2_form_meta', $reviewedAnchor['above'] ?? null);
        $this->assertEqualsWithDelta(6.0, (float) ($reviewedAnchor['offset_mm'] ?? 0), 0.01);
        $this->assertGreaterThan(0, (float) ($reviewedAnchor['above_offset_mm'] ?? 0));
        $this->assertIsArray($reviewed->options['signatory_block'] ?? null);

        foreach ([
            'fo2_signatory_reviewed',
            'fo2_signatory_certified',
            'fo2_signatory_noted',
        ] as $signatoryField) {
            $field = $revision->fields->firstWhere('name', $signatoryField);
            $this->assertNotNull($field, $signatoryField);
            $anchor = is_array($field->options['anchor'] ?? null) ? $field->options['anchor'] : [];
            $this->assertSame('fo2_notes', $anchor['below'] ?? null, $signatoryField);
            $this->assertSame('fo2_form_meta', $anchor['above'] ?? null, $signatoryField);
            $this->assertGreaterThan(0, (float) ($anchor['above_offset_mm'] ?? 0), $signatoryField);
            $this->assertSame('multiline', $field->field_type->value, $signatoryField);
        }

        $certified = $revision->fields->firstWhere('name', 'fo2_signatory_certified');
        $people = $certified?->options['signatory_block']['people'] ?? null;
        $this->assertIsArray($people);
        $this->assertCount(2, $people);
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

    public function test_fo2_notes_expand_clears_room_for_all_six_lines(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'expandFo2NotesHeightForFullInk');
        $method->setAccessible(true);

        $pageHeight = 279.4;
        $expanded = $method->invoke($filler, [
            'name' => 'fo2_notes',
            'y' => 175.0,
            'h' => 23.5,
            'font_size' => 8.5,
        ], $pageHeight);

        // Short-sheet NOTES (+1pt) need ~25mm for Notes: + 6 items; designer box is 23.5.
        $this->assertGreaterThan(24.0, (float) $expanded['h']);
    }

    public function test_fo2b_notes_leave_right_column_for_field_data(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2B')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $notes = $revision->fields->firstWhere('name', 'fo2_notes');
        $fieldData = $revision->fields->firstWhere('name', 'job_orders.field_data');
        $this->assertNotNull($notes);
        $this->assertNotNull($fieldData);

        $this->assertLessThanOrEqual(115.0, (float) $notes->width);
        $this->assertGreaterThanOrEqual(120.0, (float) $fieldData->x);
        $this->assertSame('ww_fo2b_matrix', $fieldData->options['anchor']['below'] ?? null);
        $this->assertEqualsWithDelta(18.0, (float) ($fieldData->options['anchor']['offset_mm'] ?? 0), 0.01);
    }

    public function test_fo2b_field_data_raises_instead_of_clipping_to_zero(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'drawFo2bFieldDataAboveSignatories');
        $method->setAccessible(true);

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4');
        $pdf->AddPage();

        $overlay = [
            'name' => 'job_orders.field_data',
            'type' => 'multiline',
            'x' => 126.0,
            'y' => 172.0,
            'w' => 78.0,
            'h' => 28.0,
            'font_size' => 8.0,
            'font_family' => 'times',
            'align' => 'L',
            'options' => [
                'anchor' => [
                    'below' => 'ww_fo2b_matrix',
                    'offset_mm' => 35.0,
                ],
                'static_text' => "**FIELD DATA:**\na) Temperature – 27\nb) Dissolved Oxygen - 5.5\nc) Time of Sampling - 8:15 AM – 8:45 AM",
            ],
        ];

        $absoluteFieldY = ['fo2_notes' => 200.0];
        $fieldContentBottomY = [];
        $matrixBottomY = ['ww_fo2b_matrix' => 200.0];
        // Designer Y = 235; ceiling 222.5 would previously clamp h→0.
        $ceilingY = 222.5;

        $method->invokeArgs($filler, [
            $pdf,
            $overlay,
            null,
            [],
            $matrixBottomY,
            &$absoluteFieldY,
            &$fieldContentBottomY,
            [],
            false,
            $ceilingY,
            true,
            false,
        ]);

        $this->assertArrayHasKey('job_orders.field_data', $fieldContentBottomY);
        $this->assertEqualsWithDelta(200.0, $absoluteFieldY['job_orders.field_data'], 0.01);
        // Must draw real ink (not the old h→0 clip). Content bottom tracks written lines.
        $this->assertGreaterThan(
            $absoluteFieldY['job_orders.field_data'] + 8.0,
            $fieldContentBottomY['job_orders.field_data'],
        );
    }

    public function test_fo2b_short_sheet_stacks_field_data_below_notes(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'drawFo2bFieldDataAboveSignatories');
        $method->setAccessible(true);

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4');
        $pdf->AddPage();

        $overlay = [
            'name' => 'job_orders.field_data',
            'type' => 'multiline',
            'x' => 126.0,
            'y' => 172.0,
            'w' => 78.0,
            'h' => 28.0,
            'font_size' => 8.0,
            'font_family' => 'times',
            'align' => 'L',
            'options' => [
                'anchor' => [
                    'below' => 'ww_fo2b_matrix',
                    'offset_mm' => 18.0,
                ],
                'static_text' => "**FIELD DATA:**\na) Temperature – 27\nb) Dissolved Oxygen - 5.5",
            ],
        ];

        $notesBottom = 180.0;
        $absoluteFieldY = ['fo2_notes' => 160.0];
        $fieldContentBottomY = ['fo2_notes' => $notesBottom];
        $matrixBottomY = ['ww_fo2b_matrix' => 140.0];

        // Non-tall (even when short-chrome flag is false) must still stack under NOTES.
        $method->invokeArgs($filler, [
            $pdf,
            $overlay,
            null,
            [],
            $matrixBottomY,
            &$absoluteFieldY,
            &$fieldContentBottomY,
            [],
            false,
            null,
            false,
            true,
        ]);

        // Under NOTES (+7mm gap), still in the right column (Designer x kept).
        $this->assertEqualsWithDelta(187.0, $absoluteFieldY['job_orders.field_data'], 0.01);
        $this->assertGreaterThan($notesBottom, $absoluteFieldY['job_orders.field_data']);
        $this->assertGreaterThan(
            $absoluteFieldY['job_orders.field_data'] + 5.0,
            $fieldContentBottomY['job_orders.field_data'],
        );
    }

    public function test_fo2b_form_meta_offset_is_thirty_three(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2B')->firstOrFail();
        $meta = $form->activeRevision()?->fields->firstWhere('name', 'fo2_form_meta');
        $this->assertNotNull($meta);
        $this->assertSame('fo2_notes', $meta->options['anchor']['below'] ?? null);
        $this->assertEqualsWithDelta(33.0, (float) ($meta->options['anchor']['offset_mm'] ?? 0), 0.01);
    }

    public function test_fo2a_footer_pin_drops_seven_mm(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $stamp = new ReflectionMethod(ControlledPdfFiller::class, 'pageStampY');
        $stamp->setAccessible(true);
        $pin = new ReflectionMethod(ControlledPdfFiller::class, 'formMetaYAbovePageStamp');
        $pin->setAccessible(true);

        $pageHeight = 297.0;
        $defaultStamp = $stamp->invoke($filler, $pageHeight, null);
        $fo2aStamp = $stamp->invoke($filler, $pageHeight, 'LSP-7.8-FO2A');
        $this->assertEqualsWithDelta($defaultStamp + 7.0, $fo2aStamp, 0.01);

        $defaultPin = $pin->invoke($filler, 18.0, 7.0, $pageHeight, null);
        $fo2aPin = $pin->invoke($filler, 18.0, 7.0, $pageHeight, 'LSP-7.8-FO2A');
        $this->assertEqualsWithDelta($defaultPin + 7.0, $fo2aPin, 0.01);
    }

    public function test_fo2a_signatory_offsets_dropped_seven_mm(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2A')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $reviewed = $revision->fields->firstWhere('name', 'fo2_signatory_reviewed');
        $noted = $revision->fields->firstWhere('name', 'fo2_signatory_noted');
        $meta = $revision->fields->firstWhere('name', 'fo2_form_meta');
        $this->assertNotNull($reviewed);
        $this->assertNotNull($noted);
        $this->assertNotNull($meta);
        $this->assertEqualsWithDelta(13.0, (float) ($reviewed->options['anchor']['offset_mm'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(39.0, (float) ($noted->options['anchor']['offset_mm'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(57.0, (float) ($meta->options['anchor']['offset_mm'] ?? 0), 0.01);
    }

    public function test_fo2a_anchors_noted_under_reviewed_ink(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'anchorFo2aNotedUnderReviewed');
        $method->setAccessible(true);
        $apply = new ReflectionMethod(ControlledPdfFiller::class, 'applyAnchorBelow');
        $apply->setAccessible(true);

        $overlay = [
            'name' => 'fo2_signatory_noted',
            'h' => 20.0,
            'options' => [
                'anchor' => [
                    'below' => 'fo2_notes',
                    'offset_mm' => 55.0,
                    'above' => 'fo2_form_meta',
                    'above_offset_mm' => 2.0,
                ],
            ],
        ];

        $fieldContentBottomY = [
            'fo2_notes' => 200.0,
            'fo2_signatory_reviewed' => 220.0,
        ];
        $anchoredFew = $method->invoke($filler, $overlay, $fieldContentBottomY, 7.0);
        $this->assertSame('fo2_signatory_reviewed', $anchoredFew['options']['anchor']['below'] ?? null);
        $this->assertEqualsWithDelta(7.0, (float) ($anchoredFew['options']['anchor']['offset_mm'] ?? 0), 0.01);
        $this->assertArrayNotHasKey('above', $anchoredFew['options']['anchor']);

        $placedFew = $apply->invoke(
            $filler,
            $anchoredFew,
            [],
            ['fo2_form_meta' => 260.0],
            [],
            $fieldContentBottomY,
        );
        // Few-test: Reviewed ink 220 + 7mm.
        $this->assertEqualsWithDelta(227.0, (float) $placedFew['y'], 0.01);

        $anchoredTall = $method->invoke($filler, $overlay, $fieldContentBottomY, 3.0);
        $placedTall = $apply->invoke(
            $filler,
            $anchoredTall,
            [],
            ['fo2_form_meta' => 260.0],
            [],
            $fieldContentBottomY,
        );
        // Tall/19-test: Reviewed ink 220 + 3mm.
        $this->assertEqualsWithDelta(223.0, (float) $placedTall['y'], 0.01);
    }

    public function test_fo2a_short_sheet_drops_reviewed_certified_five_mm(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'bumpFo2aFirstRowOffsets');
        $method->setAccessible(true);

        $rows = [
            [
                'field' => new ControlledFormField(['name' => 'fo2_signatory_reviewed']),
                'overlay' => [
                    'name' => 'fo2_signatory_reviewed',
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 13.0,
                            'above' => 'fo2_form_meta',
                            'above_offset_mm' => 44.0,
                        ],
                    ],
                ],
                'value' => null,
            ],
            [
                'field' => new ControlledFormField(['name' => 'fo2_signatory_certified']),
                'overlay' => [
                    'name' => 'fo2_signatory_certified',
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 13.0,
                            'above' => 'fo2_form_meta',
                            'above_offset_mm' => 44.0,
                        ],
                    ],
                ],
                'value' => null,
            ],
            [
                'field' => new ControlledFormField(['name' => 'fo2_signatory_noted']),
                'overlay' => [
                    'name' => 'fo2_signatory_noted',
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 43.0,
                            'above' => 'fo2_form_meta',
                            'above_offset_mm' => 14.0,
                        ],
                    ],
                ],
                'value' => null,
            ],
        ];

        $bumped = $method->invoke($filler, $rows, 5.0);
        $this->assertEqualsWithDelta(
            18.0,
            (float) $bumped[0]['overlay']['options']['anchor']['offset_mm'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            18.0,
            (float) $bumped[1]['overlay']['options']['anchor']['offset_mm'],
            0.01,
        );
        // Noted By is unchanged by the first-row drop helper.
        $this->assertEqualsWithDelta(
            43.0,
            (float) $bumped[2]['overlay']['options']['anchor']['offset_mm'],
            0.01,
        );
    }

    public function test_fo2b_matrix_band_matches_fo2_height(): void
    {
        $this->seed();

        $fo2 = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $fo2b = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2B')->firstOrFail();
        $fo2Matrix = $fo2->activeRevision()?->fields->firstWhere('name', 'ww_fo2_matrix');
        $fo2bMatrix = $fo2b->activeRevision()?->fields->firstWhere('name', 'ww_fo2b_matrix');
        $this->assertNotNull($fo2Matrix);
        $this->assertNotNull($fo2bMatrix);
        $this->assertEqualsWithDelta((float) $fo2Matrix->height, (float) $fo2bMatrix->height, 0.5);
        $this->assertSame(20, (int) ($fo2bMatrix->table_config['preview_rows'] ?? 0));
    }

    public function test_fo2_first_signatory_offset_reads_designer_gap(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'fo2FirstSignatoryOffsetMm');
        $method->setAccessible(true);

        $fields = collect([
            new ControlledFormField([
                'name' => 'fo2_signatory_reviewed',
                'options' => [
                    'anchor' => [
                        'below' => 'fo2_notes',
                        'offset_mm' => 25.0,
                    ],
                ],
            ]),
            new ControlledFormField([
                'name' => 'fo2_signatory_certified',
                'options' => [
                    'anchor' => [
                        'below' => 'fo2_notes',
                        'offset_mm' => 25.0,
                    ],
                ],
            ]),
        ]);

        $this->assertEqualsWithDelta(25.0, $method->invoke($filler, $fields), 0.01);
    }

    public function test_fo2_first_signatory_offset_from_rows_uses_shrunk_gap(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'fo2FirstSignatoryOffsetFromRows');
        $method->setAccessible(true);

        $rows = [
            [
                'field' => new ControlledFormField(['name' => 'fo2_signatory_certified']),
                'overlay' => [
                    'name' => 'fo2_signatory_certified',
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 1.5,
                        ],
                    ],
                ],
                'value' => null,
            ],
        ];

        $this->assertEqualsWithDelta(1.5, $method->invoke($filler, $rows), 0.01);
    }

    public function test_fo2b_signatory_stack_clears_field_data_ink(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'estimateFo2SignatoryStackBottom');
        $method->setAccessible(true);

        $rows = [
            [
                'field' => new ControlledFormField(['name' => 'fo2_signatory_certified']),
                'overlay' => [
                    'name' => 'fo2_signatory_certified',
                    'h' => 22.0,
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 6.0,
                        ],
                    ],
                ],
                'value' => null,
            ],
        ];

        // Synthetic chrome bottom = max(notes, field_data); certified starts 6mm below that.
        $chromeBottom = 200.0;
        $stackBottom = $method->invoke($filler, $rows, $chromeBottom);
        $this->assertEqualsWithDelta(228.0, $stackBottom, 0.01);
        $this->assertGreaterThan($chromeBottom + 5.0, $stackBottom);
    }

    public function test_fo2_tall_chrome_reduces_lab_refs_notes_fonts(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $should = new ReflectionMethod(ControlledPdfFiller::class, 'shouldReduceFo2TallChromeFont');
        $should->setAccessible(true);
        $apply = new ReflectionMethod(ControlledPdfFiller::class, 'applyFo2TallChromeFontReduce');
        $apply->setAccessible(true);

        foreach (['fo2_lab_conditions', 'fo2_references', 'fo2_notes', 'job_orders.field_data'] as $name) {
            $this->assertTrue($should->invoke($filler, $name), $name);
        }

        $reduced = $apply->invoke($filler, [
            'name' => 'fo2_notes',
            'font_size' => 8.0,
        ]);
        $this->assertEqualsWithDelta(7.0, (float) $reduced['font_size'], 0.01);
    }

    public function test_fo2_tall_sheet_shrinks_notes_signatory_gap(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'shrinkFo2SignatoryNotesOffsets');
        $method->setAccessible(true);

        $reviewed = new ControlledFormField(['name' => 'fo2_signatory_reviewed']);
        $noted = new ControlledFormField(['name' => 'fo2_signatory_noted']);
        $rows = [
            [
                'field' => $reviewed,
                'overlay' => [
                    'name' => 'fo2_signatory_reviewed',
                    'h' => 24.0,
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 6.0,
                            'above' => 'fo2_form_meta',
                            'above_offset_mm' => 46.0,
                        ],
                    ],
                ],
                'value' => null,
            ],
            [
                'field' => $noted,
                'overlay' => [
                    'name' => 'fo2_signatory_noted',
                    'h' => 20.0,
                    'options' => [
                        'anchor' => [
                            'below' => 'fo2_notes',
                            'offset_mm' => 28.0,
                            'above' => 'fo2_form_meta',
                            'above_offset_mm' => 24.0,
                        ],
                    ],
                ],
                'value' => null,
            ],
        ];

        // ~19-test overflow: only tighten the shared NOTES→first-row gap; keep
        // Reviewed→Noted spacing (28 − 6 = 22) so Noted never lands on Reviewed.
        $shrunk = $method->invoke($filler, $rows, 10.0);
        $this->assertEqualsWithDelta(
            1.5,
            (float) $shrunk[0]['overlay']['options']['anchor']['offset_mm'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            23.5,
            (float) $shrunk[1]['overlay']['options']['anchor']['offset_mm'],
            0.01,
        );
        $this->assertEqualsWithDelta(
            22.0,
            (float) $shrunk[1]['overlay']['options']['anchor']['offset_mm']
                - (float) $shrunk[0]['overlay']['options']['anchor']['offset_mm'],
            0.01,
        );
    }

    public function test_form_meta_follows_signatory_stack_under_notes(): void
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
                    'below' => 'fo2_notes',
                    'offset_mm' => 52.0,
                ],
            ],
        ];

        $pageHeight = 279.4;
        $pinned = (new ReflectionMethod(ControlledPdfFiller::class, 'formMetaYAbovePageStamp'))
            ->invoke($filler, 18.0, 7.0, $pageHeight);

        $fromNotes = $method->invoke(
            $filler,
            $field,
            [],
            $pageHeight,
            ['fo2_notes' => 140.0],
        );
        $this->assertEqualsWithDelta($pinned, $fromNotes, 0.01);

        $follow = $method->invoke(
            $filler,
            $field,
            [],
            $pageHeight,
            ['fo2_notes' => 140.0],
            250.0,
        );
        $this->assertEqualsWithDelta(250.0, $follow, 0.01);
        $this->assertGreaterThan($pinned, $follow);
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
        // Leftover designer-box capacity must stay open so anchors sit under the
        // last row (filler whites out blank-PDF empty rows in that band).
        $this->assertGreaterThan(40.0, ($y + $designerBoxH) - $bottomY);

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
