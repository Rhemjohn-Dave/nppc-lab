import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import QueueKpi from '@/components/lims/queue-kpi';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    statusBadgeClass,
    type ControlledFormSummary,
} from '@/lib/controlled-forms';

type ResultPanelRow = ControlledFormSummary & {
    analysis_type_count?: number;
    analysis_types?: Array<{ id: number; code: string; name: string }>;
};

type Props = {
    panels: ResultPanelRow[];
};

export default function AdminResultPanels({ panels }: Props) {
    const [query, setQuery] = useState('');

    const filtered = panels.filter((item) => {
        const typeCodes = (item.analysis_types ?? [])
            .map((type) => type.code)
            .join(' ');
        const haystack =
            `${item.form_code} ${item.name} ${item.status_label} ${typeCodes}`.toLowerCase();

        return haystack.includes(query.toLowerCase());
    });

    return (
        <>
            <Head title="Result panels" />
            <LimsWorkspace>
                <WorkspaceHeader
                    title="Result panels"
                    description="Types-only analysis result forms used as intake test panels (individual pay). Open a panel to manage its controlled PDF under Document Control."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <QueueKpi label="Panels" value={panels.length} />
                    <QueueKpi
                        label="Matching search"
                        value={filtered.length}
                        hint="shown"
                    />
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="border-b border-slate-200 p-3 sm:p-4">
                        <div className="relative max-w-md">
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                className="pl-8"
                                placeholder="Search form code, name, status, or type code"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Form code</th>
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Bound tests</th>
                                    <th className="px-4 py-3">Revision</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {filtered.map((item) => {
                                    const types = item.analysis_types ?? [];
                                    const preview = types
                                        .slice(0, 4)
                                        .map((type) => type.code)
                                        .join(', ');
                                    const extra =
                                        types.length > 4
                                            ? ` +${types.length - 4}`
                                            : '';

                                    return (
                                        <tr
                                            key={item.id}
                                            className="transition-colors hover:bg-slate-50"
                                        >
                                            <td className="px-4 py-4 align-top font-mono text-xs font-semibold text-slate-900">
                                                {item.form_code}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="font-semibold text-slate-900">
                                                    {item.name}
                                                </p>
                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                    Types-only panel
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="font-semibold text-slate-900">
                                                    {item.analysis_type_count ??
                                                        types.length}{' '}
                                                    tests
                                                </p>
                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                    {preview
                                                        ? `${preview}${extra}`
                                                        : 'No types bound'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top text-slate-700">
                                                {item.current_revision
                                                    ?.revision ?? '—'}
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <Badge
                                                    variant="outline"
                                                    className={statusBadgeClass(
                                                        item.status,
                                                    )}
                                                >
                                                    {item.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 text-right align-top">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 border-slate-200 text-xs shadow-xs"
                                                >
                                                    <Link
                                                        href={`/admin/controlled-forms/${item.id}`}
                                                    >
                                                        Open form
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {filtered.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                No panels found
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                No types-only result panels
                                                match this search.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </LimsWorkspace>
        </>
    );
}
