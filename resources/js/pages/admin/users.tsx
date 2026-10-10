import { Head, router, useForm } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueSummaryCards from '@/components/lims/queue-summary-cards';
import QueueFilterBar from '@/components/queue-filter-bar';
import WorkspaceHeader from '@/components/workspace-header';
import { ArrowRight, Pencil, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
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
import { cn } from '@/lib/utils';

type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

type Props = {
    users: {
        data: UserRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    roles: string[];
    filters: {
        q: string;
        role: string;
    };
    counts: Record<string, number>;
};

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    receiving: 'Receiving',
    analyst: 'Analyst',
    head_analysis: 'Head of Analysis',
    accounting: 'Accounting',
};

function roleLabel(role: string) {
    return ROLE_LABELS[role] ?? role;
}

function roleBadgeClass(role: string) {
    if (role === 'admin') {
        return 'border-violet-200 bg-violet-50 text-violet-800';
    }

    if (role === 'head_analysis') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    if (role === 'receiving') {
        return 'border-sky-200 bg-sky-50 text-sky-800';
    }

    if (role === 'accounting') {
        return 'border-teal-200 bg-teal-50 text-teal-800';
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-800';
}

export default function AdminUsers({
    users,
    roles,
    filters,
    counts,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);

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
    }, [query, filters.q, filters.role]);

    function applyFilters(next: { q?: string; role?: string }) {
        router.get(
            '/admin/users',
            {
                q: next.q ?? query,
                role: next.role ?? filters.role,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    const totalUsers = counts.all ?? 0;
    const kpiTotal = Math.max(totalUsers, 1);
    const summaryCards = [
        {
            id: 'all',
            label: 'Total Users',
            value: totalUsers,
            unit: 'Staff accounts',
            tone: 'info' as const,
            share: 100,
        },
        ...roles.map((role) => {
            const value = counts[role] ?? 0;
            return {
                id: role,
                label: roleLabel(role),
                value,
                unit: 'Assigned role',
                tone:
                    role === 'head_analysis'
                        ? ('warning' as const)
                        : role === 'admin'
                          ? ('info' as const)
                          : role === 'analyst'
                            ? ('success' as const)
                            : ('default' as const),
                share: Math.round((value / kpiTotal) * 100),
            };
        }),
    ];

    const roleChips = [
        { id: 'all', label: 'All roles', count: totalUsers },
        ...roles.map((role) => ({
            id: role,
            label: roleLabel(role),
            count: counts[role] ?? 0,
        })),
    ];

    return (
        <>
            <Head title="Users" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Users"
                    description="Manage staff accounts and Spatie roles. Create and edit users in modals."
                    actions={
                        <Button
                            className="h-8 gap-1.5 bg-nppc-900 text-xs hover:bg-nppc-700"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="size-3.5" />
                            Add user
                        </Button>
                    }
                />

                <QueueSummaryCards
                    cards={summaryCards}
                    activeId={filters.role || 'all'}
                    onSelect={(id) =>
                        applyFilters({ role: id, q: query.trim() })
                    }
                    className="sm:grid-cols-2 lg:grid-cols-6"
                />

                <QueueFilterBar
                    chips={roleChips}
                    activeId={filters.role || 'all'}
                    onChip={(id) =>
                        applyFilters({ role: id, q: query.trim() })
                    }
                    query={query}
                    onQueryChange={setQuery}
                    onSearch={() => applyFilters({ q: query.trim() })}
                    onClear={() => {
                        setQuery('');
                        applyFilters({ q: '' });
                    }}
                    placeholder="Search name or email…"
                />

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Email</th>
                                    <th className="px-4 py-3">Role</th>
                                    <th className="min-w-[8rem] px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="transition-colors hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-4 align-top font-semibold text-slate-900">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-4 align-top text-[11px] text-slate-500">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <div className="flex flex-wrap gap-1.5">
                                                {user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <Badge
                                                            key={role}
                                                            variant="outline"
                                                            className={cn(
                                                                'text-[10px]',
                                                                roleBadgeClass(
                                                                    role,
                                                                ),
                                                            )}
                                                        >
                                                            {roleLabel(role)}
                                                        </Badge>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-right align-top">
                                            <Button
                                                size="sm"
                                                className="h-8 gap-1.5 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                                                onClick={() =>
                                                    setEditing(user)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                                <ArrowRight className="size-3" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {users.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                {filters.q ||
                                                filters.role !== 'all'
                                                    ? 'No matching users'
                                                    : 'No users yet'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {filters.q ||
                                                filters.role !== 'all'
                                                    ? 'No users match your filters.'
                                                    : 'Add a staff account to get started.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                        <TablePagination
                            links={users.links}
                            from={users.from}
                            to={users.to}
                            total={users.total}
                            label="users"
                        />
                    </div>
                </div>
            </LimsWorkspace>

            <UserModal
                open={createOpen}
                onOpenChange={setCreateOpen}
                mode="create"
                roles={roles}
            />
            <UserModal
                open={!!editing}
                onOpenChange={(open) => !open && setEditing(null)}
                mode="edit"
                roles={roles}
                user={editing}
            />
        </>
    );
}

function UserModal({
    open,
    onOpenChange,
    mode,
    roles,
    user = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    roles: string[];
    user?: UserRow | null;
}) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        role: user?.roles[0] ?? 'analyst',
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            name: user?.name ?? '',
            email: user?.email ?? '',
            password: '',
            role: user?.roles[0] ?? 'analyst',
        });
        form.clearErrors();
        // Only re-sync when the dialog opens or the edited user changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (mode === 'create') {
            form.post('/admin/users', {
                onSuccess: () => {
                    form.reset();
                    onOpenChange(false);
                },
            });

            return;
        }

        if (!user) {
            return;
        }

        form.patch(`/admin/users/${user.id}`, {
            onSuccess: () => {
                form.setData('password', '');
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create' ? 'Add user' : 'Edit user'}
                    </DialogTitle>
                    <DialogDescription>
                        {mode === 'create'
                            ? 'Create a staff account and assign a Spatie role.'
                            : 'Update account details and role. Leave password blank to keep the current one.'}
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={submit}>
                    <div>
                        <Label htmlFor="user_name">Name *</Label>
                        <Input
                            id="user_name"
                            className="mt-1"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            autoFocus
                        />
                        {form.errors.name && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="user_email">Email *</Label>
                        <Input
                            id="user_email"
                            type="email"
                            className="mt-1"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                        />
                        {form.errors.email && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.email}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="user_password">
                            Password
                            {mode === 'create' ? ' *' : ' (optional)'}
                        </Label>
                        <Input
                            id="user_password"
                            type="password"
                            className="mt-1"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            required={mode === 'create'}
                            autoComplete="new-password"
                        />
                        {form.errors.password && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.password}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="user_role">Role *</Label>
                        <select
                            id="user_role"
                            className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            value={form.data.role}
                            onChange={(e) =>
                                form.setData('role', e.target.value)
                            }
                        >
                            {roles.map((role) => (
                                <option key={role} value={role}>
                                    {roleLabel(role)}
                                </option>
                            ))}
                        </select>
                        {form.errors.role && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.role}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-nppc-900 hover:bg-nppc-700"
                        >
                            {form.processing
                                ? mode === 'create'
                                    ? 'Creating…'
                                    : 'Saving…'
                                : mode === 'create'
                                  ? 'Create user'
                                  : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

AdminUsers.layout = {
    breadcrumbs: [{ title: 'Users', href: '/admin/users' }],
};
