<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO3 prints only the selected tests; stretching a single row to the full box
 * produced one giant cell. Turn stretch off so rows keep natural height.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->first();
        if (! $form) {
            return;
        }

        ControlledFormField::query()
            ->where('name', 'dw_old_fo3_matrix')
            ->whereIn(
                'controlled_form_revision_id',
                $form->revisions()->pluck('id'),
            )
            ->get()
            ->each(function (ControlledFormField $field): void {
                $config = is_array($field->table_config) ? $field->table_config : [];
                $config['stretch_body'] = false;
                $field->table_config = $config;
                $field->save();
            });

        foreach ($form->revisions as $revision) {
            $revision->touch();
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
