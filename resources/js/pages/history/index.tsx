import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import JoReferenceChip from '@/components/lims/jo-reference-chip';
import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueSummaryCards from '@/components/lims/queue-summary-cards';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import PdfPreviewDialog from '@/components/pdf-preview-dialog';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import ResultReportPreview from '@/components/result-report-preview';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';
import { cn } from '@/lib/utils';

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
    can_preview_result: boolean;
    result_pdf_url?: string | null;
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
    const [query, setQuery] = useState(filters.q ?? '');
    const [joPreviewOrderId, setJoPreviewOrderId] = useState<number | null>(
        null,
    );
    const [resultPreviewOrderId, setResultPreviewOrderId] = useState<
        number | null
    >(null);
    const { isRefreshing } = useInertiaNavigation();

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

    const activeStatus = filters.status || '';
    const kpiTotal = Math.max(counts.all, 1);
    const summaryCards = [
        {
            id: '',
            label: 'All Finished',
            value: counts.all,
            unit: 'Archived records',
            tone: 'default' as const,
            share: 100,
        },
        {
            id: 'unsigned',
            label: 'Ready for Pickup',
            value: counts.unsigned,
            unit: 'Awaiting signature',
            tone:
                counts.unsigned > 0
                    ? ('warning' as const)
                    : ('default' as const),
            share: Math.round((counts.unsigned / kpiTotal) * 100),
        },
        {
            id: 'signed',
            label: 'Signed',
            value: counts.signed,
            unit: 'Released archive',
            tone: 'success' as const,
            share: Math.round((counts.signed / kpiTotal) * 100),
        },
    ];

    const emptyTitle =
        filters.q || filters.status
            ? 'No matching records'
            : 'Archive is empty';
    const emptyBody =
        filters.q || filters.status
            ? 'No finished files match your filters.'
            : 'No finished / ready-for-pickup files yet.';

    return (
        <>
            <Head title="History" />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-4">
                    <WorkspaceHeader
                        title="History archive"
                        description={`Read-only archive of finished files. Search, open the official form, and download PDFs without leaving the workflow queues.${
                            canSign
                                ? ' Head Analysis can still sign unsigned files from this list or from the signing queue.'
                                : ''
                        }`}
                        refreshing={isRefreshing}
                        refreshLabel="Updating archive…"
                    />

                    <QueueSummaryCards
                        cards={summaryCards}
                        activeId={activeStatus}
                        onSelect={(id) =>
                            applyFilters({ status: id, q: query.trim() })
                        }
                        className="sm:grid-cols-3 lg:grid-cols-3"
                    />

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div className="space-y-3 border-b border-slate-200 p-3 sm:p-4">
                            <QueueFilterBar
                                chips={chips}
                                activeId={activeStatus}
                                onChip={(id) =>
                                    applyFilters({
                                        status: id,
                                        q: query.trim(),
                                    })
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
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[920px] border-collapse text-left text-xs">
                                <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                    <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                        <th className="px-4 py-3">Reference</th>
                                        <th className="px-4 py-3">Customer</th>
                                        <th className="px-4 py-3">Samples</th>
                                        <th className="px-4 py-3">Tests</th>
                                        <th className="px-4 py-3 text-right">
                                            Total
                                        </th>
                                        <th className="px-4 py-3">Date</th>
                                        <th className="min-w-[18rem] px-4 py-3 text-right">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200/80">
                                    {orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            className={cn(
                                                'transition-colors hover:bg-slate-50',
                                                !order.is_signed &&
                                                    'bg-amber-50/30',
                                            )}
                                        >
                                            <td className="px-4 py-4 align-top whitespace-nowrap">
                                                <JoReferenceChip
                                                    referenceNo={
                                                        order.reference_no
                                                    }
                                                    emphasize={!order.is_signed}
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="font-semibold text-slate-900">
                                                    {order.customer_name}
                                                </p>
                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                    {[
                                                        order.company_name,
                                                        order.classification,
                                                        order.customer_contact,
                                                        order.customer_email,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') || '—'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top tabular-nums text-slate-800">
                                                {order.samples_count}
                                            </td>
                                            <td className="px-4 py-4 align-top tabular-nums text-slate-800">
                                                {order.analyses_count}
                                            </td>
                                            <td className="px-4 py-4 text-right align-top text-xs font-bold tabular-nums text-slate-900">
                                                {money(order.total_cost)}
                                            </td>
                                            <td className="px-4 py-4 align-top text-[11px] text-slate-500">
                                                <div>
                                                    {order.is_signed
                                                        ? order.signed_at
                                                        : order.completed_at}
                                                </div>
                                                {order.is_signed &&
                                                order.signed_by ? (
                                                    <div>
                                                        by {order.signed_by}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-4 text-right align-top">
                                                <div className="inline-flex flex-wrap items-center justify-end gap-1.5">
                                                    {order.is_signed ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-800"
                                                        >
                                                            Signed
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-200 bg-amber-50 text-[10px] text-amber-900"
                                                        >
                                                            Ready for pickup
                                                        </Badge>
                                                    )}
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-8 border-slate-200 text-xs shadow-xs"
                                                        onClick={() =>
                                                            setJoPreviewOrderId(
                                                                order.id,
                                                            )
                                                        }
                                                    >
                                                        View JO
                                                    </Button>
                                                    {order.can_preview_result ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 border-slate-200 text-xs shadow-xs"
                                                            onClick={() =>
                                                                setResultPreviewOrderId(
                                                                    order.id,
                                                                )
                                                            }
                                                        >
                                                            View result
                                                        </Button>
                                                    ) : null}
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        className="h-8 gap-1.5 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                                                    >
                                                        <Link
                                                            href={`/history/${order.id}`}
                                                        >
                                                            Open
                                                            <ArrowRight className="size-3" />
                                                        </Link>
                                                    </Button>
                                                    {canSign &&
                                                    !order.is_signed ? (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 border-slate-200 text-xs shadow-xs"
                                                        >
                                                            <Link
                                                                href={`/head/${order.id}`}
                                                            >
                                                                Sign
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {orders.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-12 text-center"
                                            >
                                                <p className="text-sm font-medium text-nppc-900">
                                                    {emptyTitle}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {emptyBody}
                                                </p>
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>

                        <div className="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                            <TablePagination
                                links={orders.links}
                                from={orders.from}
                                to={orders.to}
                                total={orders.total}
                                label="finished files"
                            />
                        </div>
                    </div>
                </LimsWorkspace>
            </NavigationLoadingShell>

            <PdfPreviewDialog
                open={joPreviewOrderId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setJoPreviewOrderId(null);
                    }
                }}
                title="Job order preview"
                description="Official JO / RFA form (without result overlays)."
                load={async () => {
                    if (joPreviewOrderId == null) {
                        throw new Error('No job order selected.');
                    }

                    const response = await fetch(
                        `/history/${joPreviewOrderId}/pdf?inline=1&results=0`,
                        {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/pdf' },
                        },
                    );

                    if (!response.ok) {
                        throw new Error('Could not load the job order PDF.');
                    }

                    const blob = await response.blob();
                    const order = orders.data.find(
                        (row) => row.id === joPreviewOrderId,
                    );

                    return {
                        blob,
                        filename: `${order?.reference_no ?? 'job-order'}-jo.pdf`,
                        title: order
                            ? `JO ${order.reference_no}`
                            : 'Job order',
                        allowPrint: true,
                    };
                }}
            />

            <ResultReportPreview
                open={resultPreviewOrderId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setResultPreviewOrderId(null);
                    }
                }}
                reportUrl={
                    resultPreviewOrderId
                        ? `/history/${resultPreviewOrderId}/result-report`
                        : null
                }
            />
        </>
    );
}

HistoryIndex.layout = {
    breadcrumbs: [{ title: 'History', href: '/history' }],
};
