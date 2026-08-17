<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\JobOrderAnalysis;
use App\Services\JobOrderService;
use App\Support\AnalysisResultPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnalystController extends Controller
{
    public function __construct(private readonly JobOrderService $jobOrders) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

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

        $counts = [
            'all' => (clone $openQuery)->count(),
            'returned' => (clone $openQuery)
                ->where('status', JobOrderAnalysisStatus::Returned)
                ->count(),
            'in_progress' => (clone $openQuery)
                ->where('status', JobOrderAnalysisStatus::InProgress)
                ->count(),
            'assigned' => (clone $openQuery)
                ->whereIn('status', [
                    JobOrderAnalysisStatus::Assigned,
                    JobOrderAnalysisStatus::Pending,
                ])
                ->count(),
            'completed' => (clone $completedQuery)->count(),
        ];

        $allowedStatusFilters = [
            JobOrderAnalysisStatus::Returned->value,
            JobOrderAnalysisStatus::InProgress->value,
            JobOrderAnalysisStatus::Assigned->value,
            JobOrderAnalysisStatus::Pending->value,
            JobOrderAnalysisStatus::Completed->value,
        ];

        $tasks = (clone $baseQuery)
            ->with(['jobOrder.samples', 'analysisType'])
            ->when(
                in_array($statusFilter, $allowedStatusFilters, true) && ! $showingCompleted,
                function ($query) use ($statusFilter) {
                    if ($statusFilter === JobOrderAnalysisStatus::Assigned->value) {
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
                        ->orWhereHas('jobOrder', function ($job) use ($search) {
                            $job->where('reference_no', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%")
                                ->orWhere('company_name', 'like', "%{$search}%");
                        });
                });
            })
            ->get()
            ->sortBy(function (JobOrderAnalysis $task) {
                $priority = $task->status === JobOrderAnalysisStatus::Returned ? 0 : 1;

                return sprintf(
                    '%d-%s-%s',
                    $priority,
                    $task->jobOrder->reference_no,
                    $task->name,
                );
            })
            ->values()
            ->map(fn (JobOrderAnalysis $task) => [
                'id' => $task->id,
                'name' => $task->name,
                'category_label' => $task->resolvedCategoryLabel(),
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'result_value' => $task->result_value,
                'result_unit' => $task->result_unit,
                'result_remarks' => $task->result_remarks,
                'job_order' => [
                    'id' => $task->jobOrder->id,
                    'reference_no' => $task->jobOrder->reference_no,
                    'customer_name' => $task->jobOrder->customer_name,
                    'company_name' => $task->jobOrder->company_name,
                    'classification' => $task->jobOrder->classification,
                    'sample_storage_temp' => $task->jobOrder->sample_storage_temp,
                    'field_data' => $task->jobOrder->field_data,
                    'samples' => $task->jobOrder->samples->map(fn ($sample) => [
                        'sample_code' => $sample->sample_code,
                        'description' => $sample->description,
                        'matrix' => $sample->matrix,
                    ])->values(),
                ],
            ]);

        return Inertia::render('analyst/index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function saveDraft(Request $request, JobOrderAnalysis $analysis): RedirectResponse
    {
        $data = $request->validate([
            'result_value' => ['nullable', 'string', 'max:255'],
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
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->jobOrders->completeAnalysis($analysis, $data, $request->user());

        return back()->with('success', 'Result saved and analysis marked complete.');
    }

    public function pdf(Request $request, JobOrderAnalysis $analysis): HttpResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('admin') || $analysis->assigned_to === $user->id,
            403,
        );

        return AnalysisResultPdfExporter::download($analysis);
    }
}
