<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Services\JobOrderService;
use App\Support\JobOrderFormPresenter;
use App\Support\RfaPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReceivingController extends Controller
{
    public function __construct(private readonly JobOrderService $jobOrders) {}

    public function index(Request $request): Response
    {
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        $intakeQuery = JobOrder::query()->whereIn('status', [
            JobOrderStatus::DraftSubmitted,
            JobOrderStatus::Priced,
        ]);
        $reviewedQuery = JobOrder::query()
            ->whereNotNull('reviewed_at')
            ->where('status', JobOrderStatus::ReadyForPickup);

        $showingReviewed = $statusFilter === 'reviewed';
        $baseQuery = $showingReviewed ? $reviewedQuery : $intakeQuery;

        $counts = [
            'all' => (clone $intakeQuery)->count(),
            'draft_submitted' => (clone $intakeQuery)
                ->where('status', JobOrderStatus::DraftSubmitted)
                ->count(),
            'priced' => (clone $intakeQuery)
                ->where('status', JobOrderStatus::Priced)
                ->count(),
            'reviewed' => (clone $reviewedQuery)->count(),
        ];

        $orders = (clone $baseQuery)
            ->withCount(['analyses', 'samples'])
            ->when(
                ! $showingReviewed && in_array($statusFilter, [
                    JobOrderStatus::DraftSubmitted->value,
                    JobOrderStatus::Priced->value,
                ], true),
                fn ($query) => $query->where('status', $statusFilter),
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->when($showingReviewed, fn ($query) => $query->latest('reviewed_at'))
            ->when(! $showingReviewed, fn ($query) => $query->latest())
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JobOrder $order) => [
                'id' => $order->id,
                'reference_no' => $order->reference_no,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_contact' => $order->customer_contact,
                'company_name' => $order->company_name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'reviewed' => $order->reviewed_at !== null,
                'total_cost' => $order->total_cost,
                'analyses_count' => $order->analyses_count,
                'samples_count' => $order->samples_count,
                'created_at' => $order->created_at?->toDayDateTimeString(),
            ]);

        return Inertia::render('receiving/index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function show(JobOrder $jobOrder): Response
    {
        $jobOrder->load(['samples', 'analyses.analysisType', 'analyses.assignee']);

        return Inertia::render('receiving/show', [
            'jobOrder' => $this->transformJobOrder($jobOrder),
        ]);
    }

    public function updatePricing(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'exists:job_order_analyses,id'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->jobOrders->updatePricing($jobOrder, $data['lines']);

        return back()->with('success', 'Pricing updated.');
    }

    public function receive(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->jobOrders->receive($jobOrder, $request->user());

        return redirect()->route('receiving.index')->with('success', "Job order {$jobOrder->reference_no} received and assigned.");
    }

    public function print(Request $request, JobOrder $jobOrder): Response
    {
        $this->assertCanPrintRfa($jobOrder);

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);
        $copies = max(1, min(20, $request->integer('copies', 3)));

        return Inertia::render('rfa/print', [
            'jobOrder' => JobOrderFormPresenter::toArray($jobOrder, withResults: false),
            'copies' => $copies,
            'showResults' => false,
        ]);
    }

    public function pdf(JobOrder $jobOrder): HttpResponse
    {
        $this->assertCanPrintRfa($jobOrder);

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        return RfaPdfExporter::download($jobOrder, showResults: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformJobOrder(JobOrder $jobOrder, bool $withResults = true): array
    {
        return JobOrderFormPresenter::toArray($jobOrder, $withResults);
    }

    private function assertCanPrintRfa(JobOrder $jobOrder): void
    {
        abort_unless($jobOrder->reviewed_at !== null, 403);
    }
}
