<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Support\JobOrderFormPresenter;
use App\Support\RfaPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $statusFilter = $request->string('status')->toString();

        $baseQuery = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup);

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'unsigned' => (clone $baseQuery)->whereNull('reviewed_at')->count(),
            'signed' => (clone $baseQuery)->whereNotNull('reviewed_at')->count(),
        ];

        $orders = (clone $baseQuery)
            ->with(['reviewer'])
            ->withCount(['analyses', 'samples'])
            ->when($statusFilter === 'unsigned', fn ($query) => $query->whereNull('reviewed_at'))
            ->when($statusFilter === 'signed', fn ($query) => $query->whereNotNull('reviewed_at'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%");
                });
            })
            ->latest('updated_at')
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

        return Inertia::render('history/index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
            ],
            'canSign' => $request->user()?->hasRole(['admin', 'head_analysis']) ?? false,
        ]);
    }

    public function show(JobOrder $jobOrder): Response
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        return Inertia::render('history/show', [
            'jobOrder' => array_merge(
                JobOrderFormPresenter::toArray($jobOrder, withResults: true),
                [
                    'is_signed' => $jobOrder->reviewed_at !== null,
                ],
            ),
            'canSign' => request()->user()?->hasRole(['admin', 'head_analysis']) ?? false,
        ]);
    }

    public function print(JobOrder $jobOrder): Response
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        return Inertia::render('rfa/print', [
            'jobOrder' => JobOrderFormPresenter::toArray($jobOrder, withResults: true),
            'copies' => 1,
            'showResults' => true,
        ]);
    }

    public function pdf(JobOrder $jobOrder): HttpResponse
    {
        abort_unless(
            $jobOrder->status === JobOrderStatus::ReadyForPickup,
            404,
        );

        $jobOrder->load(['samples', 'analyses', 'receiver', 'reviewer']);

        return RfaPdfExporter::download($jobOrder, showResults: true);
    }
}
