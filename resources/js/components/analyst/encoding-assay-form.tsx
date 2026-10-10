import type { AnalystTask } from '@/components/analyst/types';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatScientificResult } from '@/lib/scientific-result';
import { cn } from '@/lib/utils';
import { Check, Circle } from 'lucide-react';

export type EncodingFormData = {
    performed_at: string;
    result_value: string;
    result_pass_fail: string;
    result_measurement: string;
    result_unit: string;
    result_remarks: string;
    result_method: string;
};

export type EncodingFormErrors = Partial<
    Record<keyof EncodingFormData | 'analysis', string>
>;

type Props = {
    task: AnalystTask;
    formData: EncodingFormData;
    formErrors: EncodingFormErrors;
    readOnly: boolean;
    analysisError?: string | null;
    onFieldChange: (key: keyof EncodingFormData, value: string) => void;
};

const REMARKS_MAX = 1000;

export default function EncodingAssayForm({
    task,
    formData,
    formErrors,
    readOnly,
    analysisError,
    onFieldChange,
}: Props) {
    const resultsLocked = !readOnly && !formData.performed_at.trim();
    const resultFilled = formData.result_value.trim().length > 0;
    const remarksLen = formData.result_remarks.length;

    return (
        <div className="space-y-2.5">
            {(analysisError || formErrors.analysis) && (
                <InputError message={analysisError || formErrors.analysis} />
            )}

            {resultsLocked ? (
                <p className="text-[11px] text-amber-800">
                    Set date &amp; time performed to unlock result fields. Click
                    Save Draft to keep this test.
                </p>
            ) : (
                <p className="text-[11px] text-slate-500">
                    Click Save Draft to keep this test. Then continue to the
                    next test, or Save &amp; Complete when every sample is
                    ready.
                </p>
            )}

            <div className="grid grid-cols-1 gap-2.5 md:grid-cols-12 md:items-start">
                <div className="md:col-span-5">
                    <Label
                        htmlFor={`performed_at_${task.id}`}
                        className="mb-1 block text-[11px] font-semibold text-slate-700"
                    >
                        Date &amp; time performed{' '}
                        <span className="text-rose-600">*</span>
                    </Label>
                    <Input
                        id={`performed_at_${task.id}`}
                        type="datetime-local"
                        className="h-10 text-xs"
                        required={!readOnly}
                        autoFocus={!readOnly}
                        readOnly={readOnly}
                        disabled={readOnly}
                        value={formData.performed_at}
                        onChange={(e) =>
                            onFieldChange('performed_at', e.target.value)
                        }
                    />
                    <InputError
                        className="mt-0.5"
                        message={formErrors.performed_at}
                    />
                </div>
                <div className="md:col-span-7">
                    <Label
                        htmlFor={`result_method_${task.id}`}
                        className="mb-1 block text-[11px] font-semibold text-slate-700"
                    >
                        Standard method / protocol
                    </Label>
                    <div className="relative">
                        <Input
                            id={`result_method_${task.id}`}
                            className="h-10 pr-28 text-xs"
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            placeholder="Defaults from Procedures"
                            value={formData.result_method}
                            onChange={(e) =>
                                onFieldChange('result_method', e.target.value)
                            }
                        />
                        {task.procedure_method ? (
                            <span className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-slate-600 uppercase">
                                SOP default
                            </span>
                        ) : null}
                    </div>
                    <InputError
                        className="mt-0.5"
                        message={formErrors.result_method}
                    />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-2.5 md:grid-cols-12 md:items-start">
                <div className="md:col-span-8">
                    <Label
                        htmlFor={`result_value_${task.id}`}
                        className="mb-1 block text-[11px] font-semibold text-slate-700"
                    >
                        Quantitative result{' '}
                        <span className="text-rose-600">*</span>
                    </Label>
                    <div className="relative">
                        <Input
                            id={`result_value_${task.id}`}
                            className="h-10 pr-28 text-sm font-semibold"
                            required={!readOnly && !resultsLocked}
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            placeholder="e.g. 0.00023, 2.3×10⁻⁴, ND"
                            value={formData.result_value}
                            onChange={(e) =>
                                onFieldChange('result_value', e.target.value)
                            }
                            onBlur={() => {
                                if (readOnly || resultsLocked) {
                                    return;
                                }
                                const next = formatScientificResult(
                                    formData.result_value,
                                );
                                if (next !== formData.result_value) {
                                    onFieldChange('result_value', next);
                                }
                            }}
                        />
                        {resultFilled && !formErrors.result_value ? (
                            <span className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2 rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">
                                Entered
                            </span>
                        ) : null}
                    </div>
                    <InputError
                        className="mt-0.5"
                        message={formErrors.result_value}
                    />
                </div>
                <div className="md:col-span-4">
                    <Label
                        htmlFor={`result_unit_${task.id}`}
                        className="mb-1 block text-[11px] font-semibold text-slate-700"
                    >
                        Unit of measure
                    </Label>
                    <Input
                        id={`result_unit_${task.id}`}
                        className="h-10 text-xs"
                        placeholder="e.g. mg/L, CFU/mL"
                        readOnly={readOnly}
                        disabled={readOnly || resultsLocked}
                        value={formData.result_unit}
                        onChange={(e) =>
                            onFieldChange('result_unit', e.target.value)
                        }
                    />
                </div>
            </div>

            {task.requires_pass_fail === true ? (
                <div>
                    <Label className="mb-1 block text-[11px] font-semibold text-slate-700">
                        Specification conformance{' '}
                        <span className="text-rose-600">*</span>
                    </Label>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {(
                            [
                                {
                                    value: 'Passed',
                                    title: 'Passed',
                                    hint: 'Within specification',
                                    badge: 'Compliant',
                                    selected:
                                        'border-2 border-emerald-500 bg-emerald-50 text-emerald-950 shadow-sm ring-2 ring-emerald-500/20',
                                    badgeClass:
                                        'bg-emerald-200/80 text-emerald-900',
                                    iconSelected: Check,
                                },
                                {
                                    value: 'Failed',
                                    title: 'Failed',
                                    hint: 'Exceeds specification',
                                    badge: 'Non-conforming',
                                    selected:
                                        'border-2 border-rose-500 bg-rose-50 text-rose-950 shadow-sm ring-2 ring-rose-500/20',
                                    badgeClass: 'bg-rose-200/80 text-rose-900',
                                    iconSelected: Check,
                                },
                            ] as const
                        ).map((option) => {
                            const selected =
                                formData.result_pass_fail === option.value;
                            const Icon = selected
                                ? option.iconSelected
                                : Circle;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    disabled={readOnly || resultsLocked}
                                    aria-pressed={selected}
                                    className={cn(
                                        'flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                        selected
                                            ? option.selected
                                            : 'border border-dashed border-slate-300 bg-slate-50/50 text-slate-500 hover:border-slate-400 hover:bg-white',
                                        (readOnly || resultsLocked) &&
                                            'cursor-default opacity-90',
                                    )}
                                    onClick={() => {
                                        if (readOnly || resultsLocked) {
                                            return;
                                        }
                                        onFieldChange(
                                            'result_pass_fail',
                                            option.value,
                                        );
                                    }}
                                >
                                    <span className="flex items-center gap-2">
                                        <Icon
                                            className={cn(
                                                'size-4 shrink-0',
                                                selected
                                                    ? option.value === 'Passed'
                                                        ? 'text-emerald-600'
                                                        : 'text-rose-600'
                                                    : 'text-slate-400',
                                            )}
                                            strokeWidth={selected ? 2.5 : 1.5}
                                        />
                                        <span>
                                            <span
                                                className={cn(
                                                    'block text-xs tracking-wide uppercase',
                                                    selected
                                                        ? 'font-bold'
                                                        : 'font-semibold',
                                                )}
                                            >
                                                {selected ? '✓ ' : '○ '}
                                                {option.title}
                                            </span>
                                            <span className="block text-[11px] opacity-80">
                                                {option.hint}
                                            </span>
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'rounded px-1.5 py-0.5 text-[9px] font-bold tracking-wide uppercase',
                                            selected
                                                ? option.badgeClass
                                                : 'bg-transparent text-slate-400',
                                        )}
                                    >
                                        {option.badge}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    <InputError
                        className="mt-0.5"
                        message={formErrors.result_pass_fail}
                    />
                </div>
            ) : null}

            <div>
                <div className="mb-1 flex items-center justify-between gap-2">
                    <Label
                        htmlFor={`result_remarks_${task.id}`}
                        className="text-[11px] font-semibold text-slate-700"
                    >
                        Analytical remarks &amp; observations
                    </Label>
                    <span className="font-mono text-[10px] text-slate-400">
                        {remarksLen} / {REMARKS_MAX}
                    </span>
                </div>
                <Textarea
                    id={`result_remarks_${task.id}`}
                    className="min-h-0 text-xs"
                    rows={2}
                    maxLength={REMARKS_MAX}
                    placeholder="Detection limits, observations…"
                    readOnly={readOnly}
                    disabled={readOnly || resultsLocked}
                    value={formData.result_remarks}
                    onChange={(e) =>
                        onFieldChange('result_remarks', e.target.value)
                    }
                />
            </div>
        </div>
    );
}
