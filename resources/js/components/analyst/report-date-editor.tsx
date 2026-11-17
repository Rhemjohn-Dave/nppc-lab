import type { ReportDateConfig } from '@/components/analyst/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    jobId: number;
    config: ReportDateConfig;
};

export default function ReportDateEditor({ jobId, config }: Props) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        sample_id: config.sample_id,
        form_id: config.form_id,
        report_date: config.value ?? '',
        reset: false,
    });

    useEffect(() => {
        setEditing(false);
        form.setData({
            sample_id: config.sample_id,
            form_id: config.form_id,
            report_date: config.value ?? '',
            reset: false,
        });
        form.clearErrors();
        // Reset when the active sheet's report date changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        config.sample_id,
        config.form_id,
        config.value,
        config.display,
        config.is_override,
    ]);

    function save() {
        form.transform((data) => ({
            sample_id: data.sample_id,
            form_id: data.form_id,
            report_date: data.report_date?.trim() || null,
            reset: false,
        }));
        form.patch(`/analyst/job-orders/${jobId}/report-date`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    }

    function resetToDefault() {
        form.transform((data) => ({
            sample_id: data.sample_id,
            form_id: data.form_id,
            report_date: null,
            reset: true,
        }));
        form.patch(`/analyst/job-orders/${jobId}/report-date`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setEditing(false),
        });
    }

    if (!editing) {
        return (
            <div className="flex min-w-0 items-start justify-between gap-2">
                <div className="min-w-0">
                    <span className="block text-[10px] text-slate-400">
                        Date Reported
                    </span>
                    {config.display ? (
                        <p className="break-words text-[11px] leading-snug font-medium text-slate-800">
                            {config.display}
                            {config.is_override ? (
                                <span className="ml-1 text-[10px] font-normal text-slate-400">
                                    (edited)
                                </span>
                            ) : null}
                        </p>
                    ) : (
                        <p className="text-[11px] leading-snug text-slate-400">
                            Not set
                        </p>
                    )}
                </div>
                {config.can_edit ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 text-nppc-800"
                        aria-label="Edit Date Reported"
                        onClick={() => {
                            form.setData({
                                sample_id: config.sample_id,
                                form_id: config.form_id,
                                report_date: config.value ?? '',
                                reset: false,
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
            <span className="block text-[10px] text-slate-400">
                Date Reported
            </span>
            <Input
                type="date"
                value={form.data.report_date}
                autoFocus
                aria-label="Date Reported"
                disabled={form.processing}
                onChange={(event) =>
                    form.setData('report_date', event.target.value)
                }
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        save();
                    }
                }}
            />
            {config.default_display ? (
                <p className="text-[10px] text-slate-400">
                    TAT default: {config.default_display}
                </p>
            ) : null}
            <InputError message={form.errors.report_date} />
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    disabled={form.processing}
                    onClick={() => save()}
                >
                    Save
                </Button>
                {config.is_override || config.default_value ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => resetToDefault()}
                    >
                        Use TAT default
                    </Button>
                ) : null}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={form.processing}
                    onClick={() => {
                        form.setData({
                            sample_id: config.sample_id,
                            form_id: config.form_id,
                            report_date: config.value ?? '',
                            reset: false,
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
