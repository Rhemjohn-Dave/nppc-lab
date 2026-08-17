<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Services\JobOrderService;
use App\Support\JobOrderFormPresenter;
use App\Support\OfficialAnalysisCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(private readonly JobOrderService $jobOrders) {}

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() === 'signed' ? 'signed' : 'unsigned';
        $search = trim($request->string('q')->toString());

        $unsignedQuery = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup)
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
        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        return Inertia::render('rfa/print', [
            'jobOrder' => JobOrderFormPresenter::toArray($jobOrder, withResults: true),
            'copies' => 1,
            'showResults' => true,
        ]);
    }

    public function pdf(JobOrder $jobOrder): HttpResponse
    {
        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        $pdf = Pdf::loadView('pdf.request-for-analysis', [
            'jobOrder' => $jobOrder,
            'catalog' => JobOrderFormPresenter::catalog(),
            'documentControl' => OfficialAnalysisCatalog::documentControl(),
            'showResults' => true,
        ])->setPaper('folio');

        return $pdf->download("RFA-Results-{$jobOrder->reference_no}.pdf");
    }
}
