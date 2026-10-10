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
    document_number: string;
    form_code: string;
    revision: string;
    user: string | null;
    printed_at: string | null;
    copies: number;
    printer_name: string | null;
    ip_address: string | null;
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

export default function PrintHistory({ logs, filters }: Props) {
    const [query, setQuery] = useState(filters.q ?? '');

    return (
        <>
            <Head title="Print History" />
            <LimsWorkspace>
                <WorkspaceHeader
                    title="Print History"
                    description="Official print actions on generated controlled documents."
                />

                <QueueKpi label="Print events" value={logs.total} hint="total" />

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="border-b border-slate-200 p-3 sm:p-4">
                        <form
                            className="flex max-w-md gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                window.location.href = `/admin/print-history?q=${encodeURIComponent(query)}`;
                            }}
                        >
                            <Input
                                value={query}
                                placeholder="Search document or form code"
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
                        <table className="w-full min-w-[800px] border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Document</th>
                                    <th className="px-4 py-3">Revision</th>
                                    <th className="px-4 py-3">User</th>
                                    <th className="px-4 py-3">Date</th>
                                    <th className="px-4 py-3">Copies</th>
                                    <th className="px-4 py-3">Printer</th>
                                    <th className="px-4 py-3">IP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {logs.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="transition-colors hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-4 align-top">
                                            <p className="font-mono font-semibold text-slate-900">
                                                {row.document_number}
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-slate-500">
                                                {row.form_code}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 align-top text-slate-700">
                                            Rev {row.revision}
                                        </td>
                                        <td className="px-4 py-4 align-top font-semibold text-slate-900">
                                            {row.user ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top text-[11px] text-slate-500">
                                            {row.printed_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top tabular-nums text-slate-800">
                                            {row.copies}
                                        </td>
                                        <td className="px-4 py-4 align-top text-slate-700">
                                            {row.printer_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top font-mono text-xs text-slate-700">
                                            {row.ip_address ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                                {logs.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                No print events
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {filters.q
                                                    ? 'No prints match your search.'
                                                    : 'Official print actions will appear here.'}
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

PrintHistory.layout = {
    breadcrumbs: [
        { title: 'Document Control', href: '/admin/controlled-forms' },
        { title: 'Print History', href: '/admin/print-history' },
    ],
};
