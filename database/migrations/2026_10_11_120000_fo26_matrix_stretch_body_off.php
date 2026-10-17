<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO26 usually prints only a few tests; stretching them across the full box
 * produced very tall rows. Turn stretch off so rows keep natural height.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO26')->first();
        if (! $form) {
            return;
        }

        ControlledFormField::query()
            ->where('name', 'food_micro_fo26_matrix')
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
