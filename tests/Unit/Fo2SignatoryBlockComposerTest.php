<?php

namespace Tests\Unit;

use App\Support\Fo2SignatoryBlockComposer;
use PHPUnit\Framework\TestCase;

class Fo2SignatoryBlockComposerTest extends TestCase
{
    public function test_compose_reviewed_appends_prc_to_license_line(): void
    {
        $text = Fo2SignatoryBlockComposer::compose([
            'label' => 'Reviewed by:',
            'people' => [[
                'name_key' => 'results.analyst_name',
                'prc_key' => 'results.analyst_prc',
                'fallback_name' => 'FALLBACK NAME',
                'lines_after_name' => [
                    'Approved Signatory (Chem)',
                    'Laboratory Analyst',
                    'Registered Chemical Technician, License #',
                ],
            ]],
        ], [
            'results.analyst_name' => 'ANA ANALYST',
            'results.analyst_prc' => '00761',
        ]);

        $this->assertSame(
            "Reviewed by:\nANA ANALYST\nApproved Signatory (Chem)\nLaboratory Analyst\nRegistered Chemical Technician, License #00761",
            $text,
        );
    }

    public function test_compose_certified_two_people_with_blank_separator(): void
    {
        $text = Fo2SignatoryBlockComposer::compose([
            'label' => 'Certified Correct:',
            'people' => [
                [
                    'name_key' => 'results.analyst_name_3',
                    'fallback_name' => 'CERT ONE',
                    'lines_after_name' => ['Laboratory Analyst', 'Registered Chemist, License #16125'],
                ],
                [
                    'name_key' => 'results.analyst_name_4',
                    'fallback_name' => 'CERT TWO',
                    'lines_after_name' => ['Laboratory Analyst', 'Registered Chemist, License #16126'],
                ],
            ],
        ], [
            'results.analyst_name_3' => 'FERRESA',
            'results.analyst_name_4' => 'JAYRAYNE',
        ]);

        $this->assertStringContainsString("Certified Correct:\nFERRESA\n", $text);
        $this->assertStringContainsString("License #16125\n\nJAYRAYNE\n", $text);
        $this->assertStringContainsString('License #16126', $text);
    }

    public function test_preview_uses_fallback_when_bag_empty(): void
    {
        $block = [
            'label' => 'Noted By:',
            'people' => [[
                'name_key' => 'results.analyst_name_2',
                'fallback_name' => 'WENDY A. TAYSON, RCh, MEnE',
                'lines_after_name' => [
                    'Laboratory Head',
                    'Registered Chemist, License #15131',
                ],
            ]],
        ];

        $this->assertSame(
            "Noted By:\nWENDY A. TAYSON, RCh, MEnE\nLaboratory Head\nRegistered Chemist, License #15131",
            Fo2SignatoryBlockComposer::previewText($block),
        );
    }

    public function test_does_not_double_append_prc_when_license_already_has_number(): void
    {
        $text = Fo2SignatoryBlockComposer::compose([
            'label' => 'Reviewed by:',
            'people' => [[
                'name_key' => 'results.analyst_name',
                'prc_key' => 'results.analyst_prc',
                'fallback_name' => 'NAME',
                'lines_after_name' => [
                    'Registered Chemical Technician, License #00761',
                ],
            ]],
        ], [
            'results.analyst_name' => 'NAME',
            'results.analyst_prc' => '99999',
        ]);

        $this->assertStringContainsString('License #00761', $text);
        $this->assertStringNotContainsString('99999', $text);
    }

    public function test_resolve_prefers_static_text_layout_including_blank_lines(): void
    {
        $block = [
            'label' => 'Reviewed by:',
            'people' => [[
                'name_key' => 'results.analyst_name',
                'fallback_name' => 'MA. ANTONIELA C. LANACA',
                'lines_after_name' => [
                    'Approved Signatory (Chem)',
                    'Laboratory Analyst',
                    'Registered Chemical Technician, License #',
                ],
            ]],
        ];

        $static = "Reviewed by:\n\nMA. ANTONIELA C. LANACA\nApproved Signatory (Chem)\nLaboratory Analyst\nRegistered Chemical Technician, License #";

        $resolved = Fo2SignatoryBlockComposer::resolve($block, [], $static);
        $this->assertSame($static, $resolved);
        $this->assertStringContainsString("Reviewed by:\n\nMA. ANTONIELA", $resolved);
    }

    public function test_resolve_injects_live_name_into_static_template(): void
    {
        $block = [
            'label' => 'Reviewed by:',
            'people' => [[
                'name_key' => 'results.analyst_name',
                'prc_key' => 'results.analyst_prc',
                'fallback_name' => 'MA. ANTONIELA C. LANACA',
                'lines_after_name' => [
                    'Registered Chemical Technician, License #',
                ],
            ]],
        ];

        $static = "Reviewed by:\n\nMA. ANTONIELA C. LANACA\nRegistered Chemical Technician, License #";
        $resolved = Fo2SignatoryBlockComposer::resolve($block, [
            'results.analyst_name' => 'LIVE REVIEWER',
            'results.analyst_prc' => '00761',
        ], $static);

        $this->assertStringContainsString("Reviewed by:\n\nLIVE REVIEWER\n", $resolved);
        $this->assertStringContainsString('License #00761', $resolved);
        $this->assertStringNotContainsString('MA. ANTONIELA C. LANACA', $resolved);
    }
}
