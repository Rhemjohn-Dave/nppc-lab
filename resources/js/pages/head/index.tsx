import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
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
    signed_at: string | null;
    signed_by?: string | null;
    is_signed: boolean;
    needs_jo_approval?: boolean;
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
        jo_approval: number;
        unsigned: number;
        signed_today: number;
    };
    filters: {
        tab: 'jo_approval' | 'unsigned' | 'signed';
        q: string;
    };
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function HeadIndex({ orders, counts, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [confirmBatch, setConfirmBatch] = useState(false);
    const [signing, setSigning] = useState(false);
    const { isRefreshing } = useInertiaNavigation();

    const tab = filters.tab;
    const joApprovalTab = tab === 'jo_approval';
    const unsignedTab = tab === 'unsigned';
    const canSelect = joApprovalTab || unsignedTab;
    const emphasizeRef = joApprovalTab || unsignedTab;

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
            '/head',
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
            joApprovalTab ? '/head/approve-batch' : '/head/sign-batch',
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

    const dateColumnLabel = joApprovalTab
        ? 'Priced'
        : unsignedTab
          ? 'Completed'
          : 'Released';

    const emptyTitle = filters.q
        ? 'No matching job orders'
        : joApprovalTab
          ? 'Queue is clear'
          : unsignedTab
            ? 'Queue is clear'
            : 'No releases today';
    const emptyBody = filters.q
        ? 'No job orders match your search.'
        : joApprovalTab
          ? 'No Job Orders waiting for Head approval.'
          : unsignedTab
            ? 'No finished jobs awaiting result release.'
            : 'No results released today yet.';

    const batchVerb = joApprovalTab ? 'Approve' : 'Release';
    const batchNoun = joApprovalTab ? 'Job Order' : 'result file';

    const kpiTotal = Math.max(
        counts.jo_approval + counts.unsigned + counts.signed_today,
        1,
    );
    const summaryCards = [
        {
            id: 'jo_approval',
            label: 'Awaiting JO Approval',
            value: counts.jo_approval,
            unit:
                counts.jo_approval > 0 ? 'Action required' : 'None waiting',
            tone:
                counts.jo_approval > 0
                    ? ('warning' as const)
                    : ('default' as const),
            share: Math.round((counts.jo_approval / kpiTotal) * 100),
        },
        {
            id: 'unsigned',
            label: 'Awaiting Result Release',
            value: counts.unsigned,
            unit: 'Finished files',
            tone:
                counts.unsigned > 0
                    ? ('warning' as const)
                    : ('default' as const),
            share: Math.round((counts.unsigned / kpiTotal) * 100),
        },
        {
            id: 'signed',
            label: 'Released Today',
            value: counts.signed_today,
            unit: 'Result releases',
            tone: 'success' as const,
            share: Math.round((counts.signed_today / kpiTotal) * 100),
        },
    ];

    return (
        <>
            <Head title="Head Analysis" />
            <LabQueueRealtime role="head" only={['orders', 'counts']} />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-4">
                    <WorkspaceHeader
                        title="Head Analysis"
                        description={
                            joApprovalTab
                                ? 'Approve Job Orders after costing so Receiving can print JO copies and send work to analysts.'
                                : 'Review finished analyses and release results for pickup. Payment stays outside this system.'
                        }
                        refreshing={isRefreshing}
                        refreshLabel="Updating queue…"
                        actions={
                            canSelect && selected.length > 0 ? (
                                <Button
                                    size="sm"
                                    className="h-8 bg-nppc-900 text-xs hover:bg-nppc-700"
                                    onClick={() => setConfirmBatch(true)}
                                >
                                    {batchVerb} selected ({selected.length})
                                </Button>
                            ) : undefined
                        }
                    />

                    <QueueSummaryCards
                        cards={summaryCards}
                        activeId={filters.tab}
                        onSelect={(id) =>
                            applyFilters({
                                tab: id,
                                q: query.trim(),
                            })
                        }
                        className="sm:grid-cols-3 lg:grid-cols-3"
                    />

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                        <div className="space-y-3 border-b border-slate-200 p-3 sm:p-4">
                            <QueueFilterBar
                                chips={[
                                    {
                                        id: 'jo_approval',
                                        label: 'Awaiting JO',
                                        count: counts.jo_approval,
                                    },
                                    {
                                        id: 'unsigned',
                                        label: 'Awaiting release',
                                        count: counts.unsigned,
                                    },
                                    {
                                        id: 'signed',
                                        label: 'Released today',
                                        count: counts.signed_today,
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
                                        {selected.length} {batchNoun}
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
                                            {batchVerb} selected
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            <QueueRangeNote
                                from={orders.from}
                                to={orders.to}
                                total={orders.total}
                                suffix={
                                    joApprovalTab
                                        ? 'Job Orders waiting for Head costing approval.'
                                        : unsignedTab
                                          ? 'finished files awaiting result release.'
                                          : 'results released today.'
                                }
                            />
                        </div>

                        <div className="overflow-x-auto">
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
                                            {dateColumnLabel}
                                        </th>
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
                                                emphasizeRef && 'bg-amber-50/30',
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
                                                    emphasize={emphasizeRef}
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
                                                {joApprovalTab ||
                                                unsignedTab ? (
                                                    order.completed_at
                                                ) : (
                                                    <div>
                                                        <div>
                                                            {order.signed_at}
                                                        </div>
                                                        {order.signed_by ? (
                                                            <div>
                                                                by{' '}
                                                                {
                                                                    order.signed_by
                                                                }
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-right align-top">
                                                <div className="inline-flex flex-wrap items-center justify-end gap-1.5">
                                                    {joApprovalTab ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-200 bg-amber-50 text-[10px] text-amber-900"
                                                        >
                                                            Needs JO approval
                                                        </Badge>
                                                    ) : order.is_signed ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-800"
                                                        >
                                                            Released
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-200 bg-amber-50 text-[10px] text-amber-900"
                                                        >
                                                            Awaiting release
                                                        </Badge>
                                                    )}
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        className={cn(
                                                            'h-8 gap-1.5 text-xs shadow-xs',
                                                            canSelect
                                                                ? 'border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                                                : 'bg-nppc-900 hover:bg-nppc-700',
                                                        )}
                                                    >
                                                        <Link
                                                            href={`/head/${order.id}`}
                                                        >
                                                            {joApprovalTab
                                                                ? 'Review & approve JO'
                                                                : order.is_signed
                                                                  ? 'Open'
                                                                  : 'Release results'}
                                                            <ArrowRight className="size-3" />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {orders.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={canSelect ? 8 : 7}
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
                title={
                    joApprovalTab
                        ? `Approve ${selected.length} Job Order${selected.length === 1 ? '' : 's'}?`
                        : `Release ${selected.length} result file${selected.length === 1 ? '' : 's'}?`
                }
                description={
                    joApprovalTab
                        ? 'Approves costing so Receiving can print 3 JO copies (customer, accounting, Head) and send to analysts. Payment stays outside this system.'
                        : 'Releases finished results for pickup and unlocks dated result print. Customers are emailed when email is on file.'
                }
                confirmLabel={
                    joApprovalTab ? 'Approve Job Orders' : 'Release results'
                }
                processingLabel={joApprovalTab ? 'Approving…' : 'Releasing…'}
                processing={signing}
                onConfirm={runBatch}
            />
        </>
    );
}

HeadIndex.layout = {
    breadcrumbs: [{ title: 'Head Analysis', href: '/head' }],
};
