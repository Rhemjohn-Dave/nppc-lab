<?php

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Heal DW-06 label (catalog was Nitrite; Issue 7 sheet is Nitrate).
        DB::table('analysis_types')
            ->where('code', 'DW-06')
            ->where('name', 'Nitrite')
            ->update(['name' => 'Nitrate']);

        foreach (['DW-01', 'DW-02', 'DW-03', 'DW-04', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09'] as $code) {
            $method = OfficialAnalysisCatalog::methodForCode($code);
            $acceptable = OfficialAnalysisCatalog::acceptableValuesForCode($code);
            $updates = [];
            if ($method !== null) {
                $updates['method'] = $method;
            }
            if ($acceptable !== null) {
                $updates['acceptable_values'] = $acceptable;
            }
            if ($updates === []) {
                continue;
            }

            DB::table('analysis_types')->where('code', $code)->update($updates);
        }

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->first();
        if ($package) {
            $package->fill([
                'form_code' => 'LSP-7.8-FO37',
                'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
                'description' => 'Mandatory physico-chemical parameters (₱4,900). Result sheet: LSP 7.8 FO37 Issue 07 (dynamic matrix).',
            ])->save();

            $ordered = AnalysisType::query()
                ->whereIn('code', ['DW-01', 'DW-03', 'DW-04', 'DW-02', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09', 'DW-10'])
                ->get()
                ->keyBy('code');

            $typeIds = collect(['DW-01', 'DW-03', 'DW-04', 'DW-02', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09', 'DW-10'])
                ->map(fn (string $code) => $ordered->get($code)?->id)
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($typeIds !== []) {
                $package->syncTypes($typeIds);
            }
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        if (! $admin || ! $package) {
            return;
        }

        $meta = OfficialAnalysisCatalog::resultFormRegistry()['dw_physico'] ?? null;
        if (! is_array($meta)) {
            return;
        }

        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);

        $form = ControlledForm::query()->firstOrCreate(
            ['form_code' => $meta['form_code']],
            [
                'name' => $meta['name'],
                'description' => sprintf(
                    'Official %s · %s · Eff. %s. Dynamic matrix package result sheet.',
                    $meta['official'],
                    $meta['revision'],
                    $meta['effective'],
                ),
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::AnalysisResult,
                'analysis_package_id' => $package->id,
                'analyst_signatory_slots' => 2,
                'analyst_require_prc' => true,
            ],
        );

        $form->fill([
            'name' => $meta['name'],
            'analysis_package_id' => $package->id,
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => true,
        ])->save();

        $forms->syncBindings($form, $package->orderedTypeIds(), $package->id);

        $revision = $form->revisions()->firstOrCreate(
            ['revision' => '01'],
            [
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'DW physico Issue 7 — dynamic matrix blueprint.',
            ],
        );

        $sourcePdf = $meta['source_pdf'] ?? null;
        if (is_string($sourcePdf) && $sourcePdf !== '' && ! $revision->hasCanonicalPdf()) {
            $absolute = resource_path('forms/official/'.$sourcePdf);
            if (is_file($absolute)) {
                $upload = new UploadedFile($absolute, $sourcePdf, 'application/pdf', null, true);
                $forms->attachFile($form, $revision->fresh(), $upload);
                $revision = $revision->fresh();
            }
        }

        if (! $revision->fields()->exists() && $forms->hasBlueprint($form)) {
            $forms->importBlueprint($revision);
        } elseif ($forms->hasBlueprint($form)) {
            // Refresh matrix/config when healing an existing draft without wiping activated coords silently.
            if ($revision->status === ControlledFormRevisionStatus::Draft) {
                $forms->importBlueprint($revision);
            }
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
        // Non-destructive heal; leave form/package data in place.
    }
};
