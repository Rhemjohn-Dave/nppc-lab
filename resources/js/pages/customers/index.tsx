import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueFilterBar from '@/components/queue-filter-bar';
import TablePagination from '@/components/table-pagination';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceHeader from '@/components/workspace-header';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';

type CustomerRow = {
    id: number;
    customer_name: string;
    customer_email?: string | null;
    customer_contact?: string | null;
    customer_address?: string | null;
    company_name?: string | null;
    ownership_type?: string | null;
    updated_at?: string | null;
};

type Props = {
    customers: {
        data: CustomerRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        q: string;
    };
    ownership_types: string[];
    total: number;
};

export default function Customers({
    customers,
    filters,
    ownership_types,
    total,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [editing, setEditing] = useState<CustomerRow | null>(null);

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

            search(trimmed);
        }, 350);

        return () => window.clearTimeout(id);
        // Search when the typed query settles.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query, filters.q]);

    function search(q: string) {
        router.get(
            '/customers',
            { q },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Customers" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Customers"
                    description="Directory of customers remembered from intake. Correcting a row updates the next suggestion and leaves existing job orders unchanged."
                />

                <QueueFilterBar
                    chips={[
                        {
                            id: 'all',
                            label: 'All customers',
                            count: total,
                        },
                    ]}
                    activeId="all"
                    onChip={() => search(query.trim())}
                    query={query}
                    onQueryChange={setQuery}
                    onSearch={() => search(query.trim())}
                    onClear={() => {
                        setQuery('');
                        search('');
                    }}
                    placeholder="Search name, company, email, or contact…"
                />

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Company</th>
                                    <th className="px-4 py-3">Email</th>
                                    <th className="px-4 py-3">Contact</th>
                                    <th className="px-4 py-3">Address</th>
                                    <th className="px-4 py-3">Ownership</th>
                                    <th className="px-4 py-3">Updated</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {customers.data.map((customer) => (
                                    <tr
                                        key={customer.id}
                                        className="transition-colors hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-3 font-semibold text-slate-900">
                                            {customer.customer_name}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {customer.company_name || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {customer.customer_email || '—'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-slate-600">
                                            {customer.customer_contact || '—'}
                                        </td>
                                        <td className="max-w-[14rem] px-4 py-3 text-slate-600">
                                            <span className="line-clamp-2">
                                                {customer.customer_address ||
                                                    '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-slate-600">
                                            {customer.ownership_type || '—'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-slate-500">
                                            {customer.updated_at || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-8 gap-1.5 text-xs"
                                                onClick={() =>
                                                    setEditing(customer)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {customers.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                {filters.q
                                                    ? 'No matching customers'
                                                    : 'No customers yet'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {filters.q
                                                    ? 'Try another name, company, email, or contact.'
                                                    : 'Customers appear here after an intake is submitted.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                        <TablePagination
                            links={customers.links}
                            from={customers.from}
                            to={customers.to}
                            total={customers.total}
                            label="customers"
                        />
                    </div>
                </div>
            </LimsWorkspace>

            <CustomerDialog
                customer={editing}
                ownershipTypes={ownership_types}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
            />
        </>
    );
}

function CustomerDialog({
    customer,
    ownershipTypes,
    onOpenChange,
}: {
    customer: CustomerRow | null;
    ownershipTypes: string[];
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        customer_name: '',
        company_name: '',
        customer_email: '',
        customer_contact: '',
        customer_address: '',
        ownership_type: '',
    });

    useEffect(() => {
        if (!customer) {
            return;
        }

        form.setData({
            customer_name: customer.customer_name ?? '',
            company_name: customer.company_name ?? '',
            customer_email: customer.customer_email ?? '',
            customer_contact: customer.customer_contact ?? '',
            customer_address: customer.customer_address ?? '',
            ownership_type: customer.ownership_type ?? '',
        });
        form.clearErrors();
        // Sync when a different customer row is opened.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [customer?.id]);

    function save() {
        if (!customer) {
            return;
        }

        form.patch(`/customers/${customer.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={customer !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit customer</DialogTitle>
                    <DialogDescription>
                        This updates the directory used for the next intake.
                        Job orders already submitted keep their original
                        details.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3">
                    <div>
                        <Label htmlFor="customer_name">Name *</Label>
                        <Input
                            id="customer_name"
                            className="mt-1"
                            value={form.data.customer_name}
                            maxLength={255}
                            autoFocus
                            onChange={(event) =>
                                form.setData(
                                    'customer_name',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.customer_name && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.customer_name}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="company_name">Company</Label>
                        <Input
                            id="company_name"
                            className="mt-1"
                            value={form.data.company_name}
                            maxLength={255}
                            onChange={(event) =>
                                form.setData('company_name', event.target.value)
                            }
                        />
                        {form.errors.company_name && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.company_name}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="customer_email">Email</Label>
                            <Input
                                id="customer_email"
                                type="email"
                                className="mt-1"
                                value={form.data.customer_email}
                                maxLength={255}
                                onChange={(event) =>
                                    form.setData(
                                        'customer_email',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.customer_email && (
                                <p className="mt-1 text-xs text-red-600">
                                    {form.errors.customer_email}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="customer_contact">Contact</Label>
                            <Input
                                id="customer_contact"
                                className="mt-1"
                                value={form.data.customer_contact}
                                maxLength={255}
                                onChange={(event) =>
                                    form.setData(
                                        'customer_contact',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="customer_address">Address</Label>
                        <Input
                            id="customer_address"
                            className="mt-1"
                            value={form.data.customer_address}
                            maxLength={255}
                            onChange={(event) =>
                                form.setData(
                                    'customer_address',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="ownership_type">Ownership</Label>
                        <select
                            id="ownership_type"
                            className="border-input mt-1 flex h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs"
                            value={form.data.ownership_type}
                            onChange={(event) =>
                                form.setData(
                                    'ownership_type',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Not set</option>
                            {ownershipTypes.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </select>
                        {form.errors.ownership_type && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.ownership_type}
                            </p>
                        )}
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={save}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
