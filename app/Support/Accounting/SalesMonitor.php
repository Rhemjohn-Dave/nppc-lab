<?php

namespace App\Support\Accounting;

use App\Enums\PaymentMode;
use App\Enums\PaymentTerms;
use App\Models\JobOrder;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesMonitor
{
    /**
     * @return array{
     *     filters: array{date: string, payment_mode: string},
     *     summary: array{job_count: int, gross: float, discount: float, net: float, avg_ticket: float},
     *     by_payment_mode: list<array{id: string, label: string, count: int, net: float}>,
     *     trend: list<array{date: string, label: string, job_count: int, net: float}>,
     *     orders: LengthAwarePaginator
     * }
     */
    public function build(?string $date, ?string $paymentMode): array
    {
        $day = $this->resolveDate($date);
        $mode = $this->resolvePaymentMode($paymentMode);

        $dayQuery = JobOrder::query()
            ->whereNotNull('jo_approved_at')
            ->whereDate('jo_approved_at', $day->toDateString())
            ->when($mode === 'unset', fn ($q) => $q->whereNull('payment_mode'))
            ->when(
                $mode !== null && $mode !== 'unset',
                fn ($q) => $q->where('payment_mode', $mode),
            );

        $aggregates = (clone $dayQuery)
            ->selectRaw('COUNT(*) as job_count')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as net')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount')
            ->first();

        $jobCount = (int) ($aggregates->job_count ?? 0);
        $net = round((float) ($aggregates->net ?? 0), 2);
        $discount = round((float) ($aggregates->discount ?? 0), 2);
        $gross = round($net + $discount, 2);
        $avgTicket = $jobCount > 0 ? round($net / $jobCount, 2) : 0.0;

        return [
            'filters' => [
                'date' => $day->toDateString(),
                'payment_mode' => $mode ?? 'all',
            ],
            'summary' => [
                'job_count' => $jobCount,
                'gross' => $gross,
                'discount' => $discount,
                'net' => $net,
                'avg_ticket' => $avgTicket,
            ],
            'by_payment_mode' => $this->paymentModeBreakdown($day),
            'trend' => $this->trendEndingOn($day, 14),
            'orders' => $this->paginateOrders($day, $mode),
        ];
    }

    private function resolveDate(?string $date): CarbonInterface
    {
        if (is_string($date) && $date !== '') {
            try {
                return Carbon::parse($date)->startOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        return now()->startOfDay();
    }

    private function resolvePaymentMode(?string $paymentMode): ?string
    {
        if (! is_string($paymentMode) || $paymentMode === '' || $paymentMode === 'all') {
            return null;
        }

        if ($paymentMode === 'unset') {
            return 'unset';
        }

        return in_array($paymentMode, PaymentMode::values(), true) ? $paymentMode : null;
    }

    /**
     * @return list<array{id: string, label: string, count: int, net: float}>
     */
    private function paymentModeBreakdown(CarbonInterface $day): array
    {
        $rows = JobOrder::query()
            ->whereNotNull('jo_approved_at')
            ->whereDate('jo_approved_at', $day->toDateString())
            ->selectRaw('payment_mode')
            ->selectRaw('COUNT(*) as job_count')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as net')
            ->groupBy('payment_mode')
            ->get()
            ->keyBy(fn ($row) => $row->payment_mode instanceof PaymentMode
                ? $row->payment_mode->value
                : (string) ($row->payment_mode ?? ''));

        $items = [];
        foreach (PaymentMode::cases() as $mode) {
            $row = $rows->get($mode->value);
            $items[] = [
                'id' => $mode->value,
                'label' => $mode->label(),
                'count' => (int) ($row->job_count ?? 0),
                'net' => round((float) ($row->net ?? 0), 2),
            ];
        }

        $unset = $rows->get('');
        $items[] = [
            'id' => 'unset',
            'label' => 'Unset',
            'count' => (int) ($unset->job_count ?? 0),
            'net' => round((float) ($unset->net ?? 0), 2),
        ];

        return $items;
    }

    /**
     * @return list<array{date: string, label: string, job_count: int, net: float}>
     */
    private function trendEndingOn(CarbonInterface $day, int $days): array
    {
        $end = $day->copy()->endOfDay();
        $start = $day->copy()->subDays($days - 1)->startOfDay();

        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', jo_approved_at)",
            default => 'DATE(jo_approved_at)',
        };

        /** @var Collection<string, object> $rows */
        $rows = JobOrder::query()
            ->whereNotNull('jo_approved_at')
            ->whereBetween('jo_approved_at', [$start, $end])
            ->selectRaw("{$dateExpr} as sale_date")
            ->selectRaw('COUNT(*) as job_count')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as net')
            ->groupBy(DB::raw($dateExpr))
            ->get()
            ->keyBy(fn ($row) => (string) $row->sale_date);

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $cursor = $start->copy()->addDays($i);
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $trend[] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'job_count' => (int) ($row->job_count ?? 0),
                'net' => round((float) ($row->net ?? 0), 2),
            ];
        }

        return $trend;
    }

    private function paginateOrders(CarbonInterface $day, ?string $mode): LengthAwarePaginator
    {
        return JobOrder::query()
            ->whereNotNull('jo_approved_at')
            ->whereDate('jo_approved_at', $day->toDateString())
            ->when($mode === 'unset', fn ($q) => $q->whereNull('payment_mode'))
            ->when(
                $mode !== null && $mode !== 'unset',
                fn ($q) => $q->where('payment_mode', $mode),
            )
            ->orderByDesc('jo_approved_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (JobOrder $order) {
                $paymentMode = $order->payment_mode;
                $paymentTerms = $order->payment_terms;

                return [
                    'id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'customer_name' => $order->customer_name,
                    'company_name' => $order->company_name,
                    'payment_mode' => $paymentMode instanceof PaymentMode ? $paymentMode->value : null,
                    'payment_mode_label' => $paymentMode instanceof PaymentMode ? $paymentMode->label() : '—',
                    'payment_terms' => $paymentTerms instanceof PaymentTerms ? $paymentTerms->value : null,
                    'payment_terms_label' => $paymentTerms instanceof PaymentTerms ? $paymentTerms->label() : null,
                    'total_cost' => (float) $order->total_cost,
                    'discount_amount' => (float) $order->discount_amount,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'jo_approved_at' => $order->jo_approved_at?->toIso8601String(),
                ];
            });
    }
}
