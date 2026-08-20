import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import SummaryStat from '@/components/summary-stat';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
        unsigned: number;
        signed_today: number;
    };
    filters: {
        tab: 'unsigned' | 'signed';
        q: string;
    };
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function HeadIndex({ orders, counts, filters }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [query, setQuery] = useState(filters.q ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [confirmBatch, setConfirmBatch] = useState(false);
    const [signing, setSigning] = useState(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(() => new Date());

    const tab = filters.tab;
    const unsignedTab = tab === 'unsigned';
    const signedTodayTab = tab === 'signed';
    const canSelect = unsignedTab;

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

    function signBatch() {
        setSigning(true);
        router.post(
            '/head/sign-batch',
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

    const dateColumnLabel = unsignedTab ? 'Completed' : 'Signed';

    const emptyMessage = filters.q
        ? 'No job orders match your search.'
        : unsignedTab
          ? 'No finished jobs awaiting signature.'
          : 'No jobs signed today yet.';

    return (
        <>
            <Head title="Head Analysis" />
            <div className="flex flex-col gap-5 p-4">
                <WorkspaceHeader
                    title="Head Analysis · Signing queue"
                    description="Results already released when analysts finish. Sign today’s finished files here. The full archive is in History. Auto-refreshes every 20 seconds."
                    flash={flash?.success}
                    refreshing={isRefreshing}
                    lastUpdated={lastUpdated}
                    refreshLabel="Refreshing review queue…"
                    hint="Use this screen for jobs sent by the designated analyst; use History for released files."
                    actions={
                        canSelect && selected.length > 0 ? (
                            <Button
                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                                onClick={() => setConfirmBatch(true)}
                            >
                                Sign selected ({selected.length})
                            </Button>
                        ) : undefined
                    }
                />

                <div className="grid gap-2 rounded-xl border bg-[#f8fafc] p-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg bg-white px-3 py-2 text-slate-700">
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            Step 1
                        </p>
                        <p className="font-medium">
                            Analysts complete results
                        </p>
                    </div>
                    <div
                        className={cn(
                            'rounded-lg px-3 py-2',
                            unsignedTab
                                ? 'bg-[#1A3694] text-white'
                                : 'bg-white text-slate-700',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                            Step 2
                        </p>
                        <p className="font-medium">Review & sign submitted files</p>
                    </div>
                    <div
                        className={cn(
                            'rounded-lg px-3 py-2',
                            signedTodayTab
                                ? 'bg-emerald-700 text-white'
                                : 'bg-white text-slate-700',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                            Step 3
                        </p>
                        <p className="font-medium">Files filed for the day</p>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <SummaryStat
                        label="Awaiting signature"
                        value={counts.unsigned}
                    />
                    <SummaryStat
                        label="Signed today"
                        value={counts.signed_today}
                        tone="success"
                    />
                </div>

                <QueueFilterBar
                    chips={[
                        {
                            id: 'unsigned',
                            label: 'Awaiting signature',
                            count: counts.unsigned,
                        },
                        {
                            id: 'signed',
                            label: 'Signed today',
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
                />

                {canSelect && selected.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#1A3694]/20 bg-[#eef3fb] px-4 py-3">
                        <p className="text-sm font-medium text-[#1A3694]">
                            {selected.length} finished file
                            {selected.length === 1 ? '' : 's'} selected for
                            signature
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
                                Sign selected
                            </Button>
                        </div>
                    </div>
                )}

                <QueueRangeNote
                    from={orders.from}
                    to={orders.to}
                    total={orders.total}
                    suffix={
                        unsignedTab
                            ? 'finished files that are still awaiting signature.'
                            : 'files signed today for end-of-day filing.'
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
                                                onCheckedChange={(checked) =>
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
                                        {unsignedTab ? (
                                            order.completed_at
                                        ) : (
                                            <div>
                                                <div>{order.signed_at}</div>
                                                {order.signed_by && (
                                                    <div className="text-xs">
                                                        by {order.signed_by}
                                                    </div>
                                                )}
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
                                                className={
                                                    unsignedTab
                                                        ? 'bg-[#1A3694] hover:bg-[#365BB0]'
                                                        : undefined
                                                }
                                                variant={
                                                    unsignedTab
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                <Link
                                                    href={`/head/${order.id}`}
                                                >
                                                    {order.is_signed
                                                        ? 'Open'
                                                        : 'Review & sign'}
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
            </div>

            <Dialog open={confirmBatch} onOpenChange={setConfirmBatch}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Sign {selected.length} finished job
                            {selected.length === 1 ? '' : 's'}?
                        </DialogTitle>
                        <DialogDescription>
                            This records your end-of-day signature on the
                            selected Request for Analysis forms. Customers were
                            already notified when analysts finished.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmBatch(false)}
                            disabled={signing}
                        >
                            Cancel
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={signBatch}
                            disabled={signing}
                        >
                            {signing ? 'Signing…' : 'Confirm signatures'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

HeadIndex.layout = {
    breadcrumbs: [{ title: 'Head Analysis', href: '/head' }],
};
