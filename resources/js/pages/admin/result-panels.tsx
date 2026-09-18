import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import LimsWorkspace from '@/components/lims/lims-workspace';
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
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Result panels
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Types-only analysis result forms used as intake test
                            panels (individual pay). Open a panel to manage its
                            controlled PDF under Document Control.
                        </p>
                    </div>
                </div>

                <div className="relative max-w-md">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        className="pl-8"
                        placeholder="Search form code, name, status, or type code"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-[#e8eef8] text-left text-[#1A3694]">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Form code
                                </th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">
                                    Bound tests
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Revision
                                </th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
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
                                    <tr key={item.id} className="border-t">
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {item.form_code}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {item.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Types-only panel
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {item.analysis_type_count ??
                                                    types.length}{' '}
                                                tests
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {preview
                                                    ? `${preview}${extra}`
                                                    : 'No types bound'}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            {item.current_revision?.revision ??
                                                '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    item.status,
                                                )}
                                            >
                                                {item.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
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
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No types-only result panels found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </LimsWorkspace>
        </>
    );
}
