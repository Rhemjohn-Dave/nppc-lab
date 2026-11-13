import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import IntakeTypePresetRow, {
    type IntakeTypePreset,
} from '@/components/intake/intake-type-preset-row';
import IntakePackageRow from '@/components/intake/intake-package-row';
import LimsWorkspace from '@/components/lims/lims-workspace';
import WorkspaceHeader from '@/components/workspace-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type CustomerSuggestion = {
    id: number;
    customer_name: string;
    customer_email: string | null;
    customer_contact: string | null;
    customer_address: string | null;
    company_name: string | null;
    ownership_type: string | null;
};

type CategoryGroup = {
    category: string;
    label: string;
    items: Array<{
        id: number;
        code: string;
        name: string;
        default_price: string;
        catalog_scope: string;
    }>;
};

type IntakePackage = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    default_price: string | number;
    form_code: string | null;
    report_layout: 'controlled_form' | 'dynamic_matrix';
    classifications: string[];
    analysis_type_ids: number[];
    visible_for_aqua: boolean;
    visible_for_non_aqua: boolean;
    tests: Array<{
        id: number;
        code: string;
        name: string;
        default_price: string;
    }>;
};

type SampleRow = {
    sample_code: string;
    description: string;
    location: string;
    sample_point: string;
    collected_time: string;
    matrix: string;
    quantity: string;
    unit: string;
};

type Options = {
    ownership: string[];
    classifications: string[];
};

type DraftPayload = {
    id: number;
    reference_no: string;
    customer_name: string;
    customer_email: string | null;
    customer_contact: string | null;
    customer_address: string | null;
    company_name: string | null;
    ownership_type: string | null;
    classification: string | null;
    sampling_date: string | null;
    sampling_time: string | null;
    sample_collected_by: string | null;
    sampling_site: string | null;
    payment_mode: string | null;
    payment_terms: string | null;
    attachment_original_name: string | null;
    see_attached_file?: boolean;
    samples: SampleRow[];
};

type Props = {
    categories: CategoryGroup[];
    packages: IntakePackage[];
    type_presets: IntakeTypePreset[];
    options: Options;
    draft?: DraftPayload | null;
};

const steps = ['Customer', 'Samples', 'Tests', 'Review'] as const;

function emptySample(): SampleRow {
    return {
        sample_code: '',
        description: '',
        location: '',
        sample_point: '',
        collected_time: '',
        matrix: 'Water',
        quantity: '',
        unit: 'mL',
    };
}

function letterLabel(index: number): string {
    let n = index;
    let suffix = '';
    while (n >= 0) {
        suffix = String.fromCharCode(65 + (n % 26)) + suffix;
        n = Math.floor(n / 26) - 1;
    }
    return suffix;
}

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

export default function ReceivingBulkCreate({
    categories,
    packages,
    type_presets,
    options,
    draft = null,
}: Props) {
    const [step, setStep] = useState(0);
    const [attempted, setAttempted] = useState(false);
    const [classification, setClassification] = useState(
        draft?.classification || 'Potability',
    );
    const [seeAttached, setSeeAttached] = useState(
        draft?.see_attached_file ?? true,
    );
    const [samples, setSamples] = useState<SampleRow[]>(() => {
        if (draft?.samples?.length) {
            return draft.samples.map((row) => ({
                ...emptySample(),
                ...row,
                collected_time: toTimeInputValue(row.collected_time),
            }));
        }

        return [emptySample(), emptySample()];
    });
    const [packageIds, setPackageIds] = useState<number[]>([]);
    const [analysisTypeIds, setAnalysisTypeIds] = useState<number[]>([]);
    const [presetKeys, setPresetKeys] = useState<string[]>([]);
    const [attachment, setAttachment] = useState<File | null>(null);
    const [draftId, setDraftId] = useState<number | null>(draft?.id ?? null);
    const [draftRef, setDraftRef] = useState<string | null>(
        draft?.reference_no ?? null,
    );
    const [submitting, setSubmitting] = useState(false);
    const [savingDraft, setSavingDraft] = useState(false);
    const [suggestions, setSuggestions] = useState<CustomerSuggestion[]>([]);
    const [suggestOpen, setSuggestOpen] = useState(false);
    const [suggestLoading, setSuggestLoading] = useState(false);
    const [activeSuggestIndex, setActiveSuggestIndex] = useState(-1);
    const suggestListId = useId();
    const suggestAbort = useRef<AbortController | null>(null);

    const form = useForm({
        customer_name: draft?.customer_name ?? '',
        customer_email: draft?.customer_email ?? '',
        customer_contact: draft?.customer_contact ?? '',
        customer_address: draft?.customer_address ?? '',
        company_name: draft?.company_name ?? '',
        ownership_type: draft?.ownership_type ?? '',
        sampling_date: draft?.sampling_date ?? '',
        sampling_time: toTimeInputValue(draft?.sampling_time),
        sample_collected_by: draft?.sample_collected_by ?? '',
        sampling_site: draft?.sampling_site ?? '',
        payment_mode: draft?.payment_mode ?? '',
        payment_terms: draft?.payment_terms ?? '',
    });

    const isAqua = classification === 'Aqua';
    const isGovernment =
        form.data.ownership_type.trim().toLowerCase() === 'public';
    const organizationLabel = isGovernment ? 'Agency' : 'Company';
    const organizationPlaceholder = isGovernment
        ? 'Government agency / LGU'
        : 'Company or organization';

    useEffect(() => {
        const trimmed = form.data.customer_name.trim();
        if (trimmed.length < 2) {
            suggestAbort.current?.abort();
            setSuggestions([]);
            setSuggestLoading(false);
            setSuggestOpen(false);
            setActiveSuggestIndex(-1);

            return;
        }

        const timer = window.setTimeout(async () => {
            suggestAbort.current?.abort();
            const controller = new AbortController();
            suggestAbort.current = controller;
            setSuggestLoading(true);

            try {
                const response = await fetch(
                    `/intake/customers/suggest?q=${encodeURIComponent(trimmed)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Suggest failed');
                }

                const payload = (await response.json()) as {
                    customers?: CustomerSuggestion[];
                };
                const rows = payload.customers ?? [];
                setSuggestions(rows);
                setSuggestOpen(true);
                setActiveSuggestIndex(rows.length > 0 ? 0 : -1);
            } catch (error) {
                if ((error as Error).name === 'AbortError') {
                    return;
                }
                setSuggestions([]);
                setSuggestOpen(true);
                setActiveSuggestIndex(-1);
            } finally {
                if (!controller.signal.aborted) {
                    setSuggestLoading(false);
                }
            }
        }, 300);

        return () => {
            window.clearTimeout(timer);
        };
    }, [form.data.customer_name]);

    function applyCustomerSuggestion(customer: CustomerSuggestion) {
        form.setData({
            ...form.data,
            customer_name: customer.customer_name,
            customer_email: customer.customer_email ?? '',
            customer_contact: customer.customer_contact ?? '',
            customer_address: customer.customer_address ?? '',
            company_name: customer.company_name ?? '',
            ownership_type: customer.ownership_type ?? '',
        });
        setSuggestOpen(false);
        setSuggestions([]);
        setActiveSuggestIndex(-1);
    }

    function onCustomerNameKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (!suggestOpen || suggestions.length === 0) {
            if (event.key === 'Escape') {
                setSuggestOpen(false);
            }

            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveSuggestIndex((index) =>
                index < suggestions.length - 1 ? index + 1 : 0,
            );

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveSuggestIndex((index) =>
                index > 0 ? index - 1 : suggestions.length - 1,
            );

            return;
        }

        if (event.key === 'Enter' && activeSuggestIndex >= 0) {
            event.preventDefault();
            applyCustomerSuggestion(suggestions[activeSuggestIndex]);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setSuggestOpen(false);
        }
    }

    const scopedPackages = useMemo(
        () =>
            packages.filter((pkg) =>
                isAqua ? pkg.visible_for_aqua : pkg.visible_for_non_aqua,
            ),
        [packages, isAqua],
    );

    const scopedPresets = useMemo(
        () =>
            type_presets.filter(
                (preset) =>
                    preset.classifications.length === 0 ||
                    preset.classifications.includes(classification),
            ),
        [type_presets, classification],
    );

    const typePriceMap = useMemo(() => {
        const map = new Map<number, number>();
        categories.forEach((group) => {
            group.items.forEach((item) => {
                map.set(item.id, Number(item.default_price) || 0);
            });
        });
        packages.forEach((pkg) => {
            pkg.tests.forEach((test) => {
                if (!map.has(test.id)) {
                    map.set(test.id, Number(test.default_price) || 0);
                }
            });
        });
        type_presets.forEach((preset) => {
            preset.tests.forEach((test) => {
                if (!map.has(test.id)) {
                    map.set(test.id, Number(test.default_price) || 0);
                }
            });
        });
        return map;
    }, [categories, packages, type_presets]);

    const estimatedPerSample = useMemo(() => {
        let total = 0;
        packageIds.forEach((id) => {
            const pkg = packages.find((row) => row.id === id);
            if (pkg) {
                total += Number(pkg.default_price) || 0;
            }
        });
        const covered = new Set<number>();
        packageIds.forEach((id) => {
            packages
                .find((row) => row.id === id)
                ?.analysis_type_ids.forEach((typeId) => covered.add(typeId));
        });
        analysisTypeIds.forEach((id) => {
            if (!covered.has(id)) {
                total += typePriceMap.get(id) ?? 0;
            }
        });
        return total;
    }, [packageIds, analysisTypeIds, packages, typePriceMap]);

    function updateSample(index: number, key: keyof SampleRow, value: string) {
        setSamples((current) =>
            current.map((row, i) =>
                i === index ? { ...row, [key]: value } : row,
            ),
        );
    }

    function canContinue(): boolean {
        if (step === 0) {
            return form.data.customer_name.trim().length > 0 && !!classification;
        }
        if (step === 1) {
            return samples.length >= 2;
        }
        if (step === 2) {
            return packageIds.length > 0 || analysisTypeIds.length > 0;
        }
        return true;
    }

    function goNext() {
        if (!canContinue()) {
            setAttempted(true);
            return;
        }
        setAttempted(false);
        setStep((s) => Math.min(s + 1, steps.length - 1));
    }

    function togglePackage(pkg: IntakePackage) {
        const selected = packageIds.includes(pkg.id);
        if (selected) {
            setPackageIds((ids) => ids.filter((id) => id !== pkg.id));
            return;
        }
        setPackageIds((ids) => [...ids, pkg.id]);
        setAnalysisTypeIds((ids) => [
            ...new Set([...ids, ...pkg.analysis_type_ids]),
        ]);
    }

    function togglePreset(preset: IntakeTypePreset) {
        const selected = presetKeys.includes(preset.key);
        if (selected) {
            setPresetKeys((keys) => keys.filter((key) => key !== preset.key));
            setAnalysisTypeIds((ids) =>
                ids.filter((id) => !preset.analysis_type_ids.includes(id)),
            );
            return;
        }
        setPresetKeys((keys) => [...keys, preset.key]);
        setAnalysisTypeIds((ids) => [
            ...new Set([...ids, ...preset.analysis_type_ids]),
        ]);
    }

    function buildPayload(includeTests: boolean): FormData {
        const payload = new FormData();
        Object.entries(form.data).forEach(([key, value]) => {
            if (value) {
                payload.append(key, String(value));
            }
        });
        payload.append('classification', classification);
        payload.append('see_attached_file', seeAttached ? '1' : '0');
        if (draftId) {
            payload.append('draft_id', String(draftId));
        }
        if (includeTests) {
            packageIds.forEach((id, index) => {
                payload.append(`package_ids[${index}]`, String(id));
            });
            analysisTypeIds.forEach((id, index) => {
                payload.append(`analysis_type_ids[${index}]`, String(id));
            });
        }
        samples.forEach((sample, index) => {
            payload.append(
                `samples[${index}][sample_code]`,
                sample.sample_code || letterLabel(index),
            );
            payload.append(
                `samples[${index}][description]`,
                sample.description,
            );
            payload.append(`samples[${index}][location]`, sample.location);
            payload.append(
                `samples[${index}][sample_point]`,
                sample.sample_point,
            );
            payload.append(
                `samples[${index}][collected_time]`,
                sample.collected_time,
            );
            payload.append(
                `samples[${index}][matrix]`,
                sample.matrix || 'Water',
            );
            if (sample.quantity) {
                payload.append(`samples[${index}][quantity]`, sample.quantity);
            }
            if (sample.unit) {
                payload.append(`samples[${index}][unit]`, sample.unit);
            }
        });
        if (attachment) {
            payload.append('attachment', attachment);
        }

        return payload;
    }

    function saveDraft() {
        if (!form.data.customer_name.trim() || !classification) {
            setAttempted(true);
            setStep(0);
            return;
        }
        if (samples.length < 1) {
            setAttempted(true);
            setStep(1);
            return;
        }

        setSavingDraft(true);
        const url = draftId
            ? `/receiving/bulk-orders/${draftId}/draft`
            : '/receiving/bulk-orders/draft';

        router.post(url, buildPayload(false), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const nextDraft = (
                    page.props as { draft?: DraftPayload | null }
                ).draft;
                if (nextDraft?.id) {
                    setDraftId(nextDraft.id);
                    setDraftRef(nextDraft.reference_no);
                }
            },
            onFinish: () => setSavingDraft(false),
        });
    }

    function submit() {
        if (!canContinue()) {
            setAttempted(true);
            return;
        }

        setSubmitting(true);
        router.post('/receiving/bulk-orders', buildPayload(true), {
            forceFormData: true,
            onFinish: () => setSubmitting(false),
        });
    }

    return (
        <>
            <Head title="Bulk order" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Bulk job order"
                    description={
                        draftRef
                            ? `Draft ${draftRef} — finish to price for the cashier; encode sample names later from the Job Order.`
                            : 'Set sample count and shared tests, finish to price now, then encode sample names later from the Job Order.'
                    }
                    actions={
                        <Button asChild variant="outline" size="sm">
                            <Link href="/receiving/bulk-orders">
                                <ArrowLeft className="size-3.5" />
                                Back to bulk orders
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {steps.map((label, index) => (
                        <button
                            key={label}
                            type="button"
                            onClick={() => index < step && setStep(index)}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-semibold',
                                index === step
                                    ? 'bg-nppc-900 text-white'
                                    : index < step
                                      ? 'bg-nppc-100 text-nppc-900'
                                      : 'bg-slate-100 text-slate-500',
                            )}
                        >
                            {index + 1}. {label}
                        </button>
                    ))}
                </div>

                {step === 0 && (
                    <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                        <h2 className="text-sm font-semibold text-nppc-900">
                            Customer & request
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="relative space-y-1.5 sm:col-span-2">
                                <Label htmlFor="customer_name">
                                    Customer name *
                                </Label>
                                <Input
                                    id="customer_name"
                                    autoComplete="off"
                                    role="combobox"
                                    aria-expanded={suggestOpen}
                                    aria-controls={suggestListId}
                                    aria-autocomplete="list"
                                    value={form.data.customer_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_name',
                                            e.target.value,
                                        )
                                    }
                                    onFocus={() => {
                                        if (suggestions.length > 0) {
                                            setSuggestOpen(true);
                                        }
                                    }}
                                    onBlur={() => {
                                        window.setTimeout(
                                            () => setSuggestOpen(false),
                                            150,
                                        );
                                    }}
                                    onKeyDown={onCustomerNameKeyDown}
                                />
                                {suggestOpen ? (
                                    <div
                                        id={suggestListId}
                                        role="listbox"
                                        className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                                    >
                                        {suggestions.length === 0 ? (
                                            <p className="px-3 py-2 text-xs text-slate-500">
                                                {suggestLoading
                                                    ? 'Searching customers…'
                                                    : 'No matching customers yet.'}
                                            </p>
                                        ) : (
                                            suggestions.map((customer, index) => (
                                                <button
                                                    key={customer.id}
                                                    type="button"
                                                    role="option"
                                                    aria-selected={
                                                        index ===
                                                        activeSuggestIndex
                                                    }
                                                    className={cn(
                                                        'flex w-full flex-col gap-0.5 px-3 py-2 text-left text-xs hover:bg-slate-50',
                                                        index ===
                                                            activeSuggestIndex &&
                                                            'bg-nppc-50',
                                                    )}
                                                    onMouseDown={(event) => {
                                                        event.preventDefault();
                                                        applyCustomerSuggestion(
                                                            customer,
                                                        );
                                                    }}
                                                >
                                                    <span className="font-semibold text-slate-900">
                                                        {customer.customer_name}
                                                    </span>
                                                    <span className="text-slate-500">
                                                        {[
                                                            customer.company_name,
                                                            customer.customer_contact,
                                                            customer.customer_email,
                                                            customer.ownership_type,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') ||
                                                            'No other details on file'}
                                                    </span>
                                                </button>
                                            ))
                                        )}
                                    </div>
                                ) : null}
                                <p className="text-[11px] text-slate-500">
                                    Type at least 2 characters to search saved
                                    customers (same as intake).
                                </p>
                                {attempted && !form.data.customer_name.trim() ? (
                                    <p className="text-xs text-rose-700">
                                        Required
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label>Ownership type</Label>
                                <div className="flex flex-wrap gap-2">
                                    {options.ownership.map((item) => {
                                        const active =
                                            form.data.ownership_type === item;

                                        return (
                                            <Button
                                                key={item}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className={cn(
                                                    'h-8 text-xs',
                                                    active &&
                                                        'bg-nppc-900 hover:bg-nppc-700',
                                                )}
                                                onClick={() =>
                                                    form.setData(
                                                        'ownership_type',
                                                        active ? '' : item,
                                                    )
                                                }
                                            >
                                                {item === 'Public'
                                                    ? 'Public (Government)'
                                                    : item}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="company_name">
                                    {organizationLabel}
                                </Label>
                                <Input
                                    id="company_name"
                                    value={form.data.company_name}
                                    placeholder={organizationPlaceholder}
                                    onChange={(e) =>
                                        form.setData(
                                            'company_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Classification *</Label>
                                <Select
                                    value={classification}
                                    onValueChange={setClassification}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.classifications.map((item) => (
                                            <SelectItem key={item} value={item}>
                                                {item}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="customer_contact">Contact</Label>
                                <Input
                                    id="customer_contact"
                                    value={form.data.customer_contact}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_contact',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="customer_email">Email</Label>
                                <Input
                                    id="customer_email"
                                    type="email"
                                    value={form.data.customer_email}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_email',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="customer_address">Address</Label>
                                <Input
                                    id="customer_address"
                                    value={form.data.customer_address}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_address',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="sampling_date">
                                    Sampling date
                                </Label>
                                <Input
                                    id="sampling_date"
                                    type="date"
                                    value={form.data.sampling_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'sampling_date',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="sample_collected_by">
                                    Sampler
                                </Label>
                                <Input
                                    id="sample_collected_by"
                                    value={form.data.sample_collected_by}
                                    onChange={(e) =>
                                        form.setData(
                                            'sample_collected_by',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="attachment">
                                    Attached sample list (optional)
                                </Label>
                                <Input
                                    id="attachment"
                                    type="file"
                                    accept=".pdf,image/*"
                                    onChange={(e) =>
                                        setAttachment(
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                                <Checkbox
                                    checked={seeAttached}
                                    onCheckedChange={(checked) =>
                                        setSeeAttached(checked === true)
                                    }
                                />
                                JO/RFA shows “see attached file”; encoded sample
                                names still print on result sheets
                            </label>
                        </div>
                    </section>
                )}

                {step === 1 && (
                    <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div className="space-y-1">
                                <h2 className="text-sm font-semibold text-nppc-900">
                                    Samples ({samples.length})
                                </h2>
                                <p className="text-xs text-slate-500">
                                    Set how many samples to bill. Names are
                                    optional now — finish to price for the
                                    cashier, then encode names later (until Send
                                    to analysts). Control #s are A, B, C… then AA
                                    after Z.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-end gap-2">
                                <div className="space-y-1">
                                    <Label
                                        htmlFor="sample_count"
                                        className="text-[11px]"
                                    >
                                        Sample count
                                    </Label>
                                    <Input
                                        id="sample_count"
                                        type="number"
                                        min={2}
                                        max={200}
                                        className="h-8 w-24"
                                        value={samples.length}
                                        onChange={(e) => {
                                            const next = Math.min(
                                                200,
                                                Math.max(
                                                    2,
                                                    Number(e.target.value) || 2,
                                                ),
                                            );
                                            setSamples((rows) => {
                                                if (next === rows.length) {
                                                    return rows;
                                                }
                                                if (next > rows.length) {
                                                    return [
                                                        ...rows,
                                                        ...Array.from(
                                                            {
                                                                length:
                                                                    next -
                                                                    rows.length,
                                                            },
                                                            () => emptySample(),
                                                        ),
                                                    ];
                                                }
                                                return rows.slice(0, next);
                                            });
                                        }}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="h-8 gap-1.5"
                                    onClick={() =>
                                        setSamples((rows) => [
                                            ...rows,
                                            emptySample(),
                                        ])
                                    }
                                >
                                    <Plus className="size-3.5" />
                                    Add sample
                                </Button>
                            </div>
                        </div>
                        {attempted && samples.length < 2 ? (
                            <p className="text-xs text-rose-700">
                                Add at least two samples for a bulk order.
                            </p>
                        ) : null}
                        <div className="overflow-x-auto">
                            <table className="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr className="border-b text-left text-[11px] uppercase tracking-wide text-slate-500">
                                        <th className="px-2 py-2">#</th>
                                        <th className="px-2 py-2">Code</th>
                                        <th className="px-2 py-2">Name</th>
                                        <th className="px-2 py-2">Location</th>
                                        <th className="px-2 py-2">Sample point</th>
                                        <th className="px-2 py-2">Time</th>
                                        <th className="px-2 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {samples.map((sample, index) => (
                                        <tr
                                            key={index}
                                            className="border-b border-slate-100"
                                        >
                                            <td className="px-2 py-1.5 font-semibold text-nppc-900">
                                                {letterLabel(index)}
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    className="h-8 min-w-[4.5rem]"
                                                    value={sample.sample_code}
                                                    placeholder={letterLabel(
                                                        index,
                                                    )}
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'sample_code',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    className="h-8 min-w-[12rem]"
                                                    value={sample.description}
                                                    placeholder={
                                                        seeAttached
                                                            ? 'see attached file'
                                                            : 'Sample name'
                                                    }
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    className="h-8 min-w-[8rem]"
                                                    value={sample.location}
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'location',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    className="h-8 min-w-[8rem]"
                                                    value={sample.sample_point}
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'sample_point',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-1.5">
                                                <Input
                                                    type="time"
                                                    className="h-8 min-w-[7.5rem]"
                                                    value={sample.collected_time}
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'collected_time',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-2 py-1.5">
                                                {samples.length > 2 ? (
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="size-8 text-rose-600"
                                                        onClick={() =>
                                                            setSamples((rows) =>
                                                                rows.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {step === 2 && (
                    <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                        <h2 className="text-sm font-semibold text-nppc-900">
                            Tests (applied to every sample)
                        </h2>
                        <p className="text-xs text-slate-500">
                            Estimate ≈ ₱{estimatedPerSample.toFixed(2)} ×{' '}
                            {samples.length} = ₱
                            {(estimatedPerSample * samples.length).toFixed(2)}
                        </p>
                        {attempted &&
                        packageIds.length === 0 &&
                        analysisTypeIds.length === 0 ? (
                            <p className="text-xs text-rose-700">
                                Select at least one package or panel.
                            </p>
                        ) : null}
                        {scopedPresets.length > 0 ? (
                            <div className="space-y-2">
                                <h3 className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    Panels
                                </h3>
                                {scopedPresets.map((preset) => (
                                    <IntakeTypePresetRow
                                        key={preset.key}
                                        preset={preset}
                                        selected={presetKeys.includes(
                                            preset.key,
                                        )}
                                        selectedTypes={analysisTypeIds}
                                        estimatedPrice={preset.tests.reduce(
                                            (sum, test) =>
                                                sum +
                                                (typePriceMap.get(test.id) ??
                                                    0),
                                            0,
                                        )}
                                        onTogglePreset={() =>
                                            togglePreset(preset)
                                        }
                                        onToggleMember={(typeId) => {
                                            setAnalysisTypeIds((ids) =>
                                                ids.includes(typeId)
                                                    ? ids.filter(
                                                          (id) => id !== typeId,
                                                      )
                                                    : [...ids, typeId],
                                            );
                                            if (
                                                !presetKeys.includes(preset.key)
                                            ) {
                                                setPresetKeys((keys) => [
                                                    ...keys,
                                                    preset.key,
                                                ]);
                                            }
                                        }}
                                        onSetMembers={(mode) => {
                                            if (
                                                !presetKeys.includes(preset.key)
                                            ) {
                                                return;
                                            }
                                            const memberIds =
                                                preset.analysis_type_ids;
                                            if (mode === 'all') {
                                                setAnalysisTypeIds((ids) => [
                                                    ...new Set([
                                                        ...ids,
                                                        ...memberIds,
                                                    ]),
                                                ]);
                                                return;
                                            }
                                            setAnalysisTypeIds((ids) =>
                                                ids.filter(
                                                    (id) =>
                                                        !memberIds.includes(id),
                                                ),
                                            );
                                        }}
                                    />
                                ))}
                            </div>
                        ) : null}
                        {scopedPackages.length > 0 ? (
                            <div className="space-y-2">
                                <h3 className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    Packages
                                </h3>
                                {scopedPackages.map((pkg) => (
                                    <IntakePackageRow
                                        key={pkg.id}
                                        pkg={pkg}
                                        selected={packageIds.includes(pkg.id)}
                                        selectedTypes={analysisTypeIds}
                                        onTogglePackage={() =>
                                            togglePackage(pkg)
                                        }
                                        onToggleMember={(typeId) => {
                                            setAnalysisTypeIds((ids) =>
                                                ids.includes(typeId)
                                                    ? ids.filter(
                                                          (id) => id !== typeId,
                                                      )
                                                    : [...ids, typeId],
                                            );
                                            if (!packageIds.includes(pkg.id)) {
                                                setPackageIds((ids) => [
                                                    ...ids,
                                                    pkg.id,
                                                ]);
                                            }
                                        }}
                                        onSetMembers={(mode) => {
                                            if (!packageIds.includes(pkg.id)) {
                                                return;
                                            }
                                            const memberIds =
                                                pkg.analysis_type_ids;
                                            if (mode === 'all') {
                                                setAnalysisTypeIds((ids) => [
                                                    ...new Set([
                                                        ...ids,
                                                        ...memberIds,
                                                    ]),
                                                ]);
                                                return;
                                            }
                                            setAnalysisTypeIds((ids) =>
                                                ids.filter(
                                                    (id) =>
                                                        !memberIds.includes(id),
                                                ),
                                            );
                                        }}
                                    />
                                ))}
                            </div>
                        ) : null}
                    </section>
                )}

                {step === 3 && (
                    <section className="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                        <h2 className="text-sm font-semibold text-nppc-900">
                            Review
                        </h2>
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Customer
                                </dt>
                                <dd className="font-medium">
                                    {form.data.customer_name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Ownership
                                </dt>
                                <dd className="font-medium">
                                    {form.data.ownership_type || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    {organizationLabel}
                                </dt>
                                <dd className="font-medium">
                                    {form.data.company_name || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Classification
                                </dt>
                                <dd className="font-medium">{classification}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Samples
                                </dt>
                                <dd className="font-medium">
                                    {samples.length} (A–
                                    {letterLabel(samples.length - 1)})
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Est. total
                                </dt>
                                <dd className="font-medium">
                                    ₱
                                    {(
                                        estimatedPerSample * samples.length
                                    ).toFixed(2)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-slate-500">
                                    Attachment
                                </dt>
                                <dd className="font-medium">
                                    {attachment?.name ??
                                        draft?.attachment_original_name ??
                                        'None'}
                                </dd>
                            </div>
                        </dl>
                        <p className="text-xs text-slate-500">
                            Creates one JO for pricing now (customer can go to
                            the cashier). Encode sample names anytime until Send
                            to analysts. Head JO approval stays as usual.
                        </p>
                    </section>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={step === 0 || submitting || savingDraft}
                        onClick={() => setStep((s) => Math.max(0, s - 1))}
                    >
                        Back
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={submitting || savingDraft}
                            onClick={saveDraft}
                        >
                            {savingDraft
                                ? 'Saving…'
                                : draftId
                                  ? 'Save draft'
                                  : 'Save for later'}
                        </Button>
                        {step < steps.length - 1 ? (
                            <Button type="button" onClick={goNext}>
                                Continue
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                disabled={submitting || savingDraft}
                                onClick={submit}
                                className="bg-nppc-900 hover:bg-nppc-700"
                            >
                                {submitting
                                    ? 'Creating…'
                                    : `Finish bulk JO (${samples.length} samples)`}
                            </Button>
                        )}
                    </div>
                </div>
            </LimsWorkspace>
        </>
    );
}

ReceivingBulkCreate.layout = {
    breadcrumbs: [
        { title: 'Receiving', href: '/receiving' },
        { title: 'Bulk order', href: '/receiving/bulk-orders' },
        {
            title: 'Create',
            href: '/receiving/bulk-orders/create',
        },
    ],
};
