import { Head } from '@inertiajs/react';
import QueueKpi from '@/components/lims/queue-kpi';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { useState } from 'react';
import TablePagination from '@/components/table-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    id: number;
    user: string | null;
    action: string;
    record: string;
    old_value: Record<string, unknown> | null;
    new_value: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
};

type Props = {
    logs: {
        data: Row[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q: string };
};

export default function DocumentAudit({ logs, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? '');

    return (
        <>
            <Head title="Audit Logs" />
            <LimsWorkspace>
                <WorkspaceHeader
                    title="Document Audit Logs"
                    description="Uploads, revisions, mapping changes, approvals, generation, and printing."
                />

                <QueueKpi label="Audit entries" value={logs.total} hint="total" />

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="border-b border-slate-200 p-3 sm:p-4">
                        <form
                            className="flex max-w-md gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                window.location.href = `/admin/document-audit?q=${encodeURIComponent(query)}`;
                            }}
                        >
                            <Input
                                value={query}
                                placeholder="Search action or user"
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                            />
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">User</th>
                                    <th className="px-4 py-3">Action</th>
                                    <th className="px-4 py-3">Record</th>
                                    <th className="px-4 py-3">Timestamp</th>
                                    <th className="px-4 py-3">IP</th>
                                    <th className="px-4 py-3">Change</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {logs.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="transition-colors hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-4 align-top font-semibold text-slate-900">
                                            {row.user ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top font-mono text-xs text-slate-700">
                                            {row.action}
                                        </td>
                                        <td className="px-4 py-4 align-top font-mono text-xs text-slate-700">
                                            {row.record}
                                        </td>
                                        <td className="px-4 py-4 align-top text-[11px] text-slate-500">
                                            {row.created_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top font-mono text-xs text-slate-700">
                                            {row.ip_address ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top font-mono text-[10px] text-muted-foreground">
                                            {row.new_value
                                                ? JSON.stringify(row.new_value)
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {logs.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                No audit entries
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {filters.q
                                                    ? 'No entries match your search.'
                                                    : 'Document changes will appear here.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                        <TablePagination
                            links={logs.links}
                            from={logs.from}
                            to={logs.to}
                            total={logs.total}
                        />
                    </div>
                </div>
            </LimsWorkspace>
        </>
    );
}

DocumentAudit.layout = {
    breadcrumbs: [
        { title: 'Document Control', href: '/admin/controlled-forms' },
        { title: 'Audit Logs', href: '/admin/document-audit' },
    ],
};
