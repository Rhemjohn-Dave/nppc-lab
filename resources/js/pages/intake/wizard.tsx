import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Plus, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import NppcLogo from '@/components/nppc-logo';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type CategoryGroup = {
    category: string;
    label: string;
    items: Array<{
        id: number;
        code: string;
        name: string;
        default_price: string;
    }>;
};

type IntakePackage = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    default_price: string | number;
    form_code: string | null;
    classifications: string[];
    category_label: string | null;
    analysis_type_ids: number[];
    tests: Array<{
        id: number;
        code: string;
        name: string;
        default_price: string;
    }>;
};

type Prefill = {
    customer_name?: string | null;
    customer_email?: string | null;
    customer_contact?: string | null;
    customer_address?: string | null;
    company_name?: string | null;
    ownership_type?: string | null;
};

type Options = {
    ownership: string[];
    classifications: string[];
    wastewater_sources: string[];
};

type SampleDraft = {
    sample_code: string;
    description: string;
    matrix: string;
    quantity: string;
    unit: string;
    remarks: string;
};

type Props = {
    categories: CategoryGroup[];
    packages?: IntakePackage[];
    prefill: Prefill;
    options: Options;
};

const steps = ['Customer', 'Samples', 'Details', 'Tests', 'Review'] as const;

const emptySample = (): SampleDraft => ({
    sample_code: '',
    description: '',
    matrix: '',
    quantity: '',
    unit: '',
    remarks: '',
});

function ChoiceChip({
    active,
    children,
    onClick,
}: {
    active: boolean;
    children: ReactNode;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'min-h-11 rounded-xl border px-4 py-2 text-sm font-medium transition',
                active
                    ? 'border-[#1A3694] bg-[#1A3694] text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
            )}
        >
            {children}
        </button>
    );
}

export default function IntakeWizard({
    categories,
    packages = [],
    prefill,
    options,
}: Props) {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [step, setStep] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [customer, setCustomer] = useState({
        customer_name: prefill.customer_name ?? '',
        customer_email: prefill.customer_email ?? '',
        customer_contact: prefill.customer_contact ?? '',
        customer_address: prefill.customer_address ?? '',
        company_name: prefill.company_name ?? '',
    });
    const [ownershipType, setOwnershipType] = useState(
        prefill.ownership_type ?? '',
    );
    const [classification, setClassification] = useState('');
    const [classificationOther, setClassificationOther] = useState('');
    const [samplingDate, setSamplingDate] = useState('');
    const [samplingTime, setSamplingTime] = useState('');
    const [sampleCollectedBy, setSampleCollectedBy] = useState('');
    const [fieldData, setFieldData] = useState('');
    const [sterileBottle, setSterileBottle] = useState(false);
    const [sampleStorageTemp, setSampleStorageTemp] = useState('');
    const [wastewaterSource, setWastewaterSource] = useState('');
    const [wastewaterSourceOther, setWastewaterSourceOther] = useState('');
    const [otherTests, setOtherTests] = useState('');
    const [openCategory, setOpenCategory] = useState<string | null>(
        categories[0]?.category ?? null,
    );
    const [testsQuery, setTestsQuery] = useState('');
    const [selectedTypes, setSelectedTypes] = useState<number[]>([]);
    const [selectedPackageIds, setSelectedPackageIds] = useState<number[]>([]);
    const [samples, setSamples] = useState<SampleDraft[]>([emptySample()]);

    const selectedTypeSet = useMemo(
        () => new Set(selectedTypes),
        [selectedTypes],
    );

    const selectedItems = useMemo(() => {
        const map = new Map<number, { name: string; price: string }>();
        categories.forEach((group) =>
            group.items.forEach((item) =>
                map.set(item.id, {
                    name: item.name,
                    price: item.default_price,
                }),
            ),
        );
        packages.forEach((pkg) =>
            pkg.tests.forEach((item) =>
                map.set(item.id, {
                    name: item.name,
                    price: item.default_price,
                }),
            ),
        );

        return selectedTypes.map((id) => map.get(id)).filter(Boolean) as Array<{
            name: string;
            price: string;
        }>;
    }, [categories, packages, selectedTypes]);

    const filteredCategories = useMemo(() => {
        const needle = testsQuery.trim().toLowerCase();
        if (!needle) {
            return categories;
        }

        return categories
            .map((group) => ({
                ...group,
                items: group.items.filter((item) =>
                    `${item.code} ${item.name}`.toLowerCase().includes(needle),
                ),
            }))
            .filter((group) => group.items.length > 0);
    }, [categories, testsQuery]);

    const resolvedClassification =
        classification === 'Others'
            ? classificationOther.trim()
                ? `Others: ${classificationOther.trim()}`
                : 'Others'
            : classification;

    const resolvedSampleSource =
        wastewaterSource === 'Others'
            ? wastewaterSourceOther.trim()
                ? `Others: ${wastewaterSourceOther.trim()}`
                : 'Others'
            : wastewaterSource;

    const suggestedPackages = useMemo(() => {
        const value = resolvedClassification.toLowerCase();

        return packages.filter((pkg) => {
            if (pkg.classifications.length === 0) {
                return true;
            }

            return pkg.classifications.some((tag) =>
                value.includes(tag.toLowerCase()),
            );
        });
    }, [packages, resolvedClassification]);

    const otherPackages = useMemo(
        () =>
            packages.filter(
                (pkg) => !suggestedPackages.some((item) => item.id === pkg.id),
            ),
        [packages, suggestedPackages],
    );

    const estimatedTotal = selectedItems.reduce(
        (sum, item) => sum + Number(item.price || 0),
        0,
    );

    function toggleType(id: number) {
        setSelectedTypes((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    }

    function togglePackage(pkg: IntakePackage) {
        const memberIds = pkg.analysis_type_ids;
        const selected = selectedPackageIds.includes(pkg.id);

        if (selected) {
            const remaining = packages.filter(
                (item) =>
                    selectedPackageIds.includes(item.id) && item.id !== pkg.id,
            );
            const keep = new Set(
                remaining.flatMap((item) => item.analysis_type_ids),
            );
            setSelectedPackageIds((current) =>
                current.filter((id) => id !== pkg.id),
            );
            setSelectedTypes((current) =>
                current.filter((id) => !memberIds.includes(id) || keep.has(id)),
            );
            return;
        }

        setSelectedPackageIds((current) => [...current, pkg.id]);
        setSelectedTypes((current) => [
            ...new Set([...current, ...memberIds]),
        ]);
    }

    function togglePackageMember(pkg: IntakePackage, typeId: number) {
        if (!selectedPackageIds.includes(pkg.id)) {
            return;
        }

        const memberIds = pkg.analysis_type_ids;
        const currentlyOn = selectedTypes.includes(typeId);
        const selectedMembers = memberIds.filter((id) =>
            currentlyOn ? id !== typeId && selectedTypes.includes(id) : selectedTypes.includes(id) || id === typeId,
        );

        if (currentlyOn && selectedMembers.length === 0) {
            togglePackage(pkg);
            return;
        }

        if (currentlyOn) {
            setSelectedTypes((current) => current.filter((id) => id !== typeId));
            return;
        }

        setSelectedTypes((current) =>
            current.includes(typeId) ? current : [...current, typeId],
        );
    }

    function renderPackageCard(pkg: IntakePackage) {
        const selected = selectedPackageIds.includes(pkg.id);
        const selectedMemberCount = pkg.analysis_type_ids.filter((id) =>
            selectedTypes.includes(id),
        ).length;

        return (
            <div
                key={pkg.id}
                className={cn(
                    'rounded-2xl border p-4 text-left transition',
                    selected
                        ? 'border-[#1A3694] bg-[#eef3fb] shadow-sm'
                        : 'border-slate-200 bg-white hover:border-[#5282D3]',
                )}
            >
                <button
                    type="button"
                    onClick={() => togglePackage(pkg)}
                    className="w-full text-left"
                >
                    <div className="flex items-start justify-between gap-3">
                        <p className="font-semibold text-[#1A3694]">{pkg.name}</p>
                        <span className="shrink-0 text-sm font-semibold text-slate-700">
                            ₱{Number(pkg.default_price).toFixed(2)}
                        </span>
                    </div>
                    {pkg.description && (
                        <p className="mt-1 text-sm text-slate-600">
                            {pkg.description}
                        </p>
                    )}
                    {pkg.form_code && (
                        <p className="mt-2 font-mono text-[11px] text-muted-foreground">
                            {pkg.form_code}
                        </p>
                    )}
                    {selected && (
                        <p className="mt-2 text-xs font-medium text-emerald-700">
                            Selected · {selectedMemberCount}/
                            {pkg.tests.length} tests
                        </p>
                    )}
                </button>
                {selected ? (
                    <div className="mt-3 space-y-2 border-t border-[#d7e2f5] pt-3">
                        <p className="text-xs text-muted-foreground">
                            Uncheck tests you do not need. Those slots print as
                            “-” on the package result form.
                        </p>
                        {pkg.tests.map((test) => {
                            const checked = selectedTypes.includes(test.id);

                            return (
                                <label
                                    key={test.id}
                                    className="flex items-start gap-2 text-sm text-slate-700"
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={() =>
                                            togglePackageMember(pkg, test.id)
                                        }
                                    />
                                    <span>
                                        <span className="font-medium">
                                            {test.name}
                                        </span>
                                        <span className="mt-0.5 block font-mono text-[11px] text-muted-foreground">
                                            {test.code}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                ) : (
                    <p className="mt-2 text-xs text-slate-500">
                        {pkg.tests.map((test) => test.name).join(' · ')}
                    </p>
                )}
            </div>
        );
    }

    function updateSample(
        index: number,
        key: keyof SampleDraft,
        value: string,
    ) {
        setSamples((current) =>
            current.map((sample, i) =>
                i === index ? { ...sample, [key]: value } : sample,
            ),
        );
    }

    function canContinue() {
        if (step === 0) {
            return (
                customer.customer_name.trim().length > 0 &&
                customer.customer_email.trim().length > 0
            );
        }

        if (step === 1) {
            return samples.every((sample) => sample.description.trim());
        }

        if (step === 2) {
            if (!classification) {
                return false;
            }

            if (classification === 'Others' && !classificationOther.trim()) {
                return false;
            }

            if (wastewaterSource === 'Others' && !wastewaterSourceOther.trim()) {
                return false;
            }

            return true;
        }

        if (step === 3) {
            return (
                selectedTypes.length > 0 ||
                selectedPackageIds.length > 0 ||
                otherTests.trim().length > 0
            );
        }

        return true;
    }

    function stepError(index: number) {
        if (index !== step) {
            return null;
        }

        if (step === 0 && !canContinue()) {
            return 'Add your full name and email to continue.';
        }

        if (step === 1 && !canContinue()) {
            return 'Each sample needs at least a description.';
        }

        if (step === 2 && !canContinue()) {
            return 'Choose a classification before continuing.';
        }

        if (step === 3 && !canContinue()) {
            return 'Select at least one listed test or describe another test.';
        }

        return null;
    }

    function submit() {
        setSubmitting(true);
        router.post(
            '/intake/job-orders',
            {
                ...customer,
                ownership_type: ownershipType || null,
                classification: resolvedClassification || null,
                sampling_date: samplingDate || null,
                sampling_time: samplingTime || null,
                sample_collected_by: sampleCollectedBy || null,
                field_data: sterileBottle
                    ? fieldData.trim()
                        ? `Water in sterile bottle. ${fieldData.trim()}`
                        : 'Water in sterile bottle'
                    : fieldData || null,
                sample_storage_temp: sampleStorageTemp || null,
                wastewater_source: resolvedSampleSource || null,
                other_tests: otherTests || null,
                samples: samples.map((sample) => ({
                    ...sample,
                    quantity: sample.quantity || null,
                })),
                analysis_type_ids: selectedTypes,
                package_ids: selectedPackageIds,
            },
            {
                onFinish: () => setSubmitting(false),
            },
        );
    }

    const progress = ((step + 1) / steps.length) * 100;
    const stepHints = [
        'Tell us who you are',
        'Describe your samples',
        'Sampling and classification',
        'Choose laboratory tests',
        'Confirm and submit',
    ] as const;

    return (
        <div className="relative min-h-screen overflow-hidden bg-[#f4f7fc] text-slate-900">
            <Head title="Request for Analysis" />

            <div aria-hidden className="pointer-events-none absolute inset-x-0 top-0 h-56 overflow-hidden sm:h-64">
                <img
                    src="/nppc.jpg"
                    alt=""
                    className="size-full object-cover object-[center_28%] opacity-40"
                />
                <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(15,42,120,0.72)_0%,rgba(244,247,252,0.92)_72%,#f4f7fc_100%)]" />
            </div>

            <div className="relative mx-auto max-w-4xl px-4 py-8 sm:px-6 sm:py-10">
                <header className="intake-enter mb-8 flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-md ring-4 ring-white/40 sm:size-16">
                            <NppcLogo className="h-[90%] w-[90%]" />
                        </div>
                        <div>
                            <p className="text-xs font-medium tracking-[0.2em] text-white/80 uppercase">
                                Customer intake
                            </p>
                            <h1 className="font-heading text-3xl font-semibold text-white sm:text-4xl">
                                Request for Analysis
                            </h1>
                            <p className="mt-1 text-sm text-white/75">
                                {stepHints[step]}
                            </p>
                        </div>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 border-white/30 bg-white/95 text-[#1A3694] hover:bg-white"
                    >
                        <Link href="/intake">Cancel</Link>
                    </Button>
                </header>

                <div className="intake-enter-delay mb-6 rounded-2xl border border-[#1A3694]/10 bg-white/90 p-4 shadow-[0_10px_30px_rgba(26,54,148,0.06)] backdrop-blur">
                    <div className="mb-3 flex items-center justify-between text-sm">
                        <span className="font-semibold text-[#1A3694]">
                            Step {step + 1} of {steps.length}
                        </span>
                        <span className="tabular-nums text-slate-500">
                            {Math.round(progress)}%
                        </span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-[#e8eef8]">
                        <div
                            className="h-full rounded-full bg-[#1A3694] transition-all duration-500 ease-out"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                    <ol className="mt-4 grid grid-cols-5 gap-2">
                        {steps.map((label, index) => {
                            const done = index < step;
                            const current = index === step;

                            return (
                                <li
                                    key={label}
                                    className="flex min-w-0 flex-col items-center gap-1.5 text-center"
                                >
                                    <button
                                        type="button"
                                        disabled={!done}
                                        onClick={() => done && setStep(index)}
                                        className={cn(
                                            'flex size-8 items-center justify-center rounded-full text-xs font-semibold transition',
                                            current &&
                                                'bg-[#1A3694] text-white shadow-sm',
                                            done &&
                                                'bg-[#5282D3]/25 text-[#1A3694] hover:bg-[#5282D3]/35',
                                            !current &&
                                                !done &&
                                                'bg-slate-100 text-slate-400',
                                            done && 'cursor-pointer',
                                        )}
                                    >
                                        {done ? (
                                            <Check className="size-4" />
                                        ) : (
                                            index + 1
                                        )}
                                    </button>
                                    <span
                                        className={cn(
                                            'hidden truncate text-[11px] font-medium sm:block',
                                            current
                                                ? 'text-[#1A3694]'
                                                : 'text-slate-500',
                                        )}
                                    >
                                        {label}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                    {stepError(step) && (
                        <p className="mt-3 text-sm text-amber-700">
                            {stepError(step)}
                        </p>
                    )}
                </div>

                <div
                    key={step}
                    className="intake-enter rounded-3xl border border-[#1A3694]/10 bg-white p-5 shadow-[0_16px_48px_rgba(26,54,148,0.08)] sm:p-8"
                >
                    {errors?.analysis_type_ids && (
                        <InputError
                            className="mb-4"
                            message={errors.analysis_type_ids}
                        />
                    )}

                    {step === 0 && (
                        <div className="space-y-5">
                            <div className="border-b border-slate-100 pb-4">
                                <h2 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                    Your details
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    Name and email are required so we can notify
                                    you when results are ready for pickup.
                                    Contact number is recommended.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'customer_name',
                                            'Full name *',
                                            'text',
                                        ],
                                        ['customer_email', 'Email *', 'email'],
                                        [
                                            'customer_contact',
                                            'Contact number',
                                            'tel',
                                        ],
                                        ['company_name', 'Company', 'text'],
                                        ['customer_address', 'Address', 'text'],
                                    ] as const
                                ).map(([key, label, type]) => (
                                    <div
                                        key={key}
                                        className={
                                            key === 'customer_address'
                                                ? 'sm:col-span-2'
                                                : ''
                                        }
                                    >
                                        <Label htmlFor={key}>{label}</Label>
                                        <Input
                                            id={key}
                                            type={type}
                                            className="mt-1 h-11"
                                            value={customer[key]}
                                            onChange={(e) =>
                                                setCustomer((c) => ({
                                                    ...c,
                                                    [key]: e.target.value,
                                                }))
                                            }
                                        />
                                        {key === 'customer_name' &&
                                            !customer.customer_name.trim() && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Required for the Request for
                                                    Analysis.
                                                </p>
                                            )}
                                        {key === 'customer_email' &&
                                            !customer.customer_email.trim() && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Required so NPPC can notify
                                                    you when results are ready.
                                                </p>
                                            )}
                                    </div>
                                ))}
                            </div>
                            <div>
                                <Label>Type of ownership</Label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {options.ownership.map((item) => (
                                        <ChoiceChip
                                            key={item}
                                            active={ownershipType === item}
                                            onClick={() =>
                                                setOwnershipType(
                                                    ownershipType === item
                                                        ? ''
                                                        : item,
                                                )
                                            }
                                        >
                                            {item}
                                        </ChoiceChip>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-4">
                                <h2 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                    Samples
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    Add each sample you are submitting today.
                                </p>
                            </div>
                            {samples.map((sample, index) => (
                                <div
                                    key={index}
                                    className="rounded-2xl border border-slate-200 bg-[#f8fafc] p-4"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <p className="font-medium text-[#1A3694]">
                                            Sample {index + 1}
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setSamples((current) => {
                                                        const copy = {
                                                            ...current[index],
                                                            sample_code: '',
                                                        };

                                                        return [
                                                            ...current.slice(
                                                                0,
                                                                index + 1,
                                                            ),
                                                            copy,
                                                            ...current.slice(
                                                                index + 1,
                                                            ),
                                                        ];
                                                    })
                                                }
                                            >
                                                Duplicate
                                            </Button>
                                            {samples.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setSamples((current) =>
                                                            current.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <Label>Sample code</Label>
                                            <Input
                                                className="mt-1 h-11 bg-white"
                                                value={sample.sample_code}
                                                onChange={(e) =>
                                                    updateSample(
                                                        index,
                                                        'sample_code',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label>Description *</Label>
                                            <Input
                                                className="mt-1 h-11 bg-white"
                                                value={sample.description}
                                                onChange={(e) =>
                                                    updateSample(
                                                        index,
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label>Matrix</Label>
                                            <Input
                                                className="mt-1 h-11 bg-white"
                                                placeholder="Water, soil, food…"
                                                value={sample.matrix}
                                                onChange={(e) =>
                                                    updateSample(
                                                        index,
                                                        'matrix',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <Label>Quantity</Label>
                                                <Input
                                                    className="mt-1 h-11 bg-white"
                                                    value={sample.quantity}
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'quantity',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label>Unit</Label>
                                                <Input
                                                    className="mt-1 h-11 bg-white"
                                                    placeholder="mL, g…"
                                                    value={sample.unit}
                                                    onChange={(e) =>
                                                        updateSample(
                                                            index,
                                                            'unit',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Label>Remarks</Label>
                                            <Input
                                                className="mt-1 h-11 bg-white"
                                                value={sample.remarks}
                                                onChange={(e) =>
                                                    updateSample(
                                                        index,
                                                        'remarks',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 border-[#1A3694]/20 text-[#1A3694]"
                                onClick={() =>
                                    setSamples((current) => [
                                        ...current,
                                        emptySample(),
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                Add another sample
                            </Button>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-5">
                            <div className="border-b border-slate-100 pb-4">
                                <h2 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                    Sample details
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    These appear on the official Request for
                                    Analysis form.
                                </p>
                            </div>

                            <div>
                                <Label>Sample classification *</Label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {options.classifications.map((item) => (
                                        <ChoiceChip
                                            key={item}
                                            active={classification === item}
                                            onClick={() =>
                                                setClassification(item)
                                            }
                                        >
                                            {item}
                                        </ChoiceChip>
                                    ))}
                                </div>
                                {classification === 'Others' && (
                                    <Input
                                        className="mt-3 h-11"
                                        placeholder="Please specify classification"
                                        value={classificationOther}
                                        onChange={(e) =>
                                            setClassificationOther(
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="sampling_date">
                                        Sampling date
                                    </Label>
                                    <Input
                                        id="sampling_date"
                                        type="date"
                                        className="mt-1 h-11"
                                        value={samplingDate}
                                        onChange={(e) =>
                                            setSamplingDate(e.target.value)
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="sampling_time">
                                        Sampling time
                                    </Label>
                                    <Input
                                        id="sampling_time"
                                        type="time"
                                        className="mt-1 h-11"
                                        value={samplingTime}
                                        onChange={(e) =>
                                            setSamplingTime(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label htmlFor="sample_collected_by">
                                        Sample collected by
                                    </Label>
                                    <Input
                                        id="sample_collected_by"
                                        className="mt-1 h-11"
                                        value={sampleCollectedBy}
                                        onChange={(e) =>
                                            setSampleCollectedBy(e.target.value)
                                        }
                                    />
                                </div>
                                <div>
                                    <Label>Field data (Potability)</Label>
                                    <label className="mt-2 flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm">
                                        <Checkbox
                                            checked={sterileBottle}
                                            onCheckedChange={(checked) =>
                                                setSterileBottle(checked === true)
                                            }
                                        />
                                        <span>Water in sterile bottle</span>
                                    </label>
                                    <Textarea
                                        id="field_data"
                                        className="mt-3"
                                        placeholder="Other potability notes (optional)"
                                        value={fieldData}
                                        onChange={(e) =>
                                            setFieldData(e.target.value)
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="sample_storage_temp">
                                        Sample storage temp. (as received)
                                    </Label>
                                    <Input
                                        id="sample_storage_temp"
                                        className="mt-1 h-11"
                                        placeholder="e.g. Ambient / 4°C"
                                        value={sampleStorageTemp}
                                        onChange={(e) =>
                                            setSampleStorageTemp(e.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            <div>
                                <Label>Sample source</Label>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Same field as the Request for Analysis
                                    (Local water district, Tank, Faucet,
                                    Deepwell). Used as Sampling Point on the
                                    drinking-water result sheet.
                                </p>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {options.wastewater_sources.map((item) => (
                                        <ChoiceChip
                                            key={item}
                                            active={wastewaterSource === item}
                                            onClick={() => {
                                                setWastewaterSource(
                                                    wastewaterSource === item
                                                        ? ''
                                                        : item,
                                                );
                                                if (item !== 'Others') {
                                                    setWastewaterSourceOther('');
                                                }
                                            }}
                                        >
                                            {item}
                                        </ChoiceChip>
                                    ))}
                                </div>
                                {wastewaterSource === 'Others' && (
                                    <Input
                                        className="mt-3 h-11"
                                        placeholder="Please specify sample source"
                                        value={wastewaterSourceOther}
                                        onChange={(e) =>
                                            setWastewaterSourceOther(
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 pb-4">
                                <div>
                                    <h2 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                        Select analyses
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-600">
                                        Choose a package when it matches your
                                        sample, or pick individual tests.
                                        Prices may be adjusted by Receiving.
                                    </p>
                                </div>
                                <div className="rounded-xl bg-[#e8eef8] px-3 py-2 text-sm font-semibold text-[#1A3694]">
                                    {selectedTypes.length} selected
                                    {selectedTypes.length > 0
                                        ? ` · Est. ₱${estimatedTotal.toFixed(2)}`
                                        : ''}
                                </div>
                            </div>

                            {packages.length > 0 && (
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm font-medium text-slate-900">
                                            {suggestedPackages.length > 0
                                                ? `Suggested packages${resolvedClassification ? ` for ${resolvedClassification}` : ''}`
                                                : 'Analysis packages'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Select a package, then uncheck any
                                            tests you do not need. The result
                                            form stays the package sheet;
                                            unchecked tests print as “-”.
                                        </p>
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {(suggestedPackages.length > 0
                                            ? suggestedPackages
                                            : packages
                                        ).map((pkg) => renderPackageCard(pkg))}
                                    </div>
                                    {suggestedPackages.length > 0 &&
                                        otherPackages.length > 0 && (
                                            <div className="space-y-2">
                                                <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                                    Other packages
                                                </p>
                                                <div className="grid gap-3 md:grid-cols-2">
                                                    {otherPackages.map((pkg) =>
                                                        renderPackageCard(pkg),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                </div>
                            )}

                            <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-[#f8fafc] p-4 lg:flex-row lg:items-start lg:justify-between">
                                <div className="max-w-xl">
                                    <p className="text-sm font-medium text-slate-900">
                                        Search and select tests
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Search by test code or name. Receiving
                                        can still adjust prices after
                                        submission.
                                    </p>
                                </div>
                                <div className="relative w-full max-w-sm">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        className="pl-9"
                                        placeholder="Search tests..."
                                        value={testsQuery}
                                        onChange={(e) =>
                                            setTestsQuery(e.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            {filteredCategories.map((group) => {
                                const selectedCount = group.items.filter(
                                    (item) => selectedTypeSet.has(item.id),
                                ).length;
                                const open = openCategory === group.category;

                                return (
                                    <div
                                        key={group.category}
                                        className="overflow-hidden rounded-2xl border border-slate-200"
                                    >
                                        <button
                                            type="button"
                                            className="flex w-full items-center justify-between gap-3 bg-[#f8fafc] px-4 py-4 text-left"
                                            onClick={() =>
                                                setOpenCategory((current) =>
                                                    current === group.category
                                                        ? null
                                                        : group.category,
                                                )
                                            }
                                        >
                                            <span className="font-semibold text-[#1A3694]">
                                                {group.label}
                                            </span>
                                            <span className="flex items-center gap-2 text-xs text-slate-500">
                                                {selectedCount} selected
                                                <ChevronDown
                                                    className={cn(
                                                        'size-4 transition',
                                                        open && 'rotate-180',
                                                    )}
                                                />
                                            </span>
                                        </button>
                                        {open && (
                                            <div className="grid gap-2 border-t px-4 py-3 sm:grid-cols-2">
                                                {group.items.map((item) => {
                                                    const checked =
                                                        selectedTypeSet.has(
                                                            item.id,
                                                        );

                                                    return (
                                                        <label
                                                            key={item.id}
                                                            className={cn(
                                                                'flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 text-sm transition',
                                                                checked
                                                                    ? 'border-[#1A3694] bg-[#eef3fb]'
                                                                    : 'border-slate-100 bg-white hover:border-[#5282D3]/40',
                                                            )}
                                                        >
                                                            <Checkbox
                                                                checked={
                                                                    checked
                                                                }
                                                                onCheckedChange={() =>
                                                                    toggleType(
                                                                        item.id,
                                                                    )
                                                                }
                                                                className="mt-0.5"
                                                            />
                                                            <span>
                                                                <span className="font-medium">
                                                                    {item.code}
                                                                </span>{' '}
                                                                {item.name}
                                                                <span className="mt-0.5 block text-xs text-slate-500">
                                                                    ₱
                                                                    {Number(
                                                                        item.default_price,
                                                                    ).toFixed(
                                                                        2,
                                                                    )}
                                                                </span>
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}

                            <div>
                                <Label htmlFor="other_tests">Other tests</Label>
                                <Input
                                    id="other_tests"
                                    className="mt-1 h-11"
                                    value={otherTests}
                                    onChange={(e) =>
                                        setOtherTests(e.target.value)
                                    }
                                    placeholder="Describe any test not listed above"
                                />
                            </div>

                            <div className="rounded-2xl border border-slate-200 bg-white p-4">
                                <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                    Selected tests summary
                                </p>
                                {selectedItems.length > 0 ? (
                                    <ul className="mt-2 space-y-1 text-sm">
                                        {selectedItems.slice(0, 8).map((item) => (
                                            <li
                                                key={item.name}
                                                className="flex justify-between gap-3"
                                            >
                                                <span>{item.name}</span>
                                                <span className="text-slate-500">
                                                    ₱{Number(item.price).toFixed(2)}
                                                </span>
                                            </li>
                                        ))}
                                        {selectedItems.length > 8 && (
                                            <li className="text-xs text-muted-foreground">
                                                +{selectedItems.length - 8} more selected
                                            </li>
                                        )}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No listed tests selected yet.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {step === 4 && (
                        <div className="space-y-5">
                            <div className="border-b border-slate-100 pb-4">
                                <h2 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                    Review before submit
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    Please confirm everything looks correct.
                                    Receiving will finalize pricing.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-2xl border border-slate-200 bg-[#f8fafc] p-4">
                                    <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                        Customer
                                    </p>
                                    <p className="mt-2 font-medium">
                                        {customer.customer_name}
                                    </p>
                                    <p className="text-sm text-slate-600">
                                        {[
                                            customer.company_name,
                                            customer.customer_contact,
                                            customer.customer_email,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {customer.customer_address ||
                                            'No address provided'}
                                    </p>
                                    {ownershipType && (
                                        <p className="mt-2 text-sm">
                                            Ownership: {ownershipType}
                                        </p>
                                    )}
                                </div>
                                <div className="rounded-2xl border border-slate-200 bg-[#f8fafc] p-4">
                                    <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                        Sample info
                                    </p>
                                    <p className="mt-2 text-sm">
                                        Classification:{' '}
                                        {resolvedClassification || '—'}
                                    </p>
                                    <p className="text-sm">
                                        Sampling:{' '}
                                        {[samplingDate, samplingTime]
                                            .filter(Boolean)
                                            .join(' ') || '—'}
                                    </p>
                                    <p className="text-sm">
                                        Collected by: {sampleCollectedBy || '—'}
                                    </p>
                                    <p className="text-sm">
                                        Sample source:{' '}
                                        {resolvedSampleSource || '—'}
                                    </p>
                                    <p className="text-sm">
                                        Field data:{' '}
                                        {sterileBottle
                                            ? fieldData.trim()
                                                ? `Water in sterile bottle — ${fieldData.trim()}`
                                                : 'Water in sterile bottle'
                                            : fieldData || '—'}
                                    </p>
                                    <p className="text-sm">
                                        Storage temp: {sampleStorageTemp || '—'}
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-slate-200 p-4">
                                <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                    Samples ({samples.length})
                                </p>
                                <ul className="mt-2 space-y-1 text-sm">
                                    {samples.map((sample, index) => (
                                        <li key={index}>
                                            {index + 1}.{' '}
                                            {sample.sample_code
                                                ? `${sample.sample_code} — `
                                                : ''}
                                            {sample.description}
                                            {sample.matrix
                                                ? ` (${sample.matrix})`
                                                : ''}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="rounded-2xl border border-slate-200 p-4">
                                <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                    Requested analyses
                                </p>
                                <ul className="mt-2 space-y-1 text-sm">
                                    {selectedItems.map((item) => (
                                        <li
                                            key={item.name}
                                            className="flex justify-between gap-3"
                                        >
                                            <span>{item.name}</span>
                                            <span className="text-slate-500">
                                                ₱{Number(item.price).toFixed(2)}
                                            </span>
                                        </li>
                                    ))}
                                    {otherTests && <li>Other: {otherTests}</li>}
                                </ul>
                                <p className="mt-3 font-semibold text-[#1A3694]">
                                    Estimated total: ₱
                                    {estimatedTotal.toFixed(2)}
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="mt-8 flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12"
                            disabled={step === 0 || submitting}
                            onClick={() => setStep((s) => Math.max(0, s - 1))}
                        >
                            Back
                        </Button>
                        {step < steps.length - 1 ? (
                            <Button
                                type="button"
                                className="h-12 bg-[#1A3694] hover:bg-[#365BB0] sm:min-w-44"
                                disabled={!canContinue()}
                                onClick={() => setStep((s) => s + 1)}
                            >
                                Continue
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                className="h-12 bg-[#1A3694] hover:bg-[#365BB0] sm:min-w-44"
                                disabled={submitting}
                                onClick={submit}
                            >
                                {submitting ? 'Submitting…' : 'Submit request'}
                            </Button>
                        )}
                    </div>
                </div>

                <p className="mt-6 text-center text-xs text-slate-500">
                    NPPC Analytical & Diagnostic Laboratory · Typical
                    turnaround 5–7 working days
                </p>
            </div>
        </div>
    );
}

IntakeWizard.layout = null;
