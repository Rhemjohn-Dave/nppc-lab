import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useMemo } from 'react';
import QueueKpi from '@/components/lims/queue-kpi';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

type RoleOption = {
    id: string;
    label: string;
};

type Props = {
    roles: RoleOption[];
    selected: string[];
};

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    receiving: 'Receiving',
    analyst: 'Analyst',
    head_analysis: 'Head of Analysis',
    accounting: 'Accounting',
};

function roleLabel(role: string, fallback: string) {
    return ROLE_LABELS[role] ?? fallback;
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

function sameRoleSet(a: string[], b: string[]): boolean {
    if (a.length !== b.length) {
        return false;
    }
    const left = [...a].sort();
    const right = [...b].sort();
    return left.every((id, index) => id === right[index]);
}

export default function HistoryAccessAdmin({ roles, selected }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const form = useForm({
        roles: selected.includes('admin')
            ? selected
            : [...selected, 'admin'],
    });

    const enabledCount = form.data.roles.length;
    const isDirty = !sameRoleSet(form.data.roles, selected);

    const notesByRole = useMemo(
        () =>
            Object.fromEntries(
                roles.map((role) => {
                    if (role.id === 'admin') {
                        return [
                            role.id,
                            'Always visible — Admin cannot be removed.',
                        ];
                    }
                    return [
                        role.id,
                        `Show History in the sidebar for ${roleLabel(role.id, role.label)}.`,
                    ];
                }),
            ) as Record<string, string>,
        [roles],
    );

    function toggle(roleId: string, checked: boolean) {
        if (roleId === 'admin') {
            return;
        }

        const next = checked
            ? [...new Set([...form.data.roles, roleId, 'admin'])]
            : form.data.roles.filter((role) => role !== roleId);

        form.setData('roles', next.includes('admin') ? next : [...next, 'admin']);
    }

    return (
        <>
            <Head title="History access" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="History visibility"
                    description="Choose which roles can open the History archive from the sidebar. Admin always keeps access."
                />

                {flash?.success ? (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                        {flash.success}
                    </div>
                ) : null}

                <div className="grid gap-3 sm:grid-cols-3">
                    <QueueKpi
                        label="Roles"
                        value={roles.length}
                        hint="in catalog"
                    />
                    <QueueKpi
                        label="History enabled"
                        value={enabledCount}
                        hint={enabledCount === 1 ? 'role' : 'roles'}
                        tone="info"
                    />
                    <QueueKpi
                        label="Always on"
                        value={1}
                        hint="Admin locked"
                        tone="success"
                    />
                </div>

                <div className="rounded-lg border border-nppc-200 bg-nppc-50 px-3 py-2 text-xs text-nppc-900">
                    Checked roles see <strong>History</strong> in the sidebar
                    and can open the archive. Unchecked roles are blocked by
                    the History access gate. Changes apply on the next
                    navigation.
                </div>

                <form
                    className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/admin/history-access', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] border-collapse text-left text-xs">
                            <thead className="border-b border-slate-200 bg-slate-50/90">
                                <tr className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Role</th>
                                    <th className="px-4 py-3 text-center">
                                        Sidebar access
                                    </th>
                                    <th className="px-4 py-3">Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {roles.map((role) => {
                                    const locked = role.id === 'admin';
                                    const checked =
                                        locked ||
                                        form.data.roles.includes(role.id);
                                    const label = roleLabel(
                                        role.id,
                                        role.label,
                                    );

                                    return (
                                        <tr
                                            key={role.id}
                                            className={cn(
                                                'transition-colors hover:bg-slate-50',
                                                checked &&
                                                    !locked &&
                                                    'bg-emerald-50/20',
                                            )}
                                        >
                                            <td className="px-4 py-3 align-middle">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-semibold text-slate-900">
                                                        {label}
                                                    </span>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'text-[10px] font-medium',
                                                            roleBadgeClass(
                                                                role.id,
                                                            ),
                                                        )}
                                                    >
                                                        {role.id}
                                                    </Badge>
                                                    {locked ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-violet-200 bg-violet-50 text-[10px] text-violet-800"
                                                        >
                                                            Always on
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center align-middle">
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={locked}
                                                    onCheckedChange={(value) =>
                                                        toggle(
                                                            role.id,
                                                            value === true,
                                                        )
                                                    }
                                                    aria-label={`History access for ${label}`}
                                                />
                                            </td>
                                            <td className="px-4 py-3 align-middle text-[11px] text-slate-500">
                                                {notesByRole[role.id]}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {form.errors.roles ? (
                        <p className="border-t border-slate-200 px-4 py-2 text-xs text-red-600">
                            {form.errors.roles}
                        </p>
                    ) : null}

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                        <p className="text-[11px] text-slate-500">
                            {enabledCount} role
                            {enabledCount === 1 ? '' : 's'} with History
                            {isDirty ? ' · unsaved changes' : ''}
                        </p>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || !isDirty}
                            className="h-8 gap-1.5 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                        >
                            {form.processing
                                ? 'Saving…'
                                : 'Save visibility'}
                            {!form.processing ? (
                                <ArrowRight className="size-3" />
                            ) : null}
                        </Button>
                    </div>
                </form>
            </LimsWorkspace>
        </>
    );
}

HistoryAccessAdmin.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'History access', href: '/admin/history-access' },
    ],
};
