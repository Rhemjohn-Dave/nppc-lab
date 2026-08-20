import NppcLogo from '@/components/nppc-logo';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type FormSample = {
    id?: number;
    sample_code?: string | null;
    description: string;
    matrix?: string | null;
    quantity?: string | number | null;
    unit?: string | null;
    remarks?: string | null;
};

export type FormAnalysis = {
    id: number;
    name: string;
    category?: string | null;
    category_label?: string | null;
    quantity: number;
    unit_price: string | number;
    total_cost: string | number;
    status_label?: string;
    assigned_to_name?: string | null;
    result_value?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
    result_remarks?: string | null;
    analysis_type_id?: number | null;
};

export type CatalogItem = {
    id: number;
    code: string;
    name: string;
    category: string;
};

export type RequestForAnalysisData = {
    reference_no: string;
    customer_name: string;
    customer_email?: string | null;
    customer_contact?: string | null;
    customer_address?: string | null;
    company_name?: string | null;
    ownership_type?: string | null;
    classification?: string | null;
    field_data?: string | null;
    sampling_date?: string | null;
    sampling_time?: string | null;
    sample_collected_by?: string | null;
    sample_storage_temp?: string | null;
    wastewater_source?: string | null;
    other_tests?: string | null;
    status_label: string;
    total_cost: string | number;
    received_at?: string | null;
    reviewed_at?: string | null;
    created_at?: string | null;
    receiver_name?: string | null;
    reviewer_name?: string | null;
    samples: FormSample[];
    analyses: FormAnalysis[];
    catalog?: CatalogItem[];
    document_control: {
        lab?: string;
        form?: string;
        code: string;
        revision: string;
        effective: string;
    };
};

type Props = {
    jobOrder: RequestForAnalysisData;
    showResults?: boolean;
    className?: string;
    selectable?: boolean;
    selectedIds?: number[];
    onToggle?: (id: number) => void;
    showPrintButton?: boolean;
};

const CLASSIFICATIONS = [
    'Aqua',
    'Potability',
    'Wastewater',
    'Agriculture',
    'Academic/Research',
    'Others',
] as const;

const SAMPLE_SOURCES = [
    'Local water district',
    'Tank',
    'Faucet',
    'Deepwell',
    'Others',
] as const;

function specifiedOther(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const match = value.match(/^others\s*:\s*(.+)$/i);

    return match ? match[1] : value;
}

function potabilityFieldData(fieldData?: string | null): {
    sterile: boolean;
    extra: string;
} {
    const text = (fieldData || '').trim();
    const sterile = /sterile bottle/i.test(text);
    const extra = text
        .replace(/^water in sterile bottle\.?\s*/i, '')
        .trim();

    return { sterile, extra };
}

function isListedChoice(stored: string, item: string): boolean {
    const value = stored.toLowerCase();
    if (item === 'Others') {
        return value === 'others' || value.startsWith('others:');
    }

    return value === item.toLowerCase();
}

const OWNERSHIP = ['Private', 'Commercial', 'Public'] as const;

/** Paper form uses parentheses marks: ( ) / (X) */
function Mark({ checked }: { checked: boolean }) {
    return (
        <span className="inline-block w-[1.15rem] shrink-0 font-normal tracking-tight">
            {checked ? '(X)' : '( )'}
        </span>
    );
}

/** Ownership blanks on the paper form: ___ Private */
function OwnershipBlank({ checked }: { checked: boolean }) {
    return (
        <span className="inline-flex min-w-[1.75rem] items-end justify-center border-b border-black px-0.5 text-[8px] leading-none">
            {checked ? 'X' : '\u00A0'}
        </span>
    );
}

function FillLine({
    label,
    value,
    className,
    lineClassName,
}: {
    label: string;
    value?: string | null;
    className?: string;
    lineClassName?: string;
}) {
    return (
        <div
            className={cn(
                'flex min-w-0 items-end gap-1 text-[10px]',
                className,
            )}
        >
            <span className="shrink-0 font-bold">{label}</span>
            <span
                className={cn(
                    'min-w-0 flex-1 border-b border-black px-1 leading-tight',
                    lineClassName,
                )}
            >
                {value || '\u00A0'}
            </span>
        </div>
    );
}

function SignatureRow({
    label,
    caption,
    date,
    captionBold = false,
}: {
    label: string;
    caption: string;
    date?: string | null;
    captionBold?: boolean;
}) {
    return (
        <div className="mb-3 flex items-start text-[10px] last:mb-0">
            <span className="w-[6.5rem] shrink-0 pt-[1px] font-bold whitespace-nowrap">
                {label}
            </span>

            {/* Name/signature line — short, like the controlled paper form */}
            <div className="w-[15.5rem] shrink-0 sm:w-[16.5rem]">
                <div className="h-[14px] border-b border-black" />
                <p
                    className={cn(
                        'pt-0.5 text-center text-[8px] leading-tight',
                        captionBold ? 'font-bold' : 'font-normal',
                    )}
                >
                    {caption}
                </p>
            </div>

            {/* Date sits close after the name line (paper spacing) */}
            <span className="ml-4 shrink-0 pt-[1px] pr-1 font-bold whitespace-nowrap">
                Date:
            </span>
            <div className="w-[5.5rem] shrink-0">
                <div className="flex h-[14px] items-end justify-center border-b border-black px-0.5 text-[9px] leading-none">
                    {date || '\u00A0'}
                </div>
                <div className="h-[11px]" />
            </div>
        </div>
    );
}

function LinedSamplesColumn({
    rows,
}: {
    rows: Array<{ description: string; control: string }>;
}) {
    return (
        <div className="text-[9px]">
            <div className="mb-0.5 grid grid-cols-[minmax(0,1fr)_5.25rem] gap-x-3 font-bold">
                <span>Sample Code/Description</span>
                <span>Control Number</span>
            </div>
            {rows.map((row, index) => (
                <div
                    key={index}
                    className="grid grid-cols-[minmax(0,1fr)_5.25rem] items-end gap-x-3"
                >
                    <span className="min-h-[11px] truncate border-b border-black leading-none">
                        {row.description || '\u00A0'}
                    </span>
                    <span className="min-h-[11px] border-b border-black leading-none">
                        {row.control || '\u00A0'}
                    </span>
                </div>
            ))}
        </div>
    );
}

function LinedBillingColumn({
    rows,
    showResults,
}: {
    rows: FormAnalysis[];
    showResults: boolean;
}) {
    return (
        <div className="text-[9px]">
            <div className="mb-0.5 grid grid-cols-[minmax(0,1fr)_3.75rem_3.75rem] gap-x-3 font-bold">
                <span>Parameters</span>
                <span className="text-right">Price/Test</span>
                <span className="text-right">Total Cost</span>
            </div>
            {rows.map((line) => (
                <div
                    key={line.id}
                    className="grid grid-cols-[minmax(0,1fr)_3.75rem_3.75rem] items-end gap-x-3"
                >
                    <span className="min-h-[11px] truncate border-b border-black leading-none">
                        {line.name
                            ? `${line.name}${
                                  showResults && line.result_value
                                      ? ` → ${[line.result_value, line.result_measurement, line.result_unit].filter(Boolean).join(' ')}`
                                      : ''
                              }`
                            : '\u00A0'}
                    </span>
                    <span className="min-h-[11px] border-b border-black text-right leading-none tabular-nums">
                        {line.name
                            ? Number(line.unit_price || 0).toFixed(2)
                            : '\u00A0'}
                    </span>
                    <span className="min-h-[11px] border-b border-black text-right leading-none tabular-nums">
                        {line.name
                            ? Number(line.total_cost || 0).toFixed(2)
                            : '\u00A0'}
                    </span>
                </div>
            ))}
        </div>
    );
}

export default function RequestForAnalysisForm({
    jobOrder,
    showResults = false,
    className,
    selectable = false,
    selectedIds = [],
    onToggle,
    showPrintButton = true,
}: Props) {
    const selectedNames = new Set(
        jobOrder.analyses.map((line) => line.name.toLowerCase()),
    );
    const selectedTypeIds = new Set(
        jobOrder.analyses
            .map((line) => line.analysis_type_id)
            .filter((id): id is number => typeof id === 'number'),
    );

    const catalog = jobOrder.catalog ?? [];
    const micro = catalog.filter((item) => item.category === 'microbiological');
    const physico = catalog.filter(
        (item) => item.category === 'physico_chemical',
    );
    const metals = catalog.filter(
        (item) => item.category === 'trace_heavy_metals',
    );
    const lime = catalog.filter((item) => item.category === 'lime');

    const isChecked = (item: CatalogItem) =>
        selectedTypeIds.has(item.id) ||
        selectedNames.has(item.name.toLowerCase());

    const classification = (jobOrder.classification || '').toLowerCase();
    const sampleRows = Array.from({ length: 9 }, (_, index) => {
        const sample = jobOrder.samples[index];

        return {
            description: sample
                ? [sample.sample_code, sample.description]
                      .filter(Boolean)
                      .join(' — ')
                : '',
            control: sample ? jobOrder.reference_no : '',
        };
    });

    const potability = potabilityFieldData(jobOrder.field_data);
    const leftSamples = sampleRows.slice(0, 5);
    const rightSamples = sampleRows.slice(5, 9);

    while (rightSamples.length < 5) {
        rightSamples.push({ description: '', control: '' });
    }

    const billing = [...jobOrder.analyses];

    while (billing.length < 14) {
        billing.push({
            id: -billing.length - 1,
            name: '',
            quantity: 1,
            unit_price: '',
            total_cost: '',
        });
    }

    const billingLeft = billing.slice(0, 7);
    const billingRight = billing.slice(7, 14);

    return (
        <div
            className={cn(
                'rfa-form bg-white font-serif text-black print:shadow-none',
                className,
            )}
        >
            <style>{`
                @media print {
                    @page { size: 8.5in 13in; margin: 1in; }
                    .rfa-form { font-size: 9px !important; font-family: "Times New Roman", Times, serif !important; }
                }
            `}</style>

            <header className="mb-2 flex items-start gap-3 pb-1">
                <NppcLogo className="h-16 w-16 shrink-0" />
                <div className="flex-1 text-center">
                    <p className="text-[13px] font-bold tracking-wide uppercase">
                        NPPC Analytical & Diagnostic Laboratory, Inc.
                    </p>
                    <p className="text-[9px] leading-tight">
                        Block 2, Lot 29, Sta. Clara Subdivision, Circumferential
                        Road, Brgy. Banago, Bacolod City 6100 Philippines
                    </p>
                    <p className="text-[9px] leading-tight">
                        Tel Nos. 034-4332131, 034-4352613 | Email:
                        nppclab@gmail.com
                    </p>
                    <p className="mt-1 text-[12px] font-bold uppercase">
                        Request for Analysis Form / Job Order
                        {showResults ? ' — with Results' : ''}
                    </p>
                </div>
            </header>

            <div className="mb-1 grid grid-cols-2 gap-x-6 gap-y-1">
                <FillLine label="Customer:" value={jobOrder.customer_name} />
                <FillLine
                    label="Reference No.:"
                    value={jobOrder.reference_no}
                />
                <FillLine label="Address:" value={jobOrder.customer_address} />
                <FillLine
                    label="Contact No.:"
                    value={jobOrder.customer_contact}
                />
            </div>

            <div className="mb-1 flex flex-wrap items-end gap-x-4 gap-y-1 text-[10px]">
                <span className="font-bold">Type of Ownership:</span>
                {OWNERSHIP.map((item) => (
                    <span
                        key={item}
                        className="inline-flex items-end gap-1"
                    >
                        <OwnershipBlank
                            checked={
                                (
                                    jobOrder.ownership_type || ''
                                ).toLowerCase() === item.toLowerCase()
                            }
                        />
                        {item}
                    </span>
                ))}
                <FillLine
                    label="Time and Date Submitted:"
                    value={jobOrder.created_at || jobOrder.received_at}
                    className="min-w-[14rem] flex-1"
                />
            </div>

            <div className="mb-1 grid grid-cols-3 gap-x-4 gap-y-1">
                <FillLine
                    label="Sampling Date:"
                    value={jobOrder.sampling_date || ''}
                />
                <FillLine
                    label="Sampling Time:"
                    value={jobOrder.sampling_time || ''}
                />
                <FillLine
                    label="Sample Collected by:"
                    value={jobOrder.sample_collected_by || ''}
                />
            </div>

            <div className="mb-2 text-[10px]">
                <span className="font-bold">Sample Classification: </span>
                {CLASSIFICATIONS.map((item) => {
                    const checked =
                        classification.includes(item.toLowerCase()) ||
                        (item === 'Others' &&
                            !!jobOrder.classification &&
                            !CLASSIFICATIONS.slice(0, 5).some((c) =>
                                classification.includes(c.toLowerCase()),
                            ));

                    return (
                        <span
                            key={item}
                            className="mr-2 inline-flex items-center gap-0.5"
                        >
                            <Mark checked={checked} /> {item}
                            {item === 'Others' ? (
                                <span className="ml-1 inline-block min-w-24 border-b border-black px-1">
                                    {checked
                                        ? specifiedOther(jobOrder.classification)
                                        : ''}
                                </span>
                            ) : null}
                        </span>
                    );
                })}
            </div>

            <section className="mb-2">
                <div className="grid grid-cols-2 gap-x-6">
                    <LinedSamplesColumn rows={leftSamples} />
                    <LinedSamplesColumn rows={rightSamples} />
                </div>
            </section>

            <div className="mb-1 grid grid-cols-2 gap-x-6 gap-y-1 text-[10px]">
                <div className="flex min-w-0 items-end gap-1 text-[10px]">
                    <span className="shrink-0 font-bold">
                        Field Data (Potability):
                    </span>
                    <span className="inline-flex min-w-0 flex-1 items-end gap-1 border-b border-black px-1 leading-tight">
                        <Mark checked={potability.sterile} />
                        Water in sterile bottle
                        {potability.extra ? ` — ${potability.extra}` : ''}
                    </span>
                </div>
                <FillLine
                    label="Sample Storage Temp. (AS RECEIVED):"
                    value={jobOrder.sample_storage_temp || ''}
                />
            </div>
            <div className="mb-2 text-[10px]">
                <span className="font-bold">
                    Field Data for Waste Water — Sample Source:{' '}
                </span>
                {SAMPLE_SOURCES.map((item) => {
                    const stored = jobOrder.wastewater_source || '';
                    const checked = isListedChoice(stored, item);

                    return (
                        <span
                            key={item}
                            className="mr-2 inline-flex items-center gap-0.5"
                        >
                            <Mark checked={checked} /> {item}
                            {item === 'Others' ? (
                                <span className="ml-1 inline-block min-w-24 border-b border-black px-1">
                                    {checked ? specifiedOther(stored) : ''}
                                </span>
                            ) : null}
                        </span>
                    );
                })}
            </div>

            <section className="mb-2 grid grid-cols-2 gap-4 text-[9px]">
                <div>
                    <p className="mb-1 font-bold">Microbiological Analysis</p>
                    <ul className="space-y-px">
                        {micro.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-start gap-0.5"
                            >
                                <Mark checked={isChecked(item)} />
                                <span>{item.name}</span>
                            </li>
                        ))}
                    </ul>
                </div>
                <div>
                    <p className="mb-1 font-bold">Physico-Chemical Analysis</p>
                    <div className="mb-1.5 columns-2 gap-x-3 space-y-px">
                        {physico.map((item) => (
                            <div
                                key={item.id}
                                className="flex break-inside-avoid items-start gap-0.5"
                            >
                                <Mark checked={isChecked(item)} />
                                <span>{item.name}</span>
                            </div>
                        ))}
                    </div>
                    <p className="mb-0.5 font-bold">
                        Trace/Heavy Metals (Water/Food)
                    </p>
                    <div className="mb-1.5 flex flex-wrap gap-x-2 gap-y-0.5">
                        {metals.map((item) => (
                            <span
                                key={item.id}
                                className="inline-flex items-center gap-0.5"
                            >
                                <Mark checked={isChecked(item)} />
                                {item.name.replace(/.*\((.+)\)/, '$1')}
                            </span>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        {lime.map((item) => (
                            <span
                                key={item.id}
                                className="inline-flex items-center gap-0.5"
                            >
                                <Mark checked={isChecked(item)} />
                                {item.name}
                            </span>
                        ))}
                    </div>
                </div>
            </section>

            <FillLine
                label="Other Tests:"
                value={jobOrder.other_tests}
                className="mb-2"
            />

            <div className="mb-2 space-y-0.5 text-[8px] leading-snug">
                <p>
                    <span className="font-bold">Sample Retention:</span> Samples
                    are discarded after analysis, except physico-chemical
                    samples which are retained until results are released.
                </p>
                <p>
                    <span className="font-bold">Turn Around Time:</span> 5–7
                    working days.
                </p>
                <p>
                    <span className="font-bold">Terms and Condition:</span>{' '}
                    NPPC-ADL has discussed policies, pricing, and methods with
                    the customer. Acceptance of this form constitutes a binding
                    agreement.
                </p>
            </div>

            <section className="mb-1">
                <div className="grid grid-cols-2 gap-x-6">
                    <LinedBillingColumn
                        rows={billingLeft}
                        showResults={showResults}
                    />
                    <LinedBillingColumn
                        rows={billingRight}
                        showResults={showResults}
                    />
                </div>
                {/* Total sits under left billing only — short lined field like the paper */}
                <div className="mt-2 grid grid-cols-2 gap-x-6">
                    <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] items-end gap-x-1 text-[10px]">
                        <span className="font-bold">Total</span>
                        <span className="flex h-[14px] items-end justify-end border-b border-black px-1 text-[9px] leading-none">
                            {Number(jobOrder.total_cost || 0) > 0
                                ? `PHP ${Number(jobOrder.total_cost || 0).toFixed(2)}`
                                : '\u00A0'}
                        </span>
                    </div>
                    <div />
                </div>
            </section>

            {showResults && selectable && (
                <section className="mb-2 print:hidden">
                    <p className="mb-1 text-[10px] font-bold">
                        Return selected analyses
                    </p>
                    <div className="flex flex-wrap gap-2 text-[10px]">
                        {jobOrder.analyses.map((line) => (
                            <label
                                key={line.id}
                                className="inline-flex items-center gap-1 rounded border px-2 py-1 font-sans"
                            >
                                <input
                                    type="checkbox"
                                    checked={selectedIds.includes(line.id)}
                                    onChange={() => onToggle?.(line.id)}
                                />
                                {line.name}
                            </label>
                        ))}
                    </div>
                </section>
            )}

            <section className="mt-6">
                <SignatureRow
                    label="Conforme:"
                    caption="Printed Name and Signature of Customer"
                />
                <SignatureRow
                    label="Received by:"
                    caption="MIKE RYSTAR M. DELA CRUZ"
                    captionBold
                    date={jobOrder.received_at}
                />
                <SignatureRow
                    label="Reviewed by:"
                    caption="ROSELYN C. USERO"
                    captionBold
                    date={jobOrder.reviewed_at}
                />
            </section>

            <footer className="mt-10 flex items-end justify-between text-[8px] leading-tight">
                <div>
                    <div className="font-bold">
                        {jobOrder.document_control.lab ??
                            jobOrder.document_control.code}
                    </div>
                    <div className="font-bold">
                        {jobOrder.document_control.form ?? 'LSP 7.1 F01'}
                    </div>
                </div>
                <div className="text-right">
                    <div className="font-bold">
                        Revision: {jobOrder.document_control.revision}
                    </div>
                    <div className="font-bold">
                        Effectivity Date:{' '}
                        {jobOrder.document_control.effective}
                    </div>
                    <div className="mt-2 font-bold">Page 1/1</div>
                </div>
            </footer>

            {showPrintButton && (
                <div className="mt-3 print:hidden">
                    <Button
                        type="button"
                        variant="outline"
                        className="font-sans"
                        onClick={() => window.print()}
                    >
                        Print
                    </Button>
                </div>
            )}
        </div>
    );
}
