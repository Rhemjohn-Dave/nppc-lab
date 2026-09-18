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
        $codes = OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes();
        $labels = OfficialAnalysisCatalog::wastewaterPhysicoIssue18Labels();

        foreach ($codes as $code) {
            $method = OfficialAnalysisCatalog::methodForCode($code);
            $updates = [];
            if ($method !== null && $method !== '') {
                $updates['method'] = $method;
            }
            if (isset($labels[$code])) {
                $updates['name'] = $labels[$code];
            }
            if ($updates === []) {
                continue;
            }

            DB::table('analysis_types')->where('code', $code)->update($updates);
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $meta = OfficialAnalysisCatalog::resultFormRegistry()['ww_physico'] ?? null;
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
                    'Official %s · %s · Eff. %s. Types-only individual pay sheet (Issue 18 wastewater physico).',
                    $meta['official'],
                    $meta['revision'],
                    $meta['effective'],
                ),
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::AnalysisResult,
                'analysis_package_id' => null,
                'analyst_signatory_slots' => 4,
                'analyst_require_prc' => true,
            ],
        );

        $form->fill([
            'name' => $meta['name'],
            'analysis_package_id' => null,
            'analyst_signatory_slots' => 4,
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
                'notes' => 'Issue 18 wastewater physico blank shell — types-only dynamic matrix.',
            ]);
        } elseif ($revision->status === ControlledFormRevisionStatus::Active && $revision->hasCanonicalPdf()) {
            $revision = $form->revisions()->create([
                'revision' => $nextRevision === '01' ? '02' : $nextRevision,
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Issue 18 wastewater physico blank shell — types-only dynamic matrix.',
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
