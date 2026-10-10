import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
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

function isAwaitingHead(status: string) {
    return status === 'pending_jo_approval' || status === 'priced';
}

function OrderActions({
    order,
    printed,
}: {
    order: Order;
    printed: boolean;
}) {
    const action = nextAction(order, { printedThisSession: printed });
    const awaitingHead = isAwaitingHead(order.status);

    return (
        <div className="inline-flex flex-wrap items-center justify-end gap-1.5">
            {action.printHref && action.printLabel ? (
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="h-8 gap-1.5 border-slate-200 text-xs shadow-xs"
                >
                    <Link href={action.printHref}>{action.printLabel}</Link>
                </Button>
            ) : null}
            <Button
                asChild
                size="sm"
                className={cn(
                    'h-8 gap-1.5 text-xs shadow-xs',
                    awaitingHead
                        ? 'border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
                        : 'bg-nppc-900 hover:bg-nppc-700',
                )}
            >
                <Link href={action.primaryHref}>
                    {action.primaryLabel}
                    <ArrowRight className="size-3" />
                </Link>
            </Button>
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

    const kpiTotal = Math.max(
        counts.all ||
            counts.draft_submitted +
                counts.pending_jo_approval +
                counts.jo_approved +
                counts.reviewed,
        1,
    );

    const summaryCards = [
        {
            id: '',
            label: 'In Queue',
            value: counts.all,
            unit: 'Orders pending',
            tone: 'default' as const,
            share: Math.round((counts.all / kpiTotal) * 100),
        },
        {
            id: 'draft_submitted',
            label: 'Needs Pricing',
            value: counts.draft_submitted,
            unit:
                counts.draft_submitted > 0
                    ? 'Needs attention'
                    : 'All priced',
            tone:
                counts.draft_submitted > 0
                    ? ('warning' as const)
                    : ('default' as const),
            share: Math.round((counts.draft_submitted / kpiTotal) * 100),
        },
        {
            id: 'pending_jo_approval',
            label: 'Awaiting Head JO',
            value: counts.pending_jo_approval,
            unit:
                counts.pending_jo_approval > 0
                    ? 'Action required'
                    : 'None waiting',
            tone:
                counts.pending_jo_approval > 0
                    ? ('warning' as const)
                    : ('default' as const),
            share: Math.round((counts.pending_jo_approval / kpiTotal) * 100),
        },
        {
            id: 'jo_approved',
            label: 'Ready for Analysts',
            value: counts.jo_approved,
            unit: 'Approved by Head',
            tone: 'success' as const,
            share: Math.round((counts.jo_approved / kpiTotal) * 100),
        },
        {
            id: 'reviewed',
            label: 'Released',
            value: counts.reviewed,
            unit: 'Results released',
            tone: 'info' as const,
            share: Math.round((counts.reviewed / kpiTotal) * 100),
        },
    ];

    const rangeSuffix =
        filters.status === 'draft_submitted'
            ? 'job orders that still need pricing.'
            : filters.status === 'pending_jo_approval'
              ? 'job orders waiting for Head JO approval.'
              : filters.status === 'jo_approved'
                ? 'approved Job Orders ready to print and send to analysts.'
                : filters.status === 'reviewed'
                  ? 'jobs with released results. Reprint JO copies for the customer packet.'
                  : 'job orders across the full receiving queue.';

    return (
        <>
            <Head title="Receiving" />
            <LabQueueRealtime role="receiving" only={['orders', 'counts']} />
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-4">
                    <WorkspaceHeader
                        title="Receiving queue"
                        description="ISO/IEC 17025 sample intake, request review, and custody handoff"
                        refreshing={isRefreshing}
                        refreshLabel="Updating queue…"
                        actions={
                            <Button
                                asChild
                                size="sm"
                                className="h-8 gap-1.5 bg-nppc-900 text-xs hover:bg-nppc-700"
                            >
                                <Link href="/intake">
                                    <Plus className="size-3.5" />
                                    New Job Order Intake
                                </Link>
                            </Button>
                        }
                    />

                    <div className="flex flex-wrap items-center gap-2 rounded-lg border border-blue-100 bg-gradient-to-r from-blue-50/90 via-slate-50 to-indigo-50/50 px-3 py-2 text-xs text-slate-700">
                        <span className="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-blue-950 uppercase">
                            Pipeline
                        </span>
                        <span className="flex flex-wrap items-center gap-1 font-medium">
                            <span>Price</span>
                            <ArrowRight className="size-3 text-slate-400" />
                            <span>Head JO approval</span>
                            <ArrowRight className="size-3 text-slate-400" />
                            <span className="font-semibold text-nppc-900">
                                Print JO ×3
                            </span>
                            <ArrowRight className="size-3 text-slate-400" />
                            <span>Send to analysts</span>
                        </span>
                        <span className="text-[11px] font-medium text-amber-800">
                            Do not send to analysts until Head digitally
                            approves the Job Order.
                        </span>
                    </div>

                    <QueueSummaryCards
                        cards={summaryCards}
                        activeId={filters.status || ''}
                        onSelect={(id) =>
                            applyFilters({ status: id, q: query.trim() })
                        }
                        className="grid-cols-2 lg:grid-cols-5"
                    />

                    <div className="flex flex-col gap-2">
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
                            sortValue={sort}
                            onSortChange={(value) =>
                                applyFilters({ sort: value })
                            }
                            sortOptions={[
                                { value: 'oldest', label: 'Oldest First' },
                                { value: 'newest', label: 'Newest First' },
                            ]}
                        />
                        <QueueRangeNote
                            from={orders.from}
                            to={orders.to}
                            total={orders.total}
                            suffix={rangeSuffix}
                        />
                    </div>

                    {/* Mobile cards */}
                    <div className="grid gap-2.5 md:hidden">
                        {orders.data.map((order) => {
                            const printed = printFlags[order.id] ?? false;

                            return (
                                <article
                                    key={order.id}
                                    className={cn(
                                        'rounded-xl border border-slate-200 bg-white p-4 shadow-xs',
                                        order.status === 'jo_approved' &&
                                            'border-emerald-200 bg-emerald-50/20',
                                        isAwaitingHead(order.status) &&
                                            'border-amber-200 bg-amber-50/30',
                                    )}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <JoReferenceChip
                                                referenceNo={
                                                    order.reference_no
                                                }
                                                emphasize={isAwaitingHead(
                                                    order.status,
                                                )}
                                            />
                                            <p className="font-semibold text-slate-900">
                                                {order.customer_name}
                                            </p>
                                            <p className="text-[11px] text-slate-500">
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
                                            className={cn(
                                                'text-[10px]',
                                                receivingStatusBadgeClass(
                                                    order.status,
                                                    Boolean(order.reviewed),
                                                ),
                                            )}
                                        >
                                            {receivingStatusLabel(order)}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-600">
                                        <span>
                                            {order.samples_count} sample
                                            {order.samples_count === 1
                                                ? ''
                                                : 's'}{' '}
                                            · {order.analyses_count} test
                                            {order.analyses_count === 1
                                                ? ''
                                                : 's'}
                                        </span>
                                        <span className="font-semibold text-slate-900">
                                            {money(order.total_cost)}
                                        </span>
                                        <span className="text-slate-500">
                                            {order.created_at ?? '—'}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex justify-end">
                                        <OrderActions
                                            order={order}
                                            printed={printed}
                                        />
                                    </div>
                                </article>
                            );
                        })}
                        {orders.data.length === 0 && (
                            <div className="rounded-xl border border-slate-200 bg-white px-4 py-12 text-center shadow-xs">
                                <p className="text-sm font-medium text-nppc-900">
                                    {filters.q || filters.status
                                        ? 'No matching job orders'
                                        : 'Queue is clear'}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {filters.q || filters.status
                                        ? 'No job orders match your filters.'
                                        : 'No job orders waiting for receiving.'}
                                </p>
                            </div>
                        )}
                        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xs">
                            <TablePagination
                                links={orders.links}
                                from={orders.from}
                                to={orders.to}
                                total={orders.total}
                                label="job orders"
                            />
                        </div>
                    </div>

                    {/* Desktop table */}
                    <div className="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs md:block">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[920px] border-collapse text-left text-xs">
                                <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                    <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                        <th className="px-4 py-3">Reference</th>
                                        <th className="px-4 py-3">
                                            Customer &amp; account
                                        </th>
                                        <th className="px-4 py-3">
                                            Samples &amp; tests
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Billing
                                        </th>
                                        <th className="px-4 py-3">Submitted</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="min-w-[12rem] px-4 py-3 text-right">
                                            Workflow action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200/80">
                                    {orders.data.map((order) => {
                                        const printed =
                                            printFlags[order.id] ?? false;
                                        const awaitingHead = isAwaitingHead(
                                            order.status,
                                        );

                                        return (
                                            <tr
                                                key={order.id}
                                                className={cn(
                                                    'transition-colors hover:bg-slate-50',
                                                    order.status ===
                                                        'jo_approved' &&
                                                        'bg-emerald-50/20',
                                                    awaitingHead &&
                                                        'bg-amber-50/30',
                                                )}
                                            >
                                                <td className="px-4 py-4 align-top whitespace-nowrap">
                                                    <JoReferenceChip
                                                        referenceNo={
                                                            order.reference_no
                                                        }
                                                        emphasize={awaitingHead}
                                                    />
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <p className="font-semibold text-slate-900">
                                                        {order.customer_name}
                                                    </p>
                                                    <p className="mt-0.5 max-w-xs truncate text-[11px] text-slate-500">
                                                        {[
                                                            order.company_name,
                                                            order.customer_contact,
                                                            order.customer_email,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') || '—'}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-4 align-top whitespace-nowrap">
                                                    <span className="font-medium text-slate-800">
                                                        {order.samples_count}{' '}
                                                        sample
                                                        {order.samples_count ===
                                                        1
                                                            ? ''
                                                            : 's'}
                                                    </span>
                                                    <div className="mt-0.5 text-[11px] text-slate-500">
                                                        {order.analyses_count}{' '}
                                                        test
                                                        {order.analyses_count ===
                                                        1
                                                            ? ''
                                                            : 's'}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-right align-top text-xs font-bold whitespace-nowrap text-slate-900 tabular-nums">
                                                    {money(order.total_cost)}
                                                </td>
                                                <td className="px-4 py-4 align-top whitespace-nowrap text-slate-500">
                                                    {order.created_at ?? '—'}
                                                </td>
                                                <td className="px-4 py-4 align-top whitespace-nowrap">
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'text-[10px]',
                                                            receivingStatusBadgeClass(
                                                                order.status,
                                                                Boolean(
                                                                    order.reviewed,
                                                                ),
                                                            ),
                                                        )}
                                                    >
                                                        {receivingStatusLabel(
                                                            order,
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4 text-right align-top">
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
                                                colSpan={7}
                                                className="px-4 py-12 text-center"
                                            >
                                                <p className="text-sm font-medium text-nppc-900">
                                                    {filters.q ||
                                                    filters.status
                                                        ? 'No matching job orders'
                                                        : 'Queue is clear'}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {filters.q ||
                                                    filters.status
                                                        ? 'No job orders match your filters.'
                                                        : 'No job orders waiting for receiving.'}
                                                </p>
                                            </td>
                                        </tr>
                                    )}
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
        </>
    );
}

ReceivingIndex.layout = {
    breadcrumbs: [{ title: 'Receiving', href: '/receiving' }],
};
