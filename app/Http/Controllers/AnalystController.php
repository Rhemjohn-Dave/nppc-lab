<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\JobOrderService;
use App\Support\AnalysisResultPdfExporter;
use App\Support\AnalysisResultReport;
use App\Support\DynamicTestMatrix;
use App\Support\ResultSignatories;
use App\Support\SampleControlNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnalystController extends Controller
{
    public function __construct(
        private readonly JobOrderService $jobOrders,
        private readonly AnalysisResultReportResolver $reports,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());
        $sort = $request->string('sort')->toString();
        $allowedSorts = ['needs_action', 'newest', 'oldest'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'needs_action';
        }

        $day = $request->string('day')->toString();
        $dayRange = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4))) {
            $labStart = Carbon::createFromFormat('Y-m-d', $day, (string) config('app.lab_timezone', 'Asia/Manila'))->startOfDay();
            $appTz = (string) config('app.timezone', 'UTC');
            $dayRange = [$labStart->copy()->timezone($appTz), $labStart->copy()->endOfDay()->timezone($appTz)];
        } else {
            $day = '';
        }

        $openStatuses = [
            JobOrderAnalysisStatus::Assigned,
            JobOrderAnalysisStatus::Returned,
            JobOrderAnalysisStatus::InProgress,
            JobOrderAnalysisStatus::Pending,
        ];

        $openQuery = JobOrderAnalysis::query()
            ->whereIn('status', $openStatuses)
            ->whereHas('jobOrder', function ($query) {
                $query->where('status', JobOrderStatus::InAnalysis);
            });

        $completedQuery = JobOrderAnalysis::query()
            ->where('status', JobOrderAnalysisStatus::Completed);

        $isAdmin = $user->hasRole('admin');
        $qualifiedTypeIds = $isAdmin
            ? []
            : $user->analysisTypes()
                ->pluck('analysis_types.id')
                ->map(fn ($id) => (int) $id)
                ->all();

        if (! $isAdmin) {
            $openQuery->where(function ($query) use ($user, $qualifiedTypeIds) {
                $query->where('assigned_to', $user->id);
                if ($qualifiedTypeIds !== []) {
                    $query->orWhereIn('analysis_type_id', $qualifiedTypeIds);
                }
            });
            $completedQuery->where(function ($query) use ($user, $qualifiedTypeIds) {
                $query->where('assigned_to', $user->id);
                if ($qualifiedTypeIds !== []) {
                    $query->orWhereIn('analysis_type_id', $qualifiedTypeIds);
                }
            });
        }

        if ($dayRange !== null) {
            $openQuery->whereHas('jobOrder', fn ($query) => $query->whereBetween('received_at', $dayRange));
            $completedQuery->whereBetween('completed_at', $dayRange);
        }

        $showingCompleted = $statusFilter === JobOrderAnalysisStatus::Completed->value;
        $baseQuery = $showingCompleted ? $completedQuery : $openQuery;

        $assignedCount = (clone $openQuery)
            ->whereIn('status', [
                JobOrderAnalysisStatus::Assigned,
                JobOrderAnalysisStatus::Pending,
            ])
            ->count();
        $returnedCount = (clone $openQuery)
            ->where('status', JobOrderAnalysisStatus::Returned)
            ->count();

        $counts = [
            'all' => (clone $openQuery)->count(),
            'needs_action' => $assignedCount + $returnedCount,
            'returned' => $returnedCount,
            'in_progress' => (clone $openQuery)
                ->where('status', JobOrderAnalysisStatus::InProgress)
                ->count(),
            'assigned' => $assignedCount,
            'completed' => (clone $completedQuery)->count(),
        ];

        $allowedStatusFilters = [
            'needs_action',
            JobOrderAnalysisStatus::Returned->value,
            JobOrderAnalysisStatus::InProgress->value,
            JobOrderAnalysisStatus::Assigned->value,
            JobOrderAnalysisStatus::Pending->value,
            JobOrderAnalysisStatus::Completed->value,
        ];

        $filteredQuery = (clone $baseQuery)
            ->when(
                in_array($statusFilter, $allowedStatusFilters, true) && ! $showingCompleted,
                function ($query) use ($statusFilter) {
                    if ($statusFilter === 'needs_action') {
                        $query->whereIn('status', [
                            JobOrderAnalysisStatus::Assigned,
                            JobOrderAnalysisStatus::Pending,
                            JobOrderAnalysisStatus::Returned,
                        ]);
                    } elseif ($statusFilter === JobOrderAnalysisStatus::Assigned->value) {
                        $query->whereIn('status', [
                            JobOrderAnalysisStatus::Assigned,
                            JobOrderAnalysisStatus::Pending,
                        ]);
                    } else {
                        $query->where('status', $statusFilter);
                    }
                },
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('category_label', 'like', "%{$search}%")
                        ->orWhereHas('jobOrder', function ($job) use ($search) {
                            $job->where('reference_no', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%")
                                ->orWhere('company_name', 'like', "%{$search}%")
                                ->orWhere('classification', 'like', "%{$search}%")
                                ->orWhereHas('samples', function ($samples) use ($search) {
                                    $samples->where('sample_code', 'like', "%{$search}%")
                                        ->orWhere('description', 'like', "%{$search}%");
                                });
                        });
                });
            });

        $returnedValue = JobOrderAnalysisStatus::Returned->value;
        $jobsQuery = JobOrder::query()
            ->whereIn('id', (clone $filteredQuery)->select('job_order_id'));

        if ($sort === 'newest') {
            $jobsQuery->orderByDesc('id');
        } elseif ($sort === 'oldest') {
            $jobsQuery->orderBy('id');
        } else {
            $jobsQuery
                ->orderByRaw(
                    '(select min(case when status = ? then 0 else 1 end) from job_order_analyses where job_order_id = job_orders.id) asc',
                    [$returnedValue],
                )
                ->orderByDesc('id');
        }

        $jobs = $jobsQuery->paginate(10)->withQueryString();

        $pageJobIds = $jobs->getCollection()->pluck('id');

        // Include every line on page jobs so analysts can see teammate suggestions.
        $tasks = JobOrderAnalysis::query()
            ->with(['jobOrder.samples', 'analysisType', 'assignee:id,name'])
            ->whereIn('job_order_id', $pageJobIds)
            ->get()
            ->sortBy(function (JobOrderAnalysis $task) use ($user, $isAdmin) {
                $isMine = $isAdmin || (int) $task->assigned_to === (int) $user->id;
                $returned = $task->status === JobOrderAnalysisStatus::Returned ? 0 : 1;
                $mineRank = $isMine ? 0 : 1;

                return sprintf(
                    '%d-%d-%s-%s',
                    $returned,
                    $mineRank,
                    $task->jobOrder->reference_no,
                    $task->name,
                );
            })
            ->values();

        $reportByJob = [];
        foreach ($tasks as $task) {
            $jobId = $task->job_order_id;
            if (! isset($reportByJob[$jobId])) {
                $reportByJob[$jobId] = $this->reports->forJobOrder($task->jobOrder, $user, withValues: false)->summary();
            }
        }

        $tasks = $tasks->map(function (JobOrderAnalysis $task) use ($user, $isAdmin, $qualifiedTypeIds, $reportByJob) {
            $isMine = $isAdmin || (int) $task->assigned_to === (int) $user->id;
            $canWork = $isAdmin
                || $isMine
                || (
                    $task->analysis_type_id
                    && in_array((int) $task->analysis_type_id, $qualifiedTypeIds, true)
                );

            $samples = $task->jobOrder->samples->values();
            $sampleCount = $samples->count();
            $sampleIndex = $samples->search(
                fn ($sample) => (int) $sample->id === (int) $task->sample_id,
            );
            $controlNumber = $sampleIndex === false
                ? null
                : SampleControlNumber::forIndex(
                    (string) $task->jobOrder->reference_no,
                    (int) $sampleIndex,
                    $sampleCount,
                );
            $sample = $sampleIndex === false ? null : $samples->get($sampleIndex);
            $sampleLabel = null;
            if ($sample) {
                $code = filled($sample->sample_code) ? (string) $sample->sample_code : null;
                $description = filled($sample->description)
                    ? (string) $sample->description
                    : 'Sample '.((int) $sampleIndex + 1);
                $sampleLabel = $code ? "{$code} — {$description}" : $description;
            }

            return [
                'id' => $task->id,
                'name' => $task->name,
                'sample_id' => $task->sample_id,
                'sample_label' => $sampleLabel,
                'control_number' => $controlNumber,
                'analysis_type_id' => $task->analysis_type_id,
                'category_label' => $task->resolvedCategoryLabel(),
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'result_value' => $task->result_value,
                'result_pass_fail' => $task->result_pass_fail,
                'result_measurement' => $task->result_measurement,
                'result_unit' => $task->result_unit,
                'result_remarks' => $task->result_remarks,
                'result_method' => $task->result_method,
                'performed_at' => $task->performed_at
                    ? $task->performed_at
                        ->timezone((string) config('app.lab_timezone', 'Asia/Manila'))
                        ->format('Y-m-d\TH:i')
                    : null,
                'procedure_method' => DynamicTestMatrix::methodForAnalysisType($task->analysisType),
                'result_mode' => $task->analysisType?->isPassFail() ? 'pass_fail' : 'value',
                'requires_pass_fail' => (bool) ($task->analysisType?->requires_pass_fail),
                'is_sample_preparation' => $task->isSamplePreparation(),
                'updated_at' => $task->updated_at?->toIso8601String(),
                'assigned_to' => $task->assigned_to,
                'assignee_name' => $task->assignee?->name,
                'is_mine' => $isMine,
                'can_work' => $canWork,
                'report' => $this->taskReportSummary($reportByJob[$task->job_order_id] ?? null, $task->jobOrder),
                'job_order' => [
                    'id' => $task->jobOrder->id,
                    'reference_no' => $task->jobOrder->reference_no,
                    'customer_name' => $task->jobOrder->customer_name,
                    'company_name' => $task->jobOrder->company_name,
                    'classification' => $task->jobOrder->classification,
                    'sample_storage_temp' => $task->jobOrder->sample_storage_temp,
                    'field_data' => $task->jobOrder->field_data,
                    'status' => $task->jobOrder->status->value,
                    'received_at' => $task->jobOrder->received_at?->toIso8601String(),
                    'reviewed_at' => $task->jobOrder->reviewed_at?->toIso8601String(),
                    'review_notes' => $task->jobOrder->review_notes,
                    'samples' => $samples->map(function ($sample, int $index) use ($task, $sampleCount) {
                        return [
                            'id' => $sample->id,
                            'sample_code' => $sample->sample_code,
                            'description' => $sample->description,
                            'matrix' => $sample->matrix,
                            'control_number' => SampleControlNumber::forIndex(
                                (string) $task->jobOrder->reference_no,
                                $index,
                                $sampleCount,
                            ),
                        ];
                    })->values()->all(),
                ],
            ];
        });

        return Inertia::render('analyst/index', [
            'tasks' => $tasks,
            'consolidations' => $this->jobOrders->consolidationsFor($user),
            'releasedPrints' => $this->jobOrders->releasedResultPrintsFor($user),
            'counts' => $counts,
            'jobs' => [
                'from' => $jobs->firstItem(),
                'to' => $jobs->lastItem(),
                'total' => $jobs->total(),
                'links' => $jobs->toArray()['links'] ?? [],
            ],
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'sort' => $sort,
                'day' => $day,
            ],
        ]);
    }

    public function saveDraft(Request $request, JobOrderAnalysis $analysis): RedirectResponse
    {
        $data = $request->validate([
            'performed_at' => ['nullable', 'date'],
            'result_value' => ['nullable', 'string', 'max:255'],
            'result_pass_fail' => ['nullable', 'string', 'max:20'],
            'result_measurement' => ['nullable', 'string', 'max:80'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
            'result_method' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->jobOrders->saveAnalysisDraft($analysis, $data, $request->user());

        // Silent redirect — autosave on blur must not toast or feel like a full navigation.
        return back();
    }

    public function complete(Request $request, JobOrderAnalysis $analysis): RedirectResponse
    {
        $analysis->loadMissing(['jobOrder.packages', 'jobOrder.analyses', 'analysisType']);
        $requiresPassFail = (bool) ($analysis->analysisType?->requires_pass_fail);
        $isPrep = $analysis->isSamplePreparation();

        $data = $request->validate([
            'performed_at' => [$isPrep ? 'nullable' : 'required', 'date'],
            'result_value' => [$isPrep ? 'nullable' : 'required', 'string', 'max:255'],
            'result_pass_fail' => [$requiresPassFail ? 'required' : 'nullable', 'string', 'max:20'],
            'result_measurement' => ['nullable', 'string', 'max:80'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
            'result_method' => ['nullable', 'string', 'max:2000'],
        ]);

        $wasCompleted = $analysis->status === JobOrderAnalysisStatus::Completed;
        $this->jobOrders->completeAnalysis($analysis, $data, $request->user());

        return back()->with(
            'success',
            $wasCompleted
                ? 'Result updated.'
                : 'Result saved and analysis marked complete.',
        );
    }

    public function completeSample(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'sample_id' => ['nullable', 'integer'],
        ]);

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;

        $completed = $this->jobOrders->completeSampleAnalyses(
            $jobOrder,
            $request->user(),
            $sampleId,
        );

        $count = count($completed);

        if ($sampleId === null) {
            return back()->with(
                'success',
                $count === 1
                    ? 'Assay marked complete.'
                    : "{$count} assays on this job order marked complete.",
            );
        }

        return back()->with(
            'success',
            $count === 1
                ? 'Sample assay marked complete.'
                : "{$count} assays for this sample marked complete.",
        );
    }

    public function submitForReview(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'signatories' => ['nullable', 'array', 'min:1', 'max:4'],
            'signatories.*.name' => ['nullable', 'string', 'max:180'],
            'signatories.*.prc_id' => ['nullable', 'string', 'max:60'],
        ]);

        $this->jobOrders->submitForReview(
            $jobOrder,
            $request->user(),
            $data['signatories'] ?? null,
        );

        return back()->with('success', "Job order {$jobOrder->reference_no} sent to Head for signature.");
    }

    public function report(Request $request, JobOrderAnalysis $analysis): JsonResponse
    {
        $user = $request->user();
        $this->assertCanViewAnalysis($user, $analysis);

        $resolved = $this->reports->forAnalysis($analysis, $user, withValues: false);
        // AcroForm client fill needs field values in the JSON manifest; overlay uses pdf_url.
        if (
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->canPreview()
            && ! $resolved->isOverlay()
        ) {
            $resolved = $this->reports->forAnalysis($analysis, $user, withValues: true);
        }
        $isOverlayCombined = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay();

        $inlinePdf = match (true) {
            $resolved->kind === AnalysisResultReport::KIND_INDIVIDUAL => "/analyst/tasks/{$analysis->id}/pdf?inline=1",
            $isOverlayCombined => "/analyst/tasks/{$analysis->id}/combined-pdf",
            default => null,
        };
        $templateUrl = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && ! $isOverlayCombined
            && $resolved->controlledRevision
            ? "/analyst/controlled-revisions/{$resolved->controlledRevision->id}?analysis={$analysis->id}"
            : '';

        $jobOrder = $analysis->jobOrder;
        $allReports = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: false);
        $baseReportUrl = "/analyst/job-orders/{$jobOrder->id}/result-report";

        return response()->json($resolved->manifest(
            $templateUrl,
            $inlinePdf,
            $resolved->fillMode(),
            [
                ...$this->signatoryManifestExtra(
                    $jobOrder,
                    $resolved,
                    "/analyst/job-orders/{$jobOrder->id}/result-signatories",
                ),
                'reports' => $allReports->map(
                    fn (AnalysisResultReport $report) => $this->reportTabManifest($jobOrder, $report, $baseReportUrl),
                )->values()->all(),
            ],
        ));
    }

    public function resultReport(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $user = $request->user();
        $this->assertCanViewJobOrder($user, $jobOrder);

        $jobOrder->loadMissing(['analyses', 'samples', 'packages']);

        $sampleId = $request->integer('sample_id') ?: null;
        $formId = $request->integer('form_id') ?: null;

        $allReports = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: false);
        $resolved = $this->reports->pickReport($allReports, $sampleId, $formId);

        if (
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->canPreview()
            && ! $resolved->isOverlay()
        ) {
            $withValues = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: true);
            $resolved = $this->reports->pickReport(
                $withValues,
                $sampleId ?: $resolved->sampleId,
                $formId ?: $resolved->controlledForm?->id,
            );
        }

        $isOverlayCombined = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay();

        // Prefer an analysis on the selected sample for task-scoped PDF/template routes.
        $anchorAnalysis = $resolved->analyses->first()
            ?? $jobOrder->analyses->firstWhere('sample_id', $resolved->sampleId)
            ?? $jobOrder->analyses->first();

        $inlinePdf = match (true) {
            $resolved->kind === AnalysisResultReport::KIND_INDIVIDUAL && $anchorAnalysis =>
                "/analyst/tasks/{$anchorAnalysis->id}/pdf?inline=1",
            $isOverlayCombined && $anchorAnalysis =>
                "/analyst/tasks/{$anchorAnalysis->id}/combined-pdf",
            default => null,
        };

        $templateUrl = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && ! $isOverlayCombined
            && $resolved->controlledRevision
            && $anchorAnalysis
            ? "/analyst/controlled-revisions/{$resolved->controlledRevision->id}?analysis={$anchorAnalysis->id}"
            : '';

        $baseReportUrl = "/analyst/job-orders/{$jobOrder->id}/result-report";

        return response()->json($resolved->manifest(
            $templateUrl,
            $inlinePdf,
            $resolved->fillMode(),
            [
                ...$this->signatoryManifestExtra(
                    $jobOrder,
                    $resolved,
                    "/analyst/job-orders/{$jobOrder->id}/result-signatories",
                ),
                'reports' => $allReports->map(
                    fn (AnalysisResultReport $report) => $this->reportTabManifest($jobOrder, $report, $baseReportUrl),
                )->values()->all(),
            ],
        ));
    }

    public function updateResultSignatories(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forJobOrder($jobOrder, $user);
        abort_unless(
            $this->reports->userCanAccessCombined($resolved->analyses, $user, $jobOrder)
            || $user->hasRole('admin'),
            403,
        );
        // Allow confirming analyst/PRC during preview; Print/Download stay gated by Head release.
        abort_unless($resolved->canPreview() || $user->hasRole('admin'), 403);
        abort_unless($resolved->controlledForm !== null, 422, 'No combined result form is bound to this job.');

        $data = $request->validate([
            'signatories' => ['required', 'array', 'min:1', 'max:4'],
            'signatories.*.name' => ['nullable', 'string', 'max:180'],
            'signatories.*.prc_id' => ['nullable', 'string', 'max:60'],
        ]);

        $normalized = ResultSignatories::normalizeForStorage(
            $data['signatories'],
            $resolved->controlledForm,
        );

        $jobOrder->update(['result_signatories' => $normalized]);
        $jobOrder->refresh();

        return response()->json([
            'ok' => true,
            'signatories' => $normalized,
            'signatory' => ResultSignatories::manifestPayload(
                $jobOrder,
                $resolved->controlledForm,
                "/analyst/job-orders/{$jobOrder->id}/result-signatories",
                $resolved->analyses,
            ),
        ]);
    }

    public function combinedPdf(Request $request, JobOrderAnalysis $analysis): HttpResponse
    {
        $user = $request->user();
        $this->assertCanViewAnalysis($user, $analysis);

        $resolved = $this->reports->forAnalysis($analysis, $user);
        abort_unless(
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay(),
            403,
        );

        abort_unless($resolved->controlledRevision instanceof ControlledFormRevision, 403);

        if ($request->boolean('print')) {
            abort_unless($resolved->canPrint() || $user->hasRole('admin'), 403);
        }

        $binary = $this->reports->renderOverlayPdf($resolved);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resolved->filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function controlledRevision(Request $request, ControlledFormRevision $revision): BinaryFileResponse
    {
        $user = $request->user();
        $analysisId = $request->integer('analysis');
        abort_unless($analysisId > 0, 404);

        $analysis = JobOrderAnalysis::query()->findOrFail($analysisId);
        $this->assertCanViewAnalysis($user, $analysis);

        $resolved = $this->reports->forAnalysis($analysis, $user);
        abort_unless(
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->controlledRevision?->id === $revision->id
            && $revision->hasCanonicalPdf(),
            403,
        );

        return response()->file($revision->canonicalAbsolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$revision->form->form_code.'.pdf"',
        ]);
    }

    public function pdf(Request $request, JobOrderAnalysis $analysis): HttpResponse
    {
        $user = $request->user();
        $this->assertCanViewAnalysis($user, $analysis);

        $resolved = $this->reports->forAnalysis($analysis, $user);
        abort_unless($resolved->kind === AnalysisResultReport::KIND_INDIVIDUAL, 404);

        if (! $request->boolean('inline')) {
            abort_unless($resolved->canPrint() || $user->hasRole('admin'), 403);
        }

        return AnalysisResultPdfExporter::download(
            $analysis,
            $request->boolean('inline'),
        );
    }

    /**
     * @param  array{kind: string, title: string, message: string|null, can_preview: bool, can_print?: bool}|null  $jobSummary
     * @return array{kind: string, title: string, message: string|null, can_preview: bool, can_print: bool}
     */
    private function taskReportSummary(?array $jobSummary, JobOrder $jobOrder): array
    {
        if ($jobSummary && $jobSummary['kind'] !== AnalysisResultReport::KIND_INDIVIDUAL) {
            return [
                ...$jobSummary,
                'can_print' => (bool) ($jobSummary['can_print'] ?? false),
            ];
        }

        return [
            'kind' => AnalysisResultReport::KIND_INDIVIDUAL,
            'title' => 'Analysis result sheet',
            'message' => $jobOrder->reviewed_at
                ? null
                : 'Print and download are available after Head releases the results.',
            'can_preview' => true,
            'can_print' => $jobOrder->reviewed_at !== null,
        ];
    }

    private function assertCanViewAnalysis(?User $user, JobOrderAnalysis $analysis): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->hasRole('admin') || (int) $analysis->assigned_to === (int) $user->id) {
            return;
        }

        $analysis->loadMissing('jobOrder.packages');

        abort_unless(
            $this->reports->userIsPackageSignatory($analysis->jobOrder, $user),
            403,
        );
    }

    private function assertCanViewJobOrder(?User $user, JobOrder $jobOrder): void
    {
        abort_unless($user instanceof User, 403);

        if ($user->hasRole('admin')) {
            return;
        }

        $jobOrder->loadMissing(['analyses', 'packages']);

        if ($this->reports->userIsPackageSignatory($jobOrder, $user)) {
            return;
        }

        abort_unless(
            $jobOrder->analyses->contains(
                fn (JobOrderAnalysis $line) => (int) $line->assigned_to === (int) $user->id,
            ),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportTabManifest(JobOrder $jobOrder, AnalysisResultReport $report, string $baseReportUrl): array
    {
        $query = http_build_query(array_filter([
            'sample_id' => $report->sampleId,
            'form_id' => $report->controlledForm?->id,
        ], fn ($value) => $value !== null && $value !== ''));

        $anchor = $report->analyses->first()
            ?? $jobOrder->analyses->firstWhere('sample_id', $report->sampleId);

        $isOverlay = $report->kind === AnalysisResultReport::KIND_COMBINED && $report->isOverlay();

        return [
            ...$report->summary(),
            'label' => $report->controlNumber
                ? 'Control no. '.$report->controlNumber
                : $report->title,
            'report_url' => $baseReportUrl.($query !== '' ? "?{$query}" : ''),
            'pdf_url' => $isOverlay && $report->canPreview() && $anchor
                ? "/analyst/tasks/{$anchor->id}/combined-pdf"
                : null,
        ];
    }

    /**
     * @return array{signatory?: array<string, mixed>}
     */
    private function signatoryManifestExtra(JobOrder $jobOrder, AnalysisResultReport $resolved, ?string $saveUrl): array
    {
        if ($resolved->controlledForm === null || ! $resolved->canPreview()) {
            return [];
        }

        return [
            'signatory' => ResultSignatories::manifestPayload(
                $jobOrder,
                $resolved->controlledForm,
                $saveUrl,
                $resolved->analyses,
            ),
        ];
    }
}
