<?php

namespace Tests\Unit;

use App\Support\DynamicTestMatrix;
use Tests\TestCase;

class DynamicTestMatrixStretchHeightTest extends TestCase
{
    public function test_stretch_distributes_leftover_across_drawn_rows_only(): void
    {
        $natural = [9.0, 9.0, 9.0, 9.0, 9.0];
        $available = 75.0; // leftover 30 → +6 each

        $stretched = DynamicTestMatrix::stretchBodyRowHeights($natural, $available);

        $this->assertCount(5, $stretched);
        $this->assertEqualsWithDelta(75.0, array_sum($stretched), 0.01);
        foreach ($stretched as $height) {
            $this->assertEqualsWithDelta(15.0, $height, 0.01);
        }
    }

    public function test_stretch_does_not_invent_empty_slots_for_waived_tests(): void
    {
        // 2 of 11 selected — only two natural heights are passed in.
        $natural = [10.0, 12.0];
        $available = 80.0;

        $stretched = DynamicTestMatrix::stretchBodyRowHeights($natural, $available);

        $this->assertCount(2, $stretched);
        $this->assertEqualsWithDelta(80.0, array_sum($stretched), 0.01);
        $this->assertEqualsWithDelta(39.0, $stretched[0], 0.01);
        $this->assertEqualsWithDelta(41.0, $stretched[1], 0.01);
    }

    public function test_stretch_keeps_natural_heights_when_content_already_fills_box(): void
    {
        $natural = [20.0, 20.0, 20.0];
        $available = 60.0;

        $stretched = DynamicTestMatrix::stretchBodyRowHeights($natural, $available);

        $this->assertSame($natural, $stretched);
    }

    public function test_nitrite_config_disables_body_stretch(): void
    {
        $config = DynamicTestMatrix::nitriteConfig();
        $this->assertFalse($config['stretch_body'] ?? true);

        // With stretch off, filler uses natural heights even when the field box is tall.
        $natural = [12.0];
        $available = 80.0;
        $this->assertSame(
            $natural,
            ($config['stretch_body'] ?? true) === false
                ? $natural
                : DynamicTestMatrix::stretchBodyRowHeights($natural, $available),
        );
        $this->assertNotEquals(
            $natural,
            DynamicTestMatrix::stretchBodyRowHeights($natural, $available),
        );
    }

    public function test_milk_blueprint_matrix_box_height_reaches_notes_band(): void
    {
        $matrix = collect(config('result_milk_form_fields.fields', []))
            ->first(fn ($field): bool => is_array($field) && ($field['name'] ?? '') === 'milk_f016_matrix');

        $this->assertIsArray($matrix);
        $this->assertGreaterThanOrEqual(90.0, (float) $matrix['h']);
        $bottom = (float) $matrix['y'] + (float) $matrix['h'];
        $this->assertGreaterThanOrEqual(160.0, $bottom);
    }
}
