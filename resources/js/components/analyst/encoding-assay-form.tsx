import type { AnalystTask } from '@/components/analyst/types';
import {
    RESIDUAL_CHLORINE_RAW_SENTINEL,
    residualChlorineMode,
} from '@/components/analyst/types';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatScientificResult } from '@/lib/scientific-result';
import { cn } from '@/lib/utils';
import { Check, Circle } from 'lucide-react';
import { useEffect, useState } from 'react';

export type EncodingFormData = {
    performed_at: string;
    result_value: string;
    result_raw: string;
    result_finish: string;
    result_chlorinated: string;
    result_pass_fail: string;
    result_measurement: string;
    result_unit: string;
    result_remarks: string;
    result_remarks_raw: string;
    result_remarks_finish: string;
    result_remarks_chlorinated: string;
    result_method: string;
    accreditation_footnote: string;
};

export type EncodingFormErrors = Partial<
    Record<keyof EncodingFormData | 'analysis', string>
>;

type Props = {
    task: AnalystTask;
    formData: EncodingFormData;
    formErrors: EncodingFormErrors;
    readOnly: boolean;
    /** FO2/FO2A/FO3/FO37: Passed/Failed buttons write into result_remarks. */
    remarksPassFail?: boolean;
    analysisError?: string | null;
    onFieldChange: (key: keyof EncodingFormData, value: string) => void;
    /** Batch-update form fields (used when switching Residual Chlorine mode). */
    onFieldsPatch?: (patch: Partial<EncodingFormData>) => void;
};

const REMARKS_MAX = 1000;

export default function EncodingAssayForm({
    task,
    formData,
    formErrors,
    readOnly,
    remarksPassFail = false,
    analysisError,
    onFieldChange,
    onFieldsPatch,
}: Props) {
    const isReturned = task.status === 'returned';
    const resultsLocked = !readOnly && !formData.performed_at.trim();
    const performedAtRequired = !readOnly && !isReturned;
    const usesRawFinish = task.uses_raw_finish === true;
    const usesResidualCl = task.uses_residual_chlorine === true;
    const analysisCode = (task.analysis_type_code ?? '').trim().toUpperCase();
    const rawFinishHints =
        analysisCode === 'DW-02'
            ? {
                  rawLabel: 'Raw',
                  finishLabel: 'Finish',
                  rawHint: 'Limit: 6.5-8.5',
                  finishHint: 'Limit: 5-7 (RO / distillation)',
                  rawPlaceholder: 'e.g. 7.2',
                  finishPlaceholder: 'e.g. 6.1',
              }
            : {
                  rawLabel: 'Raw',
                  finishLabel: 'Finish',
                  rawHint: 'Limit: 600 (no RO / distillation)',
                  finishHint: 'Limit: <10 (RO / distillation)',
                  rawPlaceholder: 'e.g. 450',
                  finishPlaceholder: 'e.g. 8',
              };
    const rawFilled = formData.result_raw.trim().length > 0;
    const finishFilled = formData.result_finish.trim().length > 0;
    const chlorinatedFilled = formData.result_chlorinated.trim().length > 0;
    const derivedClMode = usesResidualCl
        ? residualChlorineMode(formData)
        : null;
    // Explicit selection so Finish/Chlorinated stick before a value is typed.
    const [residualMode, setResidualMode] = useState<
        'finish' | 'chlorinated' | 'raw' | null
    >(derivedClMode);
    useEffect(() => {
        setResidualMode(residualChlorineMode(formData));
        // Reset when switching assays; derived fields re-seed from the new task.
        // eslint-disable-next-line react-hooks/exhaustive-deps -- task.id is the intentional reset key
    }, [task.id]);
    useEffect(() => {
        if (derivedClMode) {
            setResidualMode(derivedClMode);
        }
    }, [derivedClMode]);
    const clMode = usesResidualCl
        ? (residualMode ?? derivedClMode)
        : null;
    const resultFilled = usesRawFinish
        ? rawFilled || finishFilled
        : usesResidualCl
          ? clMode !== null
          : formData.result_value.trim().length > 0;
    const requireRaw = !readOnly && !resultsLocked && !finishFilled;
    const requireFinishRawPair = !readOnly && !resultsLocked && !rawFilled;
    const setResidualClMode = (
        mode: 'finish' | 'chlorinated' | 'raw',
    ): void => {
        if (readOnly || resultsLocked) {
            return;
        }
        setResidualMode(mode);
        const patch: Partial<EncodingFormData> =
            mode === 'raw'
                ? {
                      result_raw: RESIDUAL_CHLORINE_RAW_SENTINEL,
                      result_finish: '',
                      result_chlorinated: '',
                      result_remarks_finish: '',
                      result_remarks_chlorinated: '',
                  }
                : mode === 'finish'
                  ? {
                        result_raw: '',
                        result_chlorinated: '',
                        result_remarks_chlorinated: '',
                    }
                  : {
                        result_raw: '',
                        result_finish: '',
                        result_remarks_finish: '',
                    };
        if (onFieldsPatch) {
            onFieldsPatch(patch);
            return;
        }
        for (const [key, value] of Object.entries(patch) as Array<
            [keyof EncodingFormData, string]
        >) {
            onFieldChange(key, value);
        }
    };
    const remarksLen = usesRawFinish
        ? Math.max(
              formData.result_remarks_raw.length,
              formData.result_remarks_finish.length,
          )
        : usesResidualCl
          ? Math.max(
                formData.result_remarks_finish.length,
                formData.result_remarks_chlorinated.length,
            )
          : formData.result_remarks.length;

    if (task.is_sample_preparation === true) {
        return (
            <div className="space-y-2.5">
                {(analysisError || formErrors.analysis) && (
                    <InputError message={analysisError || formErrors.analysis} />
                )}
                <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    No result needed (sample preparation). This line is billed
                    on the job order and is not printed on the result sheet.
                </p>
            </div>
        );
    }

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
                        Date &amp; time performed
                        {performedAtRequired ? (
                            <span className="text-rose-600"> *</span>
                        ) : null}
                    </Label>
                    <Input
                        id={`performed_at_${task.id}`}
                        type="datetime-local"
                        className="h-10 text-xs"
                        required={performedAtRequired}
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

            {task.supports_accreditation_footnote === true ? (
                <div>
                    <Label
                        htmlFor={`accreditation_footnote_${task.id}`}
                        className="mb-1 block text-[11px] font-semibold text-slate-700"
                    >
                        Accreditation mark (Notes)
                    </Label>
                    <select
                        id={`accreditation_footnote_${task.id}`}
                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={readOnly || resultsLocked}
                        value={
                            formData.accreditation_footnote === '' ||
                            formData.accreditation_footnote === 'none'
                                ? 'none'
                                : formData.accreditation_footnote
                        }
                        onChange={(e) =>
                            onFieldChange(
                                'accreditation_footnote',
                                e.target.value,
                            )
                        }
                    >
                        <option value="none">None</option>
                        <option value="*">* (Note 4)</option>
                        <option value="**">** (Note 5)</option>
                        <option value="***">*** (Note 6)</option>
                    </select>
                    <p className="mt-0.5 text-[10px] text-slate-500">
                        Prefixes the TEST name on FO2 / FO2A to match the Notes
                        legend (* / ** / ***).
                    </p>
                    <InputError
                        className="mt-0.5"
                        message={formErrors.accreditation_footnote}
                    />
                </div>
            ) : null}

            {usesRawFinish ? (
                <div className="grid grid-cols-1 gap-2.5 md:grid-cols-12 md:items-start">
                    <div className="md:col-span-4">
                        <Label
                            htmlFor={`result_raw_${task.id}`}
                            className="mb-1 block text-[11px] font-semibold text-slate-700"
                        >
                            {rawFinishHints.rawLabel}
                            {requireRaw ? (
                                <span className="text-rose-600"> *</span>
                            ) : null}
                        </Label>
                        <Input
                            id={`result_raw_${task.id}`}
                            className="h-10 text-sm font-semibold"
                            required={requireRaw}
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            placeholder={rawFinishHints.rawPlaceholder}
                            value={formData.result_raw}
                            onChange={(e) =>
                                onFieldChange('result_raw', e.target.value)
                            }
                            onBlur={() => {
                                if (readOnly || resultsLocked) {
                                    return;
                                }
                                const next = formatScientificResult(
                                    formData.result_raw,
                                );
                                if (next !== formData.result_raw) {
                                    onFieldChange('result_raw', next);
                                }
                            }}
                        />
                        <p className="mt-0.5 text-[10px] text-slate-500">
                            {rawFinishHints.rawHint}
                        </p>
                        <InputError
                            className="mt-0.5"
                            message={formErrors.result_raw}
                        />
                    </div>
                    <div className="md:col-span-4">
                        <Label
                            htmlFor={`result_finish_${task.id}`}
                            className="mb-1 block text-[11px] font-semibold text-slate-700"
                        >
                            {rawFinishHints.finishLabel}
                            {requireFinishRawPair ? (
                                <span className="text-rose-600"> *</span>
                            ) : null}
                        </Label>
                        <Input
                            id={`result_finish_${task.id}`}
                            className="h-10 text-sm font-semibold"
                            required={requireFinishRawPair}
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            placeholder={rawFinishHints.finishPlaceholder}
                            value={formData.result_finish}
                            onChange={(e) =>
                                onFieldChange('result_finish', e.target.value)
                            }
                            onBlur={() => {
                                if (readOnly || resultsLocked) {
                                    return;
                                }
                                const next = formatScientificResult(
                                    formData.result_finish,
                                );
                                if (next !== formData.result_finish) {
                                    onFieldChange('result_finish', next);
                                }
                            }}
                        />
                        <p className="mt-0.5 text-[10px] text-slate-500">
                            {rawFinishHints.finishHint}
                        </p>
                        <InputError
                            className="mt-0.5"
                            message={formErrors.result_finish}
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
                            placeholder="mg/L"
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            value={formData.result_unit}
                            onChange={(e) =>
                                onFieldChange('result_unit', e.target.value)
                            }
                        />
                    </div>
                    <p className="md:col-span-12 text-[10px] text-slate-500">
                        Enter Raw or Finish (at least one).
                    </p>
                </div>
            ) : usesResidualCl ? (
                <div className="grid grid-cols-1 gap-2.5 md:grid-cols-12 md:items-start">
                    <div className="md:col-span-12">
                        <Label className="mb-1 block text-[11px] font-semibold text-slate-700">
                            Result type{' '}
                            <span className="text-rose-600">*</span>
                        </Label>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            {(
                                [
                                    {
                                        mode: 'finish' as const,
                                        title: 'Finish',
                                        hint: 'Limit: <1.0 mg/L RO',
                                    },
                                    {
                                        mode: 'chlorinated' as const,
                                        title: 'Chlorinated',
                                        hint: 'Limit: 0.3–1.5 mg/L',
                                    },
                                    {
                                        mode: 'raw' as const,
                                        title: 'Raw',
                                        hint: 'Prints as -',
                                    },
                                ] as const
                            ).map((option) => {
                                const selected = clMode === option.mode;
                                const Icon = selected ? Check : Circle;

                                return (
                                    <button
                                        key={option.mode}
                                        type="button"
                                        disabled={readOnly || resultsLocked}
                                        aria-pressed={selected}
                                        className={cn(
                                            'flex items-center gap-2 rounded-lg border px-3 py-2 text-left transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                            selected
                                                ? 'border-2 border-nppc-700 bg-nppc-50 text-nppc-950 shadow-sm ring-2 ring-nppc-700/15'
                                                : 'border border-dashed border-slate-300 bg-slate-50/50 text-slate-500 hover:border-slate-400 hover:bg-white',
                                            (readOnly || resultsLocked) &&
                                                'cursor-default opacity-90',
                                        )}
                                        onClick={() =>
                                            setResidualClMode(option.mode)
                                        }
                                    >
                                        <Icon
                                            className={cn(
                                                'size-4 shrink-0',
                                                selected
                                                    ? 'text-nppc-700'
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
                                                {option.title}
                                            </span>
                                            <span className="block text-[11px] opacity-80">
                                                {option.hint}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        <p className="mt-1 text-[10px] text-slate-500">
                            Choose one: Finish, Chlorinated, or Raw. Only the
                            selected row prints a result; Raw prints as -.
                        </p>
                        <InputError
                            className="mt-0.5"
                            message={
                                formErrors.result_finish ||
                                formErrors.result_chlorinated ||
                                formErrors.result_raw
                            }
                        />
                    </div>
                    {clMode === 'finish' ? (
                        <div className="md:col-span-8">
                            <Label
                                htmlFor={`result_finish_${task.id}`}
                                className="mb-1 block text-[11px] font-semibold text-slate-700"
                            >
                                Finish value{' '}
                                <span className="text-rose-600">*</span>
                            </Label>
                            <Input
                                id={`result_finish_${task.id}`}
                                className="h-10 text-sm font-semibold"
                                required={!readOnly && !resultsLocked}
                                readOnly={readOnly}
                                disabled={readOnly || resultsLocked}
                                placeholder="e.g. 0.4"
                                value={formData.result_finish}
                                onChange={(e) =>
                                    onFieldChange(
                                        'result_finish',
                                        e.target.value,
                                    )
                                }
                                onBlur={() => {
                                    if (readOnly || resultsLocked) {
                                        return;
                                    }
                                    const next = formatScientificResult(
                                        formData.result_finish,
                                    );
                                    if (next !== formData.result_finish) {
                                        onFieldChange('result_finish', next);
                                    }
                                }}
                            />
                            <InputError
                                className="mt-0.5"
                                message={formErrors.result_finish}
                            />
                        </div>
                    ) : null}
                    {clMode === 'chlorinated' ? (
                        <div className="md:col-span-8">
                            <Label
                                htmlFor={`result_chlorinated_${task.id}`}
                                className="mb-1 block text-[11px] font-semibold text-slate-700"
                            >
                                Chlorinated value{' '}
                                <span className="text-rose-600">*</span>
                            </Label>
                            <Input
                                id={`result_chlorinated_${task.id}`}
                                className="h-10 text-sm font-semibold"
                                required={!readOnly && !resultsLocked}
                                readOnly={readOnly}
                                disabled={readOnly || resultsLocked}
                                placeholder="e.g. 0.8"
                                value={formData.result_chlorinated}
                                onChange={(e) =>
                                    onFieldChange(
                                        'result_chlorinated',
                                        e.target.value,
                                    )
                                }
                                onBlur={() => {
                                    if (readOnly || resultsLocked) {
                                        return;
                                    }
                                    const next = formatScientificResult(
                                        formData.result_chlorinated,
                                    );
                                    if (
                                        next !== formData.result_chlorinated
                                    ) {
                                        onFieldChange(
                                            'result_chlorinated',
                                            next,
                                        );
                                    }
                                }}
                            />
                            <InputError
                                className="mt-0.5"
                                message={formErrors.result_chlorinated}
                            />
                        </div>
                    ) : null}
                    {clMode === 'raw' ? (
                        <div className="md:col-span-8">
                            <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                Raw mode selected. The Raw row prints as{' '}
                                <span className="font-semibold">-</span>; Finish
                                and Chlorinated stay blank.
                            </p>
                        </div>
                    ) : null}
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
                            placeholder="mg/L"
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            value={formData.result_unit}
                            onChange={(e) =>
                                onFieldChange('result_unit', e.target.value)
                            }
                        />
                    </div>
                </div>
            ) : (
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
            )}

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

            {usesRawFinish ? (
                <div className="grid grid-cols-1 gap-2.5 md:grid-cols-2">
                    <div>
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <Label
                                htmlFor={`result_remarks_raw_${task.id}`}
                                className="text-[11px] font-semibold text-slate-700"
                            >
                                Remarks — Raw
                            </Label>
                            <span className="font-mono text-[10px] text-slate-400">
                                {formData.result_remarks_raw.length} /{' '}
                                {REMARKS_MAX}
                            </span>
                        </div>
                        <Textarea
                            id={`result_remarks_raw_${task.id}`}
                            className="min-h-0 text-xs"
                            rows={2}
                            maxLength={REMARKS_MAX}
                            placeholder="Raw observations…"
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            value={formData.result_remarks_raw}
                            onChange={(e) =>
                                onFieldChange(
                                    'result_remarks_raw',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <div>
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <Label
                                htmlFor={`result_remarks_finish_${task.id}`}
                                className="text-[11px] font-semibold text-slate-700"
                            >
                                Remarks — Finish
                            </Label>
                            <span className="font-mono text-[10px] text-slate-400">
                                {formData.result_remarks_finish.length} /{' '}
                                {REMARKS_MAX}
                            </span>
                        </div>
                        <Textarea
                            id={`result_remarks_finish_${task.id}`}
                            className="min-h-0 text-xs"
                            rows={2}
                            maxLength={REMARKS_MAX}
                            placeholder="Finish observations…"
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            value={formData.result_remarks_finish}
                            onChange={(e) =>
                                onFieldChange(
                                    'result_remarks_finish',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                </div>
            ) : usesResidualCl ? (
                clMode === 'finish' || clMode === 'chlorinated' ? (
                    <div>
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <Label
                                htmlFor={`result_remarks_${clMode}_${task.id}`}
                                className="text-[11px] font-semibold text-slate-700"
                            >
                                Remarks —{' '}
                                {clMode === 'finish'
                                    ? 'Finish'
                                    : 'Chlorinated'}
                            </Label>
                            <span className="font-mono text-[10px] text-slate-400">
                                {(clMode === 'finish'
                                    ? formData.result_remarks_finish
                                    : formData.result_remarks_chlorinated
                                ).length}{' '}
                                / {REMARKS_MAX}
                            </span>
                        </div>
                        <Textarea
                            id={`result_remarks_${clMode}_${task.id}`}
                            className="min-h-0 text-xs"
                            rows={2}
                            maxLength={REMARKS_MAX}
                            placeholder={
                                clMode === 'finish'
                                    ? 'Finish observations…'
                                    : 'Chlorinated observations…'
                            }
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            value={
                                clMode === 'finish'
                                    ? formData.result_remarks_finish
                                    : formData.result_remarks_chlorinated
                            }
                            onChange={(e) =>
                                onFieldChange(
                                    clMode === 'finish'
                                        ? 'result_remarks_finish'
                                        : 'result_remarks_chlorinated',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                ) : null
            ) : (
                <div className="space-y-2.5">
                    {remarksPassFail && task.requires_pass_fail !== true ? (
                        <div>
                            <Label className="mb-1 block text-[11px] font-semibold text-slate-700">
                                Remarks — Passed / Failed
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
                                        },
                                        {
                                            value: 'Failed',
                                            title: 'Failed',
                                            hint: 'Exceeds specification',
                                            badge: 'Non-conforming',
                                            selected:
                                                'border-2 border-rose-500 bg-rose-50 text-rose-950 shadow-sm ring-2 ring-rose-500/20',
                                            badgeClass:
                                                'bg-rose-200/80 text-rose-900',
                                        },
                                    ] as const
                                ).map((option) => {
                                    const selected =
                                        formData.result_remarks.trim() ===
                                        option.value;
                                    const Icon = selected ? Check : Circle;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            disabled={
                                                readOnly || resultsLocked
                                            }
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
                                                if (
                                                    readOnly ||
                                                    resultsLocked
                                                ) {
                                                    return;
                                                }
                                                onFieldChange(
                                                    'result_remarks',
                                                    option.value,
                                                );
                                            }}
                                        >
                                            <span className="flex items-center gap-2">
                                                <Icon
                                                    className={cn(
                                                        'size-4 shrink-0',
                                                        selected
                                                            ? option.value ===
                                                              'Passed'
                                                                ? 'text-emerald-600'
                                                                : 'text-rose-600'
                                                            : 'text-slate-400',
                                                    )}
                                                    strokeWidth={
                                                        selected ? 2.5 : 1.5
                                                    }
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
                            placeholder={
                                remarksPassFail
                                    ? 'Passed / Failed or other observations…'
                                    : 'Detection limits, observations…'
                            }
                            readOnly={readOnly}
                            disabled={readOnly || resultsLocked}
                            value={formData.result_remarks}
                            onChange={(e) =>
                                onFieldChange(
                                    'result_remarks',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
