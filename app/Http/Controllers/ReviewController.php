<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Services\AnalysisResultReportResolver;
use App\Services\JobOrderService;
use App\Support\AnalysisResultPdfExporter;
use App\Support\AnalysisResultReport;
use App\Support\JobOrderFormPresenter;
use App\Support\ResultSignatories;
use App\Support\RfaPdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReviewController extends Controller
{
    public function __construct(
        private readonly JobOrderService $jobOrders,
        private readonly AnalysisResultReportResolver $reports,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('head.jo');
    }

    public function joIndex(Request $request): Response
    {
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['pending', 'approved'], true)) {
            $tab = 'pending';
        }
        $search = trim($request->string('q')->toString());

        $pendingQuery = JobOrder::query()
            ->whereIn('status', [
                JobOrderStatus::PendingJoApproval,
                JobOrderStatus::Priced,
            ])
            ->whereNull('jo_approved_at');

        $approvedQuery = JobOrder::query()
            ->whereNotNull('jo_approved_at');

        $counts = [
            'pending' => (clone $pendingQuery)->count(),
            'approved' => (clone $approvedQuery)->count(),
        ];

        $listQuery = $tab === 'approved'
            ? (clone $approvedQuery)->latest('jo_approved_at')
            : (clone $pendingQuery)->latest('updated_at');

        $orders = $this->paginateHeadOrders($listQuery, $search, includeResultPreview: false);

        return Inertia::render('head/jo', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'q' => $search,
            ],
        ]);
    }

    public function resultsIndex(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['unsigned', 'signed'], true)) {
            $tab = 'unsigned';
        }
        $search = trim($request->string('q')->toString());

        $unsignedQuery = JobOrder::query()
            ->where('status', JobOrderStatus::PendingReview)
            ->whereNull('reviewed_at');

        $signedTodayQuery = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup)
            ->whereNotNull('reviewed_at')
            ->whereDate('reviewed_at', today());

        $counts = [
            'unsigned' => (clone $unsignedQuery)->count(),
            'signed_today' => (clone $signedTodayQuery)->count(),
        ];

        $listQuery = $tab === 'signed'
            ? (clone $signedTodayQuery)->latest('reviewed_at')
            : (clone $unsignedQuery)->latest('updated_at');

        $orders = $this->paginateHeadOrders(
            $listQuery,
            $search,
            includeResultPreview: true,
            previewUser: $user,
        );

        return Inertia::render('head/results', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'q' => $search,
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\JobOrder>  $listQuery
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateHeadOrders(
        $listQuery,
        string $search,
        bool $includeResultPreview = false,
        ?\App\Models\User $previewUser = null,
    ) {
        return $listQuery
            ->with(['reviewer', 'joApprover'])
            ->withCount(['analyses', 'samples'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->paginate(20)
            ->withQueryString()
            ->through(function (JobOrder $order) use ($includeResultPreview, $previewUser) {
                $row = [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_contact' => $order->customer_contact,
                    'company_name' => $order->company_name,
                    'classification' => $order->classification,
                    'total_cost' => $order->total_cost,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'analyses_count' => $order->analyses_count,
                    'samples_count' => $order->samples_count,
                    'completed_at' => $order->updated_at?->toDayDateTimeString(),
                    'jo_approved_at' => $order->jo_approved_at?->toDayDateTimeString(),
                    'jo_approver_name' => $order->joApprover?->name,
                    'signed_at' => $order->reviewed_at?->toDayDateTimeString(),
                    'signed_by' => $order->reviewer?->name,
                    'is_signed' => $order->reviewed_at !== null,
                    'needs_jo_approval' => $order->jo_approved_at === null
                        && in_array($order->status, [
                            JobOrderStatus::PendingJoApproval,
                            JobOrderStatus::Priced,
                        ], true),
                    'jo_pdf_url' => "/head/{$order->id}/pdf?download=1&results=0",
                    'can_preview_result' => false,
                    'result_pdf_url' => null,
                ];

                if ($includeResultPreview && $previewUser) {
                    $resolved = $this->reports->forJobOrder($order, $previewUser, withValues: false);
                    $canPreview = $resolved->canPreview()
                        && $resolved->kind === AnalysisResultReport::KIND_COMBINED
                        && $resolved->isOverlay();
                    $row['can_preview_result'] = $canPreview;
                    $row['result_pdf_url'] = $canPreview
                        ? "/head/{$order->id}/combined-pdf"
                        : null;
                }

                return $row;
            });
    }

    public function show(JobOrder $jobOrder): Response
    {
        $needsJoApproval = $jobOrder->jo_approved_at === null
            && in_array($jobOrder->status, [
                JobOrderStatus::PendingJoApproval,
                JobOrderStatus::Priced,
            ], true);

        return Inertia::render('head/show', [
            'jobOrder' => array_merge(
                JobOrderFormPresenter::toArray($jobOrder, withResults: true),
                [
                    'is_signed' => $jobOrder->reviewed_at !== null,
                    'needs_jo_approval' => $needsJoApproval,
                    'jo_approved_at' => $jobOrder->jo_approved_at?->format('m/d/Y h:i A'),
                    'jo_approver_name' => $jobOrder->joApprover?->name,
                ],
            ),
        ]);
    }

    public function approveJobOrder(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->jobOrders->approveJobOrder($jobOrder, $request->user());

        return redirect()->route('head.jo', ['tab' => 'pending'])
            ->with('success', "Job order {$jobOrder->reference_no} approved. Receiving can print 3 JO copies and send to analysts.");
    }

    public function approveJobOrderBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'job_order_ids' => ['required', 'array', 'min:1'],
            'job_order_ids.*' => ['integer', 'exists:job_orders,id'],
        ]);

        $count = $this->jobOrders->approveJobOrders(
            $data['job_order_ids'],
            $request->user(),
        );

        return redirect()->route('head.jo', ['tab' => 'pending'])
            ->with('success', "{$count} job order(s) approved.");
    }

    public function sign(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->jobOrders->sign($jobOrder, $request->user(), $data['review_notes'] ?? null);

        return redirect()->route('head.results', ['tab' => 'unsigned'])->with('success', "Job order {$jobOrder->reference_no} results released.");
    }

    public function signBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'job_order_ids' => ['required', 'array', 'min:1'],
            'job_order_ids.*' => ['integer', 'exists:job_orders,id'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $count = $this->jobOrders->signMany(
            $data['job_order_ids'],
            $request->user(),
            $data['review_notes'] ?? null,
        );

        return redirect()->route('head.results', ['tab' => 'unsigned'])->with('success', "{$count} job order(s) results released.");
    }

    public function returnAnalyses(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'analysis_ids' => ['required', 'array', 'min:1'],
            'analysis_ids.*' => ['integer', 'exists:job_order_analyses,id'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->jobOrders->returnAnalyses(
            $jobOrder,
            $data['analysis_ids'],
            $request->user(),
            $data['review_notes'] ?? null,
        );

        return redirect()->route('head.results')->with('success', 'Selected analyses returned to analysts.');
    }

    public function print(JobOrder $jobOrder): Response
    {
        abort(403, 'Receiving prints the Job Order / RFA. Head can preview the controlled form on the review screen.');
    }

    public function pdf(Request $request, JobOrder $jobOrder): HttpResponse
    {
        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer', 'joApprover']);

        $showResults = $request->has('results')
            ? $request->boolean('results')
            : in_array($jobOrder->status, [
                JobOrderStatus::PendingReview,
                JobOrderStatus::ReadyForPickup,
                JobOrderStatus::InAnalysis,
            ], true);

        $download = $request->boolean('download') || ! $request->boolean('inline', true);

        return RfaPdfExporter::download(
            $jobOrder,
            showResults: $showResults,
            inline: ! $download,
        );
    }

    public function resultReport(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forJobOrder($jobOrder, $user, withValues: false);
        if (
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->canPreview()
            && ! $resolved->isOverlay()
        ) {
            $resolved = $this->reports->forJobOrder($jobOrder, $user, withValues: true);
        }
        $isOverlayCombined = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay();

        $templateUrl = $resolved->canPreview()
            && $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && ! $isOverlayCombined
            && $resolved->controlledRevision
            ? "/head/{$jobOrder->id}/controlled-revisions/{$resolved->controlledRevision->id}"
            : '';
        $pdfUrl = $isOverlayCombined && $resolved->canPreview()
            ? "/head/{$jobOrder->id}/combined-pdf"
            : null;

        return response()->json($resolved->manifest(
            $templateUrl,
            $pdfUrl,
            $resolved->fillMode(),
            $this->signatoryManifestExtra(
                $jobOrder,
                $resolved,
                null,
            ),
        ));
    }

    public function combinedPdf(Request $request, JobOrder $jobOrder): HttpResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forJobOrder($jobOrder, $user);
        abort_unless($resolved->canPreview(), 403);
        abort_unless(
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay(),
            403,
        );

        if ($request->boolean('print')) {
            abort_unless($resolved->canPrint() || $user->hasRole('admin'), 403);
        }

        abort_unless($resolved->controlledRevision instanceof ControlledFormRevision, 403);
        $binary = $this->reports->renderOverlayPdf($resolved);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resolved->filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function resultControlledRevision(Request $request, JobOrder $jobOrder, ControlledFormRevision $revision): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forJobOrder($jobOrder, $user);
        abort_unless($resolved->canPreview(), 403);
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

    public function resultPdf(Request $request, JobOrder $jobOrder, JobOrderAnalysis $analysis): HttpResponse
    {
        abort_unless($analysis->job_order_id === $jobOrder->id, 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forAnalysis($analysis, $user);
        abort_unless($resolved->kind === AnalysisResultReport::KIND_INDIVIDUAL, 404);

        if (! $request->boolean('inline')) {
            abort_unless($resolved->canPrint() || $user->hasRole('admin'), 403);
        }

        return AnalysisResultPdfExporter::download($analysis, $request->boolean('inline'));
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
