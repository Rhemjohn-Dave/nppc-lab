<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Support\DynamicTestMatrix;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->first();
        if (! $form) {
            return;
        }

        $config = DynamicTestMatrix::wastewaterPhysicoConfig();

        ControlledFormField::query()
            ->where('name', 'ww_fo2_matrix')
            ->whereIn(
                'controlled_form_revision_id',
                $form->revisions()->pluck('id'),
            )
            ->get()
            ->each(function (ControlledFormField $field) use ($config): void {
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
