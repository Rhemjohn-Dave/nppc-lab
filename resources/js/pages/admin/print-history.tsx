import { Head } from '@inertiajs/react';
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
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Print History
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Official print actions on generated controlled documents.
                    </p>
                </div>
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
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[800px] text-sm">
                        <thead className="bg-[#e8eef8] text-left text-[#1A3694]">
                            <tr>
                                <th className="px-3 py-2 font-medium">Document</th>
                                <th className="px-3 py-2 font-medium">Revision</th>
                                <th className="px-3 py-2 font-medium">User</th>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Copies</th>
                                <th className="px-3 py-2 font-medium">Printer</th>
                                <th className="px-3 py-2 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <div className="font-mono">{row.document_number}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.form_code}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">Rev {row.revision}</td>
                                    <td className="px-3 py-2">{row.user ?? '—'}</td>
                                    <td className="px-3 py-2">{row.printed_at ?? '—'}</td>
                                    <td className="px-3 py-2">{row.copies}</td>
                                    <td className="px-3 py-2">{row.printer_name ?? '—'}</td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {row.ip_address ?? '—'}
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

PrintHistory.layout = {
    breadcrumbs: [
        { title: 'Document Control', href: '/admin/controlled-forms' },
        { title: 'Print History', href: '/admin/print-history' },
    ],
};
