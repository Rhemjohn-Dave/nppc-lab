import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import FormSection from '@/components/intake/form-section';
import IntakeField from '@/components/intake/intake-field';
import IntakeIndividualTestCatalog from '@/components/intake/intake-individual-test-catalog';
import IntakePackageRow from '@/components/intake/intake-package-row';
import IntakePageShell from '@/components/intake/intake-page-shell';
import IntakeSampleRow from '@/components/intake/intake-sample-row';
import IntakeStepHeader from '@/components/intake/intake-step-header';
import IntakeStepper from '@/components/intake/intake-stepper';
import IntakeStickyFooter from '@/components/intake/intake-sticky-footer';
import IntakeTypePresetRow, {
    type IntakeTypePreset,
} from '@/components/intake/intake-type-preset-row';
import TestsRequestSummary from '@/components/intake/tests-request-summary';
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
    category: string | null;
    category_label: string | null;
    analysis_type_ids: number[];
    visible_for_aqua: boolean;
    visible_for_non_aqua: boolean;
    tests: Array<{
        id: number;
        code: string;
        name: string;
        default_price: string;
        catalog_scope: string;
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
    other_classification_options: string[];
    wastewater_sources: string[];
    aqua_sources: string[];
};

const PAYMENT_MODE_OPTIONS = [
    { value: 'cash', label: 'Cash' },
    { value: 'billing_partial', label: 'Billing / Partial' },
    { value: 'check', label: 'Check' },
] as const;

const PAYMENT_TERMS_OPTIONS = [
    { value: '15_days', label: '15 days' },
    { value: '30_days', label: '30 days' },
] as const;

const CLASSIFICATION_BLURBS: Record<string, string> = {
    Aqua: 'Aquaculture water and related samples',
    Potability: 'Drinking-water analysis',
    Wastewater: 'Wastewater analysis',
    Agriculture: 'Agricultural samples',
    'Academic/Research': 'Research-related requests',
    Others: 'Other laboratory requests',
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
    type_presets?: IntakeTypePreset[];
    prefill: Prefill;
    options: Options;
};

const steps = ['Customer', 'Details', 'Samples', 'Tests', 'Review'] as const;

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
                'min-h-9 rounded-xl border px-3 py-1.5 text-sm font-medium transition',
                active
                    ? 'border-[#1A3694] bg-[#1A3694] text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
            )}
        >
            {children}
        </button>
    );
}

function ReviewRow({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <p className="text-xs tracking-wide text-slate-500 uppercase">
                {label}
            </p>
            <p className="text-sm break-words text-slate-800">
                {value.trim() || '—'}
            </p>
        </div>
    );
}

function ReviewEditButton({ onClick }: { onClick: () => void }) {
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            className="h-8"
            onClick={onClick}
        >
            Edit
        </Button>
    );
}

function RadioOption({
    name,
    value,
    checked,
    label,
    hint,
    onChange,
}: {
    name: string;
    value: string;
    checked: boolean;
    label: string;
    hint?: string;
    onChange: () => void;
}) {
    return (
        <label
            className={cn(
                'flex min-h-9 cursor-pointer items-center gap-2.5 rounded-xl border px-3 py-2 text-sm transition',
                checked
                    ? 'border-[#1A3694] bg-[#1A3694]/[0.06] text-[#1A3694] shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
            )}
        >
            <input
                type="radio"
                name={name}
                value={value}
                checked={checked}
                onChange={onChange}
                className="size-4 shrink-0 accent-[#1A3694]"
            />
            <span className="min-w-0">
                <span className="font-medium">{label}</span>
                {hint ? (
                    <span className="mt-0.5 block text-xs text-slate-500">
                        {hint}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

function isGenericOtherCategory(label: string): boolean {
    return label.trim().toLowerCase() === 'other';}

const FOOD_CATEGORY_SLUGS = new Set([
    'food_products_microbiological',
    'proximate_analysis',
    'other_food_analysis',
    'nutrifacts',
    'phytochemical',
]);

function isAquaClassificationValue(classification: string): boolean {
    const value = classification.toLowerCase();
    if (!value.includes('aqua')) {
        return false;
    }

    return !value.includes('potability') && !value.includes('wastewater');
}

function scopeVisible(scope: string, isAqua: boolean): boolean {
    if (scope === 'both') {
        return true;
    }

    if (scope === 'aqua') {
        return isAqua;
    }

    return !isAqua;
}

export default function IntakeWizard({
    categories,
    packages = [],
    type_presets = [],
    prefill,
    options,
}: Props) {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [step, setStep] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [attemptedContinue, setAttemptedContinue] = useState(false);
    const [mobileSummaryOpen, setMobileSummaryOpen] = useState(false);
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
    const [classificationOtherSpecify, setClassificationOtherSpecify] =
        useState('');
    const [samplingDate, setSamplingDate] = useState('');
    const [samplingTime, setSamplingTime] = useState('');
    const [sampleCollectedBy, setSampleCollectedBy] = useState('');
    const [samplingSite, setSamplingSite] = useState('');
    const [specimen, setSpecimen] = useState('');
    const [paymentMode, setPaymentMode] = useState('');
    const [paymentTerms, setPaymentTerms] = useState('');
    const [fieldData, setFieldData] = useState('');
    const [sterileBottle, setSterileBottle] = useState(false);
    const [potabilityOpen, setPotabilityOpen] = useState(false);
    const [sampleStorageTemp, setSampleStorageTemp] = useState('');
    const [wastewaterSource, setWastewaterSource] = useState('');
    const [wastewaterSourceOther, setWastewaterSourceOther] = useState('');
    const [otherTests, setOtherTests] = useState('');
    const [activeCategory, setActiveCategory] = useState<string | null>(null);
    const [browseTestsOpen, setBrowseTestsOpen] = useState(false);
    const [testsQuery, setTestsQuery] = useState('');
    const [selectedTypes, setSelectedTypes] = useState<number[]>([]);
    const [selectedPackageIds, setSelectedPackageIds] = useState<number[]>([]);
    const [selectedPresetKeys, setSelectedPresetKeys] = useState<string[]>([]);
    const [samples, setSamples] = useState<SampleDraft[]>([emptySample()]);
    const [expandedSample, setExpandedSample] = useState<number | null>(0);
    const nextSampleFocusRef = useRef<number | null>(null);

    useEffect(() => {
        setAttemptedContinue(false);
    }, [step]);

    useEffect(() => {
        if (nextSampleFocusRef.current === null) {
            return;
        }
        const index = nextSampleFocusRef.current;
        nextSampleFocusRef.current = null;
        document
            .getElementById(`sample-card-${index}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        (
            document.getElementById(
                `sample_code_${index}`,
            ) as HTMLInputElement | null
        )?.focus();
    }, [samples.length]);

    const selectedTypeSet = useMemo(
        () => new Set(selectedTypes),
        [selectedTypes],
    );

    const resolvedClassification =
        classification === 'Others'
            ? classificationOther.trim()
                ? isGenericOtherCategory(classificationOther) &&
                  classificationOtherSpecify.trim()
                    ? `Others: Other — ${classificationOtherSpecify.trim()}`
                    : `Others: ${classificationOther.trim()}`
                : 'Others'
            : classification;

    const resolvedSampleSource =
        wastewaterSource === 'Others'
            ? wastewaterSourceOther.trim()
                ? `Others: ${wastewaterSourceOther.trim()}`
                : 'Others'
            : wastewaterSource;

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
        type_presets.forEach((preset) =>
            preset.tests.forEach((item) =>
                map.set(item.id, {
                    name: item.name,
                    price: String(item.default_price ?? '0'),
                }),
            ),
        );

        return selectedTypes.map((id) => map.get(id)).filter(Boolean) as Array<{
            name: string;
            price: string;
        }>;
    }, [categories, packages, type_presets, selectedTypes]);

    const isAquaClassification = isAquaClassificationValue(
        resolvedClassification,
    );

    const scopedPackages = useMemo(() => {
        return packages.filter((pkg) =>
            isAquaClassification
                ? pkg.visible_for_aqua
                : pkg.visible_for_non_aqua,
        );
    }, [packages, isAquaClassification]);

    const scopedCategories = useMemo(() => {
        return categories
            .map((group) => ({
                ...group,
                items: group.items.filter((item) =>
                    scopeVisible(
                        item.catalog_scope || 'non_aqua',
                        isAquaClassification,
                    ),
                ),
            }))
            .filter((group) => group.items.length > 0);
    }, [categories, isAquaClassification]);

    const needsSpecimen = useMemo(() => {
        if (classification === 'Others') {
            const otherLabel = classificationOther.trim().toLowerCase();
            if (
                otherLabel !== '' &&
                categories.some(
                    (group) =>
                        FOOD_CATEGORY_SLUGS.has(group.category ?? '') &&
                        group.label.trim().toLowerCase() === otherLabel,
                )
            ) {
                return true;
            }
        }

        const typeCategory = new Map<number, string>();
        categories.forEach((group) => {
            const slug = group.category ?? '';
            group.items.forEach((item) => typeCategory.set(item.id, slug));
        });
        packages.forEach((pkg) => {
            const slug = pkg.category ?? '';
            pkg.tests.forEach((item) => {
                if (!typeCategory.has(item.id)) {
                    typeCategory.set(item.id, slug);
                }
            });
        });

        if (
            selectedPackageIds.some((id) => {
                const pkg = packages.find((row) => row.id === id);
                return pkg?.category
                    ? FOOD_CATEGORY_SLUGS.has(pkg.category)
                    : false;
            })
        ) {
            return true;
        }

        return selectedTypes.some((id) =>
            FOOD_CATEGORY_SLUGS.has(typeCategory.get(id) ?? ''),
        );
    }, [
        categories,
        classification,
        classificationOther,
        packages,
        selectedPackageIds,
        selectedTypes,
    ]);

    useEffect(() => {
        if (!needsSpecimen && specimen) {
            setSpecimen('');
        }
    }, [needsSpecimen, specimen]);

    const suggestedPackages = useMemo(() => {
        const value = resolvedClassification.toLowerCase();

        return scopedPackages.filter((pkg) => {
            if (pkg.classifications.length === 0) {
                return true;
            }

            return pkg.classifications.some((tag) =>
                value.includes(tag.toLowerCase()),
            );
        });
    }, [scopedPackages, resolvedClassification]);

    const otherPackages = useMemo(
        () =>
            scopedPackages.filter(
                (pkg) => !suggestedPackages.some((item) => item.id === pkg.id),
            ),
        [scopedPackages, suggestedPackages],
    );

    const suggestedTypePresets = useMemo(() => {
        const needle = resolvedClassification.toLowerCase();
        if (!needle) {
            return [];
        }

        return type_presets.filter((preset) =>
            preset.classifications.some((label) =>
                needle.includes(label.toLowerCase()),
            ),
        );
    }, [type_presets, resolvedClassification]);

    const otherTypePresets = useMemo(
        () =>
            type_presets.filter(
                (preset) =>
                    !suggestedTypePresets.some((item) => item.key === preset.key),
            ),
        [type_presets, suggestedTypePresets],
    );

    useEffect(() => {
        const allowedTypeIds = new Set<number>();
        scopedCategories.forEach((group) =>
            group.items.forEach((item) => allowedTypeIds.add(item.id)),
        );
        scopedPackages.forEach((pkg) =>
            pkg.analysis_type_ids.forEach((id) => allowedTypeIds.add(id)),
        );
        type_presets.forEach((preset) =>
            preset.analysis_type_ids.forEach((id) => allowedTypeIds.add(id)),
        );

        setSelectedTypes((current) => {
            const next = current.filter((id) => allowedTypeIds.has(id));

            return next.length === current.length ? current : next;
        });
        setSelectedPackageIds((current) => {
            const next = current.filter((id) =>
                scopedPackages.some((pkg) => pkg.id === id),
            );

            return next.length === current.length ? current : next;
        });
        setSelectedPresetKeys((current) => {
            const next = current.filter((key) =>
                type_presets.some((preset) => preset.key === key),
            );

            return next.length === current.length ? current : next;
        });
    }, [
        isAquaClassification,
        scopedCategories,
        scopedPackages,
        type_presets,
    ]);

    const estimatedTotal = selectedItems.reduce(
        (sum, item) => sum + Number(item.price || 0),
        0,
    );

    const catalogItemsForSummary = useMemo(() => {
        const seen = new Set<number>();
        const items: Array<{
            id: number;
            name: string;
            default_price: string;
        }> = [];

        const push = (id: number, name: string, default_price: string) => {
            if (seen.has(id)) {
                return;
            }
            seen.add(id);
            items.push({ id, name, default_price });
        };

        categories.forEach((group) =>
            group.items.forEach((item) =>
                push(item.id, item.name, item.default_price),
            ),
        );
        packages.forEach((pkg) =>
            pkg.tests.forEach((item) =>
                push(item.id, item.name, item.default_price),
            ),
        );
        type_presets.forEach((preset) =>
            preset.tests.forEach((item) =>
                push(
                    item.id,
                    item.name,
                    String(item.default_price ?? '0'),
                ),
            ),
        );

        return items;
    }, [categories, packages, type_presets]);

    /** Tests picked from the catalog rather than covered by a package or preset. */
    const individualSelectedCount = useMemo(() => {
        const covered = new Set<number>();
        scopedPackages
            .filter((pkg) => selectedPackageIds.includes(pkg.id))
            .forEach((pkg) =>
                pkg.analysis_type_ids.forEach((id) => covered.add(id)),
            );
        type_presets
            .filter((preset) => selectedPresetKeys.includes(preset.key))
            .forEach((preset) =>
                preset.analysis_type_ids.forEach((id) => covered.add(id)),
            );

        return selectedTypes.filter((id) => !covered.has(id)).length;
    }, [
        scopedPackages,
        selectedPackageIds,
        type_presets,
        selectedPresetKeys,
        selectedTypes,
    ]);

    /** Open the catalog automatically when returning with catalog-only picks. */
    const browseAutoOpenRef = useRef(false);
    useEffect(() => {
        if (step !== 3 || browseAutoOpenRef.current) {
            return;
        }
        if (individualSelectedCount > 0) {
            browseAutoOpenRef.current = true;
            setBrowseTestsOpen(true);
        }
    }, [step, individualSelectedCount]);

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
            const keep = keptTypeIdsExcluding(memberIds, {
                exceptPackageId: pkg.id,
            });
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

    function keptTypeIdsExcluding(
        memberIds: number[],
        options: { exceptPackageId?: number; exceptPresetKey?: string } = {},
    ): Set<number> {
        const keep = new Set<number>();

        packages
            .filter(
                (item) =>
                    selectedPackageIds.includes(item.id) &&
                    item.id !== options.exceptPackageId,
            )
            .forEach((item) =>
                item.analysis_type_ids.forEach((id) => keep.add(id)),
            );

        type_presets
            .filter(
                (preset) =>
                    selectedPresetKeys.includes(preset.key) &&
                    preset.key !== options.exceptPresetKey,
            )
            .forEach((preset) =>
                preset.analysis_type_ids.forEach((id) => keep.add(id)),
            );

        return keep;
    }

    function toggleTypePreset(preset: IntakeTypePreset) {
        const memberIds = preset.analysis_type_ids;
        const selected = selectedPresetKeys.includes(preset.key);

        if (selected) {
            const keep = keptTypeIdsExcluding(memberIds, {
                exceptPresetKey: preset.key,
            });
            setSelectedPresetKeys((current) =>
                current.filter((key) => key !== preset.key),
            );
            setSelectedTypes((current) =>
                current.filter((id) => !memberIds.includes(id) || keep.has(id)),
            );
            return;
        }

        setSelectedPresetKeys((current) => [...current, preset.key]);
        setSelectedTypes((current) => [
            ...new Set([...current, ...memberIds]),
        ]);
    }

    function toggleTypePresetMember(preset: IntakeTypePreset, typeId: number) {
        if (!selectedPresetKeys.includes(preset.key)) {
            return;
        }

        const memberIds = preset.analysis_type_ids;
        const currentlyOn = selectedTypes.includes(typeId);
        const selectedMembers = memberIds.filter((id) =>
            currentlyOn
                ? id !== typeId && selectedTypes.includes(id)
                : selectedTypes.includes(id) || id === typeId,
        );

        if (currentlyOn && selectedMembers.length === 0) {
            toggleTypePreset(preset);
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

    function presetEstimatedPrice(preset: IntakeTypePreset): number {
        return preset.tests.reduce(
            (sum, test) => sum + Number(test.default_price || 0),
            0,
        );
    }

    function renderPackageRow(pkg: IntakePackage) {
        return (
            <IntakePackageRow
                key={pkg.id}
                pkg={pkg}
                selected={selectedPackageIds.includes(pkg.id)}
                selectedTypes={selectedTypes}
                onTogglePackage={() => togglePackage(pkg)}
                onToggleMember={(typeId) => togglePackageMember(pkg, typeId)}
            />
        );
    }

    function renderTypePresetRow(preset: IntakeTypePreset) {
        return (
            <IntakeTypePresetRow
                key={preset.key}
                preset={preset}
                selected={selectedPresetKeys.includes(preset.key)}
                selectedTypes={selectedTypes}
                estimatedPrice={presetEstimatedPrice(preset)}
                onTogglePreset={() => toggleTypePreset(preset)}
                onToggleMember={(typeId) =>
                    toggleTypePresetMember(preset, typeId)
                }
            />
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
            if (!classification) {
                return false;
            }

            if (classification === 'Others') {
                if (!classificationOther.trim()) {
                    return false;
                }

                if (
                    isGenericOtherCategory(classificationOther) &&
                    !classificationOtherSpecify.trim()
                ) {
                    return false;
                }
            }

            if (wastewaterSource === 'Others' && !wastewaterSourceOther.trim()) {
                return false;
            }

            if (paymentMode === 'billing_partial' && !paymentTerms) {
                return false;
            }

            return true;
        }

        if (step === 2) {
            return samples.length > 0;
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
        if (index !== step || !attemptedContinue) {
            return null;
        }

        if (step === 0 && !canContinue()) {
            return 'Add your full name and email to continue.';
        }

        if (step === 1 && !canContinue()) {
            if (!classification) {
                return 'Choose a classification before continuing.';
            }

            if (classification === 'Others' && !classificationOther.trim()) {
                return 'Select a parameter category for Others.';
            }

            if (
                classification === 'Others' &&
                isGenericOtherCategory(classificationOther) &&
                !classificationOtherSpecify.trim()
            ) {
                return 'Specify the Other classification.';
            }

            if (paymentMode === 'billing_partial' && !paymentTerms) {
                return 'Select 15 or 30 day terms for Billing / Partial.';
            }

            return 'Complete the required sample details before continuing.';
        }

        if (step === 2 && !canContinue()) {
            return 'Add at least one sample before continuing.';
        }

        if (step === 3 && !canContinue()) {
            return 'Select at least one listed test or describe another test.';
        }

        return null;
    }

    function goNext() {
        if (!canContinue()) {
            setAttemptedContinue(true);
            return;
        }
        setAttemptedContinue(false);
        setStep((s) => s + 1);
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
                sampling_site: samplingSite.trim() || null,
                specimen: needsSpecimen ? specimen.trim() || null : null,
                payment_mode: paymentMode || null,
                payment_terms:
                    paymentMode === 'billing_partial'
                        ? paymentTerms || null
                        : null,
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

    const hasPotabilityData = sterileBottle || fieldData.trim().length > 0;

    const stepHints = [
        'Enter your contact information.',
        'Tell us about your request and sample.',
        'Add the samples you are submitting.',
        'Select the analyses you need.',
        'Review your request before submitting.',
    ] as const;

    return (
        <>
            <Head title="Request for Analysis" />
            <IntakePageShell
            header={
                <header className="flex flex-wrap items-center justify-between gap-2 sm:gap-3">
                    <div className="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-md ring-4 ring-white/40 sm:size-12">
                            <NppcLogo className="h-[90%] w-[90%]" />
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-[10px] font-medium tracking-[0.16em] text-white/80 uppercase sm:text-xs">
                                NPPC Analytical & Diagnostic Laboratory
                            </p>
                            <h1 className="font-heading text-xl font-semibold text-white sm:text-2xl">
                                Request for Analysis
                            </h1>
                            <p className="mt-0.5 truncate text-xs text-white/80 sm:text-sm">
                                Customer Intake Portal · {stepHints[step]}
                            </p>
                        </div>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="h-10 shrink-0 border-white/30 bg-white/95 text-[#1A3694] hover:bg-white"
                    >
                        <Link href="/intake">Cancel</Link>
                    </Button>
                </header>
            }
        >
            <IntakeStepper
                steps={steps}
                step={step}
                onStepSelect={setStep}
                error={stepError(step)}
            />

            <div key={step} className="intake-enter min-w-0">
                    {errors?.analysis_type_ids && (
                        <InputError
                            className="mb-4"
                            message={errors.analysis_type_ids}
                        />
                    )}

                    {step === 0 && (
                        <div className="w-full space-y-4">
                            <IntakeStepHeader
                                title="Customer"
                                hint="Enter your contact information."
                            />

                            <FormSection title="Contact details" first>
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    <IntakeField
                                        label="Full name"
                                        htmlFor="customer_name"
                                        required
                                        error={
                                            !customer.customer_name.trim() &&
                                            attemptedContinue
                                                ? 'Full name is required.'
                                                : null
                                        }
                                    >
                                        <Input
                                            id="customer_name"
                                            type="text"
                                            autoComplete="name"
                                            className="h-10 w-full text-sm"
                                            value={customer.customer_name}
                                            aria-invalid={
                                                !!stepError(0) &&
                                                !customer.customer_name.trim()
                                            }
                                            onChange={(e) =>
                                                setCustomer((c) => ({
                                                    ...c,
                                                    customer_name:
                                                        e.target.value,
                                                }))
                                            }
                                        />
                                    </IntakeField>
                                    <IntakeField
                                        label="Email"
                                        htmlFor="customer_email"
                                        required
                                        error={
                                            !customer.customer_email.trim() &&
                                            attemptedContinue
                                                ? 'Email is required.'
                                                : null
                                        }
                                    >
                                        <Input
                                            id="customer_email"
                                            type="email"
                                            autoComplete="email"
                                            className="h-10 w-full text-sm"
                                            value={customer.customer_email}
                                            aria-invalid={
                                                !!stepError(0) &&
                                                !customer.customer_email.trim()
                                            }
                                            onChange={(e) =>
                                                setCustomer((c) => ({
                                                    ...c,
                                                    customer_email:
                                                        e.target.value,
                                                }))
                                            }
                                        />
                                    </IntakeField>
                                    <IntakeField
                                        label="Contact number"
                                        htmlFor="customer_contact"
                                    >
                                        <Input
                                            id="customer_contact"
                                            type="tel"
                                            autoComplete="tel"
                                            className="h-10 w-full text-sm"
                                            value={customer.customer_contact}
                                            onChange={(e) =>
                                                setCustomer((c) => ({
                                                    ...c,
                                                    customer_contact:
                                                        e.target.value,
                                                }))
                                            }
                                        />
                                    </IntakeField>
                                    <IntakeField
                                        label="Company / Organization"
                                        htmlFor="company_name"
                                    >
                                        <Input
                                            id="company_name"
                                            type="text"
                                            autoComplete="organization"
                                            className="h-10 w-full text-sm"
                                            value={customer.company_name}
                                            onChange={(e) =>
                                                setCustomer((c) => ({
                                                    ...c,
                                                    company_name:
                                                        e.target.value,
                                                }))
                                            }
                                        />
                                    </IntakeField>
                                </div>
                            </FormSection>

                            <div className="grid gap-3 border-t border-slate-100 pt-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start lg:gap-6">
                                <FormSection title="Address" first>
                                    <Textarea
                                        id="customer_address"
                                        rows={2}
                                        className="min-h-0 w-full py-2 text-sm"
                                        placeholder="Street, barangay, city / municipality"
                                        value={customer.customer_address}
                                        onChange={(e) =>
                                            setCustomer((c) => ({
                                                ...c,
                                                customer_address:
                                                    e.target.value,
                                            }))
                                        }
                                    />
                                </FormSection>

                                <FormSection title="Ownership" first>
                                    <div
                                        className="flex flex-wrap gap-2"
                                        role="group"
                                        aria-label="Type of ownership"
                                    >
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
                                </FormSection>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="w-full space-y-3">
                            <IntakeStepHeader
                                title="Samples"
                                hint={
                                    samples.length > 1
                                        ? `${samples.length} samples in this request.`
                                        : 'Add the samples you are submitting.'
                                }
                            />

                            <div className="space-y-2">
                                {samples.map((sample, index) => (
                                    <IntakeSampleRow
                                        key={index}
                                        sample={sample}
                                        index={index}
                                        expanded={
                                            samples.length === 1 ||
                                            expandedSample === index
                                        }
                                        collapsible={samples.length > 1}
                                        removable={samples.length > 1}
                                        onToggleExpand={() =>
                                            setExpandedSample((current) =>
                                                current === index
                                                    ? null
                                                    : index,
                                            )
                                        }
                                        onChange={(key, value) =>
                                            updateSample(index, key, value)
                                        }
                                        onDuplicate={() => {
                                            nextSampleFocusRef.current =
                                                index + 1;
                                            setExpandedSample(index + 1);
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
                                                    ...current.slice(index + 1),
                                                ];
                                            });
                                        }}
                                        onRemove={() => {
                                            setSamples((current) =>
                                                current.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            );
                                            setExpandedSample((current) => {
                                                if (current === null) {
                                                    return null;
                                                }

                                                return current > index
                                                    ? current - 1
                                                    : Math.min(
                                                          current,
                                                          samples.length - 2,
                                                      );
                                            });
                                        }}
                                    />
                                ))}
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                className="h-10 w-full border-dashed border-[#1A3694]/40 text-[#1A3694] hover:bg-[#eef3fb] sm:w-auto"
                                onClick={() => {
                                    nextSampleFocusRef.current = samples.length;
                                    setExpandedSample(samples.length);
                                    setSamples((current) => [
                                        ...current,
                                        emptySample(),
                                    ]);
                                }}
                            >
                                <Plus className="size-4" />
                                Add another sample
                            </Button>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="w-full space-y-4">
                            <IntakeStepHeader
                                title="Request details"
                                hint="Tell us about your request and sample."
                            />

                            <FormSection title="Classification" first>
                                <div
                                    className="flex flex-wrap gap-2"
                                    role="group"
                                    aria-label="Sample classification"
                                >
                                    {options.classifications.map((item) => {
                                        const active = classification === item;

                                        return (
                                            <button
                                                key={item}
                                                type="button"
                                                title={
                                                    CLASSIFICATION_BLURBS[
                                                        item
                                                    ] ??
                                                    'Laboratory analysis request'
                                                }
                                                aria-pressed={active}
                                                onClick={() => {
                                                    setClassification(item);
                                                    setWastewaterSource('');
                                                    setWastewaterSourceOther(
                                                        '',
                                                    );
                                                    if (item !== 'Others') {
                                                        setClassificationOther(
                                                            '',
                                                        );
                                                        setClassificationOtherSpecify(
                                                            '',
                                                        );
                                                    }
                                                }}
                                                className={cn(
                                                    'min-h-10 rounded-xl border px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none',
                                                    active
                                                        ? 'border-[#1A3694] bg-[#1A3694] text-white shadow-sm'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                                )}
                                            >
                                                {item}
                                            </button>
                                        );
                                    })}
                                </div>
                                {attemptedContinue && !classification && (
                                    <p
                                        className="text-sm text-red-600"
                                        role="alert"
                                    >
                                        Please select a classification.
                                    </p>
                                )}
                                {classification === 'Others' && (
                                    <div className="grid grid-cols-1 gap-3 pt-1 md:grid-cols-2">
                                        <IntakeField
                                            label="Parameter category"
                                            htmlFor="classification_other"
                                            required
                                            error={errors?.classification}
                                        >
                                            <select
                                                id="classification_other"
                                                className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus-visible:border-[#1A3694] focus-visible:ring-2 focus-visible:ring-[#1A3694]/20"
                                                value={classificationOther}
                                                onChange={(e) => {
                                                    setClassificationOther(
                                                        e.target.value,
                                                    );
                                                    if (
                                                        !isGenericOtherCategory(
                                                            e.target.value,
                                                        )
                                                    ) {
                                                        setClassificationOtherSpecify(
                                                            '',
                                                        );
                                                    }
                                                }}
                                            >
                                                <option value="">
                                                    Select category…
                                                </option>
                                                {(
                                                    options.other_classification_options ??
                                                    []
                                                ).map((label) => (
                                                    <option
                                                        key={label}
                                                        value={label}
                                                    >
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                        </IntakeField>
                                        {isGenericOtherCategory(
                                            classificationOther,
                                        ) && (
                                            <IntakeField
                                                label="Specify"
                                                htmlFor="classification_other_specify"
                                                required
                                            >
                                                <Input
                                                    id="classification_other_specify"
                                                    className="h-10 text-sm"
                                                    placeholder="Describe the analysis type"
                                                    value={
                                                        classificationOtherSpecify
                                                    }
                                                    onChange={(e) =>
                                                        setClassificationOtherSpecify(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </IntakeField>
                                        )}
                                    </div>
                                )}
                            </FormSection>

                            <div className="grid gap-3 border-t border-slate-100 pt-3 lg:grid-cols-2 lg:items-start lg:gap-6">
                                <FormSection title="Sampling details" first>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <IntakeField
                                            label="Sampling date"
                                            htmlFor="sampling_date"
                                        >
                                            <Input
                                                id="sampling_date"
                                                type="date"
                                                className="h-10 w-full text-sm"
                                                value={samplingDate}
                                                onChange={(e) =>
                                                    setSamplingDate(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </IntakeField>
                                        <IntakeField
                                            label="Sampling time"
                                            htmlFor="sampling_time"
                                        >
                                            <Input
                                                id="sampling_time"
                                                type="time"
                                                className="h-10 w-full text-sm"
                                                value={samplingTime}
                                                onChange={(e) =>
                                                    setSamplingTime(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </IntakeField>
                                        <IntakeField
                                            label="Sample collected by"
                                            htmlFor="sample_collected_by"
                                        >
                                            <Input
                                                id="sample_collected_by"
                                                className="h-10 w-full text-sm"
                                                value={sampleCollectedBy}
                                                onChange={(e) =>
                                                    setSampleCollectedBy(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </IntakeField>
                                        <IntakeField
                                            label="Sampling site"
                                            htmlFor="sampling_site"
                                            error={errors?.sampling_site}
                                        >
                                            <Input
                                                id="sampling_site"
                                                className="h-10 w-full text-sm"
                                                placeholder="e.g. Plant gate / Well location"
                                                value={samplingSite}
                                                onChange={(e) =>
                                                    setSamplingSite(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </IntakeField>
                                        {needsSpecimen ? (
                                            <IntakeField
                                                label="Specimen"
                                                htmlFor="specimen"
                                                error={errors?.specimen}
                                                hint="Printed on Food / Proximate result sheets when mapped in Form Designer."
                                            >
                                                <Input
                                                    id="specimen"
                                                    className="h-10 w-full text-sm"
                                                    placeholder="e.g. Dried fish / Chicken breast"
                                                    value={specimen}
                                                    onChange={(e) =>
                                                        setSpecimen(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </IntakeField>
                                        ) : null}
                                        <IntakeField
                                            label="Sample storage temp."
                                            htmlFor="sample_storage_temp"
                                            hint="(as received)"
                                            className="md:col-span-2"
                                        >
                                            <Input
                                                id="sample_storage_temp"
                                                className="h-10 w-full text-sm"
                                                placeholder="e.g. Ambient / 4°C"
                                                value={sampleStorageTemp}
                                                onChange={(e) =>
                                                    setSampleStorageTemp(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </IntakeField>
                                        {classification !== 'Aqua' && (
                                            <div className="md:col-span-2">
                                                {potabilityOpen ? (
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <Label>
                                                                Field data
                                                                (Potability)
                                                            </Label>
                                                            <button
                                                                type="button"
                                                                className="text-xs font-medium text-[#365BB0] hover:underline"
                                                                onClick={() =>
                                                                    setPotabilityOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Hide
                                                            </button>
                                                        </div>
                                                        <label className="flex min-h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                                            <Checkbox
                                                                checked={
                                                                    sterileBottle
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    setSterileBottle(
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <span>
                                                                Water in sterile
                                                                bottle
                                                            </span>
                                                        </label>
                                                        <Textarea
                                                            id="field_data"
                                                            rows={2}
                                                            className="min-h-0 py-2 text-sm"
                                                            placeholder="Other potability notes (optional)"
                                                            value={fieldData}
                                                            onChange={(e) =>
                                                                setFieldData(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="h-10 w-full border-dashed border-[#1A3694]/40 text-[#1A3694] hover:bg-[#eef3fb] sm:w-auto"
                                                        onClick={() =>
                                                            setPotabilityOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="size-4" />
                                                        {hasPotabilityData
                                                            ? 'Edit potability field data'
                                                            : 'Add potability field data'}
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </FormSection>

                                <div className="space-y-3">
                                    <FormSection title="Sample source" first>
                                        <div className="flex flex-wrap gap-2">
                                            {(classification === 'Aqua'
                                                ? options.aqua_sources
                                                : options.wastewater_sources
                                            ).map((item) => (
                                                <ChoiceChip
                                                    key={item}
                                                    active={
                                                        wastewaterSource ===
                                                        item
                                                    }
                                                    onClick={() => {
                                                        setWastewaterSource(
                                                            wastewaterSource ===
                                                                item
                                                                ? ''
                                                                : item,
                                                        );
                                                        if (
                                                            item !== 'Others'
                                                        ) {
                                                            setWastewaterSourceOther(
                                                                '',
                                                            );
                                                        }
                                                    }}
                                                >
                                                    {item}
                                                </ChoiceChip>
                                            ))}
                                        </div>
                                        {wastewaterSource === 'Others' && (
                                            <Input
                                                className="h-10 text-sm"
                                                placeholder="Please specify sample source"
                                                value={wastewaterSourceOther}
                                                onChange={(e) =>
                                                    setWastewaterSourceOther(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </FormSection>

                                    <FormSection title="Payment">
                                        <div className="grid gap-2 md:grid-cols-3 md:gap-3">
                                            {PAYMENT_MODE_OPTIONS.map(
                                                (option) => (
                                                    <RadioOption
                                                        key={option.value}
                                                        name="payment_mode"
                                                        value={option.value}
                                                        checked={
                                                            paymentMode ===
                                                            option.value
                                                        }
                                                        label={option.label}
                                                        onChange={() => {
                                                            setPaymentMode(
                                                                option.value,
                                                            );
                                                            if (
                                                                option.value !==
                                                                'billing_partial'
                                                            ) {
                                                                setPaymentTerms(
                                                                    '',
                                                                );
                                                            }
                                                        }}
                                                    />
                                                ),
                                            )}
                                        </div>
                                        {errors?.payment_mode && (
                                            <p className="text-xs text-red-600">
                                                {errors.payment_mode}
                                            </p>
                                        )}
                                        {paymentMode ===
                                            'billing_partial' && (
                                            <div className="space-y-2 border-t border-slate-200 pt-2">
                                                <Label>
                                                    Payment terms *{' '}
                                                    <span className="font-normal text-slate-500">
                                                        (required for Billing /
                                                        Partial)
                                                    </span>
                                                </Label>
                                                <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-3">
                                                    {PAYMENT_TERMS_OPTIONS.map(
                                                        (option) => (
                                                            <RadioOption
                                                                key={
                                                                    option.value
                                                                }
                                                                name="payment_terms"
                                                                value={
                                                                    option.value
                                                                }
                                                                checked={
                                                                    paymentTerms ===
                                                                    option.value
                                                                }
                                                                label={
                                                                    option.label
                                                                }
                                                                onChange={() =>
                                                                    setPaymentTerms(
                                                                        option.value,
                                                                    )
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                </div>
                                                {errors?.payment_terms && (
                                                    <p className="text-xs text-red-600">
                                                        {errors.payment_terms}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </FormSection>
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="w-full space-y-4">
                            <div>
                                <h2 className="font-heading text-lg font-semibold text-[#1A3694] sm:text-xl">
                                    Tests
                                </h2>
                                <p className="text-sm text-slate-600">
                                    Select analyses for your
                                    {samples.length > 1
                                        ? ' samples. The same tests apply to all samples.'
                                        : ' sample.'}
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-1.5">
                                <span className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                    Sample
                                </span>
                                {samples.map((sample, index) => (
                                    <span
                                        key={index}
                                        className="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-sm text-slate-700"
                                    >
                                        {[
                                            sample.sample_code.trim() ||
                                                `Sample ${index + 1}`,
                                            sample.matrix.trim() || null,
                                            sample.quantity.trim()
                                                ? `${sample.quantity.trim()}${sample.unit.trim() ? ` ${sample.unit.trim()}` : ''}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                ))}
                            </div>

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(280px,320px)] lg:items-start xl:grid-cols-[minmax(0,1fr)_340px]">
                                <div className="min-w-0 space-y-4">
                                    {type_presets.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-sm font-semibold text-slate-900">
                                                Test panels
                                            </p>
                                            <div className="space-y-2">
                                                {(suggestedTypePresets.length >
                                                0
                                                    ? suggestedTypePresets
                                                    : type_presets
                                                ).map((preset) =>
                                                    renderTypePresetRow(preset),
                                                )}
                                            </div>
                                            {suggestedTypePresets.length > 0 &&
                                                otherTypePresets.length > 0 && (
                                                    <details className="group">
                                                        <summary className="cursor-pointer text-xs font-medium text-[#365BB0] hover:underline [&::-webkit-details-marker]:hidden">
                                                            Other test panels (
                                                            {
                                                                otherTypePresets.length
                                                            }
                                                            )
                                                        </summary>
                                                        <div className="mt-2 space-y-2">
                                                            {otherTypePresets.map(
                                                                (preset) =>
                                                                    renderTypePresetRow(
                                                                        preset,
                                                                    ),
                                                            )}
                                                        </div>
                                                    </details>
                                                )}
                                        </div>
                                    )}

                                    {scopedPackages.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-sm font-semibold text-slate-900">
                                                Recommended packages
                                            </p>
                                            <div className="space-y-2">
                                                {(isAquaClassification
                                                    ? scopedPackages
                                                    : suggestedPackages.length >
                                                        0
                                                      ? suggestedPackages
                                                      : scopedPackages
                                                ).map((pkg) =>
                                                    renderPackageRow(pkg),
                                                )}
                                            </div>
                                            {!isAquaClassification &&
                                                suggestedPackages.length > 0 &&
                                                otherPackages.length > 0 && (
                                                    <details className="group">
                                                        <summary className="cursor-pointer text-xs font-medium text-[#365BB0] hover:underline [&::-webkit-details-marker]:hidden">
                                                            Other packages (
                                                            {
                                                                otherPackages.length
                                                            }
                                                            )
                                                        </summary>
                                                        <div className="mt-2 space-y-2">
                                                            {otherPackages.map(
                                                                (pkg) =>
                                                                    renderPackageRow(
                                                                        pkg,
                                                                    ),
                                                            )}
                                                        </div>
                                                    </details>
                                                )}
                                        </div>
                                    )}

                                    <div className="space-y-2 border-t border-slate-100 pt-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-900">
                                                    Need a specific analysis?
                                                </p>
                                                {individualSelectedCount > 0 ? (
                                                    <p className="text-sm text-slate-600">
                                                        {individualSelectedCount}{' '}
                                                        individual test
                                                        {individualSelectedCount ===
                                                        1
                                                            ? ''
                                                            : 's'}{' '}
                                                        selected
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="h-10 border-[#1A3694]/30 text-[#1A3694] hover:bg-[#eef3fb]"
                                                aria-expanded={browseTestsOpen}
                                                onClick={() =>
                                                    setBrowseTestsOpen(
                                                        (open) => !open,
                                                    )
                                                }
                                            >
                                                {browseTestsOpen
                                                    ? 'Hide individual tests'
                                                    : 'Browse individual tests'}
                                            </Button>
                                        </div>

                                        {browseTestsOpen && (
                                            <IntakeIndividualTestCatalog
                                                categories={scopedCategories}
                                                query={testsQuery}
                                                onQueryChange={setTestsQuery}
                                                activeCategory={activeCategory}
                                                onActiveCategoryChange={
                                                    setActiveCategory
                                                }
                                                selectedTypeIds={
                                                    selectedTypeSet
                                                }
                                                onToggleType={toggleType}
                                            />
                                        )}
                                    </div>

                                    <FormSection title="Other / special test request">
                                        <Textarea
                                            id="other_tests"
                                            rows={2}
                                            className="w-full bg-white"
                                            value={otherTests}
                                            onChange={(e) =>
                                                setOtherTests(e.target.value)
                                            }
                                            placeholder="Describe an analysis that is not listed…"
                                        />
                                        {attemptedContinue &&
                                            selectedTypes.length === 0 &&
                                            selectedPackageIds.length === 0 &&
                                            !otherTests.trim() && (
                                                <p
                                                    className="text-sm text-red-600"
                                                    role="alert"
                                                >
                                                    Select at least one listed
                                                    test, package, or describe
                                                    another test.
                                                </p>
                                            )}
                                    </FormSection>
                                </div>

                                <aside className="sticky top-3 hidden self-start rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm lg:block">
                                    <TestsRequestSummary
                                        packages={scopedPackages}
                                        selectedPackageIds={selectedPackageIds}
                                        selectedTypes={selectedTypes}
                                        catalogItems={catalogItemsForSummary}
                                        sampleCount={samples.length}
                                        otherTests={otherTests}
                                        estimatedTotal={estimatedTotal}
                                        variant="sidebar"
                                    />
                                </aside>
                            </div>

                            <div className="lg:hidden">
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between rounded-xl border border-[#1A3694]/20 bg-[#eef3fb] px-3 py-2.5 text-sm font-medium text-[#1A3694]"
                                    aria-expanded={mobileSummaryOpen}
                                    onClick={() =>
                                        setMobileSummaryOpen((open) => !open)
                                    }
                                >
                                    <span>
                                        {selectedTypes.length} analyses · ₱
                                        {estimatedTotal.toFixed(2)}
                                    </span>
                                    <span>
                                        {mobileSummaryOpen
                                            ? 'Hide'
                                            : 'View selected'}
                                    </span>
                                </button>
                                {mobileSummaryOpen && (
                                    <div className="mt-2 rounded-xl border border-slate-200 bg-white p-3">
                                        <TestsRequestSummary
                                            packages={scopedPackages}
                                            selectedPackageIds={
                                                selectedPackageIds
                                            }
                                            selectedTypes={selectedTypes}
                                            catalogItems={
                                                catalogItemsForSummary
                                            }
                                            sampleCount={samples.length}
                                            otherTests={otherTests}
                                            estimatedTotal={estimatedTotal}
                                            variant="inline"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {step === 4 && (
                        <div className="w-full space-y-4">
                            <IntakeStepHeader
                                title="Review"
                                hint="Review your request before submitting."
                            />

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(280px,320px)] lg:items-start xl:grid-cols-[minmax(0,1fr)_340px]">
                                <div className="min-w-0 space-y-3">
                                    <FormSection
                                        title="Customer"
                                        first
                                        action={
                                            <ReviewEditButton
                                                onClick={() => setStep(0)}
                                            />
                                        }
                                    >
                                        <div className="grid gap-x-4 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
                                            <ReviewRow
                                                label="Name"
                                                value={customer.customer_name}
                                            />
                                            <ReviewRow
                                                label="Email"
                                                value={customer.customer_email}
                                            />
                                            <ReviewRow
                                                label="Contact"
                                                value={
                                                    customer.customer_contact
                                                }
                                            />
                                            <ReviewRow
                                                label="Company"
                                                value={customer.company_name}
                                            />
                                            <ReviewRow
                                                label="Ownership"
                                                value={ownershipType}
                                            />
                                            <ReviewRow
                                                label="Address"
                                                value={
                                                    customer.customer_address
                                                }
                                                className="sm:col-span-2 xl:col-span-3"
                                            />
                                        </div>
                                    </FormSection>

                                    <FormSection
                                        title="Request details"
                                        action={
                                            <ReviewEditButton
                                                onClick={() => setStep(1)}
                                            />
                                        }
                                    >
                                        <div className="grid gap-x-4 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
                                            <ReviewRow
                                                label="Classification"
                                                value={resolvedClassification}
                                            />
                                            <ReviewRow
                                                label="Sampling"
                                                value={[
                                                    samplingDate,
                                                    samplingTime,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            />
                                            <ReviewRow
                                                label="Collected by"
                                                value={sampleCollectedBy}
                                            />
                                            <ReviewRow
                                                label="Sampling site"
                                                value={samplingSite}
                                            />
                                            {needsSpecimen ? (
                                                <div className="sm:col-span-2 xl:col-span-3">
                                                    <IntakeField
                                                        label="Specimen"
                                                        htmlFor="specimen_review"
                                                        error={errors?.specimen}
                                                    >
                                                        <Input
                                                            id="specimen_review"
                                                            className="h-10 w-full text-sm"
                                                            placeholder="e.g. Dried fish / Chicken breast"
                                                            value={specimen}
                                                            onChange={(e) =>
                                                                setSpecimen(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </IntakeField>
                                                </div>
                                            ) : null}
                                            <ReviewRow
                                                label="Sample source"
                                                value={resolvedSampleSource}
                                            />
                                            <ReviewRow
                                                label="Storage temp"
                                                value={sampleStorageTemp}
                                            />
                                            <ReviewRow
                                                label="Payment"
                                                value={
                                                    paymentMode
                                                        ? [
                                                              PAYMENT_MODE_OPTIONS.find(
                                                                  (o) =>
                                                                      o.value ===
                                                                      paymentMode,
                                                              )?.label ??
                                                                  paymentMode,
                                                              paymentMode ===
                                                                  'billing_partial' &&
                                                              paymentTerms
                                                                  ? PAYMENT_TERMS_OPTIONS.find(
                                                                        (o) =>
                                                                            o.value ===
                                                                            paymentTerms,
                                                                    )?.label
                                                                  : null,
                                                          ]
                                                              .filter(Boolean)
                                                              .join(' · ')
                                                        : ''
                                                }
                                            />
                                        </div>
                                    </FormSection>

                                    <FormSection
                                        title="Samples"
                                        action={
                                            <ReviewEditButton
                                                onClick={() => setStep(2)}
                                            />
                                        }
                                    >
                                        <ul className="divide-y divide-slate-100 text-sm">
                                            {samples.map((sample, index) => (
                                                <li
                                                    key={index}
                                                    className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 py-2 first:pt-0 last:pb-0"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="font-medium text-[#1A3694]">
                                                            Sample {index + 1}
                                                            {sample.sample_code
                                                                ? ` · ${sample.sample_code}`
                                                                : ''}
                                                        </p>
                                                        {sample.description.trim() ? (
                                                            <p className="text-slate-700">
                                                                {
                                                                    sample.description
                                                                }
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <p className="text-slate-600">
                                                        {[
                                                            sample.matrix ||
                                                                null,
                                                            sample.quantity
                                                                ? `${sample.quantity}${sample.unit ? ` ${sample.unit}` : ''}`
                                                                : null,
                                                            sample.remarks ||
                                                                null,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') ||
                                                            'No additional sample details'}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                    </FormSection>

                                    <FormSection
                                        title="Analyses"
                                        description="Selected for this request (job-order level)."
                                        action={
                                            <ReviewEditButton
                                                onClick={() => setStep(3)}
                                            />
                                        }
                                    >
                                        <ul className="space-y-1 text-sm">
                                            {selectedItems.map((item) => (
                                                <li
                                                    key={item.name}
                                                    className="flex justify-between gap-3"
                                                >
                                                    <span className="min-w-0 truncate">
                                                        {item.name}
                                                    </span>
                                                    <span className="shrink-0 text-slate-500">
                                                        ₱
                                                        {Number(
                                                            item.price,
                                                        ).toFixed(2)}
                                                    </span>
                                                </li>
                                            ))}
                                            {otherTests ? (
                                                <li className="text-slate-700">
                                                    <span className="font-medium">
                                                        Other:{' '}
                                                    </span>
                                                    {otherTests}
                                                </li>
                                            ) : null}
                                        </ul>
                                    </FormSection>
                                </div>

                                <aside className="sticky top-3 self-start rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm">
                                    <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                        Cost summary
                                    </p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {samples.length} sample
                                        {samples.length === 1 ? '' : 's'} ·{' '}
                                        {selectedTypes.length} analyses
                                    </p>
                                    <div className="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 text-sm">
                                        <span>Laboratory analyses</span>
                                        <span>
                                            ₱{estimatedTotal.toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 font-semibold text-[#1A3694]">
                                        <span>Estimated total</span>
                                        <span>
                                            ₱{estimatedTotal.toFixed(2)}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Receiving will finalize pricing.
                                    </p>
                                </aside>
                            </div>
                        </div>
                    )}
            </div>

            <IntakeStickyFooter
                hint={
                    step === 3 ? (
                        <p className="mb-2 text-center text-sm font-medium text-[#1A3694] sm:text-left">
                            {selectedTypes.length} selected · ₱
                            {estimatedTotal.toFixed(2)}
                        </p>
                    ) : null
                }
            >
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-10"
                        disabled={step === 0 || submitting}
                        onClick={() => setStep((s) => Math.max(0, s - 1))}
                    >
                        Back
                    </Button>
                    {step < steps.length - 1 ? (
                        <Button
                            type="button"
                            className="h-10 bg-[#1A3694] hover:bg-[#365BB0] sm:min-w-44"
                            onClick={goNext}
                        >
                            Continue
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className="h-10 bg-[#1A3694] hover:bg-[#365BB0] sm:min-w-44"
                            disabled={submitting}
                            onClick={submit}
                        >
                            {submitting
                                ? 'Submitting…'
                                : 'Submit Request for Analysis'}
                        </Button>
                    )}
                </div>
            </IntakeStickyFooter>
        </IntakePageShell>
        </>
    );
}

IntakeWizard.layout = null;
