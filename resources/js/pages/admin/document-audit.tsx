import { Head } from '@inertiajs/react';
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
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Document Audit Logs
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Uploads, revisions, mapping changes, approvals, generation, and
                        printing.
                    </p>
                </div>
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
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[900px] text-sm">
                        <thead className="bg-[#e8eef8] text-left text-[#1A3694]">
                            <tr>
                                <th className="px-3 py-2 font-medium">User</th>
                                <th className="px-3 py-2 font-medium">Action</th>
                                <th className="px-3 py-2 font-medium">Record</th>
                                <th className="px-3 py-2 font-medium">Timestamp</th>
                                <th className="px-3 py-2 font-medium">IP</th>
                                <th className="px-3 py-2 font-medium">Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((row) => (
                                <tr key={row.id} className="border-t align-top">
                                    <td className="px-3 py-2">{row.user ?? '—'}</td>
                                    <td className="px-3 py-2 font-mono text-xs">{row.action}</td>
                                    <td className="px-3 py-2 font-mono text-xs">{row.record}</td>
                                    <td className="px-3 py-2">{row.created_at ?? '—'}</td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {row.ip_address ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-[10px] text-muted-foreground">
                                        {row.new_value ? JSON.stringify(row.new_value) : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <TablePagination
                    links={logs.links}
                    from={logs.from}
                    to={logs.to}
                    total={logs.total}
                />
            </div>
        </>
    );
}

DocumentAudit.layout = {
    breadcrumbs: [
        { title: 'Document Control', href: '/admin/controlled-forms' },
        { title: 'Audit Logs', href: '/admin/document-audit' },
    ],
};
