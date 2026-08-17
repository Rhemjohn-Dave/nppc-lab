import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
        priced: number;
    };
    filters: {
        q: string;
        status: string;
    };
};

function statusBadgeClass(status: string) {
    if (status === 'priced') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    return 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]';
}

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function ReceivingIndex({ orders, counts, filters }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [query, setQuery] = useState(filters.q ?? '');

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const id = window.setInterval(() => {
            router.reload({ only: ['orders', 'counts'] });
        }, 20000);

        return () => window.clearInterval(id);
    }, []);

    function applyFilters(next: { q?: string; status?: string }) {
        router.get(
            '/receiving',
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

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        applyFilters({ q: query.trim() });
    }

    const statusChips = [
        { id: '', label: 'All', count: counts.all },
        {
            id: 'draft_submitted',
            label: 'Needs pricing',
            count: counts.draft_submitted,
        },
        { id: 'priced', label: 'Ready to receive', count: counts.priced },
    ] as const;

    return (
        <>
            <Head title="Receiving" />
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Receiving queue
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Review intake requests, set pricing, print the
                            form, then mark samples received. Auto-refreshes
                            every 20 seconds.
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            In queue
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counts.all}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Needs pricing
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counts.draft_submitted}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Ready to receive
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-emerald-800">
                            {counts.priced}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {statusChips.map((chip) => {
                            const active =
                                (filters.status || '') === chip.id;

                            return (
                                <button
                                    key={chip.id || 'all'}
                                    type="button"
                                    onClick={() =>
                                        applyFilters({
                                            status: chip.id,
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

                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-[#f8fafc] text-left">
                            <tr>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Reference
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Customer
                                </th>
                                <th className="px-3 py-3 font-medium text-slate-600">
                                    Status
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
                                    Submitted
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
                                                order.customer_contact,
                                                order.customer_email,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant="outline"
                                            className={statusBadgeClass(
                                                order.status,
                                            )}
                                        >
                                            {order.status ===
                                            'draft_submitted'
                                                ? 'Needs pricing'
                                                : order.status_label}
                                        </Badge>
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
                                        {order.created_at}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                                        >
                                            <Link
                                                href={`/receiving/${order.id}`}
                                            >
                                                {order.status === 'priced'
                                                    ? 'Receive'
                                                    : 'Price & receive'}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-12 text-center text-muted-foreground"
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
            </div>
        </>
    );
}

ReceivingIndex.layout = {
    breadcrumbs: [{ title: 'Receiving', href: '/receiving' }],
};
