<?php

namespace App\Services;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Support\AnalysisResultReport;
use App\Support\DynamicTestMatrix;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class AnalysisResultReportResolver
{
    public function forAnalysis(JobOrderAnalysis $analysis, User $user, bool $withValues = true): AnalysisResultReport
    {
        $analysis->loadMissing(['jobOrder.samples', 'jobOrder.analyses.assignee', 'jobOrder.analyses.analysisType', 'analysisType', 'assignee']);

        $jobReport = $this->forJobOrder($analysis->jobOrder, $user, $withValues);

        if ($jobReport->controlledForm) {
            return $jobReport;
        }

        return $this->individual($analysis->jobOrder, $analysis);
    }

    /**
     * @param  bool  $withValues  When false, skip FieldValueResolver (list/summary/overlay manifest). PDF fill must pass true.
     */
    public function forJobOrder(JobOrder $jobOrder, User $user, bool $withValues = true): AnalysisResultReport
    {
        $jobOrder->loadMissing(['samples', 'analyses.assignee', 'analyses.analysisType', 'reviewer']);

        $controlled = $this->matchingControlledForm($jobOrder);
        if ($controlled) {
            return $this->fromControlledForm($jobOrder, $user, $controlled, $withValues);
        }

        return new AnalysisResultReport(
            kind: AnalysisResultReport::KIND_UNAVAILABLE,
            filename: "Result-{$jobOrder->reference_no}.pdf",
            title: 'Analysis result',
            message: 'No combined result form is configured for this exact set of tests. Create and activate a controlled form in the Form Designer to enable combined PDF generation.',
            jobOrder: $jobOrder,
            analyses: $jobOrder->analyses,
            values: [],
        );
    }

    /**
     * FO4 prints measured MPN only — Pass/Fail is not collected.
     * FO5 and other result sheets still require Passed/Failed on complete.
     */
    public function requiresPassFail(JobOrder $jobOrder): bool
    {
        $form = $this->matchingControlledForm($jobOrder);

        return $form?->form_code !== 'LSP-7.8-FO4';
    }

    public function matchingControlledForm(JobOrder $jobOrder): ?ControlledForm
    {
        $jobOrder->loadMissing(['analyses', 'packages']);

        $packageForm = $this->matchingPackageControlledForm($jobOrder);
        if ($packageForm) {
            return $packageForm;
        }

        $typeIds = $jobOrder->analyses->pluck('analysis_type_id')->all();
        if ($typeIds === [] || in_array(null, $typeIds, true)) {
            return null;
        }

        $ids = array_map('intval', $typeIds);
        $unique = array_values(array_unique($ids));
        if (count($unique) !== count($ids)) {
            return null;
        }

        $exact = ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->where('combination_key', ControlledFormService::combinationKey($unique))
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->first();

        if ($exact) {
            return $exact;
        }

        return $this->matchingTypesOnlyMatrixSubsetForm($unique);
    }

    /**
     * Types-only dynamic-matrix form whose bound types contain every job analysis type.
     *
     * @param  list<int>  $jobTypeIds
     */
    private function matchingTypesOnlyMatrixSubsetForm(array $jobTypeIds): ?ControlledForm
    {
        if ($jobTypeIds === []) {
            return null;
        }

        $candidates = ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->whereHas('analysisTypes')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $form) {
            $revision = $form->activeRevision() ?? $form->currentRevision;
            if (! DynamicTestMatrix::revisionUsesMatrix($revision)) {
                continue;
            }

            $formTypeIds = $form->orderedTypeIds();
            if ($formTypeIds === []) {
                continue;
            }

            $allowed = array_fill_keys($formTypeIds, true);
            $allContained = true;
            foreach ($jobTypeIds as $typeId) {
                if (! isset($allowed[$typeId])) {
                    $allContained = false;
                    break;
                }
            }

            if ($allContained) {
                return $form;
            }
        }

        return null;
    }

    private function matchingPackageControlledForm(JobOrder $jobOrder): ?ControlledForm
    {
        $packageIds = $jobOrder->packages->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($packageIds === []) {
            return null;
        }

        return ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields', 'analysisPackage'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereIn('analysis_package_id', $packageIds)
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>  $analyses
     */
    public function userCanAccessCombined(Collection $analyses, User $user, ?JobOrder $jobOrder = null): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('head_analysis')) {
            return true;
        }

        if ($analyses->every(
            fn (JobOrderAnalysis $line) => (int) $line->assigned_to === (int) $user->id,
        )) {
            return true;
        }

        $job = $jobOrder ?? $analyses->first()?->jobOrder;

        return $job instanceof JobOrder && $this->userIsPackageSignatory($job, $user);
    }

    public function userIsPackageSignatory(JobOrder $jobOrder, User $user): bool
    {
        $jobOrder->loadMissing('packages');

        if ($jobOrder->packages->isEmpty()) {
            return false;
        }

        $signatories = $jobOrder->packages
            ->pluck('signatory_user_id')
            ->filter()
            ->unique()
            ->values();

        return $signatories->count() === 1
            && (int) $signatories->first() === (int) $user->id;
    }

    /**
     * Fill the overlay PDF once and cache briefly so reopen/preview feels snappy.
     */
    public function renderOverlayPdf(AnalysisResultReport $resolved): string
    {
        if (
            $resolved->kind !== AnalysisResultReport::KIND_COMBINED
            || ! $resolved->isOverlay()
            || $resolved->controlledRevision === null
        ) {
            throw new RuntimeException('Overlay PDF render requires a combined overlay report.');
        }

        $jobOrder = $resolved->jobOrder;
        $revision = $resolved->controlledRevision;
        $values = $resolved->values;

        if ($values === []) {
            $values = app(FieldValueResolver::class)->forResult(
                $revision->load('fields'),
                $jobOrder,
                $resolved->analyses,
                $resolved->controlledForm?->load('analysisPackage'),
            );
        }

        $revision->loadMissing('fields');

        $fingerprint = hash('xxh128', json_encode([
            $revision->id,
            $revision->updated_at?->getTimestamp(),
            $this->overlayLayoutFingerprint($revision),
            $jobOrder->updated_at?->getTimestamp(),
            $jobOrder->result_signatories,
            $jobOrder->reviewed_at?->getTimestamp(),
            $jobOrder->reviewed_by,
            $resolved->analyses->map(fn (JobOrderAnalysis $line) => [
                $line->id,
                $line->result_value,
                $line->result_pass_fail,
                $line->result_method,
                $line->result_measurement,
                $line->result_unit,
                $line->result_remarks,
                $line->completed_at?->getTimestamp(),
                $line->assigned_to,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));

        $cacheKey = "result-overlay-pdf:{$jobOrder->id}:{$fingerprint}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($revision, $values) {
            return app(ControlledPdfFiller::class)->fill(
                $revision->load('fields'),
                $values,
            );
        });
    }

    /**
     * Include field geometry so Form Designer saves bust overlay cache even if
     * revision.updated_at was not bumped (e.g. older import paths).
     */
    private function overlayLayoutFingerprint(ControlledFormRevision $revision): string
    {
        $layout = $revision->fields
            ->sortBy(fn ($field) => sprintf('%04d-%s', (int) $field->z_order, $field->name))
            ->values()
            ->map(fn ($field) => $field->toOverlayArray())
            ->all();

        return hash('xxh128', json_encode($layout, JSON_THROW_ON_ERROR));
    }

    /**
     * @return Collection<int, JobOrderAnalysis>
     */
    public function orderedAnalysesForIds(JobOrder $jobOrder, array $typeIds): Collection
    {
        $byType = $jobOrder->analyses->keyBy('analysis_type_id');

        return collect($typeIds)
            ->map(fn (int $typeId) => $byType->get($typeId))
            ->filter(fn ($line): bool => $line instanceof JobOrderAnalysis)
            ->values();
    }

    private function individual(JobOrder $jobOrder, JobOrderAnalysis $analysis): AnalysisResultReport
    {
        $slug = AnalysisResultReport::slugFor($jobOrder, null, $analysis);

        return new AnalysisResultReport(
            kind: AnalysisResultReport::KIND_INDIVIDUAL,
            filename: "Result-{$jobOrder->reference_no}-{$slug}.pdf",
            title: $analysis->name,
            message: null,
            jobOrder: $jobOrder,
            analyses: collect([$analysis]),
            values: [],
            analysis: $analysis,
        );
    }

    private function combinedFilenameFor(JobOrder $jobOrder, string $name): string
    {
        $slug = Str::slug($name) ?: 'combined';

        return "Result-{$jobOrder->reference_no}-{$slug}.pdf";
    }

    private function fromControlledForm(
        JobOrder $jobOrder,
        User $user,
        ControlledForm $form,
        bool $withValues = true,
    ): AnalysisResultReport {
        $form->loadMissing('analysisPackage');
        $revision = $form->activeRevision();
        $revision?->loadMissing('fields');
        $package = $form->analysisPackage;

        if ($package && DynamicTestMatrix::packageUsesMatrix($package)) {
            $ordered = DynamicTestMatrix::orderedSelectedAnalyses($jobOrder, $package);
        } elseif (DynamicTestMatrix::formUsesMatrix($form, $revision)) {
            $ordered = DynamicTestMatrix::orderedSelectedAnalysesForForm($jobOrder, $form);
        } else {
            $typeIds = $form->orderedTypeIds();
            if ($typeIds === [] && $package) {
                $typeIds = $package->orderedTypeIds();
            }
            $ordered = $this->orderedAnalysesForIds($jobOrder, $typeIds);
        }

        $filename = $this->combinedFilenameFor($jobOrder, $form->name);

        if (! $revision?->hasCanonicalPdf()) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: $filename,
                title: $form->name,
                message: 'The active controlled form is missing its canonical PDF.',
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
            );
        }

        if ($package && DynamicTestMatrix::packageUsesMatrix($package) && ! DynamicTestMatrix::revisionUsesMatrix($revision)) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: $filename,
                title: $form->name,
                message: 'This package uses a dynamic test matrix report. Add a Dynamic test matrix region on the bound controlled form in Form Designer.',
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
            );
        }

        $incomplete = $ordered->first(
            fn (JobOrderAnalysis $line) => $line->status !== JobOrderAnalysisStatus::Completed,
        );

        if ($incomplete) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_WAITING,
                filename: $filename,
                title: $form->name,
                message: "This job uses the \"{$form->name}\" form. Preview is available after every matched result is complete.",
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
            );
        }

        if (! $this->userCanAccessCombined($ordered, $user, $jobOrder)) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: $filename,
                title: $form->name,
                message: 'This combined form includes tests assigned to other analysts. The designated package analyst, an admin, or Head of Analysis can preview it after all results are complete.',
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
            );
        }

        // Overlay manifests use pdf_url (server fill). Callers that only need gates/summary
        // pass withValues: false to skip FieldValueResolver. PDF fill must pass true.
        $values = $withValues
            ? app(FieldValueResolver::class)->forResult(
                $revision->load('fields'),
                $jobOrder,
                $ordered,
                $form->load('analysisPackage'),
            )
            : [];

        return new AnalysisResultReport(
            kind: AnalysisResultReport::KIND_COMBINED,
            filename: $filename,
            title: $form->name,
            message: null,
            jobOrder: $jobOrder,
            analyses: $ordered,
            values: $values,
            controlledForm: $form,
            controlledRevision: $revision,
        );
    }
}
