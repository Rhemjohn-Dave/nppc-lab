<?php

namespace Tests\Unit;

use App\Support\DynamicTestMatrix;
use Tests\TestCase;

class DynamicTestMatrixHeaderSourcesTest extends TestCase
{
    public function test_resolve_header_sources_replaces_label_and_sublabel_from_bag(): void
    {
        $columns = [
            [
                'key' => 'test',
                'label' => 'TEST',
            ],
            [
                'key' => 'result',
                'label' => 'Control Number',
                'label_data_source' => 'results.control_no',
                'sublabel' => 'Sample Description',
                'sublabel_data_source' => 'results.sample_description',
            ],
        ];

        $resolved = DynamicTestMatrix::resolveHeaderSources($columns, [
            'results.control_no' => 'JO-2026-00125',
            'results.sample_description' => 'Raw milk batch A',
        ]);

        $this->assertSame('TEST', $resolved[0]['label']);
        $this->assertSame('JO-2026-00125', $resolved[1]['label']);
        $this->assertSame('Raw milk batch A', $resolved[1]['sublabel']);
    }

    public function test_resolve_header_sources_keeps_fallback_when_bag_value_empty(): void
    {
        $columns = [
            [
                'key' => 'result',
                'label' => 'Control Number',
                'label_data_source' => 'results.control_no',
                'sublabel' => 'Sample Description',
                'sublabel_data_source' => 'results.sample_description',
            ],
        ];

        $resolved = DynamicTestMatrix::resolveHeaderSources($columns, [
            'results.control_no' => '',
            'results.sample_description' => null,
        ]);

        $this->assertSame('Control Number', $resolved[0]['label']);
        $this->assertSame('Sample Description', $resolved[0]['sublabel']);
    }

    public function test_normalize_water_activity_rejects_poisoned_milk_result_headers(): void
    {
        $poisoned = [
            'columns' => [
                [
                    'key' => 'control_no',
                    'label' => 'Control Number',
                    'width_pct' => 30,
                    'align' => 'L',
                ],
                [
                    'key' => 'sample_description',
                    'label' => 'Sample Description',
                    'width_pct' => 30,
                ],
                [
                    'key' => 'result',
                    'label' => 'Control Number',
                    'sublabel' => 'Sample Description',
                    'label_data_source' => 'results.control_no',
                    'sublabel_data_source' => 'results.sample_description',
                    'width_pct' => 40,
                    'align' => 'C',
                ],
            ],
            'preview_rows' => 3,
        ];

        $normalized = DynamicTestMatrix::normalizeStoredTableConfig(
            'water_activity_f016_matrix',
            \App\Enums\ControlledFormFieldType::DynamicTestMatrix,
            $poisoned,
        );

        $this->assertIsArray($normalized);
        $columns = $normalized['columns'];
        $this->assertSame(
            ['control_no', 'sample_description', 'result'],
            array_values(array_map(static fn (array $col): string => (string) $col['key'], $columns)),
        );
        $this->assertSame('Control Number', $columns[0]['label']);
        $this->assertSame('Sample Description', $columns[1]['label']);
        $this->assertSame('Water Activity, Aw', $columns[2]['label']);
        $this->assertSame('Water Activity Meter', $columns[2]['sublabel']);
        $this->assertArrayNotHasKey('label_data_source', $columns[2]);
        $this->assertArrayNotHasKey('sublabel_data_source', $columns[2]);
        $this->assertSame('L', $columns[0]['align']);
        $this->assertSame(1, $normalized['preview_rows']);
    }

    public function test_normalize_chloramphenicol_rejects_poisoned_milk_result_headers(): void
    {
        $poisoned = [
            'columns' => [
                [
                    'key' => 'control_no',
                    'label' => 'Control Number',
                    'width_pct' => 30,
                ],
                [
                    'key' => 'sample_description',
                    'label' => 'Sample Description',
                    'width_pct' => 30,
                ],
                [
                    'key' => 'result',
                    'label' => 'Control Number',
                    'sublabel' => 'Sample Description',
                    'label_data_source' => 'results.control_no',
                    'sublabel_data_source' => 'results.sample_description',
                    'width_pct' => 40,
                ],
            ],
            'preview_rows' => 3,
        ];

        $normalized = DynamicTestMatrix::normalizeStoredTableConfig(
            'chloramphenicol_f016_matrix',
            \App\Enums\ControlledFormFieldType::DynamicTestMatrix,
            $poisoned,
        );

        $this->assertIsArray($normalized);
        $columns = $normalized['columns'];
        $this->assertSame(
            ['control_no', 'sample_description', 'result'],
            array_values(array_map(static fn (array $col): string => (string) $col['key'], $columns)),
        );
        $this->assertSame('Results', $columns[2]['label']);
        $this->assertSame('(ppb)', $columns[2]['sublabel']);
        $this->assertArrayNotHasKey('label_data_source', $columns[2]);
        $this->assertArrayNotHasKey('sublabel_data_source', $columns[2]);
        $this->assertSame(1, $normalized['preview_rows']);
    }

    public function test_normalize_nitrite_rejects_poisoned_milk_result_headers(): void
    {
        $poisoned = [
            'columns' => [
                [
                    'key' => 'control_no',
                    'label' => 'Control Number',
                    'width_pct' => 30,
                ],
                [
                    'key' => 'sample_description',
                    'label' => 'Sample Description',
                    'width_pct' => 30,
                ],
                [
                    'key' => 'result',
                    'label' => 'Control Number',
                    'sublabel' => 'Sample Description',
                    'label_data_source' => 'results.control_no',
                    'sublabel_data_source' => 'results.sample_description',
                    'width_pct' => 40,
                ],
            ],
            'preview_rows' => 3,
        ];

        $normalized = DynamicTestMatrix::normalizeStoredTableConfig(
            'nitrite_f016_matrix',
            \App\Enums\ControlledFormFieldType::DynamicTestMatrix,
            $poisoned,
        );

        $this->assertIsArray($normalized);
        $columns = $normalized['columns'];
        $this->assertSame(
            ['control_no', 'sample_description', 'result'],
            array_values(array_map(static fn (array $col): string => (string) $col['key'], $columns)),
        );
        $this->assertSame('Nitrite (mg/kg)', $columns[2]['label']);
        $this->assertSame('Spectrophotometric Method', $columns[2]['sublabel']);
        $this->assertArrayNotHasKey('label_data_source', $columns[2]);
        $this->assertArrayNotHasKey('sublabel_data_source', $columns[2]);
        $this->assertSame(1, $normalized['preview_rows']);
        $this->assertFalse($normalized['stretch_body'] ?? true);
    }
}
