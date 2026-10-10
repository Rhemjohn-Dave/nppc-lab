import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Download } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import JoReferenceChip from '@/components/lims/jo-reference-chip';
import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueSummaryCards from '@/components/lims/queue-summary-cards';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { LabQueueRealtime } from '@/hooks/use-lab-queue-realtime';
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';
import { receivingStatusBadgeClass } from '@/lib/receiving-workflow';
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
    status?: string;
    status_label?: string;
    analyses_count: number;
    samples_count: number;
    completed_at: string | null;
    jo_approved_at?: string | null;
    jo_approver_name?: string | null;
    is_signed: boolean;
    needs_jo_approval?: boolean;
    jo_pdf_url?: string;
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
        pending: number;
        approved: number;
    };
    filters: {
        tab: 'pending' | 'approved';
        q: string;
    };
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

function joStatusLabel(order: Order): string {
    if (order.needs_jo_approval) {
        return 'Needs JO approval';
    }

    switch (order.status) {
        case 'jo_approved':
            return 'JO approved';
        case 'in_analysis':
            return 'In analysis';
        case 'pending_review':
            return 'Awaiting result release';
        case 'ready_for_pickup':
            return 'Results released';
        default:
            return order.status_label || order.status || 'JO approved';
    }
}

function CustomerCell({ order }: { order: Order }) {
    return (
        <>
            <p className="font-semibold text-slate-900">{order.customer_name}</p>
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
        </>
    );
}

function OrderActions({
    order,
    pendingTab,
}: {
    order: Order;
    pendingTab: boolean;
}) {
    return (
        <div className="inline-flex flex-wrap items-center justify-end gap-1.5">
            {!pendingTab && order.jo_pdf_url ? (
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="h-8 gap-1.5 border-slate-200 text-xs shadow-xs"
                >
                    <a href={order.jo_pdf_url}>
                        <Download className="size-3.5" />
                        Download JO
                    </a>
                </Button>
            ) : null}
            <Button
                asChild
                size="sm"
                className={cn(
                    'h-8 gap-1.5 text-xs shadow-xs',
                    pendingTab
                        ? 'border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
                        : 'bg-nppc-900 hover:bg-nppc-700',
                )}
            >
                <Link href={`/head/${order.id}?view=jo`}>
                    {pendingTab ? 'Review & approve JO' : 'Open JO'}
                    <ArrowRight className="size-3" />
                </Link>
            </Button>
        </div>
    );
}

function OrderStatusBadge({
    order,
    pendingTab,
}: {
    order: Order;
    pendingTab: boolean;
}) {
    if (pendingTab) {
        return (
            <Badge
                variant="outline"
                className="border-amber-200 bg-amber-50 text-amber-900"
            >
                Needs JO approval
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className={receivingStatusBadgeClass(
                order.status ?? 'jo_approved',
                order.is_signed,
            )}
        >
            {joStatusLabel(order)}
        </Badge>
    );
}

export default function HeadJoIndex({ orders, counts, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [confirmBatch, setConfirmBatch] = useState(false);
    const [signing, setSigning] = useState(false);
    const { isRefreshing } = useInertiaNavigation();

    const pendingTab = filters.tab === 'pending';
    const approvedTab = filters.tab === 'approved';
    const canSelect = pendingTab;

    const pageIds = useMemo(
        () => orders.data.map((order) => order.id),
        [orders.data],
    );
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

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
    }, [query, filters.q, filters.tab]);

    function applyFilters(next: { q?: string; tab?: string }) {
        setSelected([]);
        router.get(
            '/head/jo',
            {
                q: next.q ?? query,
                tab: next.tab ?? filters.tab,
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

    function toggleAll(checked: boolean) {
        setSelected(checked ? pageIds : []);
    }

    function toggleOne(id: number, checked: boolean) {
        setSelected((current) =>
            checked
                ? [...new Set([...current, id])]
                : current.filter((value) => value !== id),
        );
    }

    function runBatch() {
        setSigning(true);
        router.post(
            '/head/approve-batch',
            { job_order_ids: selected },
            {
                onFinish: () => {
                    setSigning(false);
                    setConfirmBatch(false);
                    setSelected([]);
                },
            },
        );
    }

    const emptyTitle = filters.q
        ? 'No matching job orders'
        : pendingTab
          ? 'Queue is clear'
          : 'No JO-approved orders yet';
    const emptyBody = filters.q
        ? 'No job orders match your search.'
        : pendingTab
          ? 'No Job Orders waiting for Head JO approval.'
          : 'No JO-approved Job Orders yet.';

    const kpiTotal = Math.max(counts.pending + counts.approved, 1);
    const summaryCards = [
        {
            id: 'pending',
            label: 'Awaiting JO Approval',
            value: counts.pending,
            unit:
                counts.pending > 0 ? 'Action required' : 'None waiting',
            tone:
                counts.pending > 0
                    ? ('warning' as const)
                    : ('default' as const),
            share: Math.round((counts.pending / kpiTotal) * 100),
        },
        {
            id: 'approved',
            label: 'JO Approved',
            value: counts.approved,
            unit: 'All JO-approved orders',
            tone: 'success' as const,
            share: Math.round((counts.approved / kpiTotal) * 100),
        },
    ];

    return (
        <>
            <Head title="JO Approval" />
            <LabQueueRealtime role="head" only={['orders', 'counts']} />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-4">
                    <WorkspaceHeader
                        title="JO Approval"
                        description="Approve priced Job Orders for Receiving print and analyst handoff. Test result sign-off is under Head Analysis → Results."
                        refreshing={isRefreshing}
                        refreshLabel="Updating queue…"
                        actions={
                            canSelect && selected.length > 0 ? (
                                <Button
                                    size="sm"
                                    className="h-8 bg-nppc-900 text-xs hover:bg-nppc-700"
                                    onClick={() => setConfirmBatch(true)}
                                >
                                    Approve selected ({selected.length})
                                </Button>
                            ) : undefined
                        }
                    />

                    <QueueSummaryCards
                        cards={summaryCards}
                        activeId={filters.tab}
                        onSelect={(id) =>
                            applyFilters({
                                tab: id as 'pending' | 'approved',
                                q: query.trim(),
                            })
                        }
                        className="sm:grid-cols-2 lg:grid-cols-2"
                    />

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div className="space-y-3 border-b border-slate-200 p-3 sm:p-4">
                            <QueueFilterBar
                                chips={[
                                    {
                                        id: 'pending',
                                        label: 'Awaiting JO',
                                        count: counts.pending,
                                    },
                                    {
                                        id: 'approved',
                                        label: 'JO approved',
                                        count: counts.approved,
                                    },
                                ]}
                                activeId={filters.tab}
                                onChip={(id) =>
                                    applyFilters({
                                        tab: id,
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
                                placeholder="Search reference, customer…"
                            />

                            {canSelect && selected.length > 0 ? (
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-nppc-900/20 bg-nppc-50 px-3 py-2.5">
                                    <p className="text-sm font-medium text-nppc-900">
                                        {selected.length} Job Order
                                        {selected.length === 1 ? '' : 's'}{' '}
                                        selected
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-8 text-xs shadow-xs"
                                            onClick={() => setSelected([])}
                                        >
                                            Clear
                                        </Button>
                                        <Button
                                            size="sm"
                                            className="h-8 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                                            onClick={() =>
                                                setConfirmBatch(true)
                                            }
                                        >
                                            Approve selected
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            <QueueRangeNote
                                from={orders.from}
                                to={orders.to}
                                total={orders.total}
                                suffix={
                                    pendingTab
                                        ? 'Job Orders waiting for Head JO approval.'
                                        : 'JO-approved Job Orders (not the result release queue).'
                                }
                            />
                        </div>

                        {/* Mobile cards */}
                        <div className="divide-y divide-slate-200/80 md:hidden">
                            {orders.data.map((order) => (
                                <div
                                    key={order.id}
                                    className={cn(
                                        'space-y-3 p-4',
                                        pendingTab && 'bg-amber-50/30',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            {canSelect ? (
                                                <div className="mb-1">
                                                    <Checkbox
                                                        checked={selected.includes(
                                                            order.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleOne(
                                                                order.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                        aria-label={`Select ${order.reference_no}`}
                                                    />
                                                </div>
                                            ) : null}
                                            <JoReferenceChip
                                                referenceNo={order.reference_no}
                                                emphasize={pendingTab}
                                            />
                                            <CustomerCell order={order} />
                                        </div>
                                        <p className="shrink-0 text-xs font-bold tabular-nums text-slate-900">
                                            {money(order.total_cost)}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-3 text-[11px] text-slate-600">
                                        <span>
                                            {order.samples_count} sample
                                            {order.samples_count === 1
                                                ? ''
                                                : 's'}
                                        </span>
                                        <span>
                                            {order.analyses_count} test
                                            {order.analyses_count === 1
                                                ? ''
                                                : 's'}
                                        </span>
                                        <span className="text-slate-500">
                                            {pendingTab
                                                ? order.completed_at
                                                : order.jo_approved_at}
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <OrderStatusBadge
                                            order={order}
                                            pendingTab={pendingTab}
                                        />
                                        <OrderActions
                                            order={order}
                                            pendingTab={pendingTab}
                                        />
                                    </div>
                                </div>
                            ))}
                            {orders.data.length === 0 ? (
                                <div className="px-4 py-12 text-center">
                                    <p className="text-sm font-medium text-nppc-900">
                                        {emptyTitle}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {emptyBody}
                                    </p>
                                </div>
                            ) : null}
                        </div>

                        {/* Desktop table */}
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full min-w-[920px] border-collapse text-left text-xs">
                                <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                    <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                        {canSelect ? (
                                            <th className="px-4 py-3">
                                                <Checkbox
                                                    checked={allSelected}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleAll(
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label="Select all on page"
                                                />
                                            </th>
                                        ) : null}
                                        <th className="px-4 py-3">Reference</th>
                                        <th className="px-4 py-3">Customer</th>
                                        <th className="px-4 py-3">Samples</th>
                                        <th className="px-4 py-3">Tests</th>
                                        <th className="px-4 py-3 text-right">
                                            Total
                                        </th>
                                        <th className="px-4 py-3">
                                            {pendingTab
                                                ? 'Priced'
                                                : 'JO approved'}
                                        </th>
                                        {approvedTab ? (
                                            <th className="px-4 py-3">Status</th>
                                        ) : null}
                                        <th className="min-w-[12rem] px-4 py-3 text-right">
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
                                                pendingTab && 'bg-amber-50/30',
                                            )}
                                        >
                                            {canSelect ? (
                                                <td className="px-4 py-4 align-top">
                                                    <Checkbox
                                                        checked={selected.includes(
                                                            order.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleOne(
                                                                order.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                        aria-label={`Select ${order.reference_no}`}
                                                    />
                                                </td>
                                            ) : null}
                                            <td className="px-4 py-4 align-top whitespace-nowrap">
                                                <JoReferenceChip
                                                    referenceNo={
                                                        order.reference_no
                                                    }
                                                    emphasize={pendingTab}
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <CustomerCell order={order} />
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
                                                {pendingTab ? (
                                                    order.completed_at
                                                ) : (
                                                    <div>
                                                        <div>
                                                            {
                                                                order.jo_approved_at
                                                            }
                                                        </div>
                                                        {order.jo_approver_name ? (
                                                            <div>
                                                                by{' '}
                                                                {
                                                                    order.jo_approver_name
                                                                }
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                )}
                                            </td>
                                            {approvedTab ? (
                                                <td className="px-4 py-4 align-top whitespace-nowrap">
                                                    <OrderStatusBadge
                                                        order={order}
                                                        pendingTab={false}
                                                    />
                                                </td>
                                            ) : null}
                                            <td className="px-4 py-4 text-right align-top">
                                                <OrderActions
                                                    order={order}
                                                    pendingTab={pendingTab}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                    {orders.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={
                                                    canSelect
                                                        ? 8
                                                        : approvedTab
                                                          ? 8
                                                          : 7
                                                }
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
                                label="job orders"
                            />
                        </div>
                    </div>
                </LimsWorkspace>
            </NavigationLoadingShell>

            <ConfirmDialog
                open={confirmBatch}
                onOpenChange={setConfirmBatch}
                title={`Approve ${selected.length} Job Order${selected.length === 1 ? '' : 's'}?`}
                description="Approves costing so Receiving can print 3 JO copies (customer, accounting, Head) and send work to analysts. This does not sign test results — use Head Analysis → Results for that."
                confirmLabel="Approve Job Orders"
                processingLabel="Approving…"
                processing={signing}
                onConfirm={runBatch}
            />
        </>
    );
}

HeadJoIndex.layout = {
    breadcrumbs: [
        { title: 'Head Analysis', href: '/head' },
        { title: 'JO Approval', href: '/head/jo' },
    ],
};
