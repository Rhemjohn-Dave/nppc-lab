import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import JoReferenceChip from '@/components/lims/jo-reference-chip';
import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueKpi from '@/components/lims/queue-kpi';
import QueueSummaryCards from '@/components/lims/queue-summary-cards';
import QueueRangeNote from '@/components/queue-range-note';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type OrderRow = {
    id: number;
    reference_no: string;
    customer_name: string;
    company_name: string | null;
    payment_mode: string | null;
    payment_mode_label: string;
    payment_terms: string | null;
    payment_terms_label: string | null;
    total_cost: number;
    discount_amount: number;
    status: string;
    status_label: string;
    jo_approved_at: string | null;
};

type PaymentModeBucket = {
    id: string;
    label: string;
    count: number;
    net: number;
};

type TrendPoint = {
    date: string;
    label: string;
    job_count: number;
    net: number;
};

type Props = {
    filters: {
        date: string;
        payment_mode: string;
    };
    summary: {
        job_count: number;
        gross: number;
        discount: number;
        net: number;
        avg_ticket: number;
    };
    by_payment_mode: PaymentModeBucket[];
    trend: TrendPoint[];
    orders: {
        data: OrderRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
};

function money(value: number) {
    return `₱${Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatApprovedAt(value: string | null) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

export default function AccountingIndex({
    filters,
    summary,
    by_payment_mode: byPaymentMode,
    trend,
    orders,
}: Props) {
    const [date, setDate] = useState(filters.date);

    function applyFilters(next: { date?: string; payment_mode?: string }) {
        const nextDate = next.date ?? date;
        const nextMode = next.payment_mode ?? filters.payment_mode;

        router.get(
            '/accounting',
            {
                date: nextDate,
                ...(nextMode && nextMode !== 'all'
                    ? { payment_mode: nextMode }
                    : {}),
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    const modeTotal = Math.max(
        byPaymentMode.reduce((sum, row) => sum + row.count, 0),
        1,
    );

    const paymentCards = byPaymentMode.map((row) => ({
        id: row.id,
        label: row.label,
        value: row.count,
        unit: money(row.net),
        tone:
            row.id === 'cash'
                ? ('success' as const)
                : row.id === 'check'
                  ? ('info' as const)
                  : row.id === 'billing_partial'
                    ? ('warning' as const)
                    : ('default' as const),
        share: Math.round((row.count / modeTotal) * 100),
        badge: row.count > 0 ? `${row.count}` : null,
    }));

    const modeChips = [
        {
            id: 'all',
            label: 'All modes',
            count: byPaymentMode.reduce((sum, row) => sum + row.count, 0),
        },
        ...byPaymentMode.map((row) => ({
            id: row.id,
            label: row.label,
            count: row.count,
        })),
    ];

    const maxTrendNet = Math.max(...trend.map((point) => point.net), 1);

    return (
        <>
            <Head title="Accounting" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Sales monitor"
                    description="Daily billed totals from Head-approved job orders. Payment collection stays outside LMIS."
                    actions={
                        <div className="flex items-center gap-2">
                            <label
                                htmlFor="accounting-date"
                                className="text-xs font-medium text-slate-600"
                            >
                                Date
                            </label>
                            <Input
                                id="accounting-date"
                                type="date"
                                className="h-8 w-[10.5rem] text-xs"
                                value={date}
                                onChange={(event) => {
                                    const next = event.target.value;
                                    setDate(next);
                                    if (next) {
                                        applyFilters({ date: next });
                                    }
                                }}
                            />
                        </div>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <QueueKpi
                        label="Net sales"
                        value={money(summary.net)}
                        hint={`${summary.job_count} approved JO`}
                        tone="success"
                        emphasize
                    />
                    <QueueKpi
                        label="Job orders"
                        value={summary.job_count}
                        hint="Approved this day"
                        tone="info"
                    />
                    <QueueKpi
                        label="Average ticket"
                        value={money(summary.avg_ticket)}
                        hint="Net ÷ JO count"
                    />
                    <QueueKpi
                        label="Discounts"
                        value={money(summary.discount)}
                        hint={`Gross ${money(summary.gross)}`}
                        tone={summary.discount > 0 ? 'warning' : 'default'}
                    />
                </div>

                <div className="space-y-2">
                    <h2 className="text-xs font-semibold tracking-wide text-slate-900 uppercase">
                        Payment mode
                    </h2>
                    <QueueSummaryCards
                        cards={paymentCards}
                        activeId={
                            filters.payment_mode === 'all'
                                ? ''
                                : filters.payment_mode
                        }
                        onSelect={(id) =>
                            applyFilters({
                                payment_mode:
                                    filters.payment_mode === id ? 'all' : id,
                            })
                        }
                        className="sm:grid-cols-2 lg:grid-cols-4"
                    />
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div className="mb-3 flex items-baseline justify-between gap-2">
                        <h2 className="text-xs font-semibold tracking-wide text-slate-900 uppercase">
                            Last 14 days
                        </h2>
                        <p className="text-xs text-slate-500">
                            Ending {filters.date}
                        </p>
                    </div>
                    <div className="flex h-36 items-end gap-1.5">
                        {trend.map((point) => {
                            const height = Math.max(
                                (point.net / maxTrendNet) * 100,
                                point.net > 0 ? 6 : 2,
                            );
                            const selected = point.date === filters.date;

                            return (
                                <button
                                    key={point.date}
                                    type="button"
                                    title={`${point.label}: ${money(point.net)} · ${point.job_count} JO`}
                                    onClick={() => {
                                        setDate(point.date);
                                        applyFilters({ date: point.date });
                                    }}
                                    className="group flex min-w-0 flex-1 flex-col items-center gap-1"
                                >
                                    <span className="sr-only">
                                        {point.label} {money(point.net)}
                                    </span>
                                    <div className="flex h-28 w-full items-end justify-center">
                                        <div
                                            className={cn(
                                                'w-full max-w-[1.75rem] rounded-t-md transition',
                                                selected
                                                    ? 'bg-nppc-900'
                                                    : 'bg-nppc-200 group-hover:bg-nppc-400',
                                            )}
                                            style={{ height: `${height}%` }}
                                        />
                                    </div>
                                    <span
                                        className={cn(
                                            'truncate text-[10px] tabular-nums',
                                            selected
                                                ? 'font-semibold text-nppc-900'
                                                : 'text-slate-500',
                                        )}
                                    >
                                        {point.label}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div
                    className="flex items-center gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-3 text-xs shadow-xs"
                    role="tablist"
                >
                    {modeChips.map((chip) => {
                        const active =
                            (filters.payment_mode || 'all') === chip.id;

                        return (
                            <button
                                key={chip.id}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() =>
                                    applyFilters({ payment_mode: chip.id })
                                }
                                className={cn(
                                    'whitespace-nowrap rounded-lg px-3 py-1.5 font-medium transition',
                                    active
                                        ? 'border border-nppc-200/70 bg-nppc-50 font-semibold text-nppc-900 shadow-xs'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                )}
                            >
                                {chip.label}{' '}
                                <span
                                    className={cn(
                                        'ml-1 tabular-nums',
                                        active
                                            ? 'text-nppc-700'
                                            : 'text-slate-400',
                                    )}
                                >
                                    {chip.count}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">Reference</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Payment</th>
                                    <th className="px-4 py-3 text-right">
                                        Discount
                                    </th>
                                    <th className="px-4 py-3 text-right">Net</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Approved</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-10 text-center text-sm text-slate-500"
                                        >
                                            No Head-approved job orders for this
                                            day
                                            {filters.payment_mode !== 'all'
                                                ? ' with the selected payment mode'
                                                : ''}
                                            .
                                        </td>
                                    </tr>
                                ) : (
                                    orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="hover:bg-slate-50/80"
                                        >
                                            <td className="px-4 py-3">
                                                <JoReferenceChip
                                                    referenceNo={
                                                        order.reference_no
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-900">
                                                    {order.customer_name}
                                                </div>
                                                {order.company_name ? (
                                                    <div className="text-xs text-slate-500">
                                                        {order.company_name}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-slate-800">
                                                    {order.payment_mode_label}
                                                </div>
                                                {order.payment_terms_label ? (
                                                    <div className="text-xs text-slate-500">
                                                        {
                                                            order.payment_terms_label
                                                        }
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-slate-600">
                                                {money(order.discount_amount)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold tabular-nums text-slate-900">
                                                {money(order.total_cost)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className="border-slate-200 bg-slate-50 font-normal text-slate-700"
                                                >
                                                    {order.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-xs tabular-nums text-slate-600">
                                                {formatApprovedAt(
                                                    order.jo_approved_at,
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="space-y-3 border-t border-slate-100 px-4 py-3">
                        <QueueRangeNote
                            from={orders.from}
                            to={orders.to}
                            total={orders.total}
                            suffix="job orders"
                        />
                        <TablePagination links={orders.links} />
                    </div>
                </div>
            </LimsWorkspace>
        </>
    );
}

AccountingIndex.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
