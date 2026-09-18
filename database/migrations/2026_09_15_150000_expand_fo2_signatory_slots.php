<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use App\Support\DynamicTestMatrix;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2: 4 editable signatories + re-import blueprint (Reviewed / Noted / Certified x2).
 * Also re-apply matrix config while preserving designer column aligns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->first();
        if (! $form) {
            return;
        }

        $form->fill([
            'analyst_signatory_slots' => 4,
            'analyst_require_prc' => true,
        ])->save();

        $forms = app(ControlledFormService::class);
        $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->first();
        if ($revision && $forms->blueprintConfigKey($form) !== null) {
            // Preserve matrix designer tweaks (align) when re-importing other fields.
            $matrix = $revision->fields()->where('name', 'ww_fo2_matrix')->first();
            $priorMatrixConfig = is_array($matrix?->table_config) ? $matrix->table_config : null;

            $forms->importBlueprint($revision);

            if ($priorMatrixConfig !== null) {
                $matrix = $revision->fields()->where('name', 'ww_fo2_matrix')->first();
                if ($matrix) {
                    $matrix->table_config = DynamicTestMatrix::normalizeStoredTableConfig(
                        'ww_fo2_matrix',
                        $matrix->field_type,
                        $priorMatrixConfig,
                        $form,
                    );
                    $matrix->save();
                }
            }

            $revision->touch();
        }
    }

    public function down(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->first();
        if (! $form) {
            return;
        }

        $form->fill(['analyst_signatory_slots' => 2])->save();
    }
};
