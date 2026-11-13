<?php

use App\Enums\AnalysisCategory;
use App\Models\AnalysisCategory as AnalysisCategoryModel;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Services\ControlledFormService;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Add WW-16 Electrical Conductivity + WW-17 Turbidity to the shared FO2 panel
 * (Wastewater + River water). Rebinds FO2 / FO2A / FO2B pivots and bumps
 * Form Designer matrix preview_rows to 19.
 *
 * Safe to re-run. Does not re-import FO2 Designer plots.
 */
return new class extends Migration
{
    private const NEW_TYPES = [
        ['WW-16', 'Electrical Conductivity (µS/cm)', 750],
        ['WW-17', 'Turbidity (NTU)', 750],
    ];

    public function up(): void
    {
        foreach (self::NEW_TYPES as [$code, $name, $price]) {
            $this->ensureType($code, $name, (int) $price);
        }

        $forms = app(ControlledFormService::class);
        $orderedIds = $this->panelTypeIds();
        if ($orderedIds === []) {
            return;
        }

        foreach (['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'] as $formCode) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            if ($form instanceof ControlledForm) {
                $forms->syncBindings($form, $orderedIds, null);
            }
        }

        $this->healMatrixPreviewRows('LSP-7.8-FO2', 'ww_fo2_matrix');
        $this->healMatrixPreviewRows('LSP-7.8-FO2A', 'ww_fo2a_matrix');
    }

    public function down(): void
    {
        // Non-destructive — leave types and bindings in place.
    }

    private function ensureType(string $code, string $name, int $price): void
    {
        $category = AnalysisCategoryModel::query()->firstOrCreate(
            ['slug' => AnalysisCategory::WasteWater->value],
            [
                'name' => AnalysisCategory::WasteWater->label(),
                'sort_order' => 0,
                'is_active' => true,
            ],
        );

        $method = OfficialAnalysisCatalog::methodForCode($code);
        $type = AnalysisType::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'method' => $method,
                'category_id' => $category->id,
                'default_price' => $price,
                'is_active' => true,
                'show_on_kiosk' => true,
                'catalog_scope' => OfficialAnalysisCatalog::scopeForDefinition(
                    AnalysisCategory::WasteWater->value,
                    [$code, $name, $price],
                )->value,
                'sort_order' => (int) (AnalysisType::query()->max('sort_order') ?? 0) + 1,
            ],
        );

        $type->fill([
            'name' => $name,
            'category_id' => $category->id,
            'default_price' => $price,
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

    private function healMatrixPreviewRows(string $formCode, string $fieldName): void
    {
        $form = ControlledForm::query()->where('form_code', $formCode)->first();
        if (! $form instanceof ControlledForm) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->currentRevision;
        if ($revision === null) {
            return;
        }

        $field = $revision->fields()->where('name', $fieldName)->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $config = is_array($field->table_config) ? $field->table_config : [];
        if ((int) ($config['preview_rows'] ?? 0) === 19) {
            return;
        }

        $config['preview_rows'] = 19;
        $field->table_config = $config;
        $field->save();
    }
};
