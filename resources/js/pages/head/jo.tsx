import { Head, Link, router } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { Download } from 'lucide-react';
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

    const emptyMessage = filters.q
        ? 'No job orders match your search.'
        : pendingTab
          ? 'No Job Orders waiting for Head approval.'
          : 'No approved Job Orders yet.';

    return (
        <>
            <Head title="JO approval" />
            <LabQueueRealtime role="head" only={['orders', 'counts']} />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-3">
                    <WorkspaceHeader
                        title="JO approval"
                        description="Approve Job Orders after costing so Receiving can print JO copies and send work to analysts."
                        refreshing={isRefreshing}
                        refreshLabel="Updating queue…"
                        actions={
                            canSelect && selected.length > 0 ? (
                                <Button
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                    onClick={() => setConfirmBatch(true)}
                                >
                                    Approve selected ({selected.length})
                                </Button>
                            ) : undefined
                        }
                    />

                    <div className="grid gap-3 sm:grid-cols-2">
                        <SummaryStat
                            label="Awaiting JO approval"
                            value={counts.pending}
                        />
                        <SummaryStat
                            label="Approved (archive)"
                            value={counts.approved}
                            tone="success"
                        />
                    </div>

                    <QueueFilterBar
                        chips={[
                            {
                                id: 'pending',
                                label: 'Awaiting',
                                count: counts.pending,
                            },
                            {
                                id: 'approved',
                                label: 'Approved',
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
                    />

                    {canSelect && selected.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#1A3694]/20 bg-[#eef3fb] px-4 py-3">
                            <p className="text-sm font-medium text-[#1A3694]">
                                {selected.length} Job Order
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
                                    Approve selected
                                </Button>
                            </div>
                        </div>
                    )}

                    <QueueRangeNote
                        from={orders.from}
                        to={orders.to}
                        total={orders.total}
                        suffix={
                            pendingTab
                                ? 'Job Orders waiting for Head costing approval.'
                                : 'approved Job Orders (view / download JO form).'
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
                                        {pendingTab ? 'Priced' : 'Approved'}
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
                                            pendingTab && 'bg-[#eef3fb]/35',
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
                                            {pendingTab ? (
                                                order.completed_at
                                            ) : (
                                                <div>
                                                    <div>
                                                        {order.jo_approved_at}
                                                    </div>
                                                    {order.jo_approver_name && (
                                                        <div className="text-xs">
                                                            by{' '}
                                                            {
                                                                order.jo_approver_name
                                                            }
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            <div className="flex flex-wrap justify-end gap-2">
                                                {pendingTab ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-200 bg-amber-50 text-amber-900"
                                                    >
                                                        Needs JO approval
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-200 bg-emerald-50 text-emerald-800"
                                                    >
                                                        Approved
                                                    </Badge>
                                                )}
                                                {approvedTab &&
                                                    order.jo_pdf_url && (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <a
                                                                href={
                                                                    order.jo_pdf_url
                                                                }
                                                            >
                                                                <Download className="size-3.5" />
                                                                Download JO
                                                            </a>
                                                        </Button>
                                                    )}
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className={
                                                        pendingTab
                                                            ? 'bg-[#1A3694] hover:bg-[#365BB0]'
                                                            : undefined
                                                    }
                                                    variant={
                                                        pendingTab
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    <Link
                                                        href={`/head/${order.id}`}
                                                    >
                                                        {pendingTab
                                                            ? 'Review & approve JO'
                                                            : 'Open'}
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
                title={`Approve ${selected.length} Job Order${selected.length === 1 ? '' : 's'}?`}
                description="Approves costing so Receiving can print 3 JO copies (customer, accounting, Head) and send to analysts. Payment stays outside this system."
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
        { title: 'JO approval', href: '/head/jo' },
    ],
};
