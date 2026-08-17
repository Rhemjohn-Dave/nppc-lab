import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import TablePagination from '@/components/table-pagination';
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
import { Input } from '@/components/ui/input';
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
            router.reload({ only: ['orders', 'counts'] });
        }, 20000);

        return () => window.clearInterval(id);
    }, []);

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

    function submitSearch(event: FormEvent) {
        event.preventDefault();
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
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Head Analysis · End-of-day signing
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Results already released when analysts finish.
                            Sign today’s finished files here. The full
                            finished-file archive is in sidebar{' '}
                            <span className="font-medium text-slate-700">
                                History
                            </span>
                            . Auto-refreshes every 20 seconds.
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>
                    {canSelect && selected.length > 0 && (
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={() => setConfirmBatch(true)}
                        >
                            Sign selected ({selected.length})
                        </Button>
                    )}
                </div>

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
                        <p className="font-medium">Review & sign finished files</p>
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
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Awaiting signature
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counts.unsigned}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Signed today
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-emerald-800">
                            {counts.signed_today}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {(
                            [
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
                            ] as const
                        ).map((chip) => {
                            const active = filters.tab === chip.id;

                            return (
                                <button
                                    key={chip.id}
                                    type="button"
                                    onClick={() =>
                                        applyFilters({
                                            tab: chip.id,
                                            q: query.trim(),
                                        })
                                    }
                                    className={cn(
                                        'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium transition',
                                        active
                                            ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                    )}
                                >
                                    {chip.label}
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                                            active
                                                ? 'bg-white/20'
                                                : 'bg-slate-100 text-slate-600',
                                        )}
                                    >
                                        {chip.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <form
                        onSubmit={submitSearch}
                        className="flex w-full max-w-md items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search reference, customer…"
                                className="h-10 pl-9"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            className="h-10"
                        >
                            Search
                        </Button>
                    </form>
                </div>

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

                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-[#f8fafc] text-left">
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
                                    className="border-t transition hover:bg-[#f8fafc]"
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
