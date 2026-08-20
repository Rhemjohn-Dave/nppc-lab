<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Services\AnalysisResultReportResolver;
use App\Services\ControlledPdfFiller;
use App\Services\JobOrderService;
use App\Support\AnalysisResultPdfExporter;
use App\Support\AnalysisResultReport;
use App\Support\JobOrderFormPresenter;
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

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() === 'signed' ? 'signed' : 'unsigned';
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

        $orders = $listQuery
            ->with(['reviewer'])
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
            ->through(fn (JobOrder $order) => [
                'id' => $order->id,
                'reference_no' => $order->reference_no,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_contact' => $order->customer_contact,
                'company_name' => $order->company_name,
                'classification' => $order->classification,
                'total_cost' => $order->total_cost,
                'analyses_count' => $order->analyses_count,
                'samples_count' => $order->samples_count,
                'completed_at' => $order->updated_at?->toDayDateTimeString(),
                'signed_at' => $order->reviewed_at?->toDayDateTimeString(),
                'signed_by' => $order->reviewer?->name,
                'is_signed' => $order->reviewed_at !== null,
            ]);

        return Inertia::render('head/index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'q' => $search,
            ],
        ]);
    }

    public function show(JobOrder $jobOrder): Response
    {
        return Inertia::render('head/show', [
            'jobOrder' => array_merge(
                JobOrderFormPresenter::toArray($jobOrder, withResults: true),
                [
                    'is_signed' => $jobOrder->reviewed_at !== null,
                ],
            ),
        ]);
    }

    public function sign(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->jobOrders->sign($jobOrder, $request->user(), $data['review_notes'] ?? null);

        return redirect()->route('head.index')->with('success', "Job order {$jobOrder->reference_no} signed.");
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

        return redirect()->route('head.index')->with('success', "{$count} job order(s) signed.");
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

        return redirect()->route('head.index')->with('success', 'Selected analyses returned to analysts.');
    }

    public function print(JobOrder $jobOrder): Response
    {
        abort(403, 'Receiving prints the reviewed RFA after Head releases the results.');
    }

    public function pdf(JobOrder $jobOrder): HttpResponse
    {
        abort(403, 'Receiving prints the reviewed RFA after Head releases the results.');
    }

    public function resultReport(Request $request, JobOrder $jobOrder): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $released = $resolved->canPrint();
        $isOverlayCombined = $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay();

        $templateUrl = $released
            && $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && ! $isOverlayCombined
            && $resolved->controlledRevision
            ? "/head/{$jobOrder->id}/controlled-revisions/{$resolved->controlledRevision->id}"
            : '';
        $pdfUrl = $released && $isOverlayCombined
            ? "/head/{$jobOrder->id}/combined-pdf"
            : null;

        $payload = $resolved->manifest(
            $templateUrl,
            $pdfUrl,
            $resolved->fillMode(),
        );

        if (! $released) {
            $payload['can_preview'] = false;
            $payload['can_print'] = false;
            $payload['message'] = $payload['message']
                ?: 'The dated result form is available after you release the results.';
        }

        return response()->json($payload);
    }

    public function combinedPdf(Request $request, JobOrder $jobOrder): HttpResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forJobOrder($jobOrder, $user);
        abort_unless($resolved->canPrint(), 403);
        abort_unless(
            $resolved->kind === AnalysisResultReport::KIND_COMBINED
            && $resolved->isOverlay(),
            403,
        );

        abort_unless($resolved->controlledRevision instanceof ControlledFormRevision, 403);
        $binary = app(ControlledPdfFiller::class)->fill(
            $resolved->controlledRevision->load('fields'),
            $resolved->values,
        );

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resolved->filename.'"',
        ]);
    }

    public function resultControlledRevision(Request $request, JobOrder $jobOrder, ControlledFormRevision $revision): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $resolved = $this->reports->forJobOrder($jobOrder, $user);
        abort_unless($resolved->canPrint(), 403);
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
        abort_unless($resolved->canPrint(), 403);

        return AnalysisResultPdfExporter::download($analysis, $request->boolean('inline'));
    }
}
