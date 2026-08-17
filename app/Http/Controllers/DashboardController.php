<?php

namespace App\Http\Controllers;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $statusCounts = JobOrder::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $chartData = collect(JobOrderStatus::cases())->map(fn (JobOrderStatus $status) => [
            'status' => $status->label(),
            'count' => (int) ($statusCounts[$status->value] ?? 0),
        ]);

        $recent = JobOrder::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (JobOrder $order) => [
                'id' => $order->id,
                'reference_no' => $order->reference_no,
                'customer_name' => $order->customer_name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total_cost' => $order->total_cost,
                'created_at' => $order->created_at?->diffForHumans(),
            ]);

        return Inertia::render('dashboard', [
            'summary' => [
                'draft_submitted' => (int) ($statusCounts[JobOrderStatus::DraftSubmitted->value] ?? 0),
                'in_analysis' => (int) ($statusCounts[JobOrderStatus::InAnalysis->value] ?? 0),
                'awaiting_signature' => JobOrder::query()
                    ->where('status', JobOrderStatus::ReadyForPickup)
                    ->whereNull('reviewed_at')
                    ->count(),
                'ready_for_pickup' => (int) ($statusCounts[JobOrderStatus::ReadyForPickup->value] ?? 0),
                'total' => JobOrder::count(),
            ],
            'chartData' => $chartData,
            'recentOrders' => $recent,
            'roles' => $request->user()?->getRoleNames()->values() ?? [],
        ]);
    }
}
