<?php

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Metals result shell only. Does not attach a PDF or import plots.
 */
return new class extends Migration
{
    public function up(): void
    {
        $meta = OfficialAnalysisCatalog::resultFormRegistry()['metals'] ?? null;
        if (! is_array($meta)) {
            return;
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $forms = app(\App\Services\ControlledFormService::class);
        $codes = OfficialAnalysisCatalog::metalsResultTypeCodes();

        $form = ControlledForm::query()->firstOrCreate(
            ['form_code' => $meta['form_code']],
            [
                'name' => $meta['name'],
                'description' => sprintf(
                    'Official %s · %s · Eff. %s. Upload the blank PDF in Form Designer.',
                    $meta['official'],
                    $meta['revision'],
                    $meta['effective'],
                ),
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::AnalysisResult,
                'analysis_package_id' => null,
                'analyst_signatory_slots' => 2,
                'analyst_require_prc' => true,
            ],
        );

        $form->fill([
            'name' => $meta['name'],
            'analysis_package_id' => null,
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => true,
        ])->save();

        $orderedIds = collect($codes)
            ->map(fn (string $code) => AnalysisType::query()->where('code', $code)->value('id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($orderedIds !== []) {
            $forms->syncBindings($form, $orderedIds, null);
        }

        if ($form->revisions()->exists()) {
            return;
        }

        $form->revisions()->create([
            'revision' => '1',
            'status' => ControlledFormRevisionStatus::Draft,
            'created_by' => $admin?->id,
            'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
            'notes' => 'Upload the blank one-page metals PDF in Form Designer. Do not seed plots over it.',
        ]);
    }

    public function down(): void
    {
        // Leave the form in place so an uploaded PDF is not deleted.
    }
};
