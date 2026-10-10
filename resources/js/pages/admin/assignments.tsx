import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import AssignmentModal, {
    type AssignmentAnalyst,
    type AssignmentGroup,
} from '@/components/admin/assignment-modal';
import QueueKpi from '@/components/lims/queue-kpi';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Props = {
    analysts: AssignmentAnalyst[];
    groups: AssignmentGroup[];
};

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return `${parts[0][0] ?? ''}${parts[1][0] ?? ''}`.toUpperCase();
}

function isActiveAnalyst(analyst: AssignmentAnalyst): boolean {
    return analyst.analysis_type_ids.length > 0;
}

export default function AdminAssignments({ analysts, groups }: Props) {
    const [analystQuery, setAnalystQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>(
        'all',
    );
    const [modalAnalystId, setModalAnalystId] = useState<number | null>(null);

    const totalProcedures = useMemo(
        () => groups.reduce((sum, group) => sum + group.items.length, 0),
        [groups],
    );

    const modalAnalyst = useMemo(
        () =>
            modalAnalystId == null
                ? null
                : (analysts.find((analyst) => analyst.id === modalAnalystId) ??
                  null),
        [analysts, modalAnalystId],
    );

    const filteredAnalysts = useMemo(() => {
        const q = analystQuery.trim().toLowerCase();

        return analysts.filter((analyst) => {
            const active = isActiveAnalyst(analyst);
            if (statusFilter === 'active' && !active) {
                return false;
            }
            if (statusFilter === 'inactive' && active) {
                return false;
            }
            if (!q) {
                return true;
            }
            return (
                analyst.name.toLowerCase().includes(q) ||
                analyst.email.toLowerCase().includes(q)
            );
        });
    }, [analysts, analystQuery, statusFilter]);

    return (
        <>
            <Head title="Assignments" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Assignments"
                    description="Manage analyst test assignments and workload."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-sky-200 bg-sky-50 text-[10px] font-medium text-sky-800"
                        >
                            Workload Auto-Balancer
                        </Badge>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <QueueKpi
                        label="Analysts"
                        value={analysts.length}
                        hint="active"
                    />
                    <QueueKpi
                        label="Procedures"
                        value={totalProcedures}
                        hint="assignable"
                        tone="info"
                    />
                    <QueueKpi
                        label="Dispatch"
                        value="Least-busy"
                        hint="Sample Receiving"
                        tone="success"
                    />
                </div>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold text-nppc-900">
                                Analyst accounts
                            </h2>
                            <p className="text-xs text-slate-500">
                                {analysts.length} registered
                            </p>
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div className="relative min-w-0 flex-1 sm:w-56">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400" />
                                <Input
                                    value={analystQuery}
                                    onChange={(event) =>
                                        setAnalystQuery(event.target.value)
                                    }
                                    placeholder="Search analyst…"
                                    className="h-9 pl-8 text-sm"
                                />
                            </div>
                            <Select
                                value={statusFilter}
                                onValueChange={(value) =>
                                    setStatusFilter(
                                        value as 'all' | 'active' | 'inactive',
                                    )
                                }
                            >
                                <SelectTrigger className="h-9 w-full text-sm sm:w-[140px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                asChild
                                size="sm"
                                className="h-9 gap-1.5 bg-nppc-900 text-sm hover:bg-nppc-700"
                            >
                                <Link href="/admin/users">
                                    <Plus className="size-3.5" />
                                    Add analyst
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* Desktop table */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                <tr className="h-11">
                                    <th className="px-4 py-2.5">Analyst</th>
                                    <th className="px-4 py-2.5">
                                        Assigned procedures
                                    </th>
                                    <th className="px-4 py-2.5">Active tasks</th>
                                    <th className="px-4 py-2.5">Status</th>
                                    <th className="px-4 py-2.5 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredAnalysts.map((analyst) => {
                                    const assigned =
                                        analyst.analysis_type_ids.length;
                                    const active = isActiveAnalyst(analyst);
                                    const share =
                                        totalProcedures > 0
                                            ? Math.min(
                                                  100,
                                                  Math.round(
                                                      (assigned /
                                                          totalProcedures) *
                                                          100,
                                                  ),
                                              )
                                            : 0;

                                    return (
                                        <tr
                                            key={analyst.id}
                                            className="h-16 hover:bg-slate-50/80"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-nppc-50 text-[11px] font-semibold text-nppc-900">
                                                        {initials(analyst.name)}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium text-slate-900">
                                                            {analyst.name}
                                                        </p>
                                                        <p className="truncate text-xs text-slate-500">
                                                            {analyst.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="max-w-[11rem]">
                                                    <p className="tabular-nums text-slate-900">
                                                        {assigned} /{' '}
                                                        {totalProcedures}
                                                    </p>
                                                    <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                                                        <div
                                                            className="h-full rounded-full bg-nppc-900"
                                                            style={{
                                                                width: `${share}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-slate-800">
                                                {analyst.active_tasks}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'text-[10px] font-medium',
                                                        active
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                            : 'border-slate-200 bg-slate-50 text-slate-600',
                                                    )}
                                                >
                                                    {active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-8 gap-1 border-slate-200 text-xs shadow-xs"
                                                    onClick={() =>
                                                        setModalAnalystId(
                                                            analyst.id,
                                                        )
                                                    }
                                                >
                                                    Assign Tests
                                                    <ArrowRight className="size-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {filteredAnalysts.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-10 text-center text-sm text-slate-500"
                                        >
                                            {analysts.length === 0
                                                ? 'No analyst accounts yet. Add an analyst from Users.'
                                                : 'No analysts match your search or filter.'}
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile cards */}
                    <div className="space-y-3 p-3 md:hidden">
                        {filteredAnalysts.map((analyst) => {
                            const assigned = analyst.analysis_type_ids.length;
                            const active = isActiveAnalyst(analyst);

                            return (
                                <div
                                    key={analyst.id}
                                    className="rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs"
                                >
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-nppc-50 text-[11px] font-semibold text-nppc-900">
                                            {initials(analyst.name)}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-slate-900">
                                                        {analyst.name}
                                                    </p>
                                                    <p className="truncate text-xs text-slate-500">
                                                        {analyst.email}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        'shrink-0 text-[10px] font-medium',
                                                        active
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                            : 'border-slate-200 bg-slate-50 text-slate-600',
                                                    )}
                                                >
                                                    {active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </div>
                                            <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <p className="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                                        Assigned
                                                    </p>
                                                    <p className="mt-0.5 tabular-nums text-slate-900">
                                                        {assigned} /{' '}
                                                        {totalProcedures}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                                        Active tasks
                                                    </p>
                                                    <p className="mt-0.5 tabular-nums text-slate-900">
                                                        {analyst.active_tasks}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="mt-3 h-9 w-full gap-1.5 bg-nppc-900 text-sm hover:bg-nppc-700"
                                                onClick={() =>
                                                    setModalAnalystId(
                                                        analyst.id,
                                                    )
                                                }
                                            >
                                                Assign Tests
                                                <ArrowRight className="size-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                        {filteredAnalysts.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-500">
                                {analysts.length === 0
                                    ? 'No analyst accounts yet. Add an analyst from Users.'
                                    : 'No analysts match your search or filter.'}
                            </p>
                        ) : null}
                    </div>
                </section>
            </LimsWorkspace>

            <AssignmentModal
                open={modalAnalystId !== null}
                analyst={modalAnalyst}
                groups={groups}
                onOpenChange={(next) => {
                    if (!next) {
                        setModalAnalystId(null);
                    }
                }}
            />
        </>
    );
}

AdminAssignments.layout = {
    breadcrumbs: [{ title: 'Assignments', href: '/admin/assignments' }],
};
