<?php

namespace App\Services;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\Sample;
use App\Models\User;
use App\Support\AnalysisResultReport;
use App\Support\DynamicTestMatrix;
use App\Support\SampleControlNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class AnalysisResultReportResolver
{
    public function forAnalysis(JobOrderAnalysis $analysis, User $user, bool $withValues = true): AnalysisResultReport
    {
        $analysis->loadMissing(['jobOrder.samples', 'jobOrder.analyses.assignee', 'jobOrder.analyses.analysisType', 'analysisType', 'assignee']);

        $reports = $this->reportsForJobOrder($analysis->jobOrder, $user, $withValues);

        $match = $reports->first(
            fn (AnalysisResultReport $report) => $report->analyses->contains(
                fn (JobOrderAnalysis $line) => (int) $line->id === (int) $analysis->id,
            ),
        );

        if ($match instanceof AnalysisResultReport && $match->controlledForm) {
            return $match;
        }

        $sampleMatch = $reports->first(
            fn (AnalysisResultReport $report) => $report->sampleId !== null
                && (int) $report->sampleId === (int) $analysis->sample_id
                && $report->controlledForm,
        );

        if ($sampleMatch instanceof AnalysisResultReport) {
            return $sampleMatch;
        }

        return $this->individual($analysis->jobOrder, $analysis);
    }

    /**
     * @return Collection<int, AnalysisResultReport>
     */
    public function reportsForJobOrder(JobOrder $jobOrder, User $user, bool $withValues = true): Collection
    {
        $jobOrder->loadMissing(['samples', 'analyses.assignee', 'analyses.analysisType', 'packageLinks.analysisPackage', 'packages']);

        // Snapshot full analyses — fromControlledForm temporarily scopes the relation per sample.
        $allAnalyses = $jobOrder->analyses;
        $allPackageLinks = $jobOrder->packageLinks;

        $reports = collect();

        foreach ($jobOrder->samples as $index => $sample) {
            $jobOrder->setRelation('analyses', $allAnalyses);
            $jobOrder->setRelation('packageLinks', $allPackageLinks);

            $sampleReports = $this->reportsForSample($jobOrder, $sample, (int) $index, $user, $withValues);
            foreach ($sampleReports as $report) {
                $reports->push($report);
            }
        }

        $jobOrder->setRelation('analyses', $allAnalyses);
        $jobOrder->setRelation('packageLinks', $allPackageLinks);

        // Legacy jobs with analyses but no sample_id (should be rare after backfill).
        $orphanAnalyses = $allAnalyses->whereNull('sample_id');
        if ($orphanAnalyses->isNotEmpty() && $reports->isEmpty()) {
            $controlled = $this->matchingControlledForm($jobOrder);
            if ($controlled) {
                $reports->push($this->fromControlledForm($jobOrder, $user, $controlled, $withValues));
            }
        }

        if ($reports->isEmpty()) {
            $reports->push(new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: "Result-{$jobOrder->reference_no}.pdf",
                title: 'Analysis result',
                message: 'No combined result form is configured for this exact set of tests. Create and activate a controlled form in the Form Designer to enable combined PDF generation.',
                jobOrder: $jobOrder,
                analyses: $allAnalyses,
                values: [],
            ));
        }

        return $reports->values();
    }

    /**
     * Pick a sample/form report from a job-order report list (defaults to first).
     *
     * @param  Collection<int, AnalysisResultReport>  $reports
     */
    public function pickReport(Collection $reports, ?int $sampleId = null, ?int $formId = null): AnalysisResultReport
    {
        if ($sampleId || $formId) {
            $matched = $reports->first(
                function (AnalysisResultReport $report) use ($sampleId, $formId) {
                    if ($sampleId && (int) $report->sampleId !== $sampleId) {
                        return false;
                    }
                    if ($formId && (int) ($report->controlledForm?->id ?? 0) !== $formId) {
                        return false;
                    }

                    return true;
                },
            );

            if ($matched instanceof AnalysisResultReport) {
                return $matched;
            }
        }

        $first = $reports->first();

        return $first instanceof AnalysisResultReport
            ? $first
            : new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: 'result.pdf',
                title: 'Analysis result',
                message: 'No result report is available.',
                jobOrder: new JobOrder,
                analyses: collect(),
                values: [],
            );
    }

    /**
     * @return list<AnalysisResultReport>
     */
    private function reportsForSample(
        JobOrder $jobOrder,
        Sample $sample,
        int $sampleIndex,
        User $user,
        bool $withValues,
    ): array {
        $controlNumber = SampleControlNumber::forIndex(
            (string) $jobOrder->reference_no,
            $sampleIndex,
            $jobOrder->samples->count(),
        ) ?? (string) $jobOrder->reference_no;

        $sampleAnalyses = $jobOrder->analyses
            ->where('sample_id', $sample->id)
            ->values();

        if ($sampleAnalyses->isEmpty()) {
            return [];
        }

        $packageLinks = $jobOrder->packageLinks
            ->where('sample_id', $sample->id)
            ->values();

        $reports = [];
        $coveredTypeIds = [];

        foreach ($packageLinks as $link) {
            $packageId = (int) $link->analysis_package_id;
            $form = ControlledForm::query()
                ->with(['analysisTypes', 'currentRevision.fields', 'analysisPackage'])
                ->where('category', ControlledFormCategory::AnalysisResult)
                ->where('analysis_package_id', $packageId)
                ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
                ->orderBy('id')
                ->first();

            if (! $form) {
                continue;
            }

            $reports[] = $this->fromControlledForm(
                $jobOrder,
                $user,
                $form,
                $withValues,
                $sample,
                $controlNumber,
                $sampleAnalyses,
            );

            foreach (($link->selected_type_ids ?? []) as $typeId) {
                $coveredTypeIds[] = (int) $typeId;
            }
        }

        $standaloneTypeIds = $sampleAnalyses
            ->pluck('analysis_type_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => in_array($id, $coveredTypeIds, true))
            ->values()
            ->all();

        if ($standaloneTypeIds !== [] && ! in_array(null, $sampleAnalyses->pluck('analysis_type_id')->all(), true)) {
            $unique = array_values(array_unique($standaloneTypeIds));
            if (count($unique) === count($standaloneTypeIds)) {
                $exact = ControlledForm::query()
                    ->with(['analysisTypes', 'currentRevision.fields'])
                    ->where('category', ControlledFormCategory::AnalysisResult)
                    ->whereNull('analysis_package_id')
                    ->where('combination_key', ControlledFormService::combinationKey($unique))
                    ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
                    ->first();

                $form = $exact ?? $this->matchingTypesOnlyMatrixSubsetForm($unique);
                if ($form) {
                    $reports[] = $this->fromControlledForm(
                        $jobOrder,
                        $user,
                        $form,
                        $withValues,
                        $sample,
                        $controlNumber,
                        $sampleAnalyses->filter(
                            fn (JobOrderAnalysis $line) => in_array((int) $line->analysis_type_id, $unique, true),
                        )->values(),
                    );
                }
            }
        }

        if ($reports === [] && $packageLinks->isEmpty()) {
            $typeIds = $sampleAnalyses->pluck('analysis_type_id')->map(fn ($id) => $id === null ? null : (int) $id)->all();
            if ($typeIds !== [] && ! in_array(null, $typeIds, true)) {
                $unique = array_values(array_unique(array_map('intval', $typeIds)));
                if (count($unique) === count($typeIds)) {
                    $exact = ControlledForm::query()
                        ->with(['analysisTypes', 'currentRevision.fields'])
                        ->where('category', ControlledFormCategory::AnalysisResult)
                        ->whereNull('analysis_package_id')
                        ->where('combination_key', ControlledFormService::combinationKey($unique))
                        ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
                        ->first();

                    $form = $exact ?? $this->matchingTypesOnlyMatrixSubsetForm($unique);
                    if ($form) {
                        $reports[] = $this->fromControlledForm(
                            $jobOrder,
                            $user,
                            $form,
                            $withValues,
                            $sample,
                            $controlNumber,
                            $sampleAnalyses,
                        );
                    }
                }
            }
        }

        return $reports;
    }

    /**
     * Primary report for the job (first sample form). Prefer waiting if any report is waiting.
     *
     * @param  bool  $withValues  When false, skip FieldValueResolver (list/summary/overlay manifest). PDF fill must pass true.
     */
    public function forJobOrder(JobOrder $jobOrder, User $user, bool $withValues = true): AnalysisResultReport
    {
        $reports = $this->reportsForJobOrder($jobOrder, $user, $withValues);

        $waiting = $reports->first(
            fn (AnalysisResultReport $report) => $report->kind === AnalysisResultReport::KIND_WAITING,
        );
        if ($waiting instanceof AnalysisResultReport) {
            return $waiting;
        }

        return $reports->first() ?? new AnalysisResultReport(
            kind: AnalysisResultReport::KIND_UNAVAILABLE,
            filename: "Result-{$jobOrder->reference_no}.pdf",
            title: 'Analysis result',
            message: 'No combined result form is configured for this exact set of tests.',
            jobOrder: $jobOrder,
            analyses: $jobOrder->analyses,
            values: [],
        );
    }

    /**
     * Legacy form-level helper used by older package tests.
     * FO4 sheets historically skip Pass/Fail at the form level; encode/print
     * gating uses analysis_types.requires_pass_fail instead.
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
    public function orderedAnalysesForIds(JobOrder $jobOrder, array $typeIds, ?int $sampleId = null): Collection
    {
        $analyses = $sampleId !== null
            ? $jobOrder->analyses->where('sample_id', $sampleId)
            : $jobOrder->analyses;

        $byType = $analyses->keyBy('analysis_type_id');

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
            sampleId: $analysis->sample_id,
        );
    }

    private function combinedFilenameFor(JobOrder $jobOrder, string $name, ?string $controlNumber = null): string
    {
        $slug = AnalysisResultReport::slugFor($jobOrder, $name, null, $controlNumber);

        return "Result-{$jobOrder->reference_no}-{$slug}.pdf";
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>|null  $sampleAnalyses
     */
    private function fromControlledForm(
        JobOrder $jobOrder,
        User $user,
        ControlledForm $form,
        bool $withValues = true,
        ?Sample $sample = null,
        ?string $controlNumber = null,
        ?Collection $sampleAnalyses = null,
    ): AnalysisResultReport {
        $form->loadMissing('analysisPackage');
        $revision = $form->activeRevision();
        $revision?->loadMissing('fields');
        $package = $form->analysisPackage;
        $sampleId = $sample?->id;

        if ($sampleAnalyses !== null) {
            $scopedJobAnalyses = $sampleAnalyses;
        } elseif ($sampleId !== null) {
            $scopedJobAnalyses = $jobOrder->analyses->where('sample_id', $sampleId)->values();
        } else {
            $scopedJobAnalyses = $jobOrder->analyses;
        }

        // Temporarily scope analyses for matrix helpers that read $jobOrder->analyses.
        $jobOrder->setRelation('analyses', $scopedJobAnalyses);

        if ($package && DynamicTestMatrix::packageUsesMatrix($package)) {
            $ordered = DynamicTestMatrix::orderedSelectedAnalyses($jobOrder, $package);
        } elseif (DynamicTestMatrix::formUsesMatrix($form, $revision)) {
            $ordered = DynamicTestMatrix::orderedSelectedAnalysesForForm($jobOrder, $form);
        } else {
            $typeIds = $form->orderedTypeIds();
            if ($typeIds === [] && $package) {
                $typeIds = $package->orderedTypeIds();
            }
            $ordered = $this->orderedAnalysesForIds($jobOrder, $typeIds, $sampleId);
        }

        $filename = $this->combinedFilenameFor($jobOrder, $form->name, $controlNumber);
        $title = $controlNumber
            ? "{$form->name} ({$controlNumber})"
            : $form->name;

        if (! $revision?->hasCanonicalPdf()) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: $filename,
                title: $title,
                message: 'The active controlled form is missing its canonical PDF.',
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
                sampleId: $sampleId,
                controlNumber: $controlNumber,
            );
        }

        if ($package && DynamicTestMatrix::packageUsesMatrix($package) && ! DynamicTestMatrix::revisionUsesMatrix($revision)) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: $filename,
                title: $title,
                message: 'This package uses a dynamic test matrix report. Add a Dynamic test matrix region on the bound controlled form in Form Designer.',
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
                sampleId: $sampleId,
                controlNumber: $controlNumber,
            );
        }

        $incomplete = $ordered->first(
            fn (JobOrderAnalysis $line) => $line->status !== JobOrderAnalysisStatus::Completed,
        );

        if ($incomplete) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_WAITING,
                filename: $filename,
                title: $title,
                message: "This job uses the \"{$form->name}\" form. Preview is available after every matched result is complete.",
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
                sampleId: $sampleId,
                controlNumber: $controlNumber,
            );
        }

        if (! $this->userCanAccessCombined($ordered, $user, $jobOrder)) {
            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_UNAVAILABLE,
                filename: $filename,
                title: $title,
                message: 'This combined form includes tests assigned to other analysts. The designated package analyst, an admin, or Head of Analysis can preview it after all results are complete.',
                jobOrder: $jobOrder,
                analyses: $ordered,
                values: [],
                controlledForm: $form,
                controlledRevision: $revision,
                sampleId: $sampleId,
                controlNumber: $controlNumber,
            );
        }

        $values = $withValues
            ? app(FieldValueResolver::class)->forResult(
                $revision->load('fields'),
                $jobOrder,
                $ordered,
                $form->load('analysisPackage'),
                $sample,
                $controlNumber,
            )
            : [];

        return new AnalysisResultReport(
            kind: AnalysisResultReport::KIND_COMBINED,
            filename: $filename,
            title: $title,
            message: null,
            jobOrder: $jobOrder,
            analyses: $ordered,
            values: $values,
            controlledForm: $form,
            controlledRevision: $revision,
            sampleId: $sampleId,
            controlNumber: $controlNumber,
        );
    }
}
