<?php

namespace App\Services;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\Sample;
use App\Models\User;
use App\Support\AnalysisResultReport;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use App\Support\ResultPdfFilename;
use App\Support\SampleControlNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class AnalysisResultReportResolver
{
    /** @var array<int, true>|null */
    private ?array $samplePreparationTypeIds = null;

    /**
     * Request-local memo of reportsForJobOrder results.
     *
     * @var array<string, Collection<int, AnalysisResultReport>>
     */
    private array $reportsByJobCache = [];

    public function forAnalysis(JobOrderAnalysis $analysis, User $user, bool $withValues = true): AnalysisResultReport
    {
        $analysis->loadMissing(['jobOrder.samples', 'jobOrder.analyses.assignee', 'jobOrder.analyses.analysisType', 'analysisType', 'assignee']);

        $reports = $this->reportsForJobOrder($analysis->jobOrder, $user, $withValues);

        return $this->matchAnalysisInReports($reports, $analysis);
    }

    /**
     * Pick the report for one assay from an already-resolved JO report list.
     *
     * @param  Collection<int, AnalysisResultReport>  $reports
     */
    public function matchAnalysisInReports(Collection $reports, JobOrderAnalysis $analysis): AnalysisResultReport
    {
        $analysis->loadMissing(['jobOrder', 'analysisType', 'assignee']);

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

        // Fingerprint analysis rows so a request-scoped memo cannot return stale
        // reports after drafts/completes (and so PHPUnit HTTP calls stay correct).
        $analysisFingerprint = $jobOrder->analyses
            ->map(function (JobOrderAnalysis $line): string {
                $status = $line->status instanceof JobOrderAnalysisStatus
                    ? $line->status->value
                    : (string) $line->status;

                return implode(':', [
                    (int) $line->id,
                    $status,
                    (string) ($line->updated_at?->getTimestamp() ?? 0),
                    (string) ($line->result_value ?? ''),
                ]);
            })
            ->implode('|');

        $cacheKey = implode('|', [
            (int) $jobOrder->id,
            (int) $user->id,
            $withValues ? '1' : '0',
            hash('xxh3', $analysisFingerprint),
        ]);
        if (isset($this->reportsByJobCache[$cacheKey])) {
            return $this->reportsByJobCache[$cacheKey];
        }

        if ($jobOrder->is_laboratory_services) {
            return $this->reportsByJobCache[$cacheKey] = collect();
        }

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
                filename: ResultPdfFilename::for($jobOrder, $allAnalyses, null),
                title: 'Analysis result',
                message: 'No combined result form is configured for this exact set of tests. Create and activate a controlled form in the Form Designer to enable combined PDF generation.',
                jobOrder: $jobOrder,
                analyses: $allAnalyses,
                values: [],
            ));
        }

        return $this->reportsByJobCache[$cacheKey] = $reports->values();
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
                $form = $this->resolveTypesOnlyResultForm($jobOrder, $unique, $sample);
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
                    $form = $this->resolveTypesOnlyResultForm($jobOrder, $unique, $sample);
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

        return $this->disambiguateFilenames($reports);
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
            filename: ResultPdfFilename::for($jobOrder, $jobOrder->analyses, null),
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
        $jobOrder->loadMissing(['analyses', 'packages', 'samples']);

        $packageForm = $this->matchingPackageControlledForm($jobOrder);
        if ($packageForm) {
            return $packageForm;
        }

        // Multi-sample JOs repeat the same type IDs once per sample. Job-wide
        // duplicate checks would reject those and hide Signatories / Send-to-Head
        // gates even though per-sample preview already resolves a form. Prefer the
        // first sample's type set (same primary report as forJobOrder).
        $sample = $jobOrder->samples->first();
        if ($sample !== null) {
            $sampleTypeIds = $jobOrder->analyses
                ->where('sample_id', $sample->id)
                ->pluck('analysis_type_id')
                ->all();

            if ($sampleTypeIds !== [] && ! in_array(null, $sampleTypeIds, true)) {
                $sampleIds = array_map('intval', $sampleTypeIds);
                $sampleUnique = array_values(array_unique($sampleIds));
                if (count($sampleUnique) === count($sampleIds)) {
                    return $this->resolveTypesOnlyResultForm($jobOrder, $sampleUnique, $sample);
                }
            }
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

        return $this->resolveTypesOnlyResultForm($jobOrder, $unique);
    }

    /**
     * Types-only result form for a sample (or whole JO): FO2/FO2A by kind, else exact key, else subset.
     *
     * @param  list<int>  $typeIds
     */
    private function resolveTypesOnlyResultForm(JobOrder $jobOrder, array $typeIds, ?Sample $sample = null): ?ControlledForm
    {
        $wastewaterPhysico = $this->matchingWastewaterPhysicoForm($jobOrder, $typeIds, $sample);
        if ($wastewaterPhysico !== null) {
            return $wastewaterPhysico;
        }

        $exact = ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->whereNotIn('form_code', ['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'])
            ->where('combination_key', ControlledFormService::combinationKey($typeIds))
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->first();

        if ($exact) {
            return $exact;
        }

        // Individual intake of package member tests (no package on the JO) still
        // uses the package-bound result form (e.g. FO5 for HPC+TC+FC).
        $packageForm = $this->matchingPackageFormByMemberTypes($typeIds);
        if ($packageForm !== null) {
            return $packageForm;
        }

        return $this->matchingTypesOnlyMatrixSubsetForm($typeIds, $jobOrder);
    }

    /**
     * When the job has no package link but selected types are members of a
     * package that has an active Analysis Result form, use that form.
     *
     * Prefers an exact member-set match, otherwise the smallest package that
     * contains every selected type (waived slots print as "-" as usual).
     *
     * @param  list<int>  $typeIds
     */
    private function matchingPackageFormByMemberTypes(array $typeIds): ?ControlledForm
    {
        $wanted = array_values(array_unique(array_map('intval', $typeIds)));
        if ($wanted === []) {
            return null;
        }
        sort($wanted);

        $forms = ControlledForm::query()
            ->with([
                'analysisTypes',
                'currentRevision.fields',
                'analysisPackage.analysisTypes',
            ])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNotNull('analysis_package_id')
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->orderBy('id')
            ->get();

        $exact = null;
        /** @var list<array{form: ControlledForm, size: int}> $subsets */
        $subsets = [];

        foreach ($forms as $form) {
            $package = $form->analysisPackage;
            if ($package === null) {
                continue;
            }

            $memberIds = $package->analysisTypes
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            if ($memberIds === []) {
                continue;
            }

            $allowed = array_fill_keys($memberIds, true);
            foreach ($wanted as $typeId) {
                if (! isset($allowed[$typeId])) {
                    continue 2;
                }
            }

            if ($memberIds === $wanted) {
                $exact = $form;
                break;
            }

            $subsets[] = [
                'form' => $form,
                'size' => count($memberIds),
            ];
        }

        if ($exact instanceof ControlledForm) {
            return $exact;
        }

        if ($subsets === []) {
            return null;
        }

        usort(
            $subsets,
            function (array $a, array $b): int {
                if ($a['size'] !== $b['size']) {
                    return $a['size'] <=> $b['size'];
                }

                return $a['form']->id <=> $b['form']->id;
            },
        );

        return $subsets[0]['form'];
    }

    /**
     * FO2 / FO2A / FO2B share the WW physico type panel; pick by sample description.
     *
     * @param  list<int>  $jobTypeIds
     */
    private function matchingWastewaterPhysicoForm(JobOrder $jobOrder, array $jobTypeIds, ?Sample $sample = null): ?ControlledForm
    {
        $jobOrder->loadMissing('samples');

        $candidates = ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->whereIn('form_code', ['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'])
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->get()
            ->keyBy('form_code');

        if ($candidates->isEmpty()) {
            return null;
        }

        $contained = function (ControlledForm $form) use ($jobTypeIds): bool {
            $revision = $form->activeRevision() ?? $form->currentRevision;
            if (! DynamicTestMatrix::revisionUsesMatrix($revision)) {
                return false;
            }
            $allowed = array_fill_keys($form->orderedTypeIds(), true);
            if ($allowed === []) {
                return false;
            }
            $matched = 0;
            foreach ($jobTypeIds as $typeId) {
                if (isset($allowed[$typeId])) {
                    $matched++;

                    continue;
                }
                if ($this->typeIsIgnorableSamplePreparation((int) $typeId, $allowed)) {
                    continue;
                }

                return false;
            }

            return $matched > 0;
        };

        $fo2 = $candidates->get('LSP-7.8-FO2');
        $fo2a = $candidates->get('LSP-7.8-FO2A');
        $fo2b = $candidates->get('LSP-7.8-FO2B');
        $description = trim((string) (($sample ?? $jobOrder->samples->first())?->description ?? ''));
        $wantAbstracted = OfficialAnalysisCatalog::isWastewaterAbstractedKind($description);
        $wantCoastal = OfficialAnalysisCatalog::isWastewaterCoastalKind($description);

        if ($wantCoastal && $fo2b instanceof ControlledForm && $contained($fo2b)) {
            return $fo2b;
        }

        if ($wantAbstracted && $fo2a instanceof ControlledForm && $contained($fo2a)) {
            return $fo2a;
        }

        if ($fo2 instanceof ControlledForm && $contained($fo2)) {
            return $fo2;
        }

        if ($fo2b instanceof ControlledForm && $contained($fo2b)) {
            return $fo2b;
        }

        if ($fo2a instanceof ControlledForm && $contained($fo2a)) {
            return $fo2a;
        }

        return null;
    }

    /**
     * Types-only dynamic-matrix form whose bound types contain every job analysis type.
     *
     * @param  list<int>  $jobTypeIds
     */
    private function matchingTypesOnlyMatrixSubsetForm(array $jobTypeIds, ?JobOrder $jobOrder = null): ?ControlledForm
    {
        if ($jobTypeIds === []) {
            return null;
        }

        $candidates = ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->whereNotIn('form_code', ['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'])
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
            $matched = 0;
            $allContained = true;
            foreach ($jobTypeIds as $typeId) {
                if (isset($allowed[$typeId])) {
                    $matched++;

                    continue;
                }
                if ($this->typeIsIgnorableSamplePreparation((int) $typeId, $allowed)) {
                    continue;
                }
                $allContained = false;
                break;
            }

            if ($allContained && $matched > 0) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Sample Preparation is billed on the job order and kept off result sheets.
     * An extra prep line must not hide the form that covers the real tests.
     *
     * @param  array<int, true>  $allowed
     */
    private function typeIsIgnorableSamplePreparation(int $typeId, array $allowed): bool
    {
        if (isset($allowed[$typeId])) {
            return false;
        }

        return isset($this->samplePreparationTypeIds()[$typeId]);
    }

    /**
     * @return array<int, true>
     */
    private function samplePreparationTypeIds(): array
    {
        if ($this->samplePreparationTypeIds !== null) {
            return $this->samplePreparationTypeIds;
        }

        $this->samplePreparationTypeIds = AnalysisType::query()
            ->where('name', 'Sample Preparation')
            ->pluck('id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();

        return $this->samplePreparationTypeIds;
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

        if ($analyses->isNotEmpty() && $analyses->every(
            fn (JobOrderAnalysis $line) => (int) $line->assigned_to === (int) $user->id,
        )) {
            return true;
        }

        $qualifiedTypeIds = $user->analysisTypes()
            ->pluck('analysis_types.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (
            $analyses->isNotEmpty()
            && $qualifiedTypeIds !== []
            && $analyses->every(
                fn (JobOrderAnalysis $line) => $line->analysis_type_id
                    && in_array((int) $line->analysis_type_id, $qualifiedTypeIds, true),
            )
        ) {
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

    private function controlNumberForSample(JobOrder $jobOrder, ?int $sampleId): ?string
    {
        $jobOrder->loadMissing('samples');
        $index = $jobOrder->samples->search(fn (Sample $sample): bool => $sample->id === $sampleId);

        return $index === false ? null : SampleControlNumber::forJobOrder($jobOrder, (int) $index);
    }

    /**
     * Same sample + same name (e.g. FO37 and FO3 subset) → append the form code to each.
     *
     * @param  list<AnalysisResultReport>  $reports
     * @return list<AnalysisResultReport>
     */
    private function disambiguateFilenames(array $reports): array
    {
        $counts = array_count_values(array_map(fn (AnalysisResultReport $report): string => $report->filename, $reports));

        return array_map(function (AnalysisResultReport $report) use ($counts): AnalysisResultReport {
            if (($counts[$report->filename] ?? 0) < 2 || ! $report->controlledForm) {
                return $report;
            }

            return $report->withFilename(ResultPdfFilename::for(
                $report->jobOrder,
                $report->analyses,
                $report->controlNumber,
                $report->controlledForm->form_code,
                withFormCode: true,
            ));
        }, $reports);
    }

    private function individual(JobOrder $jobOrder, JobOrderAnalysis $analysis): AnalysisResultReport
    {
        return new AnalysisResultReport(
            kind: AnalysisResultReport::KIND_INDIVIDUAL,
            filename: ResultPdfFilename::for($jobOrder, collect([$analysis]), $this->controlNumberForSample($jobOrder, $analysis->sample_id)),
            title: $analysis->name,
            message: null,
            jobOrder: $jobOrder,
            analyses: collect([$analysis]),
            values: [],
            analysis: $analysis,
            sampleId: $analysis->sample_id,
        );
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

        $filename = ResultPdfFilename::for($jobOrder, $ordered, $controlNumber, $form->form_code);
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

        $incompleteLines = $ordered->reject(
            fn (JobOrderAnalysis $line) => $line->isSamplePreparation()
                || $line->status === JobOrderAnalysisStatus::Completed,
        );

        if ($incompleteLines->isNotEmpty()) {
            $missingNames = $incompleteLines
                ->map(fn (JobOrderAnalysis $line): string => trim((string) $line->name))
                ->filter(fn (string $name): bool => $name !== '')
                ->values()
                ->all();
            $missingLabel = $missingNames !== []
                ? implode(', ', $missingNames)
                : 'all matched tests';

            return new AnalysisResultReport(
                kind: AnalysisResultReport::KIND_WAITING,
                filename: $filename,
                title: $title,
                message: "Preview for \"{$form->name}\" needs these results completed: {$missingLabel}.",
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
