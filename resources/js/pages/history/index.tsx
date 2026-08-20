import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import SummaryStat from '@/components/summary-stat';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Order = {
    id: number;
    reference_no: string;
    customer_name: string;
    customer_email: string | null;
    customer_contact: string | null;
    company_name: string | null;
    classification: string | null;
    total_cost: string | number;
    analyses_count: number;
    samples_count: number;
    completed_at: string | null;
    signed_at: string | null;
    signed_by?: string | null;
    is_signed: boolean;
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
        unsigned: number;
        signed: number;
    };
    filters: {
        q: string;
        status: string;
    };
    canSign: boolean;
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function HistoryIndex({
    orders,
    counts,
    filters,
    canSign,
}: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [query, setQuery] = useState(filters.q ?? '');

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

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
            '/history',
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

    function submitSearch() {
        applyFilters({ q: query.trim() });
    }

    const chips = [
        { id: '', label: 'All finished', count: counts.all },
        {
            id: 'unsigned',
            label: 'Ready for pickup',
            count: counts.unsigned,
        },
        { id: 'signed', label: 'Signed', count: counts.signed },
    ] as const;

    return (
        <>
            <Head title="History" />
            <div className="flex flex-col gap-5 p-4">
                <WorkspaceHeader
                    title="History archive"
                    description={`Read-only archive of finished files. Search, open the official form, and download PDFs without leaving the workflow queues.${
                        canSign
                            ? ' Head Analysis can still sign unsigned files from this list or from the signing queue.'
                            : ''
                    }`}
                    flash={flash?.success}
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <SummaryStat label="All finished" value={counts.all} />
                    <SummaryStat
                        label="Ready for pickup"
                        value={counts.unsigned}
                    />
                    <SummaryStat
                        label="Signed"
                        value={counts.signed}
                        tone="success"
                    />
                </div>

                <QueueFilterBar
                    chips={chips}
                    activeId={filters.status || ''}
                    onChip={(id) =>
                        applyFilters({ status: id, q: query.trim() })
                    }
                    query={query}
                    onQueryChange={setQuery}
                    onSearch={submitSearch}
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
                        filters.status === 'unsigned'
                            ? 'archived records that are still waiting for signature.'
                            : filters.status === 'signed'
                              ? 'archived records that already have a recorded signature.'
                              : 'archived records.'
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
                                    Samples
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Tests
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Total
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Date
                                </th>
                                <th className="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr
                                    key={order.id}
                                    className="border-t transition hover:bg-[#f8fafc]"
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
                                                order.classification,
                                                order.customer_contact,
                                                order.customer_email,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </div>
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
                                        <div>
                                            {order.is_signed
                                                ? order.signed_at
                                                : order.completed_at}
                                        </div>
                                        {order.is_signed && order.signed_by && (
                                            <div className="text-xs">
                                                by {order.signed_by}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <div className="flex flex-wrap justify-end gap-2">
                                            {order.is_signed ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-200 bg-emerald-50 text-emerald-800"
                                                >
                                                    Signed
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]"
                                                >
                                                    Ready for pickup
                                                </Badge>
                                            )}
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={`/history/${order.id}`}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                            {canSign && !order.is_signed && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                                >
                                                    <Link
                                                        href={`/head/${order.id}`}
                                                    >
                                                        Sign
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
                                        colSpan={7}
                                        className="px-3 py-12 text-center text-muted-foreground"
                                    >
                                        {filters.q || filters.status
                                            ? 'No finished files match your filters.'
                                            : 'No finished / ready-for-pickup files yet.'}
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
                    label="finished files"
                />
            </div>
        </>
    );
}

HistoryIndex.layout = {
    breadcrumbs: [{ title: 'History', href: '/history' }],
};
