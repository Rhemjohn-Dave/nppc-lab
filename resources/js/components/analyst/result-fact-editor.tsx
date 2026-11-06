import type { ResultFact } from '@/components/analyst/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    jobId: number;
    sampleId: number;
    fact: ResultFact;
    canEdit: boolean;
};

export default function ResultFactEditor({
    jobId,
    sampleId,
    fact,
    canEdit,
}: Props) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        sample_id: sampleId,
        value: fact.value,
        time: fact.time ?? '',
    });

    useEffect(() => {
        setEditing(false);
        form.setData({
            sample_id: sampleId,
            value: fact.value,
            time: fact.time ?? '',
        });
        form.clearErrors();
        // Reset when the printed fact changes after a successful save.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sampleId, fact.key, fact.value, fact.time]);

    const shown =
        fact.type === 'datetime'
            ? [fact.value, fact.time].filter(Boolean).join(' ')
            : fact.value;

    function save() {
        const value = form.data.value.trim();
        const time = form.data.time.trim();
        form.transform(() => {
            if (fact.type === 'datetime') {
                return {
                    sample_id: sampleId,
                    sampling_date: value || null,
                    sampling_time: time || null,
                };
            }
            if (fact.type === 'time') {
                return {
                    sample_id: sampleId,
                    collected_time: value || null,
                };
            }

            return {
                sample_id: sampleId,
                [fact.key]: value || null,
            };
        });
        form.patch(`/analyst/job-orders/${jobId}/result-facts`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    }

    const errors = form.errors as Record<string, string | undefined>;
    const error =
        fact.type === 'datetime'
            ? errors.sampling_date || errors.sampling_time
            : fact.type === 'time'
              ? errors.collected_time
              : errors[fact.key];

    if (!editing) {
        return (
            <div className="flex min-w-0 items-start justify-between gap-2">
                <div className="min-w-0">
                    <span className="block text-[10px] text-slate-400">
                        {fact.label}
                    </span>
                    {shown ? (
                        <p className="break-words text-[11px] leading-snug font-medium text-slate-800">
                            {shown}
                        </p>
                    ) : (
                        <p className="text-[11px] leading-snug text-slate-400">
                            Not set
                        </p>
                    )}
                </div>
                {canEdit ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 text-nppc-800"
                        aria-label={`Edit ${fact.label}`}
                        onClick={() => {
                            form.setData({
                                sample_id: sampleId,
                                value: fact.value,
                                time: fact.time ?? '',
                            });
                            form.clearErrors();
                            setEditing(true);
                        }}
                    >
                        <Pencil className="size-3.5" />
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <div className="space-y-1.5">
            <span className="block text-[10px] text-slate-400">{fact.label}</span>
            {fact.type === 'time' ? (
                <Input
                    type="time"
                    value={form.data.value}
                    autoFocus
                    aria-label={fact.label}
                    disabled={form.processing}
                    onChange={(event) =>
                        form.setData('value', event.target.value)
                    }
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            save();
                        }
                    }}
                />
            ) : fact.type === 'datetime' ? (
                <div className="flex gap-1.5">
                    <Input
                        type="date"
                        value={form.data.value}
                        aria-label={`${fact.label} date`}
                        disabled={form.processing}
                        onChange={(event) =>
                            form.setData('value', event.target.value)
                        }
                    />
                    <Input
                        type="time"
                        value={form.data.time}
                        aria-label={`${fact.label} time`}
                        disabled={form.processing}
                        onChange={(event) =>
                            form.setData('time', event.target.value)
                        }
                    />
                </div>
            ) : (
                <Input
                    value={form.data.value}
                    maxLength={255}
                    autoFocus
                    aria-label={fact.label}
                    disabled={form.processing}
                    onChange={(event) =>
                        form.setData('value', event.target.value)
                    }
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            save();
                        }
                    }}
                />
            )}
            <InputError message={error} />
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    disabled={form.processing}
                    onClick={() => save()}
                >
                    Save
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={form.processing}
                    onClick={() => {
                        form.setData({
                            sample_id: sampleId,
                            value: fact.value,
                            time: fact.time ?? '',
                        });
                        form.clearErrors();
                        setEditing(false);
                    }}
                >
                    Cancel
                </Button>
            </div>
        </div>
    );
}
