<?php

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
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
        $codes = [
            'DW-12', 'DW-13', 'DW-14', 'DW-15', 'DW-16', 'DW-17', 'DW-18', 'DW-19', 'DW-20',
        ];

        foreach ($codes as $code) {
            $method = OfficialAnalysisCatalog::methodForCode($code);
            $acceptable = OfficialAnalysisCatalog::acceptableValuesForCode($code);
            $updates = array_filter([
                'method' => $method,
                'acceptable_values' => $acceptable,
            ], fn ($value) => $value !== null && $value !== '');

            if ($updates !== []) {
                DB::table('analysis_types')->where('code', $code)->update($updates);
            }
        }

        // Ensure new catalog rows exist (heal DBs that already ran seeders).
        $drinking = DB::table('analysis_categories')->where('slug', 'drinking_water')->first();
        if ($drinking) {
            $definitions = OfficialAnalysisCatalog::definitions()[\App\Enums\AnalysisCategory::DrinkingWater->value] ?? [];
            $sort = (int) DB::table('analysis_types')->max('sort_order');
            foreach ($definitions as $row) {
                [$code, $name, $price] = $row;
                if (! in_array($code, ['DW-13', 'DW-14', 'DW-15', 'DW-16', 'DW-17', 'DW-18', 'DW-19', 'DW-20'], true)) {
                    continue;
                }

                $exists = DB::table('analysis_types')->where('code', $code)->exists();
                if ($exists) {
                    DB::table('analysis_types')->where('code', $code)->update([
                        'name' => $name,
                        'default_price' => $price,
                        'method' => OfficialAnalysisCatalog::methodForCode($code),
                        'acceptable_values' => OfficialAnalysisCatalog::acceptableValuesForCode($code),
                        'is_active' => true,
                        'show_on_kiosk' => true,
                    ]);

                    continue;
                }

                DB::table('analysis_types')->insert([
                    'code' => $code,
                    'name' => $name,
                    'method' => OfficialAnalysisCatalog::methodForCode($code),
                    'acceptable_values' => OfficialAnalysisCatalog::acceptableValuesForCode($code),
                    'category_id' => $drinking->id,
                    'default_price' => $price,
                    'is_active' => true,
                    'show_on_kiosk' => true,
                    'catalog_scope' => 'non_aqua',
                    'sort_order' => ++$sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $meta = OfficialAnalysisCatalog::resultFormRegistry()['dw_physico_old'] ?? null;
        if (! $admin || ! is_array($meta)) {
            return;
        }

        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);

        $typeIds = AnalysisType::query()
            ->whereIn('code', OfficialAnalysisCatalog::drinkingWaterOldIssue11TypeCodes())
            ->get()
            ->keyBy('code');

        $orderedIds = collect(OfficialAnalysisCatalog::drinkingWaterOldIssue11TypeCodes())
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
                    'Official %s · %s · Eff. %s. Types-only individual pay sheet (Issue 11 OLD).',
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

        $forms->syncBindings($form, $orderedIds, null);

        $nextRevision = (string) str_pad(
            (string) max(1, ((int) $form->revisions()->max('revision')) + 1),
            2,
            '0',
            STR_PAD_LEFT,
        );

        // Prefer revision 01 if empty draft; otherwise create next.
        $revision = $form->revisions()->where('revision', '01')->first();
        if (! $revision) {
            $revision = $form->revisions()->create([
                'revision' => '01',
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Issue 11 OLD blank shell — types-only dynamic matrix.',
            ]);
        } elseif ($revision->status === ControlledFormRevisionStatus::Active && $revision->hasCanonicalPdf()) {
            $revision = $form->revisions()->create([
                'revision' => $nextRevision === '01' ? '02' : $nextRevision,
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Issue 11 OLD blank shell — types-only dynamic matrix.',
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
