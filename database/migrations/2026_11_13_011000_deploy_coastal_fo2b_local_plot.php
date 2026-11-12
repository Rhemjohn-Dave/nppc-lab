<?php

use App\Enums\AnalysisCategory;
use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisCategory as AnalysisCategoryModel;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledFormService;
use App\Services\RevisionWorkflow;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * Deploy the local FO2B Coastal Water plot (PDF + Form Designer fields +
 * signatory chrome) to another server without redoing Form Designer.
 *
 * Source of truth: config/result_ww_physico_coastal_form_fields.php
 * (exported via `php artisan controlled-forms:export-blueprints --form=LSP-7.8-FO2B --pdfs`).
 */
return new class extends Migration
{
    private const FORM_CODE = 'LSP-7.8-FO2B';

    private const PDF = 'PC Wastewater result Form Issue 18 09012026 blank.pdf';

    public function up(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first()
            ?? User::query()->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        $this->ensureWw15();

        $meta = OfficialAnalysisCatalog::resultFormRegistry()['ww_physico_coastal'] ?? null;
        if (! is_array($meta)) {
            return;
        }

        $forms = app(ControlledFormService::class);
        $workflow = app(RevisionWorkflow::class);

        $form = ControlledForm::query()->firstOrCreate(
            ['form_code' => self::FORM_CODE],
            [
                'name' => $meta['name'],
                'description' => sprintf(
                    'Official %s · %s · Eff. %s. Coastal Water physico sheet (Issue 18 blank + FIELD DATA).',
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

        $orderedIds = $this->panelTypeIds();
        if ($orderedIds !== []) {
            $forms->syncBindings($form, $orderedIds, null);
        }

        if (! $forms->hasBlueprint($form)) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->first();
        if (! $revision) {
            $revision = $form->revisions()->create([
                'revision' => '01',
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Deploy local FO2B Coastal Water Form Designer plot.',
            ]);
        } elseif ($revision->status === ControlledFormRevisionStatus::Active) {
            // Import into the active revision so the server plot matches the exported blueprint.
            // ControlledFormService::importBlueprint replaces fields in place.
        } elseif (! $revision->status->isEditable()) {
            $revision = $form->revisions()->create([
                'revision' => (string) str_pad(
                    (string) max(1, ((int) $form->revisions()->max('revision')) + 1),
                    2,
                    '0',
                    STR_PAD_LEFT,
                ),
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Deploy local FO2B Coastal Water Form Designer plot.',
            ]);
        }

        $absolute = resource_path('forms/official/'.self::PDF);
        if (is_file($absolute)) {
            if ($revision->status !== ControlledFormRevisionStatus::Draft
                && $revision->status !== ControlledFormRevisionStatus::Active
            ) {
                // no-op; attachFile expects an editable or we re-attach on draft
            }

            if ($revision->status === ControlledFormRevisionStatus::Active) {
                // Keep active PDF; importBlueprint still refreshes fields.
            } elseif (! $revision->hasCanonicalPdf()) {
                $upload = new UploadedFile($absolute, self::PDF, 'application/pdf', null, true);
                $revision = $forms->attachFile($form, $revision->fresh() ?? $revision, $upload);
            }
        }

        $forms->importBlueprint($revision->fresh() ?? $revision);
        $revision = $revision->fresh() ?? $revision;
        $revision->touch();

        if (
            $revision->hasCanonicalPdf()
            && $revision->fields()->exists()
            && $revision->status !== ControlledFormRevisionStatus::Active
        ) {
            $workflow->activate($revision, $admin);
        }

        if ($revision->status === ControlledFormRevisionStatus::Active
            && (int) $form->fresh()->current_revision_id !== (int) $revision->id
        ) {
            $form->update(['current_revision_id' => $revision->id]);
        }
    }

    public function down(): void
    {
        // Leave the deployed PDF and plots in place.
    }

    private function ensureWw15(): void
    {
        $category = AnalysisCategoryModel::query()->firstOrCreate(
            ['slug' => AnalysisCategory::WasteWater->value],
            [
                'name' => AnalysisCategory::WasteWater->label(),
                'sort_order' => 0,
                'is_active' => true,
            ],
        );

        $method = OfficialAnalysisCatalog::methodForCode('WW-15');
        $type = AnalysisType::query()->firstOrCreate(
            ['code' => 'WW-15'],
            [
                'name' => '*Nitrite (mg/L)',
                'method' => $method,
                'category_id' => $category->id,
                'default_price' => 750,
                'is_active' => true,
                'show_on_kiosk' => true,
                'catalog_scope' => OfficialAnalysisCatalog::scopeForDefinition(
                    AnalysisCategory::WasteWater->value,
                    ['WW-15', '*Nitrite (mg/L)', 750],
                )->value,
                'sort_order' => (int) (AnalysisType::query()->max('sort_order') ?? 0) + 1,
            ],
        );

        $type->fill([
            'name' => '*Nitrite (mg/L)',
            'category_id' => $category->id,
            'default_price' => 750,
            'is_active' => true,
            'method' => $method ?? $type->method,
        ])->save();
    }

    /**
     * @return list<int>
     */
    private function panelTypeIds(): array
    {
        $codes = OfficialAnalysisCatalog::wastewaterPhysicoPanelTypeCodes();
        $byCode = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        return collect($codes)
            ->map(fn (string $code) => $byCode->get($code)?->id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
};
