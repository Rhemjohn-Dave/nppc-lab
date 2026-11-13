<?php

namespace App\Support\Accounting;

use App\Enums\PaymentMode;
use App\Enums\PaymentTerms;
use App\Models\JobOrder;
use App\Support\OfficialAnalysisCatalog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesMonitor
{
    private const MAX_RANGE_DAYS = 90;

    /**
     * @param  array{
     *     date?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     q?: string|null,
     *     classification?: string|null,
     *     payment_mode?: string|null
     * }  $input
     * @return array{
     *     filters: array{date_from: string, date_to: string, q: string, classification: string, payment_mode: string},
     *     classification_options: list<string>,
     *     summary: array{job_count: int, gross: float, discount: float, net: float, avg_ticket: float},
     *     by_payment_mode: list<array{id: string, label: string, count: int, net: float}>,
     *     trend: list<array{date: string, label: string, job_count: int, net: float}>,
     *     orders: LengthAwarePaginator
     * }
     */
    public function build(array $input): array
    {
        $filters = $this->resolveFilters($input);

        $scoped = $this->scopedQuery($filters, includePaymentMode: true);
        $aggregates = (clone $scoped)
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
                'date_from' => $filters['date_from']->toDateString(),
                'date_to' => $filters['date_to']->toDateString(),
                'q' => $filters['q'],
                'classification' => $filters['classification'] ?? 'all',
                'payment_mode' => $filters['payment_mode'] ?? 'all',
            ],
            'classification_options' => OfficialAnalysisCatalog::classifications(),
            'summary' => [
                'job_count' => $jobCount,
                'gross' => $gross,
                'discount' => $discount,
                'net' => $net,
                'avg_ticket' => $avgTicket,
            ],
            'by_payment_mode' => $this->paymentModeBreakdown($filters),
            'trend' => $this->trendForRange($filters),
            'orders' => $this->paginateOrders($filters),
        ];
    }

    /**
     * @param  array{
     *     date?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     q?: string|null,
     *     classification?: string|null,
     *     payment_mode?: string|null
     * }  $input
     * @return array{
     *     date_from: CarbonInterface,
     *     date_to: CarbonInterface,
     *     q: string,
     *     classification: string|null,
     *     payment_mode: string|null
     * }
     */
    private function resolveFilters(array $input): array
    {
        $legacyDate = is_string($input['date'] ?? null) ? trim((string) $input['date']) : '';
        $fromRaw = is_string($input['date_from'] ?? null) ? trim((string) $input['date_from']) : '';
        $toRaw = is_string($input['date_to'] ?? null) ? trim((string) $input['date_to']) : '';

        if ($fromRaw === '' && $toRaw === '' && $legacyDate !== '') {
            $fromRaw = $legacyDate;
            $toRaw = $legacyDate;
        }

        $from = $this->parseDay($fromRaw) ?? now()->startOfDay();
        $to = $this->parseDay($toRaw) ?? $from->copy();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->startOfDay()];
        }

        $spanDays = $from->diffInDays($to) + 1;
        if ($spanDays > self::MAX_RANGE_DAYS) {
            $to = $from->copy()->addDays(self::MAX_RANGE_DAYS - 1)->startOfDay();
        }

        $q = trim((string) ($input['q'] ?? ''));
        $classification = $this->resolveClassification(
            is_string($input['classification'] ?? null) ? (string) $input['classification'] : null,
        );
        $paymentMode = $this->resolvePaymentMode(
            is_string($input['payment_mode'] ?? null) ? (string) $input['payment_mode'] : null,
        );

        return [
            'date_from' => $from->startOfDay(),
            'date_to' => $to->startOfDay(),
            'q' => $q,
            'classification' => $classification,
            'payment_mode' => $paymentMode,
        ];
    }

    private function parseDay(string $value): ?CarbonInterface
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
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

    private function resolveClassification(?string $classification): ?string
    {
        if (! is_string($classification) || $classification === '' || $classification === 'all') {
            return null;
        }

        $allowed = OfficialAnalysisCatalog::classifications();

        return in_array($classification, $allowed, true) ? $classification : null;
    }

    /**
     * @param  array{
     *     date_from: CarbonInterface,
     *     date_to: CarbonInterface,
     *     q: string,
     *     classification: string|null,
     *     payment_mode: string|null
     * }  $filters
     * @return Builder<JobOrder>
     */
    private function scopedQuery(array $filters, bool $includePaymentMode): Builder
    {
        $from = $filters['date_from']->copy()->startOfDay();
        $to = $filters['date_to']->copy()->endOfDay();

        $query = JobOrder::query()
            ->whereNotNull('jo_approved_at')
            ->whereBetween('jo_approved_at', [$from, $to]);

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('reference_no', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('company_name', 'like', $term);
            });
        }

        if ($filters['classification'] !== null) {
            $query->where('classification', $filters['classification']);
        }

        if ($includePaymentMode) {
            $mode = $filters['payment_mode'];
            if ($mode === 'unset') {
                $query->whereNull('payment_mode');
            } elseif ($mode !== null) {
                $query->where('payment_mode', $mode);
            }
        }

        return $query;
    }

    /**
     * @param  array{
     *     date_from: CarbonInterface,
     *     date_to: CarbonInterface,
     *     q: string,
     *     classification: string|null,
     *     payment_mode: string|null
     * }  $filters
     * @return list<array{id: string, label: string, count: int, net: float}>
     */
    private function paymentModeBreakdown(array $filters): array
    {
        $rows = $this->scopedQuery($filters, includePaymentMode: false)
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
     * @param  array{
     *     date_from: CarbonInterface,
     *     date_to: CarbonInterface,
     *     q: string,
     *     classification: string|null,
     *     payment_mode: string|null
     * }  $filters
     * @return list<array{date: string, label: string, job_count: int, net: float}>
     */
    private function trendForRange(array $filters): array
    {
        $start = $filters['date_from']->copy()->startOfDay();
        $end = $filters['date_to']->copy()->endOfDay();
        $days = $start->diffInDays($filters['date_to']->copy()->startOfDay()) + 1;

        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', jo_approved_at)",
            default => 'DATE(jo_approved_at)',
        };

        /** @var Collection<string, object> $rows */
        $rows = $this->scopedQuery($filters, includePaymentMode: true)
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

    /**
     * @param  array{
     *     date_from: CarbonInterface,
     *     date_to: CarbonInterface,
     *     q: string,
     *     classification: string|null,
     *     payment_mode: string|null
     * }  $filters
     */
    private function paginateOrders(array $filters): LengthAwarePaginator
    {
        return $this->scopedQuery($filters, includePaymentMode: true)
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
                    'classification' => $order->classification,
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
