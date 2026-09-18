import { Head, Link, router } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { useEffect, useState } from 'react';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import SummaryStat from '@/components/summary-stat';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';
import { LabQueueRealtime } from '@/hooks/use-lab-queue-realtime';
import {
    hasPrintedThisSession,
    nextAction,
    receivingStatusBadgeClass,
    receivingStatusLabel,
} from '@/lib/receiving-workflow';
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
    jo_approved?: boolean;
    can_print_rfa?: boolean;
    can_receive?: boolean;
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
        pending_jo_approval: number;
        jo_approved: number;
        reviewed: number;
    };
    filters: {
        q: string;
        status: string;
        sort?: string;
    };
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

function OrderActions({
    order,
    printed,
}: {
    order: Order;
    printed: boolean;
}) {
    const action = nextAction(order, { printedThisSession: printed });

    return (
        <div
            className={cn(
                'flex flex-col gap-2 rounded-lg border px-2.5 py-1.5 sm:flex-row sm:items-center sm:justify-between',
                action.emphasize
                    ? 'border-emerald-200 bg-emerald-50/80 text-emerald-950'
                    : 'border-slate-200 bg-slate-50 text-slate-700',
            )}
        >
            <div className="min-w-0 text-left">
                <p className="text-[10px] font-semibold tracking-wide text-[#365BB0] uppercase">
                    Next action
                </p>
                <p className="text-xs font-semibold leading-snug">
                    {action.title}
                </p>
            </div>
            <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                {action.printHref && action.printLabel ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={action.printHref}>{action.printLabel}</Link>
                    </Button>
                ) : null}
                <Button
                    asChild
                    size="sm"
                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                >
                    <Link href={action.primaryHref}>{action.primaryLabel}</Link>
                </Button>
            </div>
        </div>
    );
}

export default function ReceivingIndex({ orders, counts, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [printFlags, setPrintFlags] = useState<Record<number, boolean>>({});
    const { isRefreshing } = useInertiaNavigation();
    const sort = filters.sort === 'newest' ? 'newest' : 'oldest';

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const next: Record<number, boolean> = {};
        orders.data.forEach((order) => {
            next[order.id] = hasPrintedThisSession(order.id);
        });
        setPrintFlags(next);
    }, [orders.data]);

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
    }, [query, filters.q, filters.status, filters.sort]);

    function applyFilters(next: {
        q?: string;
        status?: string;
        sort?: string;
    }) {
        router.get(
            '/receiving',
            {
                q: next.q ?? query,
                status: next.status ?? filters.status,
                sort: next.sort ?? sort,
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
        {
            id: 'pending_jo_approval',
            label: 'Awaiting Head JO',
            count: counts.pending_jo_approval,
        },
        {
            id: 'jo_approved',
            label: 'Ready for analysts',
            count: counts.jo_approved,
        },
        { id: 'reviewed', label: 'Results released', count: counts.reviewed },
    ] as const;

    return (
        <>
            <Head title="Receiving" />
            <LabQueueRealtime role="receiving" only={['orders', 'counts']} />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace>
                    <WorkspaceHeader
                        title="Receiving queue"
                        description="Price → Head JO approval → Print 3 JO copies → Send to analysts"
                        hint="Do not send to analysts until Head approves the Job Order."
                        refreshing={isRefreshing}
                        refreshLabel="Updating queue…"
                    />

                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <SummaryStat label="In queue" value={counts.all} />
                        <SummaryStat
                            label="Needs pricing"
                            value={counts.draft_submitted}
                            tone={
                                counts.draft_submitted > 0
                                    ? 'warning'
                                    : 'default'
                            }
                        />
                        <SummaryStat
                            label="Awaiting Head JO"
                            value={counts.pending_jo_approval}
                            tone={
                                counts.pending_jo_approval > 0
                                    ? 'warning'
                                    : 'default'
                            }
                        />
                        <SummaryStat
                            label="Ready for analysts"
                            value={counts.jo_approved}
                            tone="success"
                        />
                        <SummaryStat
                            label="Results released"
                            value={counts.reviewed}
                            tone="info"
                        />
                    </div>

                    <div className="flex flex-col gap-3">
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
                            placeholder="Search reference, customer, email, contact…"
                        />
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <QueueRangeNote
                                from={orders.from}
                                to={orders.to}
                                total={orders.total}
                                suffix={
                                    filters.status === 'draft_submitted'
                                        ? 'job orders that still need pricing.'
                                        : filters.status ===
                                            'pending_jo_approval'
                                          ? 'job orders waiting for Head JO approval.'
                                          : filters.status === 'jo_approved'
                                            ? 'approved Job Orders ready to print and send to analysts.'
                                            : filters.status === 'reviewed'
                                              ? 'jobs with released results. Reprint JO copies for the customer packet.'
                                              : 'job orders across the full receiving queue.'
                                }
                            />
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <span className="whitespace-nowrap">
                                    Sort by
                                </span>
                                <select
                                    className="h-9 rounded-md border border-slate-200 bg-white px-2 text-sm"
                                    value={sort}
                                    onChange={(event) =>
                                        applyFilters({
                                            sort: event.target.value,
                                        })
                                    }
                                >
                                    <option value="oldest">
                                        Oldest first
                                    </option>
                                    <option value="newest">
                                        Newest first
                                    </option>
                                </select>
                            </label>
                        </div>
                    </div>

                    {/* Mobile cards */}
                    <div className="grid gap-3 md:hidden">
                        {orders.data.map((order) => {
                            const printed = printFlags[order.id] ?? false;

                            return (
                                <article
                                    key={order.id}
                                    className={cn(
                                        'rounded-xl border bg-white p-4 shadow-sm',
                                        order.status === 'jo_approved' &&
                                            'border-emerald-200 bg-emerald-50/20',
                                    )}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-heading text-lg font-semibold text-[#1A3694]">
                                                {order.reference_no}
                                            </p>
                                            <p className="font-medium text-slate-900">
                                                {order.customer_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {[
                                                    order.company_name,
                                                    order.customer_contact,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={receivingStatusBadgeClass(
                                                order.status,
                                                Boolean(order.reviewed),
                                            )}
                                        >
                                            {receivingStatusLabel(order)}
                                        </Badge>
                                    </div>
                                    <dl className="mt-3 grid grid-cols-3 gap-2 text-xs text-slate-600">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Samples
                                            </dt>
                                            <dd className="font-medium tabular-nums">
                                                {order.samples_count}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Tests
                                            </dt>
                                            <dd className="font-medium tabular-nums">
                                                {order.analyses_count}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Total
                                            </dt>
                                            <dd className="font-medium tabular-nums">
                                                {money(order.total_cost)}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Submitted {order.created_at ?? '—'}
                                    </p>
                                    <div className="mt-3">
                                        <OrderActions
                                            order={order}
                                            printed={printed}
                                        />
                                    </div>
                                </article>
                            );
                        })}
                        {orders.data.length === 0 && (
                            <div className="rounded-xl border bg-white px-3 py-12 text-center text-muted-foreground">
                                {filters.q || filters.status
                                    ? 'No job orders match your filters.'
                                    : 'No job orders waiting for receiving.'}
                            </div>
                        )}
                    </div>

                    {/* Desktop table */}
                    <div className="hidden overflow-x-auto rounded-xl border bg-white md:block">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 z-10 bg-[#f8fafc] text-left">
                                <tr>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Reference
                                    </th>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Customer
                                    </th>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Status
                                    </th>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Samples
                                    </th>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Tests
                                    </th>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Total
                                    </th>
                                    <th className="px-2.5 py-2 font-medium text-slate-600">
                                        Submitted
                                    </th>
                                    <th className="min-w-[12rem] px-2.5 py-2 font-medium text-slate-600">
                                        Next action
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((order) => {
                                    const printed =
                                        printFlags[order.id] ?? false;

                                    return (
                                        <tr
                                            key={order.id}
                                            className={cn(
                                                'border-t transition hover:bg-[#f8fafc]',
                                                order.status ===
                                                    'jo_approved' &&
                                                    'bg-emerald-50/30',
                                            )}
                                        >
                                            <td className="px-2.5 py-2 font-semibold text-[#1A3694]">
                                                {order.reference_no}
                                            </td>
                                            <td className="px-2.5 py-2">
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
                                            <td className="px-2.5 py-2">
                                                <Badge
                                                    variant="outline"
                                                    className={receivingStatusBadgeClass(
                                                        order.status,
                                                        Boolean(order.reviewed),
                                                    )}
                                                >
                                                    {receivingStatusLabel(
                                                        order,
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-2.5 py-2 tabular-nums">
                                                {order.samples_count}
                                            </td>
                                            <td className="px-2.5 py-2 tabular-nums">
                                                {order.analyses_count}
                                            </td>
                                            <td className="px-2.5 py-2 tabular-nums">
                                                {money(order.total_cost)}
                                            </td>
                                            <td className="px-2.5 py-2 text-muted-foreground">
                                                {order.created_at}
                                            </td>
                                            <td className="px-2.5 py-2">
                                                <OrderActions
                                                    order={order}
                                                    printed={printed}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                                {orders.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-2.5 py-12 text-center text-muted-foreground"
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
                </LimsWorkspace>
            </NavigationLoadingShell>
        </>
    );
}

ReceivingIndex.layout = {
    breadcrumbs: [{ title: 'Receiving', href: '/receiving' }],
};
