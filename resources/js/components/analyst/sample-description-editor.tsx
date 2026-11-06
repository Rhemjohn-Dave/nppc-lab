import type { SampleSummary } from '@/components/analyst/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    jobId: number;
    sample: SampleSummary;
    fallback: string;
    prefix?: string;
    suffix?: string;
    className?: string;
};

export default function SampleDescriptionEditor({
    jobId,
    sample,
    fallback,
    prefix = '',
    suffix = '',
    className,
}: Props) {
    const choices = sample.description_choices ?? null;
    const canEdit = Boolean(sample.can_edit_description && sample.id);
    const [editing, setEditing] = useState(false);
    const savedDescription = sample.description?.trim() ?? '';
    const sampleCode = sample.sample_code?.trim() ?? '';
    const printed = savedDescription || (choices ? '' : sampleCode);
    const form = useForm({
        description: printed,
    });

    useEffect(() => {
        setEditing(false);
        form.setData('description', printed);
        form.clearErrors();
        // Reset when the saved description changes after a successful save.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sample.id, sample.description, sample.sample_code]);

    const shown = printed || fallback;

    function save(next?: string) {
        const description = (next ?? form.data.description).trim();
        form.transform(() => ({ description }));
        form.patch(`/analyst/job-orders/${jobId}/samples/${sample.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    }

    if (!editing) {
        return (
            <div className={cn('flex min-w-0 items-start justify-between gap-2', className)}>
                <p className="min-w-0 text-sm text-slate-700">
                    {prefix}
                    {shown}
                    {suffix}
                </p>
                {canEdit ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 text-nppc-800"
                        aria-label={`Edit sample description for ${shown}`}
                        onClick={() => {
                            form.setData('description', printed);
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
        <div className={cn('space-y-2', className)}>
            <p className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                Sample description
            </p>
            {choices ? (
                <div className="flex flex-wrap gap-1.5" role="group" aria-label="Sample description">
                    {choices.map((kind) => {
                        const active = form.data.description === kind;

                        return (
                            <button
                                key={kind}
                                type="button"
                                aria-pressed={active}
                                disabled={form.processing}
                                onClick={() => form.setData('description', kind)}
                                className={cn(
                                    'rounded-md border px-2 py-1 text-xs font-semibold transition',
                                    active
                                        ? 'border-nppc-900 bg-nppc-900 text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500/60',
                                )}
                            >
                                {kind}
                            </button>
                        );
                    })}
                </div>
            ) : (
                <Input
                    value={form.data.description}
                    maxLength={255}
                    autoFocus
                    aria-label="Sample description"
                    disabled={form.processing}
                    onChange={(event) => form.setData('description', event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            save(event.currentTarget.value);
                        }
                    }}
                />
            )}
            <InputError message={form.errors.description} />
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
                        form.setData('description', printed);
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
