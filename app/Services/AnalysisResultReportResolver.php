<?php

namespace App\Services;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Support\AnalysisResultReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalysisResultReportResolver
{
    public function forAnalysis(JobOrderAnalysis $analysis, User $user): AnalysisResultReport
    {
        $analysis->loadMissing(['jobOrder.samples', 'jobOrder.analyses.assignee', 'jobOrder.analyses.analysisType', 'analysisType', 'assignee']);

        $jobReport = $this->forJobOrder($analysis->jobOrder, $user);

        if ($jobReport->controlledForm) {
            return $jobReport;
        }

        return $this->individual($analysis->jobOrder, $analysis);
    }

    public function forJobOrder(JobOrder $jobOrder, User $user): AnalysisResultReport
    {
        $jobOrder->loadMissing(['samples', 'analyses.assignee', 'analyses.analysisType', 'reviewer']);

        $controlled = $this->matchingControlledForm($jobOrder);
        if ($controlled) {
            return $this->fromControlledForm($jobOrder, $user, $controlled);
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

        return ControlledForm::query()
            ->with(['analysisTypes', 'currentRevision.fields'])
            ->where('category', ControlledFormCategory::AnalysisResult)
            ->whereNull('analysis_package_id')
            ->where('combination_key', ControlledFormService::combinationKey($unique))
            ->whereHas('revisions', fn ($query) => $query->where('status', ControlledFormRevisionStatus::Active))
            ->first();
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

    private function fromControlledForm(JobOrder $jobOrder, User $user, ControlledForm $form): AnalysisResultReport
    {
        $revision = $form->activeRevision();
        $ordered = $this->orderedAnalysesForIds($jobOrder, $form->orderedTypeIds());
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

        $values = app(FieldValueResolver::class)->forResult($revision->load('fields'), $jobOrder, $ordered, $form);

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
