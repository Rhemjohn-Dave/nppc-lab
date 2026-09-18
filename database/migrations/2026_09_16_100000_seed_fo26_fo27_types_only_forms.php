<?php

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use App\Enums\CatalogScope;
use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * Sheet-align FO26 food micro types, add FO27 SM-* sugar types,
 * and seed types-only dynamic-matrix forms LSP-7.8-FO26 / LSP-7.8-FO27.
 */
return new class extends Migration
{
    public function up(): void
    {
        $foodMicro = AnalysisCategory::query()
            ->where('slug', AnalysisCategoryEnum::FoodProductsMicrobiological->value)
            ->first();

        if ($foodMicro) {
            $sort = (int) AnalysisType::query()->where('category_id', $foodMicro->id)->max('sort_order');

            foreach (OfficialAnalysisCatalog::definitions()[AnalysisCategoryEnum::FoodProductsMicrobiological->value] ?? [] as $row) {
                [$code, $name, $price] = $row;
                $method = OfficialAnalysisCatalog::methodForCode($code);
                $type = AnalysisType::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'method' => $method,
                        'category_id' => $foodMicro->id,
                        'default_price' => $price,
                        'is_active' => true,
                        'show_on_kiosk' => true,
                        'catalog_scope' => CatalogScope::NonAqua->value,
                        'sort_order' => ++$sort,
                    ],
                );

                $type->fill([
                    'name' => $name,
                    'method' => $method,
                    'category_id' => $foodMicro->id,
                    'default_price' => $price,
                    'is_active' => true,
                    'show_on_kiosk' => true,
                    'catalog_scope' => CatalogScope::NonAqua->value,
                ])->save();
            }
        }

        $this->syncInactivePackageMembers(
            'PKG-MIC-FOOD',
            OfficialAnalysisCatalog::foodMicroFo26TypeCodes(),
        );
        $this->syncInactivePackageMembers(
            'PKG-MIC-SUGAR',
            OfficialAnalysisCatalog::foodMicroSugarTypeCodes(),
        );

        $this->seedTypesOnlyForm(
            'food_micro',
            OfficialAnalysisCatalog::foodMicroFo26TypeCodes(),
            2,
        );
        $this->seedTypesOnlyForm(
            'food_micro_sugar',
            OfficialAnalysisCatalog::foodMicroSugarTypeCodes(),
            2,
        );
    }

    /**
     * @param  list<string>  $codes
     */
    private function syncInactivePackageMembers(string $packageCode, array $codes): void
    {
        $package = AnalysisPackage::query()->where('code', $packageCode)->first();
        if (! $package) {
            return;
        }

        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $orderedIds = collect($codes)
            ->map(fn (string $code) => $typeIds->get($code)?->id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($orderedIds !== []) {
            $package->syncTypes($orderedIds);
            $package->update(['form_code' => null, 'is_active' => false]);
        }
    }

    /**
     * @param  list<string>  $codes
     */
    private function seedTypesOnlyForm(string $registryKey, array $codes, int $signatorySlots): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $meta = OfficialAnalysisCatalog::resultFormRegistry()[$registryKey] ?? null;
        if (! $admin || ! is_array($meta)) {
            return;
        }

        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);

        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $orderedIds = collect($codes)
            ->map(fn (string $code) => $typeIds->get($code)?->id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($orderedIds === []) {
            return;
        }

        $form = ControlledForm::query()->firstOrCreate(
            ['form_code' => $meta['form_code']],
            [
                'name' => $meta['name'],
                'description' => sprintf(
                    'Official %s · %s · Eff. %s. Types-only individual pay sheet.',
                    $meta['official'],
                    $meta['revision'],
                    $meta['effective'],
                ),
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::AnalysisResult,
                'analysis_package_id' => null,
                'analyst_signatory_slots' => $signatorySlots,
                'analyst_require_prc' => true,
            ],
        );

        $form->fill([
            'name' => $meta['name'],
            'analysis_package_id' => null,
            'analyst_signatory_slots' => $signatorySlots,
            'analyst_require_prc' => true,
        ])->save();

        $forms->syncBindings($form, $orderedIds, null);

        $nextRevision = (string) str_pad(
            (string) max(1, ((int) $form->revisions()->max('revision')) + 1),
            2,
            '0',
            STR_PAD_LEFT,
        );

        $revision = $form->revisions()->where('revision', '01')->first();
        if (! $revision) {
            $revision = $form->revisions()->create([
                'revision' => '01',
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Issue 4 food micro blank shell — types-only dynamic matrix.',
            ]);
        } elseif ($revision->status === ControlledFormRevisionStatus::Active && $revision->hasCanonicalPdf()) {
            $revision = $form->revisions()->create([
                'revision' => $nextRevision === '01' ? '02' : $nextRevision,
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Issue 4 food micro blank shell — types-only dynamic matrix.',
            ]);
        }

        $sourcePdf = $meta['source_pdf'] ?? null;
        if (is_string($sourcePdf) && $sourcePdf !== '' && ! $revision->hasCanonicalPdf()) {
            $absolute = resource_path('forms/official/'.$sourcePdf);
            if (is_file($absolute)) {
                $upload = new UploadedFile($absolute, $sourcePdf, 'application/pdf', null, true);
                $forms->attachFile($form, $revision->fresh(), $upload);
                $revision = $revision->fresh();
            }
        }

        if ($forms->hasBlueprint($form) && (
            ! $revision->fields()->exists()
            || $revision->status === ControlledFormRevisionStatus::Draft
        )) {
            $forms->importBlueprint($revision->fresh());
        }

        if (
            $revision->fresh()?->hasCanonicalPdf()
            && $revision->fresh()?->fields()->exists()
            && $revision->status !== ControlledFormRevisionStatus::Active
        ) {
            $workflow->activate($revision->fresh(), $admin);
        }
    }

    public function down(): void
    {
        // Non-destructive heal.
    }
};
