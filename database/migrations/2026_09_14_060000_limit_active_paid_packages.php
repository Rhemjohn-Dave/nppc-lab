<?php

use App\Enums\ControlledFormCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $keep = OfficialAnalysisCatalog::activePaidPackageCodes();
        $forms = app(ControlledFormService::class);

        AnalysisPackage::query()
            ->whereNotIn('code', $keep)
            ->update([
                'is_active' => false,
                'form_code' => null,
            ]);

        AnalysisPackage::query()
            ->where('code', 'PKG-MIC-NDW')
            ->update([
                'is_active' => true,
                'default_price' => 750,
                'form_code' => 'LSP-7.8-FO4',
                'description' => 'Total Coliform and Thermotolerant Coliform (MPN/100ml) for wastewater / non-potable samples (₱750). Result sheet: LSP 7.8 FO4.',
            ]);

        AnalysisPackage::query()
            ->whereIn('code', ['PKG-DW-PHYSICO', 'PKG-DW-BACT'])
            ->update(['is_active' => true]);

        AnalysisType::query()
            ->whereIn('code', ['MB-02A', 'MB-02B'])
            ->update(['default_price' => 375, 'show_on_kiosk' => false]);

        AnalysisType::query()
            ->where('code', 'MB-01')
            ->update(['show_on_kiosk' => true]);

        AnalysisType::query()
            ->whereIn('code', OfficialAnalysisCatalog::packageOnlyCodes())
            ->update(['show_on_kiosk' => false]);

        foreach (OfficialAnalysisCatalog::resultFormRegistry() as $meta) {
            $form = ControlledForm::query()
                ->where('form_code', $meta['form_code'])
                ->where('category', ControlledFormCategory::AnalysisResult)
                ->first();

            if (! $form) {
                continue;
            }

            if (! empty($meta['package_code'])) {
                $package = AnalysisPackage::query()
                    ->where('code', $meta['package_code'])
                    ->where('is_active', true)
                    ->first();

                if ($package) {
                    $forms->syncBindings($form, $package->orderedTypeIds(), $package->id);
                }

                continue;
            }

            $typeCodes = $meta['type_codes'] ?? [];
            if ($typeCodes === [] && ($meta['form_code'] ?? null) === 'LSP-7.8-FO3') {
                $typeCodes = OfficialAnalysisCatalog::drinkingWaterOldIssue11TypeCodes();
            }

            if ($typeCodes === []) {
                if ($form->analysis_package_id) {
                    $ordered = $form->orderedTypeIds();
                    if ($ordered === [] && $form->analysisPackage) {
                        $ordered = $form->analysisPackage->orderedTypeIds();
                    }
                    $forms->syncBindings($form, $ordered, null);
                }

                continue;
            }

            $byCode = AnalysisType::query()
                ->whereIn('code', $typeCodes)
                ->get()
                ->keyBy('code');

            $orderedIds = collect($typeCodes)
                ->map(fn (string $code) => $byCode->get($code)?->id)
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($orderedIds !== []) {
                $forms->syncBindings($form, $orderedIds, null);
            }
        }
    }

    public function down(): void
    {
        // Irreversible catalog heal — re-seed if needed.
    }
};
