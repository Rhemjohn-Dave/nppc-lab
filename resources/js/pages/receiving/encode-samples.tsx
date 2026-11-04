import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import FlashAlert from '@/components/flash-alert';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type SampleRow = {
    id: number;
    control_number: string;
    sample_code: string;
    description: string;
    location: string;
    sample_point: string;
    collected_time: string;
    matrix: string;
    quantity: string;
    unit: string;
};

type Props = {
    jobOrder: {
        id: number;
        reference_no: string;
        customer_name: string;
        company_name: string | null;
        classification: string | null;
        status: string;
        status_label: string;
        see_attached_file: boolean;
        samples_count: number;
        samples_named_count: number;
        samples_encode_complete: boolean;
        show_url: string;
    };
    samples: SampleRow[];
};

function toTimeInputValue(value: string): string {
    const raw = value.trim();
    if (!raw) {
        return '';
    }
    const twentyFour = raw.match(/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/);
    if (twentyFour) {
        return `${twentyFour[1].padStart(2, '0')}:${twentyFour[2]}`;
    }
    const twelve = raw.match(/^(\d{1,2}):([0-5]\d)\s*([AaPp][Mm])$/);
    if (!twelve) {
        return '';
    }
    let hour = Number(twelve[1]);
    const minute = twelve[2];
    const meridiem = twelve[3].toUpperCase();
    if (meridiem === 'AM') {
        hour = hour === 12 ? 0 : hour;
    } else {
        hour = hour === 12 ? 12 : hour + 12;
    }
    return `${String(hour).padStart(2, '0')}:${minute}`;
}

export default function ReceivingEncodeSamples({ jobOrder, samples }: Props) {
    const form = useForm({
        samples: samples.map((row) => ({
            ...row,
            collected_time: toTimeInputValue(row.collected_time),
        })),
    });

    const namedCount = form.data.samples.filter(
        (row) => row.description.trim().length > 0,
    ).length;

    function updateRow(
        index: number,
        key: keyof SampleRow,
        value: string,
    ): void {
        form.setData(
            'samples',
            form.data.samples.map((row, i) =>
                i === index ? { ...row, [key]: value } : row,
            ),
        );
    }

    function save(): void {
        form.patch(`/receiving/${jobOrder.id}/samples`, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={`Encode samples ${jobOrder.reference_no}`} />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title={`Encode samples — ${jobOrder.reference_no}`}
                    description={`${jobOrder.customer_name}${jobOrder.company_name ? ` · ${jobOrder.company_name}` : ''} — fill Names gradually; pricing and Head approval can proceed in parallel. Locked after Send to analysts.`}
                    actions={
                        <Button asChild variant="outline" size="sm">
                            <Link href={jobOrder.show_url}>
                                <ArrowLeft className="size-3.5" />
                                Back to Job Order
                            </Link>
                        </Button>
                    }
                />

                <FlashAlert />

                <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <p className="text-sm text-slate-700">
                        <span className="font-semibold text-nppc-900">
                            {namedCount}/{jobOrder.samples_count}
                        </span>{' '}
                        samples named
                        {jobOrder.see_attached_file
                            ? ' · JO/RFA uses “see attached file”'
                            : ''}
                    </p>
                    <Button
                        type="button"
                        size="sm"
                        disabled={form.processing}
                        onClick={save}
                    >
                        {form.processing ? 'Saving…' : 'Save sample details'}
                    </Button>
                </div>

                <section className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table className="min-w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                                <th className="px-3 py-2">Control #</th>
                                <th className="px-3 py-2">Code</th>
                                <th className="px-3 py-2">
                                    Name <span className="text-rose-600">*</span>
                                </th>
                                <th className="px-3 py-2">Location</th>
                                <th className="px-3 py-2">Sample point</th>
                                <th className="px-3 py-2">Time</th>
                                <th className="px-3 py-2">Matrix</th>
                            </tr>
                        </thead>
                        <tbody>
                            {form.data.samples.map((sample, index) => {
                                const missingName =
                                    sample.description.trim() === '';

                                return (
                                    <tr
                                        key={sample.id}
                                        className="border-b border-slate-100"
                                    >
                                        <td className="px-3 py-1.5 font-semibold whitespace-nowrap text-nppc-900">
                                            {sample.control_number}
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <Input
                                                className="h-8 min-w-[4.5rem]"
                                                value={sample.sample_code}
                                                onChange={(e) =>
                                                    updateRow(
                                                        index,
                                                        'sample_code',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <Input
                                                className={`h-8 min-w-[12rem] ${missingName ? 'border-amber-300' : ''}`}
                                                value={sample.description}
                                                placeholder="Sample name"
                                                onChange={(e) =>
                                                    updateRow(
                                                        index,
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <Input
                                                className="h-8 min-w-[8rem]"
                                                value={sample.location}
                                                onChange={(e) =>
                                                    updateRow(
                                                        index,
                                                        'location',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <Input
                                                className="h-8 min-w-[8rem]"
                                                value={sample.sample_point}
                                                onChange={(e) =>
                                                    updateRow(
                                                        index,
                                                        'sample_point',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <Input
                                                type="time"
                                                className="h-8 min-w-[7.5rem]"
                                                value={sample.collected_time}
                                                onChange={(e) =>
                                                    updateRow(
                                                        index,
                                                        'collected_time',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-1.5">
                                            <Input
                                                className="h-8 min-w-[5rem]"
                                                value={sample.matrix}
                                                onChange={(e) =>
                                                    updateRow(
                                                        index,
                                                        'matrix',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </section>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-slate-500">
                        Send to analysts requires a Name on every sample.
                    </p>
                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={jobOrder.show_url}>Back to pricing</Link>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={form.processing}
                            onClick={save}
                        >
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                </div>

                {form.errors.samples ? (
                    <p className="text-sm text-rose-700">{form.errors.samples}</p>
                ) : null}
                {Object.keys(form.errors).length > 0 ? (
                    <div className="space-y-1 text-xs text-rose-700">
                        {Object.entries(form.errors)
                            .filter(([key]) => key !== 'samples')
                            .slice(0, 5)
                            .map(([key, message]) => (
                                <p key={key}>
                                    {key}: {message}
                                </p>
                            ))}
                    </div>
                ) : null}
            </LimsWorkspace>
        </>
    );
}
