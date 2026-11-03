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
    requires_atmosphere?: boolean;
    atmosphere_complete?: boolean;
    show_notes_fields?: boolean;
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

/**
 * Lab conditions (required Temp/Humidity/Test Report #) plus FO2/FO2A references and notes.
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
    const reportPrefix = (config.test_report_prefix ?? 'CHE WW –').trim();

    const [temperature, setTemperature] = useState(config.temperature_c ?? '');
    const [humidity, setHumidity] = useState(config.humidity_pct ?? '');
    const [testReportNo, setTestReportNo] = useState(config.test_report_no ?? '');
    const [labConditions, setLabConditions] = useState(config.lab_conditions);
    const [references, setReferences] = useState(config.references);
    const [notes, setNotes] = useState(config.notes);

    useEffect(() => {
        setTemperature(config.temperature_c ?? '');
        setHumidity(config.humidity_pct ?? '');
        setTestReportNo(config.test_report_no ?? '');
        setLabConditions(config.lab_conditions);
        setReferences(config.references);
        setNotes(config.notes);
    }, [
        config.temperature_c,
        config.humidity_pct,
        config.test_report_no,
        config.lab_conditions,
        config.references,
        config.notes,
    ]);

    function submit(event: FormEvent) {
        event.preventDefault();
        onConfirm({
            temperature_c: temperature.trim(),
            humidity_pct: humidity.trim(),
            test_report_no: testReportNo.trim(),
            lab_conditions: labConditions,
            references,
            notes,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex max-h-[min(85vh,40rem)] w-full flex-col overflow-hidden rounded-lg border bg-white shadow-sm"
        >
            <div className="shrink-0 border-b px-5 pt-4 pb-3">
                <h2 className="font-heading text-base font-semibold text-nppc-900 sm:text-lg">
                    Lab conditions
                </h2>
                <p className="mt-1 text-xs text-slate-600">
                    Enter temperature, humidity, and Test Report # before encoding
                    results. Values print on FO2 / FO2A / FO3 / FO37 result sheets.
                </p>
            </div>

            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4">
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

            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t bg-slate-50/80 px-5 py-3">
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
