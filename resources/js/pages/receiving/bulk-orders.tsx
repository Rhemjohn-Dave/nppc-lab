import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import JoReferenceChip from '@/components/lims/jo-reference-chip';
import LimsWorkspace from '@/components/lims/lims-workspace';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';
import { cn } from '@/lib/utils';

type BulkOrderRow = {
    id: number;
    reference_no: string;
    customer_name: string;
    company_name: string | null;
    customer_contact: string | null;
    classification: string | null;
    status: string;
    status_label: string;
    samples_count: number;
    samples_named_count?: number;
    samples_encode_complete?: boolean;
    analyses_count: number;
    is_bulk_draft: boolean;
    has_attachment: boolean;
    attachment_original_name: string | null;
    continue_url: string | null;
    encode_url?: string | null;
    show_url: string | null;
    updated_at: string | null;
    created_at: string | null;
};

type Props = {
    orders: {
        data: BulkOrderRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    counts: {
        drafts: number;
        submitted: number;
    };
    filters: {
        tab: 'drafts' | 'submitted';
        q: string;
    };
};

export default function ReceivingBulkOrders({ orders, counts, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const { isRefreshing } = useInertiaNavigation();
    const tab = filters.tab === 'submitted' ? 'submitted' : 'drafts';

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    function applyFilters(next: { tab?: string; q?: string }) {
        router.get(
            '/receiving/bulk-orders',
            {
                tab: next.tab ?? tab,
                q: next.q ?? query,
            },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Bulk orders" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Bulk orders"
                    description="Saved drafts and multi-sample JOs — continue later, then finish with shared tests."
                    refreshing={isRefreshing}
                    refreshLabel="Updating list…"
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button asChild size="sm" variant="outline">
                                <Link href="/receiving">
                                    <ArrowLeft className="size-3.5" />
                                    Queue
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                className="bg-nppc-900 hover:bg-nppc-700"
                            >
                                <Link href="/receiving/bulk-orders/create">
                                    <Plus className="size-3.5" />
                                    New bulk order
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    {(
                        [
                            {
                                key: 'drafts',
                                label: 'Drafts',
                                count: counts.drafts,
                            },
                            {
                                key: 'submitted',
                                label: 'Submitted',
                                count: counts.submitted,
                            },
                        ] as const
                    ).map((item) => (
                        <Button
                            key={item.key}
                            type="button"
                            size="sm"
                            variant={tab === item.key ? 'default' : 'outline'}
                            className={cn(
                                'h-8 text-xs',
                                tab === item.key &&
                                    'bg-nppc-900 hover:bg-nppc-700',
                            )}
                            onClick={() => applyFilters({ tab: item.key })}
                        >
                            {item.label}
                            <span className="ml-1.5 tabular-nums opacity-80">
                                {item.count}
                            </span>
                        </Button>
                    ))}
                    <form
                        className="ml-auto flex min-w-[14rem] flex-1 gap-2 sm:max-w-sm"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters({ q: query });
                        }}
                    >
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search reference or customer…"
                            className="h-8 text-xs"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            className="h-8 text-xs"
                        >
                            Search
                        </Button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] border-collapse text-left text-xs">
                            <thead className="border-b border-slate-200 bg-slate-50/90">
                                <tr className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Reference</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Samples</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Updated</th>
                                    <th className="px-4 py-3 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {orders.data.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="transition-colors hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-3 align-top whitespace-nowrap">
                                            <JoReferenceChip
                                                referenceNo={order.reference_no}
                                            />
                                            {order.has_attachment ? (
                                                <p className="mt-1 max-w-[12rem] truncate text-[10px] text-slate-500">
                                                    {order.attachment_original_name ??
                                                        'Attachment'}
                                                </p>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <p className="font-semibold text-slate-900">
                                                {order.customer_name}
                                            </p>
                                            <p className="mt-0.5 max-w-xs truncate text-[11px] text-slate-500">
                                                {[
                                                    order.company_name,
                                                    order.customer_contact,
                                                    order.classification,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 align-top whitespace-nowrap">
                                            <span className="font-medium text-slate-800">
                                                {order.samples_count} sample
                                                {order.samples_count === 1
                                                    ? ''
                                                    : 's'}
                                            </span>
                                            <div className="mt-0.5 text-[11px] text-slate-500">
                                                {order.analyses_count} test
                                                {order.analyses_count === 1
                                                    ? ''
                                                    : 's'}
                                                {!order.is_bulk_draft &&
                                                order.samples_count >= 2
                                                    ? ` · ${order.samples_named_count ?? 0}/${order.samples_count} named`
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 align-top whitespace-nowrap">
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'text-[10px]',
                                                    order.is_bulk_draft
                                                        ? 'border-amber-200 bg-amber-50 text-amber-900'
                                                        : 'border-slate-200 bg-slate-50 text-slate-700',
                                                )}
                                            >
                                                {order.is_bulk_draft
                                                    ? 'Draft'
                                                    : order.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 align-top whitespace-nowrap text-slate-500">
                                            {order.updated_at ??
                                                order.created_at ??
                                                '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            {order.continue_url ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className="h-8 gap-1.5 bg-nppc-900 text-xs hover:bg-nppc-700"
                                                >
                                                    <Link
                                                        href={order.continue_url}
                                                    >
                                                        Continue
                                                        <ArrowRight className="size-3" />
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <div className="inline-flex flex-col items-end gap-1">
                                                    {order.encode_url &&
                                                    !order.samples_encode_complete ? (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            className="h-8 gap-1.5 bg-nppc-900 text-xs hover:bg-nppc-700"
                                                        >
                                                            <Link
                                                                href={
                                                                    order.encode_url
                                                                }
                                                            >
                                                                Encode
                                                                <ArrowRight className="size-3" />
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                    {order.show_url ? (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-8 gap-1.5 text-xs"
                                                        >
                                                            <Link
                                                                href={
                                                                    order.show_url
                                                                }
                                                            >
                                                                Open JO
                                                                <ArrowRight className="size-3" />
                                                            </Link>
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {orders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                {tab === 'drafts'
                                                    ? 'No saved bulk drafts'
                                                    : 'No submitted bulk orders yet'}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Start a multi-sample JO, then use
                                                Save for later to park a draft
                                                here.
                                            </p>
                                            <Button
                                                asChild
                                                size="sm"
                                                className="mt-4 bg-nppc-900 hover:bg-nppc-700"
                                            >
                                                <Link href="/receiving/bulk-orders/create">
                                                    <Plus className="size-3.5" />
                                                    New bulk order
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                    <TablePagination
                        links={orders.links}
                        from={orders.from}
                        to={orders.to}
                        total={orders.total}
                        label="bulk orders"
                    />
                </div>
            </LimsWorkspace>
        </>
    );
}

ReceivingBulkOrders.layout = {
    breadcrumbs: [
        { title: 'Receiving', href: '/receiving' },
        { title: 'Bulk order', href: '/receiving/bulk-orders' },
    ],
};
