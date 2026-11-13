import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
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
    classification?: string | null;
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
        date_from: string;
        date_to: string;
        q: string;
        classification: string;
        payment_mode: string;
    };
    classification_options: string[];
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
    classification_options: classificationOptions,
    summary,
    by_payment_mode: byPaymentMode,
    trend,
    orders,
}: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [q, setQ] = useState(filters.q);
    const [classification, setClassification] = useState(
        filters.classification || 'all',
    );
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        setDateFrom(filters.date_from);
        setDateTo(filters.date_to);
        setQ(filters.q);
        setClassification(filters.classification || 'all');
    }, [
        filters.date_from,
        filters.date_to,
        filters.q,
        filters.classification,
    ]);

    function applyFilters(next: {
        date_from?: string;
        date_to?: string;
        q?: string;
        classification?: string;
        payment_mode?: string;
    }) {
        const nextFrom = next.date_from ?? dateFrom;
        const nextTo = next.date_to ?? dateTo;
        const nextQ = (next.q ?? q).trim();
        const nextClassification =
            next.classification ?? classification ?? 'all';
        const nextMode = next.payment_mode ?? filters.payment_mode;

        router.get(
            '/accounting',
            {
                date_from: nextFrom,
                date_to: nextTo,
                ...(nextQ !== '' ? { q: nextQ } : {}),
                ...(nextClassification && nextClassification !== 'all'
                    ? { classification: nextClassification }
                    : {}),
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

    function onSearchChange(value: string) {
        setQ(value);
        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
        }
        searchTimer.current = setTimeout(() => {
            applyFilters({ q: value });
        }, 300);
    }

    useEffect(() => {
        return () => {
            if (searchTimer.current) {
                clearTimeout(searchTimer.current);
            }
        };
    }, []);

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
    const singleDayRange = filters.date_from === filters.date_to;

    return (
        <>
            <Head title="Accounting" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Sales monitor"
                    description="Billed totals from Head-approved job orders. Payment collection stays outside LMIS."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                    <div className="space-y-1">
                        <label
                            htmlFor="accounting-date-from"
                            className="text-[11px] font-medium text-slate-600"
                        >
                            From
                        </label>
                        <Input
                            id="accounting-date-from"
                            type="date"
                            className="h-8 w-[10.5rem] text-xs"
                            value={dateFrom}
                            onChange={(event) => {
                                const next = event.target.value;
                                setDateFrom(next);
                                if (next) {
                                    applyFilters({ date_from: next });
                                }
                            }}
                        />
                    </div>
                    <div className="space-y-1">
                        <label
                            htmlFor="accounting-date-to"
                            className="text-[11px] font-medium text-slate-600"
                        >
                            To
                        </label>
                        <Input
                            id="accounting-date-to"
                            type="date"
                            className="h-8 w-[10.5rem] text-xs"
                            value={dateTo}
                            onChange={(event) => {
                                const next = event.target.value;
                                setDateTo(next);
                                if (next) {
                                    applyFilters({ date_to: next });
                                }
                            }}
                        />
                    </div>
                    <div className="min-w-[12rem] flex-1 space-y-1">
                        <label
                            htmlFor="accounting-search"
                            className="text-[11px] font-medium text-slate-600"
                        >
                            Search
                        </label>
                        <Input
                            id="accounting-search"
                            type="search"
                            placeholder="JO, customer, or company"
                            className="h-8 text-xs"
                            value={q}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <label
                            htmlFor="accounting-classification"
                            className="text-[11px] font-medium text-slate-600"
                        >
                            Classification
                        </label>
                        <select
                            id="accounting-classification"
                            className="h-8 rounded-md border border-slate-200 bg-white px-2 text-xs text-slate-800"
                            value={classification}
                            onChange={(event) => {
                                const next = event.target.value;
                                setClassification(next);
                                applyFilters({ classification: next });
                            }}
                        >
                            <option value="all">All</option>
                            {classificationOptions.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

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
                        hint="Approved in range"
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
                            Trend
                        </h2>
                        <p className="text-xs text-slate-500">
                            {filters.date_from === filters.date_to
                                ? filters.date_from
                                : `${filters.date_from} → ${filters.date_to}`}
                        </p>
                    </div>
                    <div className="flex h-36 items-end gap-1.5">
                        {trend.map((point) => {
                            const height = Math.max(
                                (point.net / maxTrendNet) * 100,
                                point.net > 0 ? 6 : 2,
                            );
                            const selected =
                                singleDayRange &&
                                point.date === filters.date_from;

                            return (
                                <button
                                    key={point.date}
                                    type="button"
                                    title={`${point.label}: ${money(point.net)} · ${point.job_count} JO`}
                                    onClick={() => {
                                        setDateFrom(point.date);
                                        setDateTo(point.date);
                                        applyFilters({
                                            date_from: point.date,
                                            date_to: point.date,
                                        });
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
                                            No Head-approved job orders for
                                            these filters
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
                                                {order.classification ? (
                                                    <div className="text-[11px] text-slate-400">
                                                        {order.classification}
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
