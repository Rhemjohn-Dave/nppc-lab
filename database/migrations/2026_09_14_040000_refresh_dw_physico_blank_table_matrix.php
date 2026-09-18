<?php

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
        foreach (['DW-01', 'DW-02', 'DW-03', 'DW-04', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09'] as $code) {
            $definitionName = null;
            foreach (OfficialAnalysisCatalog::definitions()[\App\Enums\AnalysisCategory::DrinkingWater->value] ?? [] as $row) {
                if (($row[0] ?? null) === $code) {
                    $definitionName = (string) $row[1];
                    break;
                }
            }

            $updates = array_filter([
                'name' => $definitionName,
                'method' => OfficialAnalysisCatalog::methodForCode($code),
                'acceptable_values' => OfficialAnalysisCatalog::acceptableValuesForCode($code),
            ], fn ($value) => $value !== null && $value !== '');

            if ($updates !== []) {
                DB::table('analysis_types')->where('code', $code)->update($updates);
            }
        }

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->first();
        if ($package) {
            $ordered = AnalysisType::query()
                ->whereIn('code', ['DW-01', 'DW-03', 'DW-04', 'DW-02', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09'])
                ->get()
                ->keyBy('code');

            $typeIds = collect(['DW-01', 'DW-03', 'DW-04', 'DW-02', 'DW-05', 'DW-06', 'DW-07', 'DW-08', 'DW-09'])
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
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO37')->first();
        $source = resource_path('forms/official/PC Drinking Water Test Result Form Issue 7 09012026.pdf');

        if (! $admin || ! $form || ! is_file($source) || ! $package) {
            return;
        }

        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);

        $forms->syncBindings($form, $package->orderedTypeIds(), $package->id);

        $nextRevision = (string) str_pad(
            (string) (((int) $form->revisions()->max('revision')) + 1),
            2,
            '0',
            STR_PAD_LEFT,
        );

        $revision = $form->revisions()->create([
            'revision' => $nextRevision,
            'status' => ControlledFormRevisionStatus::Draft,
            'created_by' => $admin->id,
            'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
            'notes' => 'Blank-table Issue 7 shell — dynamic matrix draws the full table.',
        ]);

        $upload = new UploadedFile(
            $source,
            'PC Drinking Water Test Result Form Issue 7 09012026.pdf',
            'application/pdf',
            null,
            true,
        );
        $forms->attachFile($form, $revision, $upload);
        $forms->importBlueprint($revision->fresh());
        $workflow->activate($revision->fresh(), $admin);
    }

    public function down(): void
    {
        // Non-destructive content heal.
    }
};
