<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\ControlledPdfFiller;
use App\Services\JobOrderService;
use App\Support\AnalysisResultPdfExporter;
use App\Support\AnalysisResultReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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

        if (! $user->hasRole('admin')) {
            $openQuery->where('assigned_to', $user->id);
            $completedQuery->where('assigned_to', $user->id);
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
        $isAdmin = $user->hasRole('admin');

        // Include every line on page jobs so analysts can see teammate assignments.
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
                $reportByJob[$jobId] = $this->reports->forJobOrder($task->jobOrder, $user)->summary();
            }
        }

        $tasks = $tasks->map(function (JobOrderAnalysis $task) use ($user, $isAdmin, $reportByJob) {
            $isMine = $isAdmin || (int) $task->assigned_to === (int) $user->id;

            return [
                'id' => $task->id,
                'name' => $task->name,
                'analysis_type_id' => $task->analysis_type_id,
                'category_label' => $task->resolvedCategoryLabel(),
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'result_value' => $task->result_value,
                'result_measurement' => $task->result_measurement,
                'result_unit' => $task->result_unit,
                'result_remarks' => $task->result_remarks,
                'result_mode' => $task->analysisType?->isPassFail() ? 'pass_fail' : 'value',
                'updated_at' => $task->updated_at?->toIso8601String(),
                'assigned_to' => $task->assigned_to,
                'assignee_name' => $task->assignee?->name,
                'is_mine' => $isMine,
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
                    'samples' => $task->jobOrder->samples->map(fn ($sample) => [
                        'sample_code' => $sample->sample_code,
                        'description' => $sample->description,
                        'matrix' => $sample->matrix,
                    ])->values()->all(),
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
            ],
        ]);
    }

    public function saveDraft(Request $request, JobOrderAnalysis $analysis): RedirectResponse
    {
        $data = $request->validate([
            'result_value' => ['nullable', 'string', 'max:255'],
            'result_measurement' => ['nullable', 'string', 'max:80'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->jobOrders->saveAnalysisDraft($analysis, $data, $request->user());

        return back()->with('success', 'Draft result saved.');
    }

    public function complete(Request $request, JobOrderAnalysis $analysis): RedirectResponse
    {
        $data = $request->validate([
            'result_value' => ['required', 'string', 'max:255'],
            'result_measurement' => ['nullable', 'string', 'max:80'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->jobOrders->completeAnalysis($analysis, $data, $request->user());

        return back()->with('success', 'Result saved and analysis marked complete.');
    }

    public function submitForReview(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->jobOrders->submitForReview($jobOrder, $request->user());

        return back()->with('success', "Job order {$jobOrder->reference_no} sent to Head for signature.");
    }

    public function report(Request $request, JobOrderAnalysis $analysis): JsonResponse
    {
        $user = $request->user();
        $this->assertCanViewAnalysis($user, $analysis);

        $resolved = $this->reports->forAnalysis($analysis, $user);
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

        return response()->json($resolved->manifest(
            $templateUrl,
            $inlinePdf,
            $resolved->fillMode(),
        ));
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

        $binary = app(ControlledPdfFiller::class)->fill(
            $resolved->controlledRevision->load('fields'),
            $resolved->values,
        );

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resolved->filename.'"',
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
}
