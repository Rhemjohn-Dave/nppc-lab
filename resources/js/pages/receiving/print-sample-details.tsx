import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';

type SampleRow = {
    index: number;
    sample_code: string;
    description: string;
    location: string;
    sample_point: string;
    collected_time: string;
};

type Props = {
    jobOrder: {
        id: number;
        reference_no: string;
        customer_name: string;
        company_name: string | null;
        classification: string | null;
        see_attached_file: boolean;
        samples_count: number;
        show_url: string;
    };
    samples: SampleRow[];
};

export default function ReceivingPrintSampleDetails({
    jobOrder,
    samples,
}: Props) {
    useEffect(() => {
        const timer = window.setTimeout(() => window.print(), 250);
        return () => window.clearTimeout(timer);
    }, []);

    return (
        <div className="min-h-dvh bg-white text-slate-900">
            <Head
                title={`Sample details ${jobOrder.reference_no}`}
            />

            <div className="print:hidden sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
                <div>
                    <p className="text-sm font-semibold text-nppc-900">
                        Sample details — {jobOrder.reference_no}
                    </p>
                    <p className="text-xs text-slate-500">
                        Internal list for bulk receiving (Code, Name, Location,
                        Sample point, Time)
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href={jobOrder.show_url}>Back to Job Order</Link>
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => window.print()}
                    >
                        Print
                    </Button>
                </div>
            </div>

            <main className="mx-auto max-w-5xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
                <header className="mb-4 space-y-1 print:mb-3">
                    <h1 className="text-lg font-semibold tracking-tight text-slate-900">
                        Bulk sample details
                    </h1>
                    <p className="text-sm text-slate-700">
                        <span className="font-medium">JO:</span>{' '}
                        {jobOrder.reference_no}
                        <span className="mx-2 text-slate-300">·</span>
                        <span className="font-medium">Customer:</span>{' '}
                        {jobOrder.customer_name}
                        {jobOrder.company_name
                            ? ` · ${jobOrder.company_name}`
                            : ''}
                    </p>
                    <p className="text-sm text-slate-600">
                        {jobOrder.samples_count} sample
                        {jobOrder.samples_count === 1 ? '' : 's'}
                        {jobOrder.classification
                            ? ` · ${jobOrder.classification}`
                            : ''}
                        {jobOrder.see_attached_file
                            ? ' · JO/RFA uses “see attached file”'
                            : ''}
                    </p>
                </header>

                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b-2 border-slate-800 text-left text-[11px] uppercase tracking-wide text-slate-700">
                            <th className="px-2 py-2 w-10">#</th>
                            <th className="px-2 py-2">Code</th>
                            <th className="px-2 py-2">Name</th>
                            <th className="px-2 py-2">Location</th>
                            <th className="px-2 py-2">Sample point</th>
                            <th className="px-2 py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        {samples.map((sample) => (
                            <tr
                                key={sample.index}
                                className="border-b border-slate-200 align-top"
                            >
                                <td className="px-2 py-1.5 tabular-nums text-slate-600">
                                    {sample.index}
                                </td>
                                <td className="px-2 py-1.5 font-mono text-[13px]">
                                    {sample.sample_code || '—'}
                                </td>
                                <td className="px-2 py-1.5 font-medium">
                                    {sample.description || '—'}
                                </td>
                                <td className="px-2 py-1.5">
                                    {sample.location || '—'}
                                </td>
                                <td className="px-2 py-1.5">
                                    {sample.sample_point || '—'}
                                </td>
                                <td className="px-2 py-1.5 tabular-nums">
                                    {sample.collected_time || '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </main>
        </div>
    );
}
