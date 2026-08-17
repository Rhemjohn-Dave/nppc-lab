import { Head, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    const { flash } = usePage().props as { flash?: { success?: string } };
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
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Control number
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Set where the next Request for Analysis control number
                        starts. Numbers use year + sequence (e.g.{' '}
                        {formatControl(counter.year, 1)}).
                    </p>
                    {flash?.success && (
                        <p className="mt-2 text-sm text-emerald-700">
                            {flash.success}
                        </p>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Year</p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counter.year_full}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Series prefix {counter.year}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Highest issued
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counter.highest_issued > 0
                                ? formatControl(
                                      counter.year,
                                      counter.highest_issued,
                                  )
                                : '—'}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            From existing job orders
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Next to issue
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-emerald-800">
                            {counter.next_reference}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Assigned on new intake submissions
                        </p>
                    </div>
                </div>

                <form
                    className="max-w-xl space-y-4 rounded-xl border bg-white p-5"
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
                            <span className="font-semibold text-[#1A3694]">
                                {preview}
                            </span>
                        </p>
                        {form.errors.next_number && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.next_number}
                            </p>
                        )}
                    </div>

                    <div className="rounded-lg border border-[#c5d4f0] bg-[#eef3fb] px-3 py-2 text-xs text-[#1A3694]">
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
                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                    >
                        {form.processing
                            ? 'Saving…'
                            : 'Save control number start'}
                    </Button>
                </form>
            </div>
        </>
    );
}

ControlNumberAdmin.layout = {
    breadcrumbs: [
        { title: 'Control number', href: '/admin/control-number' },
    ],
};
