import { useEffect, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ResultFooterConfig = {
    temperature_c?: string;
    humidity_pct?: string;
    test_report_no?: string;
    test_report_prefix?: string;
    lab_conditions: string;
    references: string;
    notes: string;
    field_data?: string;
    field_data_temperature?: string;
    field_data_dissolved_oxygen?: string;
    field_data_time_of_sampling?: string;
    field_data_time_of_sampling_from?: string;
    field_data_time_of_sampling_to?: string;
    requires_atmosphere?: boolean;
    requires_field_data?: boolean;
    atmosphere_complete?: boolean;
    field_data_complete?: boolean;
    show_notes_fields?: boolean;
    show_field_data?: boolean;
    save_url?: string | null;
    can_edit?: boolean;
};

export type ResultFooterValues = {
    temperature_c: string;
    humidity_pct: string;
    test_report_no: string;
    lab_conditions: string;
    references: string;
    notes: string;
    field_data: string;
    field_data_temperature: string;
    field_data_dissolved_oxygen: string;
    field_data_time_of_sampling: string;
    field_data_time_of_sampling_from: string;
    field_data_time_of_sampling_to: string;
};

type Props = {
    config: ResultFooterConfig;
    submitting?: boolean;
    error?: string | null;
    confirmLabel?: string;
    onCancel: () => void;
    onConfirm: (footer: ResultFooterValues) => void;
    onReset?: () => void;
};

const COASTAL_FIELD_DATA_TEMPLATE = [
    'FIELD DATA:',
    'a) Temperature –',
    'b) Dissolved Oxygen -',
    'c) Time of Sampling -',
    'd) Other field data will be available upon request',
    '(Field Sampling Logbook)',
].join('\n');

/** Normalize stored times (HH:mm or "12:04 PM") for `<input type="time">`. */
function toTimeInputValue(value: string | null | undefined): string {
    const raw = (value ?? '').trim();
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

function formatClockDisplay(value: string): string {
    const normalized = toTimeInputValue(value);
    if (!normalized) {
        return value.trim();
    }

    const [hourRaw, minute] = normalized.split(':');
    let hour = Number(hourRaw);
    const meridiem = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    if (hour === 0) {
        hour = 12;
    }

    return `${hour}:${minute} ${meridiem}`;
}

function parseTimeOfSamplingRange(value: string | null | undefined): {
    from: string;
    to: string;
} {
    const raw = (value ?? '').trim();
    if (!raw) {
        return { from: '', to: '' };
    }

    const parts = raw.split(/\s*[–\-—]\s*/).map((part) => part.trim()).filter(Boolean);
    if (parts.length >= 2) {
        return {
            from: toTimeInputValue(parts[0]),
            to: toTimeInputValue(parts[1]),
        };
    }

    return { from: toTimeInputValue(raw), to: '' };
}

function formatTimeOfSamplingRange(from: string, to: string): string {
    const start = formatClockDisplay(from);
    const end = formatClockDisplay(to);
    if (start && end) {
        return `${start} – ${end}`;
    }

    return start || end;
}

function composeCoastalFieldData(
    temperature: string,
    dissolvedOxygen: string,
    timeFrom: string,
    timeTo: string,
): string {
    const temp = temperature.trim();
    const dissolved = dissolvedOxygen.trim();
    const time = formatTimeOfSamplingRange(timeFrom, timeTo);

    if (!temp && !dissolved && !time) {
        return COASTAL_FIELD_DATA_TEMPLATE;
    }

    return [
        'FIELD DATA:',
        `a) Temperature – ${temp}`,
        `b) Dissolved Oxygen - ${dissolved}`,
        `c) Time of Sampling - ${time}`,
        'd) Other field data will be available upon request',
        '(Field Sampling Logbook)',
    ].join('\n');
}

/**
 * Lab conditions (required Temp/Humidity/Test Report #) plus FO2 family references/notes and FO2B FIELD DATA.
 */
export default function ResultFooterPanel({
    config,
    submitting = false,
    error = null,
    confirmLabel = 'Save lab conditions',
    onCancel,
    onConfirm,
    onReset,
}: Props) {
    const requiresAtmosphere = config.requires_atmosphere !== false;
    const showNotes = config.show_notes_fields === true;
    const showFieldData = config.show_field_data === true;
    const reportPrefix = (config.test_report_prefix ?? 'CHE WW –').trim();

    const [temperature, setTemperature] = useState(config.temperature_c ?? '');
    const [humidity, setHumidity] = useState(config.humidity_pct ?? '');
    const [testReportNo, setTestReportNo] = useState(config.test_report_no ?? '');
    const [labConditions, setLabConditions] = useState(config.lab_conditions);
    const [references, setReferences] = useState(config.references);
    const [notes, setNotes] = useState(config.notes);
    const [fieldTemp, setFieldTemp] = useState(
        config.field_data_temperature ?? '',
    );
    const [fieldDo, setFieldDo] = useState(
        config.field_data_dissolved_oxygen ?? '',
    );
    const initialTimeRange = parseTimeOfSamplingRange(
        config.field_data_time_of_sampling_from ||
            config.field_data_time_of_sampling_to
            ? `${config.field_data_time_of_sampling_from ?? ''} – ${config.field_data_time_of_sampling_to ?? ''}`
            : config.field_data_time_of_sampling,
    );
    const [fieldTimeFrom, setFieldTimeFrom] = useState(initialTimeRange.from);
    const [fieldTimeTo, setFieldTimeTo] = useState(initialTimeRange.to);

    useEffect(() => {
        setTemperature(config.temperature_c ?? '');
        setHumidity(config.humidity_pct ?? '');
        setTestReportNo(config.test_report_no ?? '');
        setLabConditions(config.lab_conditions);
        setReferences(config.references);
        setNotes(config.notes);
        setFieldTemp(config.field_data_temperature ?? '');
        setFieldDo(config.field_data_dissolved_oxygen ?? '');
        const range = parseTimeOfSamplingRange(
            config.field_data_time_of_sampling_from ||
                config.field_data_time_of_sampling_to
                ? `${config.field_data_time_of_sampling_from ?? ''} – ${config.field_data_time_of_sampling_to ?? ''}`
                : config.field_data_time_of_sampling,
        );
        setFieldTimeFrom(range.from);
        setFieldTimeTo(range.to);
    }, [
        config.temperature_c,
        config.humidity_pct,
        config.test_report_no,
        config.lab_conditions,
        config.references,
        config.notes,
        config.field_data_temperature,
        config.field_data_dissolved_oxygen,
        config.field_data_time_of_sampling,
        config.field_data_time_of_sampling_from,
        config.field_data_time_of_sampling_to,
    ]);

    function submit(event: FormEvent) {
        event.preventDefault();
        const fieldTemperature = fieldTemp.trim();
        const fieldDissolvedOxygen = fieldDo.trim();
        const timeFrom = toTimeInputValue(fieldTimeFrom);
        const timeTo = toTimeInputValue(fieldTimeTo);
        const timeRange = formatTimeOfSamplingRange(timeFrom, timeTo);
        onConfirm({
            temperature_c: temperature.trim(),
            humidity_pct: humidity.trim(),
            test_report_no: testReportNo.trim(),
            lab_conditions: labConditions,
            references,
            notes,
            field_data_temperature: fieldTemperature,
            field_data_dissolved_oxygen: fieldDissolvedOxygen,
            field_data_time_of_sampling: timeRange,
            field_data_time_of_sampling_from: formatClockDisplay(timeFrom),
            field_data_time_of_sampling_to: formatClockDisplay(timeTo),
            field_data: showFieldData
                ? composeCoastalFieldData(
                      fieldTemperature,
                      fieldDissolvedOxygen,
                      timeFrom,
                      timeTo,
                  )
                : '',
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex h-full max-h-[min(90vh,40rem)] w-full min-h-0 flex-1 flex-col overflow-hidden rounded-lg border bg-white shadow-sm"
        >
            <div className="shrink-0 border-b px-5 pt-4 pb-3">
                <h2 className="font-heading text-base font-semibold text-nppc-900 sm:text-lg">
                    Lab conditions
                </h2>
                <p className="mt-1 text-xs text-slate-600">
                    Enter temperature, humidity, and Test Report #
                    {showFieldData ? ', plus FIELD DATA' : ''} before encoding
                    results. Values print on FO2 / FO2A / FO2B / FO3 / FO37 result
                    sheets.
                </p>
            </div>

            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-5 py-4 pb-5">
                {error ? (
                    <p
                        className="border-l-2 border-rose-300 py-1 pl-2 text-xs font-medium text-rose-700"
                        role="alert"
                    >
                        {error}
                    </p>
                ) : null}

                {requiresAtmosphere ? (
                    <>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="lab_temperature_c">
                                    Temperature (°C)
                                </Label>
                                <Input
                                    id="lab_temperature_c"
                                    type="text"
                                    inputMode="decimal"
                                    required
                                    className="text-sm"
                                    value={temperature}
                                    onChange={(e) => setTemperature(e.target.value)}
                                    disabled={submitting}
                                    placeholder="e.g. 25"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="lab_humidity_pct">Humidity (%)</Label>
                                <Input
                                    id="lab_humidity_pct"
                                    type="text"
                                    inputMode="decimal"
                                    required
                                    className="text-sm"
                                    value={humidity}
                                    onChange={(e) => setHumidity(e.target.value)}
                                    disabled={submitting}
                                    placeholder="e.g. 55"
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="lab_test_report_no">Test Report #</Label>
                            <div className="flex items-center gap-2">
                                <span className="shrink-0 text-sm font-medium text-slate-700">
                                    {reportPrefix}
                                </span>
                                <Input
                                    id="lab_test_report_no"
                                    type="text"
                                    required
                                    className="text-sm"
                                    value={testReportNo}
                                    onChange={(e) => setTestReportNo(e.target.value)}
                                    disabled={submitting}
                                    placeholder="suffix"
                                    aria-label={`Test Report number after ${reportPrefix}`}
                                />
                            </div>
                        </div>
                    </>
                ) : null}

                {showFieldData ? (
                    <div className="space-y-2">
                        <div>
                            <Label>FIELD DATA</Label>
                            <p className="mt-0.5 text-[11px] text-slate-500">
                                Required for Coastal Water — answer each item
                                before encoding (same as temperature and
                                humidity).
                            </p>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="field_data_temperature">
                                    a) Temperature
                                </Label>
                                <Input
                                    id="field_data_temperature"
                                    type="text"
                                    inputMode="decimal"
                                    required={config.requires_field_data === true}
                                    className="text-sm"
                                    value={fieldTemp}
                                    onChange={(e) => setFieldTemp(e.target.value)}
                                    disabled={submitting}
                                    placeholder="e.g. 28"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="field_data_dissolved_oxygen">
                                    b) Dissolved Oxygen
                                </Label>
                                <Input
                                    id="field_data_dissolved_oxygen"
                                    type="text"
                                    inputMode="decimal"
                                    required={config.requires_field_data === true}
                                    className="text-sm"
                                    value={fieldDo}
                                    onChange={(e) => setFieldDo(e.target.value)}
                                    disabled={submitting}
                                    placeholder="e.g. 6.2"
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label>c) Time of Sampling</Label>
                            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                                <Input
                                    id="field_data_time_of_sampling_from"
                                    type="time"
                                    required={config.requires_field_data === true}
                                    className="text-sm"
                                    value={fieldTimeFrom}
                                    onChange={(e) =>
                                        setFieldTimeFrom(e.target.value)
                                    }
                                    disabled={submitting}
                                    aria-label="Time of sampling from"
                                />
                                <span className="text-xs font-medium text-slate-500">
                                    to
                                </span>
                                <Input
                                    id="field_data_time_of_sampling_to"
                                    type="time"
                                    required={config.requires_field_data === true}
                                    className="text-sm"
                                    value={fieldTimeTo}
                                    onChange={(e) =>
                                        setFieldTimeTo(e.target.value)
                                    }
                                    disabled={submitting}
                                    aria-label="Time of sampling to"
                                />
                            </div>
                        </div>
                        <p className="text-[11px] text-slate-500">
                            d) Other field data will be available upon request
                            (Field Sampling Logbook)
                        </p>
                        <div className="space-y-1.5">
                            <Label htmlFor="fo2_field_data_preview">
                                FIELD DATA preview
                            </Label>
                            <Textarea
                                id="fo2_field_data_preview"
                                rows={7}
                                className="font-mono text-xs leading-relaxed"
                                value={composeCoastalFieldData(
                                    fieldTemp,
                                    fieldDo,
                                    fieldTimeFrom,
                                    fieldTimeTo,
                                )}
                                readOnly
                                disabled={submitting}
                            />
                        </div>
                    </div>
                ) : null}

                {showNotes ? (
                    <>
                        <div className="space-y-1.5">
                            <Label htmlFor="fo2_lab_conditions">
                                Lab conditions preview
                            </Label>
                            <Textarea
                                id="fo2_lab_conditions"
                                rows={3}
                                className="text-sm"
                                value={
                                    temperature.trim() && humidity.trim()
                                        ? `Laboratory Conditions during the conduct of test: Temp = ${temperature.trim()} °C ; Humidity = ${humidity.trim()} %\nNA – Not Applicable for specified water body .`
                                        : labConditions
                                }
                                readOnly
                                disabled={submitting}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="fo2_references">References</Label>
                            <Textarea
                                id="fo2_references"
                                rows={3}
                                className="text-sm"
                                value={references}
                                onChange={(e) => setReferences(e.target.value)}
                                disabled={submitting}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="fo2_notes">Notes</Label>
                            <Textarea
                                id="fo2_notes"
                                rows={8}
                                className="font-mono text-xs leading-relaxed"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                disabled={submitting}
                            />
                        </div>
                    </>
                ) : null}
            </div>

            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t bg-slate-50 px-5 py-3">
                {onReset ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="mr-auto text-xs"
                        disabled={submitting}
                        onClick={onReset}
                    >
                        Reset
                    </Button>
                ) : null}
                <Button
                    type="button"
                    variant="outline"
                    disabled={submitting}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Saving…' : confirmLabel}
                </Button>
            </div>
        </form>
    );
}
