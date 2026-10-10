import { Head, useForm } from '@inertiajs/react';
import QueueKpi from '@/components/lims/queue-kpi';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { limsPanel } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type Counter = {
    year: string;
    year_full: number;
    last_number: number;
    next_number: number;
    next_reference: string;
    min_next: number;
    highest_issued: number;
};

type Props = {
    counter: Counter;
};

function formatControl(year: string, number: number) {
    return `${year}-${String(number).padStart(4, '0')}`;
}

export default function ControlNumberAdmin({ counter }: Props) {
    const form = useForm({
        next_number: counter.next_number,
    });

    const preview = formatControl(
        counter.year,
        Number(form.data.next_number) || 0,
    );

    return (
        <>
            <Head title="Control number" />
            <LimsWorkspace>
                <WorkspaceHeader
                    title="Control number"
                    description={`Set where the next Request for Analysis control number starts. Numbers use year + sequence (e.g. ${formatControl(counter.year, 1)}).`}
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <QueueKpi
                        label="Year"
                        value={counter.year_full}
                        hint={`prefix ${counter.year}`}
                    />
                    <QueueKpi
                        label="Highest issued"
                        value={
                            counter.highest_issued > 0
                                ? formatControl(
                                      counter.year,
                                      counter.highest_issued,
                                  )
                                : '—'
                        }
                        hint="from job orders"
                    />
                    <QueueKpi
                        label="Next to issue"
                        value={counter.next_reference}
                        hint="on intake"
                        tone="success"
                    />
                </div>

                <form
                    className={cn('max-w-xl space-y-4 p-5', limsPanel)}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/admin/control-number', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div>
                        <Label htmlFor="next_number">
                            Start / next sequence number
                        </Label>
                        <Input
                            id="next_number"
                            type="number"
                            min={counter.min_next}
                            max={9999}
                            className="mt-1"
                            value={form.data.next_number}
                            onChange={(e) =>
                                form.setData(
                                    'next_number',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Minimum allowed: {counter.min_next}. Next control
                            number preview:{' '}
                            <span className="font-semibold text-nppc-900">
                                {preview}
                            </span>
                        </p>
                        {form.errors.next_number && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.next_number}
                            </p>
                        )}
                    </div>

                    <div className="rounded-lg border border-nppc-200 bg-nppc-50 px-3 py-2 text-xs text-nppc-900">
                        Example: set <strong>500</strong> to issue{' '}
                        <strong>
                            {formatControl(counter.year, 500)}
                        </strong>{' '}
                        on the next intake. You can skip ahead, but you cannot
                        reuse a number already assigned this year.
                    </div>

                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="bg-nppc-900 hover:bg-nppc-700"
                    >
                        {form.processing
                            ? 'Saving…'
                            : 'Save control number start'}
                    </Button>
                </form>
            </LimsWorkspace>
        </>
    );
}

ControlNumberAdmin.layout = {
    breadcrumbs: [
        { title: 'Control number', href: '/admin/control-number' },
    ],
};
