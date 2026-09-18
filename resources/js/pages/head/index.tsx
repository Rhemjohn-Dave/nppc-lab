import { Head, Link, router } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { useEffect, useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import SummaryStat from '@/components/summary-stat';
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
    const signedTodayTab = tab === 'signed';
    const resultsPrimary = unsignedTab || signedTodayTab;
    const canSelect = joApprovalTab || unsignedTab;

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

    function selectPrimary(primary: 'jo' | 'results') {
        if (primary === 'jo') {
            applyFilters({ tab: 'jo_approval', q: query.trim() });
            return;
        }

        // Keep Released today if already there; otherwise open Awaiting release.
        applyFilters({
            tab: signedTodayTab ? 'signed' : 'unsigned',
            q: query.trim(),
        });
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

    const emptyMessage = filters.q
        ? 'No job orders match your search.'
        : joApprovalTab
          ? 'No Job Orders waiting for Head approval.'
          : unsignedTab
            ? 'No finished jobs awaiting result release.'
            : 'No results released today yet.';

    const batchVerb = joApprovalTab ? 'Approve' : 'Release';
    const batchNoun = joApprovalTab ? 'Job Order' : 'result file';
    return (
        <>
            <Head title="Head Analysis" />
            <LabQueueRealtime role="head" only={['orders', 'counts']} />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-3">
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
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                    onClick={() => setConfirmBatch(true)}
                                >
                                    {batchVerb} selected ({selected.length})
                                </Button>
                            ) : undefined
                        }
                    />

                    <div
                        className="grid grid-cols-2 gap-1 rounded-xl border bg-[#f8fafc] p-1"
                        role="tablist"
                        aria-label="Head work areas"
                    >
                        <button
                            type="button"
                            role="tab"
                            aria-selected={joApprovalTab}
                            onClick={() => selectPrimary('jo')}
                            className={cn(
                                'rounded-lg px-3 py-3 text-left transition',
                                joApprovalTab
                                    ? 'bg-[#1A3694] text-white shadow-sm'
                                    : 'text-slate-700 hover:bg-white',
                            )}
                        >
                            <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                                JO approval
                            </p>
                            <p className="mt-0.5 font-medium">
                                Costing &amp; Job Order
                            </p>
                            <p
                                className={cn(
                                    'mt-1 text-sm tabular-nums',
                                    joApprovalTab
                                        ? 'text-white/90'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {counts.jo_approval} waiting
                            </p>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected={resultsPrimary}
                            onClick={() => selectPrimary('results')}
                            className={cn(
                                'rounded-lg px-3 py-3 text-left transition',
                                resultsPrimary
                                    ? 'bg-[#1A3694] text-white shadow-sm'
                                    : 'text-slate-700 hover:bg-white',
                            )}
                        >
                            <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                                Results
                            </p>
                            <p className="mt-0.5 font-medium">
                                Release finished files
                            </p>
                            <p
                                className={cn(
                                    'mt-1 text-sm tabular-nums',
                                    resultsPrimary
                                        ? 'text-white/90'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {counts.unsigned} awaiting ·{' '}
                                {counts.signed_today} today
                            </p>
                        </button>
                    </div>

                    {joApprovalTab ? (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <SummaryStat
                                label="Waiting for JO approval"
                                value={counts.jo_approval}
                            />
                            <div className="rounded-xl border bg-white px-4 py-3 text-sm text-muted-foreground">
                                Approving a Job Order unlocks Receiving to print
                                3 JO copies and send the job to analysts.
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <SummaryStat
                                label="Awaiting result release"
                                value={counts.unsigned}
                            />
                            <SummaryStat
                                label="Released today"
                                value={counts.signed_today}
                                tone="success"
                            />
                        </div>
                    )}

                    <QueueFilterBar
                        chips={
                            resultsPrimary
                                ? [
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
                                  ]
                                : []
                        }
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
                    />

                    {canSelect && selected.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#1A3694]/20 bg-[#eef3fb] px-4 py-3">
                            <p className="text-sm font-medium text-[#1A3694]">
                                {selected.length} {batchNoun}
                                {selected.length === 1 ? '' : 's'} selected
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSelected([])}
                                >
                                    Clear
                                </Button>
                                <Button
                                    size="sm"
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                    onClick={() => setConfirmBatch(true)}
                                >
                                    {batchVerb} selected
                                </Button>
                            </div>
                        </div>
                    )}

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
                    <div className="overflow-x-auto rounded-xl border bg-white">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 z-10 bg-[#f8fafc] text-left">
                                <tr>
                                    {canSelect && (
                                        <th className="px-3 py-3">
                                            <Checkbox
                                                checked={allSelected}
                                                onCheckedChange={(checked) =>
                                                    toggleAll(checked === true)
                                                }
                                                aria-label="Select all on page"
                                            />
                                        </th>
                                    )}
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
                                        {dateColumnLabel}
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
                                            !order.is_signed &&
                                                'bg-[#eef3fb]/35',
                                        )}
                                    >
                                        {canSelect && (
                                            <td className="px-3 py-3">
                                                <Checkbox
                                                    checked={selected.includes(
                                                        order.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleOne(
                                                            order.id,
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`Select ${order.reference_no}`}
                                                />
                                            </td>
                                        )}
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
                                            {joApprovalTab || unsignedTab ? (
                                                order.completed_at
                                            ) : (
                                                <div>
                                                    <div>
                                                        {order.signed_at}
                                                    </div>
                                                    {order.signed_by && (
                                                        <div className="text-xs">
                                                            by{' '}
                                                            {order.signed_by}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <div className="flex flex-wrap justify-end gap-2">
                                                {joApprovalTab ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-200 bg-amber-50 text-amber-900"
                                                    >
                                                        Needs JO approval
                                                    </Badge>
                                                ) : order.is_signed ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-200 bg-emerald-50 text-emerald-800"
                                                    >
                                                        Released
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]"
                                                    >
                                                        Awaiting release
                                                    </Badge>
                                                )}
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className={
                                                        canSelect
                                                            ? 'bg-[#1A3694] hover:bg-[#365BB0]'
                                                            : undefined
                                                    }
                                                    variant={
                                                        canSelect
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    <Link
                                                        href={`/head/${order.id}`}
                                                    >
                                                        {joApprovalTab
                                                            ? 'Review & approve JO'
                                                            : order.is_signed
                                                              ? 'Open'
                                                              : 'Release results'}
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {orders.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={canSelect ? 8 : 7}
                                            className="px-3 py-12 text-center text-muted-foreground"
                                        >
                                            {emptyMessage}
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
