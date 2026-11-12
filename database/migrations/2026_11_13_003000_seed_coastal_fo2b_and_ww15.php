<?php

use App\Enums\AnalysisCategory;
use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisCategory as AnalysisCategoryModel;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Ensure WW-15 exists and create an empty FO2B shell if missing.
 *
 * Does NOT re-import FO2 / FO2A / FO2B Designer plots (server edits stay intact).
 * Does NOT rebind FO2 / FO2A type pivots.
 *
 * For the calibrated Coastal plot + signatories, run:
 *   php artisan migrate --path=database/migrations/2026_11_13_011000_deploy_coastal_fo2b_local_plot.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureWw15();

        $admin = User::query()->where('email', 'admin@nppc.local')->first()
            ?? User::query()->orderBy('id')->first();
        $meta = OfficialAnalysisCatalog::resultFormRegistry()['ww_physico_coastal'] ?? null;
        if (! $admin || ! is_array($meta)) {
            return;
        }

        if (ControlledForm::query()->where('form_code', 'LSP-7.8-FO2B')->exists()) {
            return;
        }

        $form = ControlledForm::query()->create([
            'form_code' => $meta['form_code'],
            'name' => $meta['name'],
            'description' => sprintf(
                'Official %s · %s · Eff. %s. Coastal Water physico sheet — plot via deploy_coastal_fo2b_local_plot.',
                $meta['official'],
                $meta['revision'],
                $meta['effective'],
            ),
            'department' => 'Laboratory',
            'category' => ControlledFormCategory::AnalysisResult,
            'analysis_package_id' => null,
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => true,
        ]);

        $form->revisions()->create([
            'revision' => '01',
            'status' => ControlledFormRevisionStatus::Draft,
            'created_by' => $admin->id,
            'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
            'notes' => 'Empty FO2B shell — fields come from 011000 deploy migration.',
        ]);
    }

    public function down(): void
    {
        // Non-destructive.
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
};
