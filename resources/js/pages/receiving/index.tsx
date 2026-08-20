import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import SummaryStat from '@/components/summary-stat';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Order = {
    id: number;
    reference_no: string;
    customer_name: string;
    customer_email: string | null;
    customer_contact: string | null;
    company_name: string | null;
    status: string;
    status_label: string;
    reviewed?: boolean;
    total_cost: string | number;
    analyses_count: number;
    samples_count: number;
    created_at: string | null;
};

type Props = {
    orders: {
        data: Order[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    counts: {
        all: number;
        draft_submitted: number;
        priced: number;
        reviewed: number;
    };
    filters: {
        q: string;
        status: string;
    };
};

function statusBadgeClass(status: string) {
    if (status === 'priced') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    return 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]';
}

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function ReceivingIndex({ orders, counts, filters }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [query, setQuery] = useState(filters.q ?? '');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(() => new Date());

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const id = window.setInterval(() => {
            setIsRefreshing(true);
            router.reload({
                only: ['orders', 'counts'],
                onFinish: () => {
                    setIsRefreshing(false);
                    setLastUpdated(new Date());
                },
            });
        }, 20000);

        return () => window.clearInterval(id);
    }, []);

    useEffect(() => {
        const trimmed = query.trim();
        const active = filters.q ?? '';
        const id = window.setTimeout(() => {
            if (trimmed === active) {
                return;
            }

            applyFilters({ q: trimmed });
        }, 350);

        return () => window.clearTimeout(id);
    }, [query, filters.q, filters.status]);

    function applyFilters(next: { q?: string; status?: string }) {
        router.get(
            '/receiving',
            {
                q: next.q ?? query,
                status: next.status ?? filters.status,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    const statusChips = [
        { id: '', label: 'All', count: counts.all },
        {
            id: 'draft_submitted',
            label: 'Needs pricing',
            count: counts.draft_submitted,
        },
        { id: 'priced', label: 'Ready to receive', count: counts.priced },
        { id: 'reviewed', label: 'Reviewed', count: counts.reviewed },
    ] as const;

    return (
        <>
            <Head title="Receiving" />
            <div className="flex flex-col gap-5 p-4">
                <WorkspaceHeader
                    title="Receiving queue"
                    description="Price and receive samples. After Head releases results, reprint reviewed RFA copies for the customer packet. Auto-refreshes every 20 seconds."
                    flash={flash?.success}
                    refreshing={isRefreshing}
                    lastUpdated={lastUpdated}
                    refreshLabel="Refreshing queue…"
                    hint="Priority: price first, then receive."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryStat label="In queue" value={counts.all} />
                    <SummaryStat
                        label="Needs pricing"
                        value={counts.draft_submitted}
                    />
                    <SummaryStat
                        label="Ready to receive"
                        value={counts.priced}
                        tone="success"
                    />
                    <SummaryStat
                        label="Reviewed RFAs"
                        value={counts.reviewed}
                        tone="info"
                    />
                </div>

                <QueueFilterBar
                    chips={statusChips}
                    activeId={filters.status || ''}
                    onChip={(id) =>
                        applyFilters({ status: id, q: query.trim() })
                    }
                    query={query}
                    onQueryChange={setQuery}
                    onSearch={() => applyFilters({ q: query.trim() })}
                    onClear={() => {
                        setQuery('');
                        applyFilters({ q: '' });
                    }}
                />

                <QueueRangeNote
                    from={orders.from}
                    to={orders.to}
                    total={orders.total}
                    suffix={
                        filters.status === 'draft_submitted'
                            ? 'job orders that still need pricing.'
                            : filters.status === 'priced'
                              ? 'job orders that are ready to be received.'
                              : filters.status === 'reviewed'
                                ? 'jobs Head has reviewed. Print RFA copies for the customer packet.'
                                : 'job orders across the full receiving queue.'
                    }
                />

                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 z-10 bg-[#f8fafc] text-left">
                            <tr>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Reference
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Customer
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Status
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Samples
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Tests
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Total
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Submitted
                                </th>
                                <th className="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    className={cn(
                                        'border-t transition hover:bg-[#f8fafc]',
                                        order.status === 'priced' &&
                                            'bg-emerald-50/30',
                                    )}
                                >
                                    <td className="px-3 py-3 font-semibold text-[#1A3694]">
                                        {order.reference_no}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="font-medium">
                                            {order.customer_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {[
                                                order.company_name,
                                                order.customer_contact,
                                                order.customer_email,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant="outline"
                                            className={statusBadgeClass(
                                                order.status,
                                            )}
                                        >
                                            {order.status ===
                                            'draft_submitted'
                                                ? 'Needs pricing'
                                                : order.reviewed
                                                  ? 'Reviewed'
                                                  : order.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {order.samples_count}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {order.analyses_count}
                                    </td>
                                    <td className="px-3 py-3 tabular-nums">
                                        {money(order.total_cost)}
                                    </td>
                                    <td className="px-3 py-3 text-muted-foreground">
                                        {order.created_at}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex flex-wrap items-center justify-end gap-2">
                                            {order.status === 'priced' && (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-200 bg-emerald-50 text-emerald-800"
                                                >
                                                    Next: mark received
                                                </Badge>
                                            )}
                                            {filters.status === 'reviewed' ||
                                            order.reviewed ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/receiving/${order.id}/print?copies=3`}
                                                    >
                                                        Print RFA copies
                                                    </Link>
                                                </Button>
                                            ) : null}
                                            {filters.status === 'reviewed' ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                                >
                                                    <Link
                                                        href={`/receiving/${order.id}`}
                                                    >
                                                        Open
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                                >
                                                    <Link
                                                        href={`/receiving/${order.id}`}
                                                    >
                                                        {order.status ===
                                                        'priced'
                                                            ? 'Open & receive'
                                                            : 'Price & receive'}
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-12 text-center text-muted-foreground"
                                    >
                                        {filters.q || filters.status
                                            ? 'No job orders match your filters.'
                                            : 'No job orders waiting for receiving.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <TablePagination
                    links={orders.links}
                    from={orders.from}
                    to={orders.to}
                    total={orders.total}
                    label="job orders"
                />
            </div>
        </>
    );
}

ReceivingIndex.layout = {
    breadcrumbs: [{ title: 'Receiving', href: '/receiving' }],
};
