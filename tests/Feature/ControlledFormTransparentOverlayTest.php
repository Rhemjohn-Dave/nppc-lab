<?php

namespace Tests\Feature;

use App\Models\ControlledForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledFormTransparentOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_fo2_prc_fields_have_no_white_cover(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->firstOrFail();
        $revision->load('fields');

        $prcNames = [
            'results.analyst_prc',
            'results.analyst_prc_2',
            'results.analyst_prc_3',
            'results.analyst_prc_4',
        ];

        foreach ($prcNames as $name) {
            $field = $revision->fields->firstWhere('name', $name)
                ?? $revision->fields->firstWhere('data_source_key', $name);

            $this->assertNotNull($field, "Expected FO2 field {$name}");
            $options = is_array($field->options) ? $field->options : [];
            $this->assertFalse(
                (bool) ($options['cover'] ?? false),
                "FO2 {$name} must not use white cover fill",
            );
        }

        foreach ($revision->fields as $field) {
            $options = is_array($field->options) ? $field->options : [];
            $this->assertFalse(
                (bool) ($options['cover'] ?? false),
                "FO2 field {$field->name} must not use white cover fill",
            );
        }
    }

    public function test_ww_physico_blueprint_omits_cover(): void
    {
        $fields = config('result_ww_physico_form_fields.fields', []);
        $this->assertNotEmpty($fields);

        foreach ($fields as $field) {
            $options = is_array($field['options'] ?? null) ? $field['options'] : [];
            $this->assertArrayNotHasKey(
                'cover',
                $options,
                'FO2 blueprint must not seed options.cover (field '.($field['name'] ?? '?').')',
            );
        }
    }
}
