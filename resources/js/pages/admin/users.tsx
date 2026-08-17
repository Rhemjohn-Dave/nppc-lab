import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
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

    return 'border-emerald-200 bg-emerald-50 text-emerald-800';
}

export default function AdminUsers({
    users,
    roles,
    filters,
    counts,
}: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [query, setQuery] = useState(filters.q ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

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

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        applyFilters({ q: query });
    }

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Users
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage staff accounts and Spatie roles. Create and
                            edit users in modals.
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>
                    <Button
                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                        onClick={() => setCreateOpen(true)}
                    >
                        <Plus className="size-4" />
                        Add user
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Total users
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counts.all ?? 0}
                        </p>
                    </div>
                    {roles.map((role) => (
                        <div
                            key={role}
                            className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                {roleLabel(role)}
                            </p>
                            <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                                {counts[role] ?? 0}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => applyFilters({ role: 'all' })}
                            className={cn(
                                'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium transition',
                                filters.role === 'all'
                                    ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                            )}
                        >
                            All roles
                            <span
                                className={cn(
                                    'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                                    filters.role === 'all'
                                        ? 'bg-white/20'
                                        : 'bg-slate-100 text-slate-600',
                                )}
                            >
                                {counts.all ?? 0}
                            </span>
                        </button>
                        {roles.map((role) => (
                            <button
                                key={role}
                                type="button"
                                onClick={() => applyFilters({ role })}
                                className={cn(
                                    'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium transition',
                                    filters.role === role
                                        ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                )}
                            >
                                {roleLabel(role)}
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                                        filters.role === role
                                            ? 'bg-white/20'
                                            : 'bg-slate-100 text-slate-600',
                                    )}
                                >
                                    {counts[role] ?? 0}
                                </span>
                            </button>
                        ))}
                    </div>

                    <form
                        onSubmit={submitSearch}
                        className="relative min-w-64 flex-1 lg:max-w-sm"
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search name or email…"
                            className="h-10 pl-9"
                        />
                    </form>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-[#f8fafc] text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Name
                                </th>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Email
                                </th>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Role
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-t transition hover:bg-[#f8fafc]"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1.5">
                                                {user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <Badge
                                                            key={role}
                                                            variant="outline"
                                                            className={roleBadgeClass(
                                                                role,
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
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditing(user)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </Button>
                                        </td>
                                    </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        {filters.q || filters.role !== 'all'
                                            ? 'No users match your filters.'
                                            : 'No users yet. Add a staff account to get started.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <TablePagination
                    links={users.links}
                    from={users.from}
                    to={users.to}
                    total={users.total}
                    label="users"
                />
            </div>

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
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
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
