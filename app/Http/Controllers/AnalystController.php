<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\Sample;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\JobOrderService;
use App\Support\AnalysisResultPdfExporter;
use App\Support\AnalysisResultReport;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use App\Support\BulkSampleRemarks;
use App\Support\ResultFooter;
use App\Support\ResultSheetFacts;
use App\Support\ResultSignatories;
use App\Support\SampleControlNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            if ($qualifiedTypeIds === []) {
                $openQuery->whereRaw('0 = 1');
                $completedQuery->whereRaw('0 = 1');
            } else {
                $openQuery->whereIn('analysis_type_id', $qualifiedTypeIds);
                $completedQuery->whereIn('analysis_type_id', $qualifiedTypeIds);
            }
        }

        if ($dayRange !== null) {
            $openQuery->whereHas('jobOrder', fn ($query) => $query->whereBetween('received_at', $dayRange));
            $completedQuery->whereBetween('completed_at', $dayRange);
        }

        $showingCompleted = $statusFilter === JobOrderAnalysisStatus::Completed->value;
        $showingReadyToSubmit = $statusFilter === 'ready_to_submit';

        $readyToSubmitJobsQuery = $this->readyToSubmitJobsQuery($user, $isAdmin, $dayRange, $search);
        $readyToSubmitCount = (clone $readyToSubmitJobsQuery)->count();

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
            'ready_to_submit' => $readyToSubmitCount,
            'completed' => (clone $completedQuery)->count(),
        ];

        $allowedStatusFilters = [
            'needs_action',
            'ready_to_submit',
            JobOrderAnalysisStatus::Returned->value,
            JobOrderAnalysisStatus::InProgress->value,
            JobOrderAnalysisStatus::Assigned->value,
            JobOrderAnalysisStatus::Pending->value,
            JobOrderAnalysisStatus::Completed->value,
        ];

        $returnedValue = JobOrderAnalysisStatus::Returned->value;

        if ($showingReadyToSubmit) {
            $jobsQuery = $readyToSubmitJobsQuery;
        } else {
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

            $jobsQuery = JobOrder::query()
                ->whereIn('id', (clone $filteredQuery)->select('job_order_id'));
        }

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

        // Only lines this analyst is assigned to (Admin → Assignments), not teammate types.
        $tasksQuery = JobOrderAnalysis::query()
            ->with(['jobOrder.samples', 'analysisType', 'assignee:id,name'])
            ->whereIn('job_order_id', $pageJobIds);

        if (! $isAdmin) {
            if ($qualifiedTypeIds === []) {
                $tasksQuery->whereRaw('0 = 1');
            } else {
                $tasksQuery->whereIn('analysis_type_id', $qualifiedTypeIds);
            }
        }

        $tasks = $tasksQuery
            ->get()
            ->sortBy(function (JobOrderAnalysis $task) {
                $returned = $task->status === JobOrderAnalysisStatus::Returned ? 0 : 1;

                return sprintf(
                    '%d-%s-%s',
                    $returned,
                    $task->jobOrder->reference_no,
                    $task->name,
                );
            })
            ->values();

        $reportByTaskId = [];
        foreach ($tasks as $task) {
            $reportByTaskId[(int) $task->id] = $this->reports->forAnalysis($task, $user, withValues: false);
        }

        $editableSampleIds = [];
        foreach ($tasks as $line) {
            if ($line->sample_id === null || ! $this->userCanWorkLine($user, $line, $qualifiedTypeIds)) {
                continue;
            }
            $owned = $line->jobOrder->samples->first(
                fn ($row) => (int) $row->id === (int) $line->sample_id,
            );
            if ($owned && $owned->released_at === null) {
                $editableSampleIds[(int) $owned->id] = true;
            }
        }

        $tasks = $tasks->map(function (JobOrderAnalysis $task) use ($user, $isAdmin, $qualifiedTypeIds, $reportByTaskId, $editableSampleIds) {
            $canWork = $this->userCanWorkLine($user, $task, $qualifiedTypeIds);
            $isMine = $canWork;

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
                'analysis_type_code' => $task->analysisType?->code,
                'uses_raw_finish' => OfficialAnalysisCatalog::usesRawFinishResults(
                    $task->analysisType?->code,
                ),
                'uses_residual_chlorine' => OfficialAnalysisCatalog::usesResidualChlorineResults(
                    $task->analysisType?->code,
                ),
                'result_value' => $task->result_value,
                'result_raw' => $task->result_raw,
                'result_finish' => $task->result_finish,
                'result_chlorinated' => $task->result_chlorinated,
                'result_pass_fail' => $task->result_pass_fail,
                'result_measurement' => $task->result_measurement,
                'result_unit' => $task->result_unit,
                'result_remarks' => $task->result_remarks,
                'result_remarks_raw' => $task->result_remarks_raw,
                'result_remarks_finish' => $task->result_remarks_finish,
                'result_remarks_chlorinated' => $task->result_remarks_chlorinated,
                'result_method' => $task->result_method,
                'accreditation_footnote' => ($supportsFootnote = OfficialAnalysisCatalog::isWastewaterPhysicoPanelCode(
                    $task->analysisType?->code,
                ))
                    ? (OfficialAnalysisCatalog::resolveWastewaterAccreditationFootnote(
                        $task->accreditation_footnote,
                        $task->analysisType?->code,
                    ) ?? '')
                    : null,
                'supports_accreditation_footnote' => $supportsFootnote,
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
                'report' => $this->taskReportSummary(
                    ($resolvedReport = $reportByTaskId[(int) $task->id] ?? null) instanceof AnalysisResultReport
                        ? $resolvedReport->summary()
                        : null,
                    $task->jobOrder,
                ),
                'result_facts' => $resolvedReport instanceof AnalysisResultReport
                    ? ResultSheetFacts::forReport($resolvedReport)
                    : [],
                'can_edit_result_facts' => $sample !== null && isset($editableSampleIds[(int) $sample->id]),
                'job_order' => [
                    'id' => $task->jobOrder->id,
                    'reference_no' => $task->jobOrder->reference_no,
                    'customer_name' => $task->jobOrder->customer_name,
                    'company_name' => $task->jobOrder->company_name,
                    'classification' => $task->jobOrder->classification,
                    'sampling_site' => $task->jobOrder->sampling_site,
                    'sample_storage_temp' => $task->jobOrder->sample_storage_temp,
                    'field_data' => $task->jobOrder->field_data,
                    'status' => $task->jobOrder->status->value,
                    'received_at' => $task->jobOrder->received_at?->toIso8601String(),
                    'reviewed_at' => $task->jobOrder->reviewed_at?->toIso8601String(),
                    'review_notes' => $task->jobOrder->review_notes,
                    'samples' => $samples->map(function ($sample, int $index) use ($task, $sampleCount, $editableSampleIds) {
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
                            'submitted_for_review' => $sample->submitted_for_review_at !== null,
                            'released' => $sample->released_at !== null,
                            'can_edit_description' => isset($editableSampleIds[(int) $sample->id]),
                            'description_choices' => $this->sampleDescriptionChoices($sample->description),
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

    public function updateSampleDescription(Request $request, JobOrder $jobOrder, Sample $sample): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless((int) $sample->job_order_id === (int) $jobOrder->id, 404);

        $sample->loadMissing('analyses');
        abort_unless($this->userCanEditSampleDescription($user, $sample), 403);

        $choices = $this->sampleDescriptionChoices($sample->description);
        $data = $request->validate([
            'description' => $choices === null
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', Rule::in($choices)],
        ]);

        $description = trim((string) ($data['description'] ?? ''));
        $sample->update([
            'description' => $description !== '' ? $description : null,
        ]);

        return $this->redirectToAnalystReferer($request);
    }

    public function updateResultFacts(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'sample_id' => ['required', 'integer'],
            'form_id' => ['nullable', 'integer'],
            'wastewater_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sampling_site' => ['sometimes', 'nullable', 'string', 'max:255'],
            'specimen' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sample_collected_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sampling_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'sampling_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sample_point' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collected_time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ]);

        $sample = Sample::query()
            ->where('job_order_id', $jobOrder->id)
            ->whereKey($data['sample_id'])
            ->firstOrFail();
        abort_unless($this->userCanEditSampleDescription($user, $sample), 403);

        $reports = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: false);
        // Mixed FO2+FO4 samples list FO4 first; union keys from every sheet for this sample
        // so FO2-only facts (sampling_site) still save when the WW sheet plots them.
        $sampleReports = $reports->filter(
            fn (AnalysisResultReport $report) => $report->sampleId === null
                || (int) $report->sampleId === (int) $sample->id,
        );
        $allowed = $sampleReports
            ->flatMap(fn (AnalysisResultReport $report) => collect(ResultSheetFacts::forReport($report))->pluck('key'))
            ->unique()
            ->values();

        if ($jobOrder->is_receiving_bulk) {
            $parsed = BulkSampleRemarks::parse($sample->remarks);
            $touched = false;
            foreach (['location', 'sample_point', 'collected_time'] as $part) {
                if (! $allowed->contains($part) || ! array_key_exists($part, $data)) {
                    continue;
                }
                $parsed[$part] = trim((string) ($data[$part] ?? ''));
                $touched = true;
            }
            if ($touched) {
                $sample->update([
                    'remarks' => BulkSampleRemarks::compose($parsed),
                ]);
            }
        }

        $updates = [];
        foreach (['wastewater_source', 'sampling_site', 'specimen', 'sample_collected_by'] as $column) {
            if (! $allowed->contains($column) || ! array_key_exists($column, $data)) {
                continue;
            }
            $value = trim((string) ($data[$column] ?? ''));
            $updates[$column] = $value !== '' ? $value : null;
        }

        if ($allowed->contains('sampling_datetime') && (array_key_exists('sampling_date', $data) || array_key_exists('sampling_time', $data))) {
            $date = trim((string) ($data['sampling_date'] ?? ''));
            $time = trim((string) ($data['sampling_time'] ?? ''));
            $updates['sampling_date'] = $date !== '' ? $date : null;
            $updates['sampling_time'] = $date !== '' && $time !== '' ? $time : null;
        }

        if ($updates !== []) {
            $jobOrder->update($updates);
        }

        return $this->redirectToAnalystReferer($request);
    }

    public function saveDraft(Request $request, JobOrderAnalysis $analysis): RedirectResponse
    {
        $data = $request->validate([
            'performed_at' => ['nullable', 'date'],
            'result_value' => ['nullable', 'string', 'max:255'],
            'result_raw' => ['nullable', 'string', 'max:255'],
            'result_finish' => ['nullable', 'string', 'max:255'],
            'result_chlorinated' => ['nullable', 'string', 'max:255'],
            'result_pass_fail' => ['nullable', 'string', 'max:20'],
            'result_measurement' => ['nullable', 'string', 'max:80'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
            'result_remarks_raw' => ['nullable', 'string', 'max:1000'],
            'result_remarks_finish' => ['nullable', 'string', 'max:1000'],
            'result_remarks_chlorinated' => ['nullable', 'string', 'max:1000'],
            'result_method' => ['nullable', 'string', 'max:2000'],
            'accreditation_footnote' => ['nullable', 'string', 'max:8'],
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
        $rawFinish = OfficialAnalysisCatalog::usesRawFinishResults($analysis->analysisType?->code);
        $residualCl = OfficialAnalysisCatalog::usesResidualChlorineResults($analysis->analysisType?->code);
        $hasStoredPerformedAt = $analysis->performed_at !== null
            || $analysis->status === JobOrderAnalysisStatus::Returned;
        $performedAtRule = $isPrep || $hasStoredPerformedAt ? 'nullable' : 'required';

        $data = $request->validate([
            'performed_at' => [$performedAtRule, 'date'],
            'result_value' => [
                $isPrep || $rawFinish || $residualCl ? 'nullable' : 'required',
                'string',
                'max:255',
            ],
            'result_raw' => [
                $rawFinish && ! $isPrep
                    ? 'required_without:result_finish'
                    : ($residualCl && ! $isPrep
                        ? 'required_without_all:result_finish,result_chlorinated'
                        : 'nullable'),
                'string',
                'max:255',
            ],
            'result_finish' => [
                $rawFinish && ! $isPrep
                    ? 'required_without:result_raw'
                    : ($residualCl && ! $isPrep
                        ? 'required_without_all:result_raw,result_chlorinated'
                        : 'nullable'),
                'string',
                'max:255',
            ],
            'result_chlorinated' => [
                $residualCl && ! $isPrep
                    ? 'required_without_all:result_raw,result_finish'
                    : 'nullable',
                'string',
                'max:255',
            ],
            'result_pass_fail' => [$requiresPassFail ? 'required' : 'nullable', 'string', 'max:20'],
            'result_measurement' => ['nullable', 'string', 'max:80'],
            'result_unit' => ['nullable', 'string', 'max:50'],
            'result_remarks' => ['nullable', 'string', 'max:1000'],
            'result_remarks_raw' => ['nullable', 'string', 'max:1000'],
            'result_remarks_finish' => ['nullable', 'string', 'max:1000'],
            'result_remarks_chlorinated' => ['nullable', 'string', 'max:1000'],
            'result_method' => ['nullable', 'string', 'max:2000'],
            'accreditation_footnote' => ['nullable', 'string', 'max:8'],
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
            'form_id' => ['nullable', 'integer', 'exists:controlled_forms,id'],
        ]);

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;
        $formId = isset($data['form_id']) ? (int) $data['form_id'] : null;

        $completed = $this->jobOrders->completeSampleAnalyses(
            $jobOrder,
            $request->user(),
            $sampleId,
            $formId,
        );

        $count = count($completed);

        if ($formId !== null) {
            return back()->with(
                'success',
                $count === 1
                    ? 'Result sheet assay marked complete.'
                    : "{$count} assays on this result sheet marked complete.",
            );
        }

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
            'sample_id' => ['nullable', 'integer', 'exists:samples,id'],
            'form_id' => ['nullable', 'integer', 'exists:controlled_forms,id'],
            'signatories' => ['nullable', 'array', 'min:1', 'max:4'],
            'signatories.*.name' => ['nullable', 'string', 'max:180'],
            'signatories.*.prc_id' => ['nullable', 'string', 'max:60'],
        ]);

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;
        $formId = isset($data['form_id']) ? (int) $data['form_id'] : null;

        $this->jobOrders->submitForReview(
            $jobOrder,
            $request->user(),
            $data['signatories'] ?? null,
            $sampleId,
            $formId,
        );

        if ($formId !== null) {
            return back()->with(
                'success',
                "Result sheet on {$jobOrder->reference_no} sent to Head for signature.",
            );
        }

        if ($sampleId !== null) {
            $sample = $jobOrder->samples()->find($sampleId);

            return back()->with(
                'success',
                "Sample ".($sample?->sample_code ?: '#'.$sampleId)." on {$jobOrder->reference_no} sent to Head for signature.",
            );
        }

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

        $jobOrder->loadMissing(['analyses', 'samples', 'packages']);

        $data = $request->validate([
            'signatories' => ['required', 'array', 'min:1', 'max:4'],
            'signatories.*.name' => ['nullable', 'string', 'max:180'],
            'signatories.*.prc_id' => ['nullable', 'string', 'max:60'],
            'sample_id' => ['nullable', 'integer'],
            'form_id' => ['nullable', 'integer'],
        ]);

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;
        $formId = isset($data['form_id']) ? (int) $data['form_id'] : null;

        $allReports = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: false);
        // Mixed WW+micro JOs list FO4 first; forJobOrder would 403 the WW analyst.
        // Prefer the sheet being edited, else any sheet this user can access.
        if ($formId || $sampleId) {
            $resolved = $this->reports->pickReport($allReports, $sampleId, $formId);
            $canAccessPicked = $user->hasRole('admin')
                || $this->reports->userCanAccessCombined($resolved->analyses, $user, $jobOrder);
            if (! $canAccessPicked) {
                $resolved = $this->accessibleSignatoryReport($allReports, $user, $jobOrder)
                    ?? $resolved;
            }
        } else {
            $resolved = $this->accessibleSignatoryReport($allReports, $user, $jobOrder)
                ?? $this->reports->forJobOrder($jobOrder, $user);
        }

        abort_unless(
            $this->reports->userCanAccessCombined($resolved->analyses, $user, $jobOrder)
            || $user->hasRole('admin'),
            403,
        );
        // Allow confirming analyst/PRC during preview; Print/Download stay gated by Head release.
        abort_unless($resolved->canPreview() || $user->hasRole('admin'), 403);
        abort_unless($resolved->controlledForm !== null, 422, 'No combined result form is bound to this job.');

        $normalized = ResultSignatories::normalizeForStorage(
            $data['signatories'],
            $resolved->controlledForm,
            $jobOrder,
        );

        $merged = ResultSignatories::mergeIntoStorage(
            is_array($jobOrder->result_signatories) ? $jobOrder->result_signatories : null,
            $resolved->controlledForm,
            $normalized,
        );

        $jobOrder->update(['result_signatories' => $merged]);
        $jobOrder->refresh();

        return response()->json([
            'ok' => true,
            'signatories' => $normalized,
            'signatory' => ResultSignatories::manifestPayload(
                $jobOrder,
                $resolved->controlledForm,
                "/analyst/job-orders/{$jobOrder->id}/result-signatories",
                $resolved->analyses,
                $resolved->sampleId,
            ),
        ]);
    }

    public function updateResultFooter(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $jobOrder->loadMissing(['analyses', 'samples', 'packages']);

        $data = $request->validate([
            'temperature_c' => ['nullable', 'string', 'max:40'],
            'humidity_pct' => ['nullable', 'string', 'max:40'],
            'test_report_no' => ['nullable', 'string', 'max:80'],
            'lab_conditions' => ['nullable', 'string', 'max:2000'],
            'references' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:8000'],
            'reset' => ['sometimes', 'boolean'],
            'sample_id' => ['nullable', 'integer'],
            'form_id' => ['nullable', 'integer'],
        ]);

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;
        $formId = isset($data['form_id']) ? (int) $data['form_id'] : null;

        $allReports = $this->reports->reportsForJobOrder($jobOrder, $user, withValues: false);
        // Mixed WW+micro JOs list FO4 first; forJobOrder would 403 the WW analyst.
        // Prefer the sheet being edited, else any footer-capable sheet this user can access.
        if ($formId || $sampleId) {
            $resolved = $this->reports->pickReport($allReports, $sampleId, $formId);
        } else {
            $resolved = $allReports->first(
                fn (AnalysisResultReport $report) => ResultFooter::formSupportsFooter($report->controlledForm)
                    && (
                        $user->hasRole('admin')
                        || $this->reports->userCanAccessCombined($report->analyses, $user, $jobOrder)
                    ),
            ) ?? $this->reports->forJobOrder($jobOrder, $user);
        }

        abort_unless(
            $this->reports->userCanAccessCombined($resolved->analyses, $user, $jobOrder)
            || $user->hasRole('admin'),
            403,
        );

        $form = $resolved->controlledForm
            ?? $this->reports->matchingControlledForm($jobOrder);
        abort_unless(
            ResultFooter::formSupportsFooter($form),
            422,
            'Lab conditions apply only to FO2 / FO2A / FO3 / FO37 physico result sheets.',
        );

        if (($data['reset'] ?? false) === true) {
            $jobOrder->update(['result_footer' => null]);
        } else {
            if (ResultFooter::formRequiresLabAtmosphere($form)) {
                $temp = trim((string) ($data['temperature_c'] ?? ''));
                $humidity = trim((string) ($data['humidity_pct'] ?? ''));
                $testReportNo = trim((string) ($data['test_report_no'] ?? ''));
                $atmosphereErrors = [];
                if ($temp === '') {
                    $atmosphereErrors['temperature_c'] = 'Enter laboratory temperature (°C).';
                }
                if ($humidity === '') {
                    $atmosphereErrors['humidity_pct'] = 'Enter laboratory humidity (%).';
                }
                if ($testReportNo === '') {
                    $atmosphereErrors['test_report_no'] = 'Enter the Test Report #.';
                }
                if ($atmosphereErrors !== []) {
                    throw ValidationException::withMessages($atmosphereErrors);
                }
            }

            $jobOrder->update([
                'result_footer' => ResultFooter::normalizeForStorage($data, $form),
            ]);
        }

        $jobOrder->refresh();

        return response()->json([
            'ok' => true,
            'result_footer' => $jobOrder->result_footer,
            'footer' => ResultFooter::manifestPayload(
                $jobOrder,
                $form,
                "/analyst/job-orders/{$jobOrder->id}/result-footer",
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
    /**
     * Job orders still in analysis with every line completed that the user may send to Head.
     *
     * @param  array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}|null  $dayRange
     * @return \Illuminate\Database\Eloquent\Builder<JobOrder>
     */
    private function readyToSubmitJobsQuery(
        User $user,
        bool $isAdmin,
        ?array $dayRange,
        string $search,
    ) {
        $completed = JobOrderAnalysisStatus::Completed;

        // Whole JO ready, or at least one unsubmitted sample whose assays are all complete.
        $query = JobOrder::query()
            ->where('status', JobOrderStatus::InAnalysis)
            ->whereHas('analyses')
            ->where(function ($outer) use ($completed) {
                $outer->whereDoesntHave('analyses', function ($analyses) use ($completed) {
                    $analyses->where('status', '!=', $completed);
                })->orWhereHas('samples', function ($samples) use ($completed) {
                    $samples->whereNull('submitted_for_review_at')
                        ->whereHas('analyses')
                        ->whereDoesntHave('analyses', function ($analyses) use ($completed) {
                            $analyses->where('status', '!=', $completed);
                        });
                });
            });

        if (! $isAdmin) {
            $qualifiedTypeIds = $user->analysisTypes()
                ->pluck('analysis_types.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $query->where(function ($outer) use ($user, $qualifiedTypeIds) {
                $outer->where(function ($packageBound) use ($user) {
                    $packageBound->whereHas('packages')
                        ->whereHas('packages', function ($packages) use ($user) {
                            $packages->where('analysis_packages.signatory_user_id', $user->id);
                        });
                })->orWhere(function ($typesOnly) use ($user, $qualifiedTypeIds) {
                    $typesOnly->whereDoesntHave('packages')
                        ->whereHas('analyses', function ($analyses) use ($user, $qualifiedTypeIds) {
                            $analyses->where(function ($inner) use ($user, $qualifiedTypeIds) {
                                $inner->where('assigned_to', $user->id);
                                if ($qualifiedTypeIds !== []) {
                                    $inner->orWhereIn('analysis_type_id', $qualifiedTypeIds);
                                }
                            });
                        });
                });
            });
        }

        if ($dayRange !== null) {
            $query->whereBetween('received_at', $dayRange);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('classification', 'like', "%{$search}%")
                    ->orWhereHas('samples', function ($samples) use ($search) {
                        $samples->where('sample_code', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    })
                    ->orWhereHas('analyses', function ($analyses) use ($search) {
                        $analyses->where('name', 'like', "%{$search}%")
                            ->orWhere('category_label', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

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

        if ($user->hasRole('admin') || $this->userCanWorkLine($user, $analysis)) {
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

        $qualifiedTypeIds = $user->analysisTypes()
            ->pluck('analysis_types.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(
            $jobOrder->analyses->contains(
                fn (JobOrderAnalysis $line) => $this->userCanWorkLine($user, $line, $qualifiedTypeIds),
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
     * Prefer a previewable sheet the user can access (mixed FO2+FO4 JOs).
     *
     * @param  \Illuminate\Support\Collection<int, AnalysisResultReport>  $reports
     */
    private function accessibleSignatoryReport(
        \Illuminate\Support\Collection $reports,
        User $user,
        JobOrder $jobOrder,
    ): ?AnalysisResultReport {
        $accessible = $reports->filter(function (AnalysisResultReport $report) use ($user, $jobOrder) {
            if ($report->controlledForm === null) {
                return false;
            }

            return $user->hasRole('admin')
                || $this->reports->userCanAccessCombined($report->analyses, $user, $jobOrder);
        });

        return $accessible->first(fn (AnalysisResultReport $report) => $report->canPreview())
            ?? $accessible->first();
    }

    /**
     * @return array{signatory?: array<string, mixed>, footer?: array<string, mixed>}
     */
    private function signatoryManifestExtra(JobOrder $jobOrder, AnalysisResultReport $resolved, ?string $saveUrl): array
    {
        if ($resolved->controlledForm === null || ! $resolved->canPreview()) {
            return [];
        }

        $extra = [
            'signatory' => ResultSignatories::manifestPayload(
                $jobOrder,
                $resolved->controlledForm,
                $saveUrl,
                $resolved->analyses,
                $resolved->sampleId,
            ),
        ];

        if (ResultFooter::formSupportsFooter($resolved->controlledForm)) {
            $extra['footer'] = ResultFooter::manifestPayload(
                $jobOrder,
                $resolved->controlledForm,
                $saveUrl
                    ? "/analyst/job-orders/{$jobOrder->id}/result-footer"
                    : null,
            );
        }

        return $extra;
    }

    /**
     * Same rule as the queue `can_work` flag: Admin → Assignments for the analysis type.
     *
     * @param  list<int>|null  $qualifiedTypeIds
     */
    private function userCanWorkLine(User $user, JobOrderAnalysis $line, ?array $qualifiedTypeIds = null): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $line->analysis_type_id) {
            return false;
        }

        if ($qualifiedTypeIds !== null) {
            return in_array((int) $line->analysis_type_id, $qualifiedTypeIds, true);
        }

        return $user->analysisTypes()->whereKey($line->analysis_type_id)->exists();
    }

    /**
     * Stay on the open workbench. ?job= is client state, so a plain back()
     * returns to the queue and closes the sample the analyst was editing.
     */
    private function redirectToAnalystReferer(Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        $parts = is_string($referer) ? parse_url($referer) : false;
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        if (
            is_array($parts)
            && ($parts['host'] ?? '') === $request->getHost()
            && ($path === '/analyst' || str_starts_with($path, '/analyst/'))
        ) {
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return redirect($path.$query);
        }

        return back();
    }

    private function userCanEditSampleDescription(User $user, Sample $sample): bool
    {
        if ($sample->released_at !== null) {
            return false;
        }

        $sample->loadMissing('analyses');

        return $sample->analyses->contains(
            fn (JobOrderAnalysis $line): bool => $this->userCanWorkLine($user, $line),
        );
    }

    /**
     * Wastewater kinds stay a fixed choice so FO2 vs FO2A still follows the description.
     *
     * @return list<string>|null
     */
    private function sampleDescriptionChoices(?string $description): ?array
    {
        $current = trim((string) $description);
        $kinds = OfficialAnalysisCatalog::wastewaterKinds();

        return in_array($current, $kinds, true) ? $kinds : null;
    }
}
