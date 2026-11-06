import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Calendar,
    Check,
    ChevronDown,
    CircleHelp,
    Clock,
    ContactRound,
    Droplets,
    Factory,
    Fish,
    FlaskConical,
    Landmark,
    Layers,
    ListChecks,
    Mail,
    MapPin,
    MoreHorizontal,
    Package,
    Pencil,
    PenLine,
    Phone,
    Plus,
    Sparkles,
    Tag,
    TestTube2,
    Thermometer,
    UserRound,
    Users,
    Waves,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import IntakeAlert from '@/components/intake/intake-alert';
import IntakeField from '@/components/intake/intake-field';
import IntakeIndividualTestCatalog from '@/components/intake/intake-individual-test-catalog';
import IntakePackageRow from '@/components/intake/intake-package-row';
import IntakePageShell from '@/components/intake/intake-page-shell';
import IntakePanel from '@/components/intake/intake-panel';
import IntakeSampleRow, {
    IntakeSamplesTip,
} from '@/components/intake/intake-sample-row';
import IntakeSampleSwitcher from '@/components/intake/intake-sample-switcher';
import IntakeStepHeader from '@/components/intake/intake-step-header';
import IntakeStickyFooter from '@/components/intake/intake-sticky-footer';
import IntakeTypePresetRow, {
    type IntakeTypePreset,
} from '@/components/intake/intake-type-preset-row';
import TestsRequestSummary from '@/components/intake/tests-request-summary';
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
import { Textarea } from '@/components/ui/textarea';
import { samplePackagePricing } from '@/lib/package-pricing';
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
    wastewater_kinds?: string[];
    wastewater_fo2_default_codes?: Record<string, string[]>;
};

const FO2_PRESET_KEY = 'ww_physico_fo2';
const FO2_FORM_CODE = 'LSP-7.8-FO2';

function isFo2Preset(preset: IntakeTypePreset): boolean {
    return preset.key === FO2_PRESET_KEY || preset.form_code === FO2_FORM_CODE;
}

/** FO2 member type IDs to select for a wastewater sample-description kind. */
function fo2DefaultMemberIds(
    preset: IntakeTypePreset,
    kind: string,
    defaultCodes: Record<string, string[]> | undefined,
): number[] {
    const codes = defaultCodes?.[kind];
    if (!codes?.length) {
        return [];
    }

    const codeSet = new Set(codes);

    return preset.tests
        .filter((test) => codeSet.has(test.code))
        .map((test) => test.id);
}

const PAYMENT_MODE_OPTIONS = [
    {
        value: 'cash',
        label: 'Cash',
        hint: 'Pay at reception on drop-off',
        badge: 'PHP',
    },
    {
        value: 'billing_partial',
        label: 'Billing / Partial',
        hint: 'Invoice / partial settlement',
        badge: 'Corporate',
    },
    {
        value: 'check',
        label: 'Check',
        hint: 'Bank check at reception',
        badge: 'Bank',
    },
] as const;

const PAYMENT_TERMS_OPTIONS = [
    { value: '15_days', label: '15 days' },
    { value: '30_days', label: '30 days' },
] as const;

const SOURCE_ICONS: Record<string, LucideIcon> = {
    'Surface Water': Waves,
    Groundwater: Droplets,
    'Process Water': Factory,
    Effluent: Waves,
    Pond: Fish,
    Cage: Fish,
    Tank: FlaskConical,
    Others: MoreHorizontal,
};

type SampleDraft = {
    sample_code: string;
    description: string;
    matrix: string;
    quantity: string;
    unit: string;
    remarks: string;
    classification: string;
    classification_other: string;
    classification_other_specify: string;
    package_ids: number[];
    analysis_type_ids: number[];
    preset_keys: string[];
};

type SampleFieldKey = Exclude<
    keyof SampleDraft,
    'package_ids' | 'analysis_type_ids' | 'preset_keys'
>;

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
    classification: '',
    classification_other: '',
    classification_other_specify: '',
    package_ids: [],
    analysis_type_ids: [],
    preset_keys: [],
});

function ChoiceChip({
    active,
    children,
    onClick,
    icon: Icon,
    title,
}: {
    active: boolean;
    children: ReactNode;
    onClick: () => void;
    icon?: LucideIcon;
    title?: string;
}) {
    return (
        <button
            type="button"
            title={title}
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                active
                    ? 'border-nppc-900 bg-nppc-900 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500 hover:bg-nppc-50/40',
            )}
        >
            {Icon ? <Icon className="size-3.5 shrink-0" aria-hidden /> : null}
            {children}
        </button>
    );
}

function ReviewRow({
    label,
    value,
    className,
    icon: Icon,
}: {
    label: string;
    value: string;
    className?: string;
    icon?: LucideIcon;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <p className="inline-flex items-center gap-1 text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                {Icon ? (
                    <Icon className="size-3 text-nppc-700" aria-hidden />
                ) : null}
                {label}
            </p>
            <p className="mt-0.5 text-sm font-medium break-words text-slate-900">
                {value.trim() || '—'}
            </p>
        </div>
    );
}

function ReviewCard({
    title,
    icon: Icon,
    onEdit,
    children,
    defaultOpen = true,
}: {
    title: string;
    icon?: LucideIcon;
    onEdit: () => void;
    children: ReactNode;
    defaultOpen?: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/60 px-3 py-2">
                <button
                    type="button"
                    aria-expanded={open}
                    onClick={() => setOpen((value) => !value)}
                    className="flex min-w-0 items-center gap-2 rounded-md text-left focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none"
                >
                    {Icon ? (
                        <Icon
                            className="size-4 shrink-0 text-nppc-700"
                            aria-hidden
                        />
                    ) : null}
                    <h3 className="text-sm font-semibold text-nppc-900">
                        {title}
                    </h3>
                    <ChevronDown
                        className={cn(
                            'size-3.5 shrink-0 text-slate-400 transition',
                            open && 'rotate-180',
                        )}
                        aria-hidden
                    />
                </button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-7 gap-1 border-nppc-900/25 px-2 text-[11px] text-nppc-900 hover:bg-nppc-50"
                    onClick={onEdit}
                >
                    <Pencil className="size-3" aria-hidden />
                    Edit
                </Button>
            </div>
            {open ? <div className="p-3">{children}</div> : null}
        </section>
    );
}

function RadioOption({
    name,
    value,
    checked,
    label,
    hint,
    badge,
    onChange,
}: {
    name: string;
    value: string;
    checked: boolean;
    label: string;
    hint?: string;
    badge?: string;
    onChange: () => void;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-2 text-xs transition',
                checked
                    ? 'border-nppc-900 bg-nppc-900/[0.06] text-nppc-900 shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500',
            )}
        >
            <input
                type="radio"
                name={name}
                value={value}
                checked={checked}
                onChange={onChange}
                className="size-3.5 shrink-0 accent-nppc-900"
            />
            <span className="min-w-0 flex-1">
                <span className="font-semibold">{label}</span>
                {hint ? (
                    <span className="mt-0.5 block text-[11px] leading-snug text-slate-500">
                        {hint}
                    </span>
                ) : null}
            </span>
            {badge ? (
                <span
                    className={cn(
                        'shrink-0 rounded-md border px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase',
                        checked
                            ? 'border-nppc-900/20 bg-nppc-50 text-nppc-900'
                            : 'border-slate-200 bg-slate-50 text-slate-500',
                    )}
                >
                    {badge}
                </span>
            ) : null}
        </label>
    );
}

function isGenericOtherCategory(label: string): boolean {
    return label.trim().toLowerCase() === 'other';
}

/** Others → food category: also show Metals in the individual catalog. */
const FOOD_CATEGORY_SLUGS = new Set([
    'food_products_microbiological',
    'other_food_analysis',
    'proximate_analysis',
    'nutrifacts',
]);

function isAquaClassificationValue(classification: string): boolean {
    const value = classification.toLowerCase();
    if (!value.includes('aqua')) {
        return false;
    }

    return !value.includes('potability') && !value.includes('wastewater');
}

function resolveSampleClassification(sample: SampleDraft): string {
    if (sample.classification !== 'Others') {
        return sample.classification;
    }

    if (!sample.classification_other.trim()) {
        return 'Others';
    }

    if (
        isGenericOtherCategory(sample.classification_other) &&
        sample.classification_other_specify.trim()
    ) {
        return `Others: Other — ${sample.classification_other_specify.trim()}`;
    }

    return `Others: ${sample.classification_other.trim()}`;
}

function sampleClassificationComplete(sample: SampleDraft): boolean {
    if (!sample.classification.trim()) {
        return false;
    }

    if (sample.classification !== 'Others') {
        return true;
    }

    if (!sample.classification_other.trim()) {
        return false;
    }

    return (
        !isGenericOtherCategory(sample.classification_other) ||
        sample.classification_other_specify.trim().length > 0
    );
}

function classificationsShareJobOrderForm(samples: SampleDraft[]): boolean {
    let hasAqua = false;
    let hasWater = false;

    samples.forEach((sample) => {
        const value = resolveSampleClassification(sample).toLowerCase();
        if (value.includes('potability') || value.includes('wastewater')) {
            hasWater = true;
        } else if (value.includes('aqua')) {
            hasAqua = true;
        }
    });

    return !(hasAqua && hasWater);
}

function classificationSortKey(label: string, order: string[]): number {
    const value = label.toLowerCase();
    const index = order.findIndex((name) => {
        const needle = name.toLowerCase();

        return needle === 'others'
            ? value.includes('other')
            : value.includes(needle);
    });

    return index === -1 ? order.length : index;
}

/** Category slugs shown in the individual-test catalog for a Details classification. */
const CLASSIFICATION_CATEGORY_SLUGS: Record<string, string[]> = {
    Potability: ['drinking_water', 'microbiological', 'metals'],
    Wastewater: ['waste_water'],
    Aqua: [
        'water_analysis_aquaculture',
        'soil_analysis_aquaculture',
        'adult_fry_prawn_aquaculture',
        'metals',
    ],
    Agriculture: [
        'fertilizer',
        'lime_analysis',
        'soil_analysis_agriculture',
        'metals',
        'special_analysis',
    ],
    'Academic/Research': [
        'metals',
        'special_analysis',
        'phytochemical',
        'proximate_analysis',
        'other_food_analysis',
        'food_products_microbiological',
        'nutrifacts',
    ],
};

function scopeVisible(scope: string, isAqua: boolean): boolean {
    if (scope === 'both') {
        return true;
    }

    if (scope === 'aqua') {
        return isAqua;
    }

    return !isAqua;
}

/**
 * Allowed individual-test category slugs for the Details classification.
 * `null` = no classification filter (keep aqua/non-aqua scope only).
 */
function categorySlugsForClassification(
    classification: string,
    classificationOther: string,
    categories: Array<{ category: string | null; label: string }>,
): Set<string> | null {
    if (!classification.trim()) {
        return null;
    }

    if (classification === 'Others') {
        const otherLabel = classificationOther.trim().toLowerCase();
        if (!otherLabel || isGenericOtherCategory(classificationOther)) {
            return null;
        }

        const match = categories.find(
            (group) => group.label.trim().toLowerCase() === otherLabel,
        );
        if (match?.category) {
            if (FOOD_CATEGORY_SLUGS.has(match.category)) {
                return new Set([match.category, 'metals']);
            }

            return new Set([match.category]);
        }

        return null;
    }

    const mapped = CLASSIFICATION_CATEGORY_SLUGS[classification];
    if (mapped) {
        return new Set(mapped);
    }

    const needle = classification.toLowerCase();
    for (const [label, slugs] of Object.entries(
        CLASSIFICATION_CATEGORY_SLUGS,
    )) {
        if (needle.includes(label.toLowerCase())) {
            return new Set(slugs);
        }
    }

    return null;
}

function catalogForSample(
    sample: SampleDraft,
    packages: IntakePackage[],
    categories: CategoryGroup[],
) {
    const resolved = resolveSampleClassification(sample);
    const isAqua = isAquaClassificationValue(resolved);
    const visiblePackages = packages.filter((pkg) =>
        isAqua ? pkg.visible_for_aqua : pkg.visible_for_non_aqua,
    );
    const allowedSlugs = categorySlugsForClassification(
        sample.classification,
        sample.classification_other,
        categories,
    );
    const visibleCategories = categories
        .map((group) => ({
            ...group,
            items: group.items.filter((item) =>
                scopeVisible(item.catalog_scope || 'non_aqua', isAqua),
            ),
        }))
        .filter((group) => group.items.length > 0)
        .filter((group) => {
            if (allowedSlugs === null) {
                return true;
            }

            return group.category != null && allowedSlugs.has(group.category);
        });

    return { resolved, isAqua, visiblePackages, visibleCategories };
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
    const [requestKind, setRequestKind] = useState<
        'analysis' | 'laboratory_services' | ''
    >('');
    const isLaboratoryServices = requestKind === 'laboratory_services';
    const [submitting, setSubmitting] = useState(false);
    const [exitConfirmOpen, setExitConfirmOpen] = useState(false);
    const [submitConfirmOpen, setSubmitConfirmOpen] = useState(false);
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
    /** Sample index that tried FO2 panel without a Sample description kind. */
    const [fo2NeedsKindSample, setFo2NeedsKindSample] = useState<number | null>(
        null,
    );
    const [otherTests, setOtherTests] = useState('');

    const wastewaterKinds = options.wastewater_kinds ?? [
        'Wastewater',
        'River water',
        'Abstracted water',
    ];
    const wastewaterKindSet = useMemo(
        () => new Set(wastewaterKinds),
        [wastewaterKinds],
    );
    const fo2DefaultCodes = options.wastewater_fo2_default_codes;
    const fo2Preset = type_presets.find(isFo2Preset);
    const [activeCategory, setActiveCategory] = useState<string | null>(null);
    const [testsView, setTestsView] = useState<'packages' | 'individual'>(
        'packages',
    );
    const [testsQuery, setTestsQuery] = useState('');
    const [samples, setSamples] = useState<SampleDraft[]>([emptySample()]);
    const [activeTestSample, setActiveTestSample] = useState(0);
    const [expandedSample, setExpandedSample] = useState<number | null>(0);
    const nextSampleFocusRef = useRef<number | null>(null);

    useEffect(() => {
        setAttemptedContinue(false);
    }, [step]);

    useEffect(() => {
        setActiveTestSample((current) =>
            Math.min(current, Math.max(0, samples.length - 1)),
        );
    }, [samples.length]);

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

    const typePriceMap = useMemo(() => {
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

        return map;
    }, [categories, packages, type_presets]);

    const sampleSelectionSummaries = useMemo(
        () =>
            samples.map((sample, index) => {
                const { subtotal } = samplePackagePricing(
                    sample.package_ids,
                    sample.analysis_type_ids,
                    packages,
                    (id) => Number(typePriceMap.get(id)?.price || 0),
                );

                return {
                    label: sample.sample_code.trim() || `Sample ${index + 1}`,
                    package_ids: sample.package_ids,
                    analysis_type_ids: sample.analysis_type_ids,
                    subtotal,
                    names: sample.analysis_type_ids
                        .map((id) => typePriceMap.get(id)?.name)
                        .filter(Boolean) as string[],
                    packageNames: sample.package_ids
                        .map(
                            (id) => packages.find((pkg) => pkg.id === id)?.name,
                        )
                        .filter(Boolean) as string[],
                };
            }),
        [samples, typePriceMap, packages],
    );

    const estimatedTotal = sampleSelectionSummaries.reduce(
        (sum, sample) => sum + sample.subtotal,
        0,
    );

    const totalSelectedAnalyses = samples.reduce(
        (sum, sample) => sum + sample.analysis_type_ids.length,
        0,
    );

    const activeCatalog = useMemo(() => {
        const sample = samples[activeTestSample] ?? samples[0] ?? emptySample();

        return catalogForSample(sample, packages, categories);
    }, [samples, activeTestSample, packages, categories]);

    const resolvedClassification = activeCatalog.resolved;
    const isAquaClassification = activeCatalog.isAqua;
    const scopedPackages = activeCatalog.visiblePackages;
    const scopedCategories = activeCatalog.visibleCategories;

    const joinedClassification = useMemo(() => {
        const order = options.classifications;
        const unique: string[] = [];

        samples.forEach((sample) => {
            const label = resolveSampleClassification(sample).trim();
            if (label !== '' && !unique.includes(label)) {
                unique.push(label);
            }
        });

        unique.sort(
            (left, right) =>
                classificationSortKey(left, order) -
                    classificationSortKey(right, order) ||
                left.localeCompare(right),
        );

        return unique.join(', ');
    }, [samples, options.classifications]);

    const resolvedSampleSource =
        wastewaterSource === 'Others'
            ? wastewaterSourceOther.trim()
                ? `Others: ${wastewaterSourceOther.trim()}`
                : 'Others'
            : wastewaterSource;

    const usesAquaSources =
        samples.some((sample) =>
            isAquaClassificationValue(resolveSampleClassification(sample)),
        ) &&
        !samples.some((sample) => {
            const value = resolveSampleClassification(sample).toLowerCase();

            return (
                value.includes('potability') || value.includes('wastewater')
            );
        });

    const hidePotabilityFields =
        samples.length > 0 &&
        samples.every(
            (sample) => resolveSampleClassification(sample) === 'Aqua',
        );

    useEffect(() => {
        if (
            activeCategory !== null &&
            !scopedCategories.some((group) => group.category === activeCategory)
        ) {
            setActiveCategory(null);
        }
    }, [scopedCategories, activeCategory]);

    const needsSpecimen = useMemo(() => {
        if (
            samples.some((sample) => {
                if (sample.classification !== 'Others') {
                    return false;
                }

                const otherLabel = sample.classification_other
                    .trim()
                    .toLowerCase();

                return (
                    otherLabel !== '' &&
                    categories.some(
                        (group) =>
                            FOOD_CATEGORY_SLUGS.has(group.category ?? '') &&
                            group.label.trim().toLowerCase() === otherLabel,
                    )
                );
            })
        ) {
            return true;
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

        const allPackageIds = samples.flatMap((sample) => sample.package_ids);
        const allTypeIds = samples.flatMap(
            (sample) => sample.analysis_type_ids,
        );

        if (
            allPackageIds.some((id) => {
                const pkg = packages.find((row) => row.id === id);
                return pkg?.category
                    ? FOOD_CATEGORY_SLUGS.has(pkg.category)
                    : false;
            })
        ) {
            return true;
        }

        return allTypeIds.some((id) =>
            FOOD_CATEGORY_SLUGS.has(typeCategory.get(id) ?? ''),
        );
    }, [categories, packages, samples]);

    useEffect(() => {
        if (!needsSpecimen && specimen) {
            setSpecimen('');
        }
    }, [needsSpecimen, specimen]);

    useEffect(() => {
        setActiveTestSample((current) =>
            Math.min(current, Math.max(0, samples.length - 1)),
        );
    }, [samples.length]);

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
                    !suggestedTypePresets.some(
                        (item) => item.key === preset.key,
                    ),
            ),
        [type_presets, suggestedTypePresets],
    );

    const classificationSignature = samples
        .map(
            (sample) =>
                `${sample.classification}|${sample.classification_other}|${sample.classification_other_specify}`,
        )
        .join('\n');

    useEffect(() => {
        const allowedPresetKeys = new Set(
            type_presets.map((preset) => preset.key),
        );

        setSamples((current) => {
            let changed = false;
            const next = current.map((sample) => {
                const catalog = catalogForSample(sample, packages, categories);
                const allowedTypeIds = new Set<number>();
                catalog.visibleCategories.forEach((group) =>
                    group.items.forEach((item) => allowedTypeIds.add(item.id)),
                );
                catalog.visiblePackages.forEach((pkg) =>
                    pkg.analysis_type_ids.forEach((id) =>
                        allowedTypeIds.add(id),
                    ),
                );
                type_presets.forEach((preset) =>
                    preset.analysis_type_ids.forEach((id) =>
                        allowedTypeIds.add(id),
                    ),
                );
                const allowedPackageIds = new Set(
                    catalog.visiblePackages.map((pkg) => pkg.id),
                );
                const package_ids = sample.package_ids.filter((id) =>
                    allowedPackageIds.has(id),
                );
                const analysis_type_ids = sample.analysis_type_ids.filter(
                    (id) => allowedTypeIds.has(id),
                );
                const preset_keys = sample.preset_keys.filter((key) =>
                    allowedPresetKeys.has(key),
                );

                if (
                    package_ids.length === sample.package_ids.length &&
                    analysis_type_ids.length ===
                        sample.analysis_type_ids.length &&
                    preset_keys.length === sample.preset_keys.length
                ) {
                    return sample;
                }

                changed = true;
                return {
                    ...sample,
                    package_ids,
                    analysis_type_ids,
                    preset_keys,
                };
            });

            return changed ? next : current;
        });
    }, [classificationSignature, packages, categories, type_presets]);

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
                push(item.id, item.name, String(item.default_price ?? '0')),
            ),
        );

        return items;
    }, [categories, packages, type_presets]);

    function individualSelectedCountFor(sample: SampleDraft): number {
        const covered = new Set<number>();
        scopedPackages
            .filter((pkg) => sample.package_ids.includes(pkg.id))
            .forEach((pkg) =>
                pkg.analysis_type_ids.forEach((id) => covered.add(id)),
            );
        type_presets
            .filter((preset) => sample.preset_keys.includes(preset.key))
            .forEach((preset) =>
                preset.analysis_type_ids.forEach((id) => covered.add(id)),
            );

        return sample.analysis_type_ids.filter((id) => !covered.has(id)).length;
    }

    function updateSampleSelection(
        index: number,
        patch: Partial<
            Pick<
                SampleDraft,
                'package_ids' | 'analysis_type_ids' | 'preset_keys'
            >
        >,
    ) {
        setActiveTestSample(index);
        setSamples((current) =>
            current.map((sample, i) =>
                i === index ? { ...sample, ...patch } : sample,
            ),
        );
    }

    function keptTypeIdsExcluding(
        sample: SampleDraft,
        memberIds: number[],
        options: { exceptPackageId?: number; exceptPresetKey?: string } = {},
    ): Set<number> {
        const keep = new Set<number>();

        packages
            .filter(
                (item) =>
                    sample.package_ids.includes(item.id) &&
                    item.id !== options.exceptPackageId,
            )
            .forEach((item) =>
                item.analysis_type_ids.forEach((id) => keep.add(id)),
            );

        type_presets
            .filter(
                (preset) =>
                    sample.preset_keys.includes(preset.key) &&
                    preset.key !== options.exceptPresetKey,
            )
            .forEach((preset) =>
                preset.analysis_type_ids.forEach((id) => keep.add(id)),
            );

        return keep;
    }

    function packageOwnedTypes(sample: SampleDraft): Map<number, string> {
        const owned = new Map<number, string>();

        packages
            .filter((item) => sample.package_ids.includes(item.id))
            .forEach((item) =>
                item.analysis_type_ids.forEach((id) => {
                    if (!owned.has(id)) {
                        owned.set(id, item.name);
                    }
                }),
            );

        return owned;
    }

    function toggleType(sampleIndex: number, id: number) {
        setActiveTestSample(sampleIndex);
        setSamples((current) =>
            current.map((sample, i) => {
                if (i !== sampleIndex) {
                    return sample;
                }

                return {
                    ...sample,
                    analysis_type_ids: sample.analysis_type_ids.includes(id)
                        ? sample.analysis_type_ids.filter(
                              (value) => value !== id,
                          )
                        : [...sample.analysis_type_ids, id],
                };
            }),
        );
    }

    function togglePackage(sampleIndex: number, pkg: IntakePackage) {
        setActiveTestSample(sampleIndex);
        setSamples((current) =>
            current.map((sample, i) => {
                if (i !== sampleIndex) {
                    return sample;
                }

                const memberIds = pkg.analysis_type_ids;
                const selected = sample.package_ids.includes(pkg.id);

                if (selected) {
                    const keep = keptTypeIdsExcluding(sample, memberIds, {
                        exceptPackageId: pkg.id,
                    });

                    return {
                        ...sample,
                        package_ids: sample.package_ids.filter(
                            (id) => id !== pkg.id,
                        ),
                        analysis_type_ids: sample.analysis_type_ids.filter(
                            (id) => !memberIds.includes(id) || keep.has(id),
                        ),
                    };
                }

                return {
                    ...sample,
                    package_ids: [...sample.package_ids, pkg.id],
                    analysis_type_ids: [
                        ...new Set([...sample.analysis_type_ids, ...memberIds]),
                    ],
                };
            }),
        );
    }

    function togglePackageMember(
        sampleIndex: number,
        pkg: IntakePackage,
        typeId: number,
    ) {
        setActiveTestSample(sampleIndex);
        setSamples((current) => {
            const sample = current[sampleIndex];
            if (!sample?.package_ids.includes(pkg.id)) {
                return current;
            }

            const memberIds = pkg.analysis_type_ids;
            const currentlyOn = sample.analysis_type_ids.includes(typeId);
            const selectedMembers = memberIds.filter((id) =>
                currentlyOn
                    ? id !== typeId && sample.analysis_type_ids.includes(id)
                    : sample.analysis_type_ids.includes(id) || id === typeId,
            );

            if (currentlyOn && selectedMembers.length === 0) {
                const keep = keptTypeIdsExcluding(sample, memberIds, {
                    exceptPackageId: pkg.id,
                });

                return current.map((row, i) =>
                    i === sampleIndex
                        ? {
                              ...row,
                              package_ids: row.package_ids.filter(
                                  (id) => id !== pkg.id,
                              ),
                              analysis_type_ids: row.analysis_type_ids.filter(
                                  (id) =>
                                      !memberIds.includes(id) || keep.has(id),
                              ),
                          }
                        : row,
                );
            }

            return current.map((row, i) => {
                if (i !== sampleIndex) {
                    return row;
                }

                if (currentlyOn) {
                    return {
                        ...row,
                        analysis_type_ids: row.analysis_type_ids.filter(
                            (id) => id !== typeId,
                        ),
                    };
                }

                return {
                    ...row,
                    analysis_type_ids: row.analysis_type_ids.includes(typeId)
                        ? row.analysis_type_ids
                        : [...row.analysis_type_ids, typeId],
                };
            });
        });
    }

    function withFo2DefaultsForKind(
        sample: SampleDraft,
        kind: string,
    ): SampleDraft {
        if (
            !fo2Preset ||
            !kind ||
            !sample.preset_keys.includes(fo2Preset.key)
        ) {
            return { ...sample, description: kind };
        }

        const defaultIds = fo2DefaultMemberIds(
            fo2Preset,
            kind,
            fo2DefaultCodes,
        );
        const fo2AllIds = new Set(fo2Preset.analysis_type_ids);
        const kept = sample.analysis_type_ids.filter((id) => !fo2AllIds.has(id));

        return {
            ...sample,
            description: kind,
            analysis_type_ids: [...new Set([...kept, ...defaultIds])],
        };
    }

    function toggleTypePreset(sampleIndex: number, preset: IntakeTypePreset) {
        setActiveTestSample(sampleIndex);

        if (
            isFo2Preset(preset) &&
            samples[sampleIndex]?.classification === 'Wastewater'
        ) {
            const selected = samples[sampleIndex]?.preset_keys.includes(
                preset.key,
            );
            const kind = samples[sampleIndex]?.description.trim() ?? '';
            if (!selected && !wastewaterKindSet.has(kind)) {
                setFo2NeedsKindSample(sampleIndex);
                return;
            }
        }

        setFo2NeedsKindSample((current) =>
            current === sampleIndex ? null : current,
        );
        setSamples((current) =>
            current.map((sample, i) => {
                if (i !== sampleIndex) {
                    return sample;
                }

                const sampleKind = sample.description.trim();
                const memberIds =
                    isFo2Preset(preset) && wastewaterKindSet.has(sampleKind)
                        ? fo2DefaultMemberIds(
                              preset,
                              sampleKind,
                              fo2DefaultCodes,
                          )
                        : preset.analysis_type_ids;
                // Always drop the full FO2 panel when unselecting so leftover members clear.
                const removeIds = isFo2Preset(preset)
                    ? preset.analysis_type_ids
                    : memberIds;
                const selected = sample.preset_keys.includes(preset.key);

                if (selected) {
                    const keep = keptTypeIdsExcluding(sample, removeIds, {
                        exceptPresetKey: preset.key,
                    });

                    return {
                        ...sample,
                        preset_keys: sample.preset_keys.filter(
                            (key) => key !== preset.key,
                        ),
                        analysis_type_ids: sample.analysis_type_ids.filter(
                            (id) => !removeIds.includes(id) || keep.has(id),
                        ),
                    };
                }

                return {
                    ...sample,
                    preset_keys: [...sample.preset_keys, preset.key],
                    analysis_type_ids: [
                        ...new Set([...sample.analysis_type_ids, ...memberIds]),
                    ],
                };
            }),
        );
    }

    function toggleTypePresetMember(
        sampleIndex: number,
        preset: IntakeTypePreset,
        typeId: number,
    ) {
        setActiveTestSample(sampleIndex);
        setSamples((current) => {
            const sample = current[sampleIndex];
            if (!sample?.preset_keys.includes(preset.key)) {
                return current;
            }

            // Tests owned by a selected package stay with the package.
            const owned = packageOwnedTypes(sample);
            if (owned.has(typeId)) {
                return current;
            }

            const memberIds = preset.analysis_type_ids.filter(
                (id) => !owned.has(id),
            );
            const currentlyOn = sample.analysis_type_ids.includes(typeId);
            const selectedMembers = memberIds.filter((id) =>
                currentlyOn
                    ? id !== typeId && sample.analysis_type_ids.includes(id)
                    : sample.analysis_type_ids.includes(id) || id === typeId,
            );

            if (currentlyOn && selectedMembers.length === 0) {
                const keep = keptTypeIdsExcluding(sample, memberIds, {
                    exceptPresetKey: preset.key,
                });

                return current.map((row, i) =>
                    i === sampleIndex
                        ? {
                              ...row,
                              preset_keys: row.preset_keys.filter(
                                  (key) => key !== preset.key,
                              ),
                              analysis_type_ids: row.analysis_type_ids.filter(
                                  (id) =>
                                      !memberIds.includes(id) || keep.has(id),
                              ),
                          }
                        : row,
                );
            }

            return current.map((row, i) => {
                if (i !== sampleIndex) {
                    return row;
                }

                if (currentlyOn) {
                    return {
                        ...row,
                        analysis_type_ids: row.analysis_type_ids.filter(
                            (id) => id !== typeId,
                        ),
                    };
                }

                return {
                    ...row,
                    analysis_type_ids: row.analysis_type_ids.includes(typeId)
                        ? row.analysis_type_ids
                        : [...row.analysis_type_ids, typeId],
                };
            });
        });
    }

    function presetEstimatedPrice(preset: IntakeTypePreset): number {
        return preset.tests.reduce(
            (sum, test) => sum + Number(test.default_price || 0),
            0,
        );
    }

    function copyTestsFromSample(fromIndex: number, toIndex: number) {
        const source = samples[fromIndex];
        if (!source) {
            return;
        }

        updateSampleSelection(toIndex, {
            package_ids: [...source.package_ids],
            analysis_type_ids: [...source.analysis_type_ids],
            preset_keys: [...source.preset_keys],
        });
    }

    function sampleHasSelection(sample: SampleDraft, index: number): boolean {
        if (
            sample.analysis_type_ids.length > 0 ||
            sample.package_ids.length > 0
        ) {
            return true;
        }

        return index === 0 && otherTests.trim().length > 0;
    }

    function renderPackageRow(sampleIndex: number, pkg: IntakePackage) {
        const sample = samples[sampleIndex];

        return (
            <IntakePackageRow
                key={pkg.id}
                pkg={pkg}
                selected={sample.package_ids.includes(pkg.id)}
                selectedTypes={sample.analysis_type_ids}
                onTogglePackage={() => togglePackage(sampleIndex, pkg)}
                onToggleMember={(typeId) =>
                    togglePackageMember(sampleIndex, pkg, typeId)
                }
            />
        );
    }

    function renderTypePresetRow(
        sampleIndex: number,
        preset: IntakeTypePreset,
    ) {
        const sample = samples[sampleIndex];

        return (
            <IntakeTypePresetRow
                key={preset.key}
                preset={preset}
                selected={sample.preset_keys.includes(preset.key)}
                selectedTypes={sample.analysis_type_ids}
                lockedTypes={packageOwnedTypes(sample)}
                estimatedPrice={presetEstimatedPrice(preset)}
                onTogglePreset={() => toggleTypePreset(sampleIndex, preset)}
                onToggleMember={(typeId) =>
                    toggleTypePresetMember(sampleIndex, preset, typeId)
                }
            />
        );
    }

    function clearSampleSelections(sample: SampleDraft): SampleDraft {
        return {
            ...sample,
            package_ids: [],
            analysis_type_ids: [],
            preset_keys: [],
        };
    }

    function setSampleClassification(index: number, value: string) {
        setSamples((current) =>
            current.map((sample, i) => {
                if (i !== index || sample.classification === value) {
                    return sample;
                }

                const leavingWastewater =
                    sample.classification === 'Wastewater' &&
                    value !== 'Wastewater';

                return clearSampleSelections({
                    ...sample,
                    classification: value,
                    classification_other:
                        value === 'Others' ? sample.classification_other : '',
                    classification_other_specify:
                        value === 'Others'
                            ? sample.classification_other_specify
                            : '',
                    description:
                        leavingWastewater &&
                        wastewaterKindSet.has(sample.description)
                            ? ''
                            : sample.description,
                });
            }),
        );
    }

    function setSampleClassificationOther(index: number, value: string) {
        setSamples((current) =>
            current.map((sample, i) => {
                if (i !== index) {
                    return sample;
                }

                return clearSampleSelections({
                    ...sample,
                    classification_other: value,
                    classification_other_specify: isGenericOtherCategory(value)
                        ? sample.classification_other_specify
                        : '',
                });
            }),
        );
    }

    function setSampleClassificationOtherSpecify(index: number, value: string) {
        setSamples((current) =>
            current.map((sample, i) =>
                i === index
                    ? { ...sample, classification_other_specify: value }
                    : sample,
            ),
        );
    }

    function updateSample(index: number, key: SampleFieldKey, value: string) {
        if (
            key === 'description' &&
            samples[index]?.classification === 'Wastewater' &&
            (value === '' || wastewaterKindSet.has(value))
        ) {
            if (value !== '') {
                setFo2NeedsKindSample((current) =>
                    current === index ? null : current,
                );
            }
            setSamples((current) =>
                current.map((sample, i) =>
                    i === index ? withFo2DefaultsForKind(sample, value) : sample,
                ),
            );

            return;
        }

        setSamples((current) =>
            current.map((sample, i) =>
                i === index ? { ...sample, [key]: value } : sample,
            ),
        );
    }

    function canContinue() {
        if (step === 0) {
            return (
                requestKind !== '' &&
                customer.customer_name.trim().length > 0
            );
        }

        if (isLaboratoryServices) {
            return true;
        }

        if (step === 1) {
            if (
                wastewaterSource === 'Others' &&
                !wastewaterSourceOther.trim()
            ) {
                return false;
            }

            if (paymentMode === 'billing_partial' && !paymentTerms) {
                return false;
            }

            return true;
        }

        if (step === 2) {
            if (samples.length === 0) {
                return false;
            }

            if (!samples.every((sample) => sampleClassificationComplete(sample))) {
                return false;
            }

            if (!classificationsShareJobOrderForm(samples)) {
                return false;
            }

            return samples.every(
                (sample) =>
                    sample.classification !== 'Wastewater' ||
                    wastewaterKindSet.has(sample.description.trim()),
            );
        }

        if (step === 3) {
            return samples.every((sample, index) =>
                sampleHasSelection(sample, index),
            );
        }

        return true;
    }

    function stepError(index: number) {
        if (index !== step || !attemptedContinue) {
            return null;
        }

        if (step === 0 && !canContinue()) {
            if (!requestKind) {
                return 'Choose Sample analysis or Laboratory services.';
            }

            return 'Add your full name to continue.';
        }

        if (isLaboratoryServices) {
            return null;
        }

        if (step === 1 && !canContinue()) {
            if (paymentMode === 'billing_partial' && !paymentTerms) {
                return 'Select 15 or 30 day terms for Billing / Partial.';
            }

            return 'Complete the required sample details before continuing.';
        }

        if (step === 2 && !canContinue()) {
            if (
                samples.some((sample) => !sampleClassificationComplete(sample))
            ) {
                return 'Choose a classification for every sample.';
            }

            if (!classificationsShareJobOrderForm(samples)) {
                return 'Potability or Wastewater cannot share this request with Aqua.';
            }

            if (
                samples.some(
                    (sample) =>
                        sample.classification === 'Wastewater' &&
                        !wastewaterKindSet.has(sample.description.trim()),
                )
            ) {
                return 'Choose a sample description (Wastewater, River water, or Abstracted water) for every wastewater sample.';
            }

            return 'Add at least one sample before continuing.';
        }

        if (step === 3 && !canContinue()) {
            return 'Select tests for every sample, or describe another test for Sample 1.';
        }

        return null;
    }

    function scrollToFirstIntakeIssue() {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const root = document.querySelector('[data-intake-wizard]');
                const scope = root ?? document;
                const target =
                    scope.querySelector<HTMLElement>('[aria-invalid="true"]') ??
                    scope.querySelector<HTMLElement>('[data-intake-error]');

                target?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            });
        });
    }

    function goNext() {
        if (!canContinue()) {
            setAttemptedContinue(true);
            scrollToFirstIntakeIssue();
            return;
        }
        setAttemptedContinue(false);
        if (step === 0 && isLaboratoryServices) {
            setStep(4);
            return;
        }
        setStep((s) => s + 1);
    }

    function goBack() {
        if (step === 4 && isLaboratoryServices) {
            setStep(0);
            return;
        }
        setStep((s) => Math.max(0, s - 1));
    }

    const requestIsDirty =
        step > 0 ||
        Object.values(customer).some((value) => value.trim().length > 0) ||
        ownershipType.trim().length > 0 ||
        paymentMode.trim().length > 0 ||
        samples.some(
            (sample) =>
                sample.classification.trim() ||
                sample.sample_code.trim() ||
                sample.description.trim() ||
                sample.matrix.trim() ||
                sample.quantity.trim() ||
                sample.remarks.trim() ||
                sample.package_ids.length > 0 ||
                sample.analysis_type_ids.length > 0,
        ) ||
        otherTests.trim().length > 0;

    function requestExit() {
        if (requestIsDirty) {
            setExitConfirmOpen(true);
            return;
        }

        router.visit('/intake');
    }

    function submit() {
        if (!canContinue()) {
            setAttemptedContinue(true);
            scrollToFirstIntakeIssue();
            return;
        }

        setSubmitConfirmOpen(true);
    }

    function confirmSubmit() {
        setSubmitting(true);
        router.post(
            '/intake/job-orders',
            isLaboratoryServices
                ? {
                      ...customer,
                      ownership_type: ownershipType || null,
                      is_laboratory_services: true,
                      payment_mode: paymentMode || null,
                      payment_terms:
                          paymentMode === 'billing_partial'
                              ? paymentTerms || null
                              : null,
                      samples: [],
                  }
                : {
                      ...customer,
                      ownership_type: ownershipType || null,
                      is_laboratory_services: false,
                      classification: joinedClassification || null,
                      sampling_date: samplingDate || null,
                      sampling_time: samplingTime || null,
                      sample_collected_by: sampleCollectedBy || null,
                      sampling_site: samplingSite.trim() || null,
                      specimen: needsSpecimen
                          ? specimen.trim() || null
                          : null,
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
                          classification:
                              resolveSampleClassification(sample) || null,
                          sample_code: sample.sample_code,
                          description: sample.description,
                          matrix: sample.matrix,
                          quantity: sample.quantity || null,
                          unit: sample.unit,
                          remarks: sample.remarks,
                          package_ids: sample.package_ids,
                          analysis_type_ids: sample.analysis_type_ids,
                      })),
                  },
            {
                onFinish: () => {
                    setSubmitting(false);
                    setSubmitConfirmOpen(false);
                },
            },
        );
    }

    const hasPotabilityData = sterileBottle || fieldData.trim().length > 0;

    return (
        <>
            <Head title="Request for Analysis" />
            <IntakePageShell
                wizard={{
                    steps,
                    step,
                    onStepSelect: (next) => {
                        if (
                            isLaboratoryServices &&
                            next > 0 &&
                            next < 4
                        ) {
                            return;
                        }
                        setStep(next);
                    },
                }}
                onExit={requestExit}
            >
                <div className="flex min-h-0 flex-1 flex-col gap-2">
                    <div
                        key={step}
                        data-intake-wizard
                        className="intake-enter flex min-h-0 min-w-0 flex-1 flex-col"
                    >
                        {errors?.analysis_type_ids ? (
                            <IntakeAlert
                                variant="error"
                                className="mb-1 shrink-0"
                                data-intake-error=""
                            >
                                {errors.analysis_type_ids}
                            </IntakeAlert>
                        ) : null}

                        {step === 0 && (
                            <div className="grid min-h-0 flex-1 gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)] lg:items-stretch">
                                <IntakePanel
                                    title="Contact & Organization Details"
                                    description="Enter the requestor contact details used for receiving and result release."
                                    icon={ContactRound}
                                    className="flex h-full min-h-0 flex-col"
                                    bodyClassName="flex flex-col"
                                    action={
                                        <span className="text-[10px] font-medium text-rose-600">
                                            * Mandatory fields
                                        </span>
                                    }
                                >
                                    <div className="flex h-full min-h-0 flex-col gap-3 md:gap-3.5">
                                        <IntakeField
                                            label="What do you need?"
                                            required
                                            description="Sample analysis uses packages and tests. Laboratory services are priced by Receiving (no samples)."
                                            error={
                                                !requestKind &&
                                                attemptedContinue
                                                    ? 'Choose Sample analysis or Laboratory services.'
                                                    : null
                                            }
                                        >
                                            <div className="flex flex-wrap gap-2">
                                                <ChoiceChip
                                                    active={
                                                        requestKind ===
                                                        'analysis'
                                                    }
                                                    onClick={() =>
                                                        setRequestKind(
                                                            'analysis',
                                                        )
                                                    }
                                                >
                                                    Sample analysis
                                                </ChoiceChip>
                                                <ChoiceChip
                                                    active={
                                                        requestKind ===
                                                        'laboratory_services'
                                                    }
                                                    onClick={() =>
                                                        setRequestKind(
                                                            'laboratory_services',
                                                        )
                                                    }
                                                >
                                                    Laboratory services
                                                </ChoiceChip>
                                            </div>
                                        </IntakeField>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-x-4">
                                            <IntakeField
                                                label="Full Name"
                                                htmlFor="customer_name"
                                                required
                                                description="Legal name of the requestor"
                                                icon={UserRound}
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
                                                    className={cn(
                                                        'h-10 w-full border-slate-200 bg-white text-sm',
                                                        !!stepError(0) &&
                                                            !customer.customer_name.trim() &&
                                                            'border-rose-300 focus-visible:border-rose-300 focus-visible:ring-rose-200',
                                                    )}
                                                    value={
                                                        customer.customer_name
                                                    }
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
                                                label="Email Address"
                                                htmlFor="customer_email"
                                                hint="(optional)"
                                                description="Used for result notifications"
                                                icon={Mail}
                                            >
                                                <Input
                                                    id="customer_email"
                                                    type="email"
                                                    autoComplete="email"
                                                    className="h-10 w-full border-slate-200 bg-white text-sm"
                                                    value={
                                                        customer.customer_email
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
                                        </div>

                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-x-4">
                                            <IntakeField
                                                label="Contact Phone Number"
                                                htmlFor="customer_contact"
                                                hint="(optional)"
                                                description="Mobile or landline for receiving follow-up"
                                                icon={Phone}
                                            >
                                                <Input
                                                    id="customer_contact"
                                                    type="tel"
                                                    autoComplete="tel"
                                                    className="h-10 w-full border-slate-200 bg-white text-sm"
                                                    value={
                                                        customer.customer_contact
                                                    }
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
                                                hint="(optional)"
                                                description="Leave blank for individual requests"
                                                icon={Building2}
                                            >
                                                <Input
                                                    id="company_name"
                                                    type="text"
                                                    autoComplete="organization"
                                                    placeholder="Enter company or organization"
                                                    className="h-10 w-full border-slate-200 bg-white text-sm"
                                                    value={
                                                        customer.company_name
                                                    }
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

                                        <IntakeField
                                            label="Address"
                                            htmlFor="customer_address"
                                            hint="(optional)"
                                            description="Street, barangay, city / municipality"
                                            icon={MapPin}
                                        >
                                            <Input
                                                id="customer_address"
                                                type="text"
                                                autoComplete="street-address"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
                                                placeholder="Street, barangay, city / municipality"
                                                value={
                                                    customer.customer_address
                                                }
                                                onChange={(e) =>
                                                    setCustomer((c) => ({
                                                        ...c,
                                                        customer_address:
                                                            e.target.value,
                                                    }))
                                                }
                                            />
                                        </IntakeField>

                                        <div className="mt-auto border-t border-slate-100 pt-3">
                                            <p className="mb-0.5 text-[11px] font-semibold text-slate-700">
                                                Ownership Type{' '}
                                                <span className="font-normal text-slate-400">
                                                    (optional)
                                                </span>
                                            </p>
                                            <p className="mb-2 text-[10px] text-slate-500">
                                                Used for billing classification
                                                and laboratory records
                                            </p>
                                            <div
                                                className="grid grid-cols-1 gap-2 sm:grid-cols-3"
                                                role="group"
                                                aria-label="Type of ownership"
                                            >
                                                {(
                                                    [
                                                        {
                                                            item: 'Private',
                                                            blurb: 'Individual / Household',
                                                            Icon: UserRound,
                                                        },
                                                        {
                                                            item: 'Commercial',
                                                            blurb: 'Business Entity',
                                                            Icon: Building2,
                                                        },
                                                        {
                                                            item: 'Public',
                                                            blurb: 'Government / LGU',
                                                            Icon: Landmark,
                                                        },
                                                    ] as const
                                                )
                                                    .filter(({ item }) =>
                                                        options.ownership.includes(
                                                            item,
                                                        ),
                                                    )
                                                    .map(
                                                        ({
                                                            item,
                                                            blurb,
                                                            Icon,
                                                        }) => {
                                                            const active =
                                                                ownershipType ===
                                                                item;

                                                            return (
                                                                <button
                                                                    key={item}
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setOwnershipType(
                                                                            active
                                                                                ? ''
                                                                                : item,
                                                                        )
                                                                    }
                                                                    className={cn(
                                                                        'inline-flex items-center gap-2 rounded-md border px-2.5 py-2 text-left transition',
                                                                        active
                                                                            ? 'border-[#1d3b91] bg-[#eef2ff] shadow-xs ring-1 ring-[#1d3b91]/20'
                                                                            : 'border-slate-200 bg-white hover:border-[#1d3b91]/35 hover:bg-slate-50/80',
                                                                    )}
                                                                >
                                                                    {active ? (
                                                                        <Check className="size-3.5 shrink-0 text-[#1d3b91]" />
                                                                    ) : (
                                                                        <Icon className="size-3.5 shrink-0 text-slate-500" />
                                                                    )}
                                                                    <span className="min-w-0">
                                                                        <span
                                                                            className={cn(
                                                                                'block text-xs font-semibold',
                                                                                active
                                                                                    ? 'text-[#112747]'
                                                                                    : 'text-slate-800',
                                                                            )}
                                                                        >
                                                                            {
                                                                                item
                                                                            }
                                                                        </span>
                                                                        <span className="block text-[10px] leading-snug text-slate-500">
                                                                            {
                                                                                blurb
                                                                            }
                                                                        </span>
                                                                    </span>
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                            </div>
                                        </div>
                                    </div>
                                </IntakePanel>

                                <aside className="flex min-h-0 flex-col gap-3 lg:h-full">
                                    <IntakePanel
                                        title="Live Intake Summary"
                                        icon={Users}
                                        variant="summary"
                                        className="flex min-h-0 flex-1 flex-col"
                                        bodyClassName="flex flex-1 flex-col"
                                    >
                                        <dl className="flex flex-1 flex-col justify-center gap-3">
                                            {(
                                                [
                                                    [
                                                        'Customer',
                                                        customer.customer_name.trim() ||
                                                            'Not provided',
                                                        UserRound,
                                                    ],
                                                    [
                                                        'Email',
                                                        customer.customer_email.trim() ||
                                                            'Not provided',
                                                        Mail,
                                                    ],
                                                    [
                                                        'Contact',
                                                        customer.customer_contact.trim() ||
                                                            'Not provided',
                                                        Phone,
                                                    ],
                                                    [
                                                        'Company',
                                                        customer.company_name.trim() ||
                                                            'Not provided',
                                                        Building2,
                                                    ],
                                                    [
                                                        'Ownership',
                                                        ownershipType.trim() ||
                                                            'Not provided',
                                                        Tag,
                                                    ],
                                                ] as const
                                            ).map(([label, value, Icon]) => (
                                                <div
                                                    key={label}
                                                    className="flex min-w-0 items-start gap-2"
                                                >
                                                    <Icon
                                                        className="mt-0.5 size-3.5 shrink-0 text-slate-400"
                                                        aria-hidden
                                                    />
                                                    <div className="min-w-0 flex-1">
                                                        <dt className="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                                            {label}
                                                        </dt>
                                                        <dd className="mt-0.5 truncate text-sm leading-snug font-medium text-slate-900">
                                                            {value}
                                                        </dd>
                                                    </div>
                                                </div>
                                            ))}
                                        </dl>
                                    </IntakePanel>

                                    <IntakeAlert
                                        variant="warning"
                                        title="Chain of Custody Notice"
                                        className="shrink-0 px-2.5 py-2.5 text-xs"
                                    >
                                        <p>
                                            Bring labeled samples to Receiving
                                            during counter hours. Integrity is
                                            verified at drop-off before analysis
                                            begins.
                                        </p>
                                        <p className="mt-1.5 font-medium text-amber-900">
                                            Receiving Hours: 8:00 AM – 5:00 PM
                                        </p>
                                    </IntakeAlert>
                                </aside>
                            </div>
                        )}

                        {step === 2 && (
                            <div className="flex w-full flex-col gap-2 pb-0.5">
                                <IntakeStepHeader
                                    title="Samples"
                                    badge="Step 3 of 5 · Specimens"
                                    hint={
                                        samples.length > 1
                                            ? `${samples.length} samples in this request. Each sample can use its own classification, except Aqua with Potability or Wastewater.`
                                            : 'Add the samples you are submitting today for laboratory analysis.'
                                    }
                                    action={
                                        <span className="text-nppc-800 inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-[11px] font-medium">
                                            <CircleHelp
                                                className="size-3.5 text-nppc-700"
                                                aria-hidden
                                            />
                                            Turnaround: 5–7 business days
                                        </span>
                                    }
                                    className="shrink-0 [&_h2]:text-lg sm:[&_h2]:text-xl [&_p]:mt-0.5"
                                />

                                <div className="grid gap-2 lg:grid-cols-[minmax(0,1.55fr)_minmax(260px,0.78fr)] lg:items-start">
                                    <div className="min-w-0 space-y-2">
                                        <div className="space-y-2 rounded-xl border border-slate-200/80 bg-slate-50/50 p-2.5 sm:p-3">
                                            {samples.map((sample, index) => (
                                                <IntakeSampleRow
                                                    key={index}
                                                    sample={sample}
                                                    index={index}
                                                    expanded={
                                                        samples.length === 1 ||
                                                        expandedSample === index
                                                    }
                                                    collapsible={
                                                        samples.length > 1
                                                    }
                                                    removable={
                                                        samples.length > 1
                                                    }
                                                    showWastewaterKind={
                                                        sample.classification ===
                                                        'Wastewater'
                                                    }
                                                    classifications={
                                                        options.classifications
                                                    }
                                                    otherClassificationOptions={
                                                        options.other_classification_options ??
                                                        []
                                                    }
                                                    classificationError={
                                                        attemptedContinue &&
                                                        step === 2 &&
                                                        !sampleClassificationComplete(
                                                            sample,
                                                        )
                                                    }
                                                    onClassificationChange={(
                                                        value,
                                                    ) =>
                                                        setSampleClassification(
                                                            index,
                                                            value,
                                                        )
                                                    }
                                                    onClassificationOtherChange={(
                                                        value,
                                                    ) =>
                                                        setSampleClassificationOther(
                                                            index,
                                                            value,
                                                        )
                                                    }
                                                    onClassificationOtherSpecifyChange={(
                                                        value,
                                                    ) =>
                                                        setSampleClassificationOtherSpecify(
                                                            index,
                                                            value,
                                                        )
                                                    }
                                                    wastewaterKinds={
                                                        wastewaterKinds
                                                    }
                                                    wastewaterKindError={
                                                        attemptedContinue &&
                                                        step === 2
                                                    }
                                                    onToggleExpand={() =>
                                                        setExpandedSample(
                                                            (current) =>
                                                                current ===
                                                                index
                                                                    ? null
                                                                    : index,
                                                        )
                                                    }
                                                    onChange={(key, value) =>
                                                        updateSample(
                                                            index,
                                                            key,
                                                            value,
                                                        )
                                                    }
                                                    onDuplicate={() => {
                                                        nextSampleFocusRef.current =
                                                            index + 1;
                                                        setExpandedSample(
                                                            index + 1,
                                                        );
                                                        setSamples(
                                                            (current) => {
                                                                const copy = {
                                                                    ...current[
                                                                        index
                                                                    ],
                                                                    sample_code:
                                                                        '',
                                                                };

                                                                return [
                                                                    ...current.slice(
                                                                        0,
                                                                        index +
                                                                            1,
                                                                    ),
                                                                    copy,
                                                                    ...current.slice(
                                                                        index +
                                                                            1,
                                                                    ),
                                                                ];
                                                            },
                                                        );
                                                    }}
                                                    onRemove={() => {
                                                        setSamples((current) =>
                                                            current.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                        );
                                                        setActiveTestSample(
                                                            (current) => {
                                                                if (
                                                                    current >
                                                                    index
                                                                ) {
                                                                    return (
                                                                        current -
                                                                        1
                                                                    );
                                                                }

                                                                return Math.min(
                                                                    current,
                                                                    Math.max(
                                                                        0,
                                                                        samples.length -
                                                                            2,
                                                                    ),
                                                                );
                                                            },
                                                        );
                                                        setExpandedSample(
                                                            (current) => {
                                                                if (
                                                                    current ===
                                                                    null
                                                                ) {
                                                                    return null;
                                                                }

                                                                return current >
                                                                    index
                                                                    ? current -
                                                                          1
                                                                    : Math.min(
                                                                          current,
                                                                          samples.length -
                                                                              2,
                                                                      );
                                                            },
                                                        );
                                                    }}
                                                />
                                            ))}

                                            <Button
                                                type="button"
                                                className="h-9 gap-1.5 bg-sky-50 px-3.5 text-xs font-semibold text-nppc-900 hover:bg-sky-100"
                                                onClick={() => {
                                                    nextSampleFocusRef.current =
                                                        samples.length;
                                                    setExpandedSample(
                                                        samples.length,
                                                    );
                                                    setSamples((current) => {
                                                        const previous =
                                                            current[
                                                                current.length -
                                                                    1
                                                            ];

                                                        return [
                                                            ...current,
                                                            {
                                                                ...emptySample(),
                                                                classification:
                                                                    previous?.classification ??
                                                                    '',
                                                                classification_other:
                                                                    previous?.classification_other ??
                                                                    '',
                                                                classification_other_specify:
                                                                    previous?.classification_other_specify ??
                                                                    '',
                                                            },
                                                        ];
                                                    });
                                                }}
                                            >
                                                <Plus className="size-3.5" />
                                                Add Another Sample
                                            </Button>
                                        </div>

                                        <IntakeSamplesTip />
                                    </div>

                                    <aside className="hidden self-start lg:sticky lg:top-0 lg:block">
                                        <TestsRequestSummary
                                            packages={scopedPackages}
                                            samples={sampleSelectionSummaries}
                                            catalogItems={
                                                catalogItemsForSummary
                                            }
                                            otherTests={otherTests}
                                            estimatedTotal={estimatedTotal}
                                            customerName={
                                                customer.customer_name
                                            }
                                            variant="sidebar"
                                            mode="samples"
                                        />
                                    </aside>
                                </div>

                                <div className="lg:hidden">
                                    <button
                                        type="button"
                                        className="flex w-full items-center justify-between rounded-lg border border-nppc-900/20 bg-nppc-50 px-3 py-2 text-xs font-medium text-nppc-900"
                                        aria-expanded={mobileSummaryOpen}
                                        onClick={() =>
                                            setMobileSummaryOpen(
                                                (open) => !open,
                                            )
                                        }
                                    >
                                        <span>
                                            {samples.length} sample
                                            {samples.length === 1 ? '' : 's'} ·
                                            ₱{estimatedTotal.toFixed(2)}
                                        </span>
                                        <span>
                                            {mobileSummaryOpen
                                                ? 'Hide'
                                                : 'View summary'}
                                        </span>
                                    </button>
                                    {mobileSummaryOpen ? (
                                        <div className="mt-1.5">
                                            <TestsRequestSummary
                                                packages={scopedPackages}
                                                samples={
                                                    sampleSelectionSummaries
                                                }
                                                catalogItems={
                                                    catalogItemsForSummary
                                                }
                                                otherTests={otherTests}
                                                estimatedTotal={estimatedTotal}
                                                customerName={
                                                    customer.customer_name
                                                }
                                                variant="inline"
                                                mode="samples"
                                            />
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        )}

                        {step === 1 && (
                            <div className="flex w-full flex-col gap-2 pb-0.5">
                                <IntakeStepHeader
                                    title="Request Details"
                                    badge="Step 2 of 5 · Job specs & sampling site"
                                    hint="Specify source, collection timeline, and billing terms. Classification is chosen on each sample."
                                    className="shrink-0 [&_h2]:text-lg sm:[&_h2]:text-xl [&_p]:mt-0.5"
                                />

                                <div className="grid gap-2 lg:grid-cols-[minmax(0,1.55fr)_minmax(260px,0.78fr)] lg:items-start">
                                    <div className="min-w-0 space-y-2.5">
                                        <IntakePanel
                                            title="Sampling Details"
                                            className="[&_[data-intake-panel-body]]:p-2.5 sm:[&_[data-intake-panel-body]]:p-3 [&_[data-intake-panel-head]]:px-3 [&_[data-intake-panel-head]]:py-1.5 sm:[&_[data-intake-panel-head]]:px-3.5"
                                        >
                                            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 md:gap-x-3 md:gap-y-2">
                                                <IntakeField
                                                    label="Sampling Date"
                                                    htmlFor="sampling_date"
                                                    icon={Calendar}
                                                >
                                                    <Input
                                                        id="sampling_date"
                                                        type="date"
                                                        className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
                                                        value={samplingDate}
                                                        onChange={(e) =>
                                                            setSamplingDate(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </IntakeField>
                                                <IntakeField
                                                    label="Sampling Time"
                                                    htmlFor="sampling_time"
                                                    icon={Clock}
                                                >
                                                    <Input
                                                        id="sampling_time"
                                                        type="time"
                                                        className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
                                                        value={samplingTime}
                                                        onChange={(e) =>
                                                            setSamplingTime(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </IntakeField>
                                                <IntakeField
                                                    label="Sample Collected By"
                                                    htmlFor="sample_collected_by"
                                                    icon={UserRound}
                                                    className="md:col-span-2"
                                                >
                                                    <Input
                                                        id="sample_collected_by"
                                                        className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
                                                        placeholder="Collector name"
                                                        value={
                                                            sampleCollectedBy
                                                        }
                                                        onChange={(e) =>
                                                            setSampleCollectedBy(
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
                                                        hint="(food / proximate sheets)"
                                                        className="md:col-span-2"
                                                    >
                                                        <Input
                                                            id="specimen"
                                                            className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
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
                                                ) : null}
                                                <IntakeField
                                                    label="Sampling Site"
                                                    htmlFor="sampling_site"
                                                    icon={MapPin}
                                                    error={
                                                        errors?.sampling_site
                                                    }
                                                >
                                                    <Input
                                                        id="sampling_site"
                                                        className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
                                                        placeholder="e.g. Plant gate / Well location"
                                                        value={samplingSite}
                                                        onChange={(e) =>
                                                            setSamplingSite(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </IntakeField>
                                                <IntakeField
                                                    label="Sample Storage Temp."
                                                    htmlFor="sample_storage_temp"
                                                    hint="(as received)"
                                                    icon={Thermometer}
                                                >
                                                    <Input
                                                        id="sample_storage_temp"
                                                        className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
                                                        placeholder="e.g. Ambient / 4°C"
                                                        value={
                                                            sampleStorageTemp
                                                        }
                                                        onChange={(e) =>
                                                            setSampleStorageTemp(
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </IntakeField>
                                                {!hidePotabilityFields && (
                                                    <div className="md:col-span-2">
                                                        {potabilityOpen ? (
                                                            <div className="space-y-2 rounded-lg border border-slate-100 bg-slate-50/60 p-2.5">
                                                                <div className="flex items-center justify-between gap-2">
                                                                    <Label>
                                                                        Field
                                                                        data
                                                                        (Potability)
                                                                    </Label>
                                                                    <button
                                                                        type="button"
                                                                        className="text-xs font-medium text-nppc-700 hover:underline"
                                                                        onClick={() =>
                                                                            setPotabilityOpen(
                                                                                false,
                                                                            )
                                                                        }
                                                                    >
                                                                        Hide
                                                                    </button>
                                                                </div>
                                                                <label className="flex min-h-8 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs">
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
                                                                        Water in
                                                                        sterile
                                                                        bottle
                                                                    </span>
                                                                </label>
                                                                <Textarea
                                                                    id="field_data"
                                                                    rows={2}
                                                                    className="min-h-0 bg-white py-1.5 text-sm"
                                                                    placeholder="Other potability notes or site observations (optional)"
                                                                    value={
                                                                        fieldData
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setFieldData(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        ) : (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                className="h-9 w-full border-dashed border-nppc-900/40 text-xs text-nppc-900 hover:bg-nppc-50 sm:w-auto"
                                                                onClick={() =>
                                                                    setPotabilityOpen(
                                                                        true,
                                                                    )
                                                                }
                                                            >
                                                                <Plus className="size-3.5" />
                                                                {hasPotabilityData
                                                                    ? 'Edit Potability Field Data'
                                                                    : 'Add Potability Field Data'}
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </IntakePanel>
                                    </div>

                                    <aside className="space-y-2 lg:sticky lg:top-0">
                                        <IntakePanel
                                            title="Sample Source"
                                            className="[&_[data-intake-panel-body]]:p-2.5 sm:[&_[data-intake-panel-body]]:p-3 [&_[data-intake-panel-head]]:px-3 [&_[data-intake-panel-head]]:py-1.5"
                                        >
                                            <div
                                                className="flex flex-wrap gap-1.5"
                                                role="group"
                                                aria-label="Sample source"
                                            >
                                                {(usesAquaSources
                                                    ? options.aqua_sources
                                                    : options.wastewater_sources
                                                ).map((item) => (
                                                    <ChoiceChip
                                                        key={item}
                                                        active={
                                                            wastewaterSource ===
                                                            item
                                                        }
                                                        icon={
                                                            SOURCE_ICONS[
                                                                item
                                                            ] ?? Droplets
                                                        }
                                                        onClick={() => {
                                                            setWastewaterSource(
                                                                wastewaterSource ===
                                                                    item
                                                                    ? ''
                                                                    : item,
                                                            );
                                                            if (
                                                                item !==
                                                                'Others'
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
                                                    className="mt-2 h-9 text-sm"
                                                    placeholder="Please specify sample source"
                                                    value={
                                                        wastewaterSourceOther
                                                    }
                                                    onChange={(e) =>
                                                        setWastewaterSourceOther(
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </IntakePanel>

                                        <IntakePanel
                                            title="Payment Method"
                                            className="[&_[data-intake-panel-body]]:p-2.5 sm:[&_[data-intake-panel-body]]:p-3 [&_[data-intake-panel-head]]:px-3 [&_[data-intake-panel-head]]:py-1.5"
                                        >
                                            <div className="flex flex-col gap-1.5">
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
                                                            hint={option.hint}
                                                            badge={option.badge}
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
                                            {errors?.payment_mode ? (
                                                <IntakeAlert
                                                    variant="error"
                                                    className="mt-2"
                                                    data-intake-error=""
                                                >
                                                    {errors.payment_mode}
                                                </IntakeAlert>
                                            ) : null}
                                            {paymentMode ===
                                                'billing_partial' && (
                                                <div className="mt-2 space-y-1.5 border-t border-slate-200 pt-2">
                                                    <Label className="text-[11px] font-semibold tracking-wide text-slate-600">
                                                        Payment terms{' '}
                                                        <span className="text-rose-600">
                                                            *
                                                        </span>
                                                    </Label>
                                                    <div className="grid grid-cols-2 gap-1.5">
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
                                                    {errors?.payment_terms ? (
                                                        <IntakeAlert
                                                            variant="error"
                                                            data-intake-error=""
                                                        >
                                                            {
                                                                errors.payment_terms
                                                            }
                                                        </IntakeAlert>
                                                    ) : null}
                                                </div>
                                            )}
                                        </IntakePanel>

                                        <IntakeAlert
                                            variant="info"
                                            className="px-2.5 py-2 text-xs"
                                        >
                                            All microbiology samples must reach
                                            the analytical holding chamber
                                            within{' '}
                                            <strong>
                                                24 hours of collection
                                            </strong>{' '}
                                            under verified cold chain conditions
                                            (&lt; 10°C).
                                        </IntakeAlert>
                                    </aside>
                                </div>
                            </div>
                        )}

                        {step === 3 && (
                            <div className="flex w-full flex-col gap-2 pb-0.5">
                                <IntakeStepHeader
                                    title="Select Tests"
                                    badge="Step 4 of 5 · Test parameters"
                                    hint={
                                        samples.length > 1
                                            ? 'Each sample is billed separately · …A / …B on forms.'
                                            : 'Select packages or individual analyses for this sample.'
                                    }
                                    action={
                                        <span className="text-nppc-800 inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-[11px] font-medium">
                                            <CircleHelp
                                                className="size-3.5 text-nppc-700"
                                                aria-hidden
                                            />
                                            Turnaround: 5–7 business days
                                        </span>
                                    }
                                    className="shrink-0 [&_h2]:text-lg sm:[&_h2]:text-xl [&_p]:mt-0.5"
                                />

                                <div className="grid gap-2 lg:grid-cols-[minmax(0,1.55fr)_minmax(260px,0.78fr)] lg:items-start">
                                    <div className="min-w-0 space-y-2.5">
                                        {(() => {
                                            const sampleIndex =
                                                activeTestSample;
                                            const sample =
                                                samples[sampleIndex] ??
                                                samples[0];
                                            if (!sample) {
                                                return null;
                                            }

                                            const individualCount =
                                                individualSelectedCountFor(
                                                    sample,
                                                );
                                            const missingSelection =
                                                attemptedContinue &&
                                                !sampleHasSelection(
                                                    sample,
                                                    sampleIndex,
                                                );
                                            const packageCoveredIds =
                                                new Set<number>();
                                            sample.package_ids.forEach(
                                                (pkgId) => {
                                                    const pkg = packages.find(
                                                        (row) =>
                                                            row.id === pkgId,
                                                    );
                                                    pkg?.analysis_type_ids.forEach(
                                                        (id) => {
                                                            if (
                                                                sample.analysis_type_ids.includes(
                                                                    id,
                                                                )
                                                            ) {
                                                                packageCoveredIds.add(
                                                                    id,
                                                                );
                                                            }
                                                        },
                                                    );
                                                },
                                            );
                                            const sampleMeta = [
                                                resolveSampleClassification(
                                                    sample,
                                                ) || null,
                                                sample.description.trim() ||
                                                    null,
                                                sample.quantity.trim()
                                                    ? `${sample.quantity.trim()}${sample.unit.trim() ? ` ${sample.unit.trim()}` : ''}`
                                                    : null,
                                                sample.matrix.trim() || null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ');

                                            const packageList =
                                                isAquaClassification
                                                    ? scopedPackages
                                                    : suggestedPackages.length >
                                                        0
                                                      ? suggestedPackages
                                                      : scopedPackages;

                                            return (
                                                <>
                                                    {samples.length > 1 ? (
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <IntakeSampleSwitcher
                                                                samples={samples.map(
                                                                    (
                                                                        row,
                                                                        index,
                                                                    ) => ({
                                                                        label: `Sample ${index + 1}${
                                                                            row.sample_code.trim()
                                                                                ? ` (#${row.sample_code.trim()})`
                                                                                : ''
                                                                        }`,
                                                                        hasSelection:
                                                                            sampleHasSelection(
                                                                                row,
                                                                                index,
                                                                            ),
                                                                        analysisCount:
                                                                            row
                                                                                .analysis_type_ids
                                                                                .length,
                                                                    }),
                                                                )}
                                                                activeIndex={
                                                                    sampleIndex
                                                                }
                                                                onSelect={
                                                                    setActiveTestSample
                                                                }
                                                                showMissing={
                                                                    attemptedContinue
                                                                }
                                                                meta={
                                                                    sampleMeta ||
                                                                    `Sample ${sampleIndex + 1}`
                                                                }
                                                            />
                                                            {sampleIndex > 0 ? (
                                                                <button
                                                                    type="button"
                                                                    className="text-[11px] font-medium text-nppc-700 hover:underline focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none"
                                                                    onClick={() =>
                                                                        copyTestsFromSample(
                                                                            0,
                                                                            sampleIndex,
                                                                        )
                                                                    }
                                                                >
                                                                    Same as
                                                                    Sample 1
                                                                </button>
                                                            ) : null}
                                                        </div>
                                                    ) : null}

                                                    {missingSelection ? (
                                                        <IntakeAlert
                                                            variant="error"
                                                            data-intake-error=""
                                                        >
                                                            Select at least one
                                                            package or test for
                                                            this sample
                                                            {sampleIndex === 0
                                                                ? ', or describe another test below.'
                                                                : '.'}
                                                        </IntakeAlert>
                                                    ) : null}

                                                    <div
                                                        className="grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-1"
                                                        role="tablist"
                                                        aria-label="Test selection mode"
                                                    >
                                                        <button
                                                            type="button"
                                                            role="tab"
                                                            aria-selected={
                                                                testsView ===
                                                                'packages'
                                                            }
                                                            onClick={() =>
                                                                setTestsView(
                                                                    'packages',
                                                                )
                                                            }
                                                            className={cn(
                                                                'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                                                testsView ===
                                                                    'packages'
                                                                    ? 'bg-nppc-900 text-white shadow-sm'
                                                                    : 'text-nppc-800 hover:bg-white',
                                                            )}
                                                        >
                                                            <Package
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                            Test Packages
                                                        </button>
                                                        <button
                                                            type="button"
                                                            role="tab"
                                                            aria-selected={
                                                                testsView ===
                                                                'individual'
                                                            }
                                                            onClick={() =>
                                                                setTestsView(
                                                                    'individual',
                                                                )
                                                            }
                                                            className={cn(
                                                                'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                                                testsView ===
                                                                    'individual'
                                                                    ? 'bg-nppc-900 text-white shadow-sm'
                                                                    : 'text-nppc-800 hover:bg-white',
                                                            )}
                                                        >
                                                            <ListChecks
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                            Individual Tests
                                                            {individualCount > 0
                                                                ? ` · ${individualCount}`
                                                                : ''}
                                                        </button>
                                                    </div>

                                                    {testsView ===
                                                    'packages' ? (
                                                        <>
                                                            {type_presets.length >
                                                            0 ? (
                                                                <section className="space-y-1.5">
                                                                    <div className="flex items-center gap-1.5">
                                                                        <Layers
                                                                            className="size-3.5 text-nppc-700"
                                                                            aria-hidden
                                                                        />
                                                                        <h3 className="text-sm font-semibold text-nppc-900">
                                                                            Test
                                                                            Panels
                                                                        </h3>
                                                                    </div>
                                                                    {fo2NeedsKindSample ===
                                                                    sampleIndex ? (
                                                                        <IntakeAlert
                                                                            variant="error"
                                                                            data-intake-error=""
                                                                        >
                                                                            Choose
                                                                            a
                                                                            Sample
                                                                            description
                                                                            for
                                                                            this
                                                                            sample
                                                                            (Wastewater,
                                                                            River
                                                                            water,
                                                                            or
                                                                            Abstracted
                                                                            water)
                                                                            before
                                                                            selecting
                                                                            the
                                                                            Physico-Chemical
                                                                            panel.
                                                                        </IntakeAlert>
                                                                    ) : null}
                                                                    <div className="space-y-1.5">
                                                                        {(suggestedTypePresets.length >
                                                                        0
                                                                            ? suggestedTypePresets
                                                                            : type_presets
                                                                        ).map(
                                                                            (
                                                                                preset,
                                                                            ) =>
                                                                                renderTypePresetRow(
                                                                                    sampleIndex,
                                                                                    preset,
                                                                                ),
                                                                        )}
                                                                    </div>
                                                                    {suggestedTypePresets.length >
                                                                        0 &&
                                                                    otherTypePresets.length >
                                                                        0 ? (
                                                                        <details className="group">
                                                                            <summary className="cursor-pointer text-xs font-medium text-nppc-700 hover:underline [&::-webkit-details-marker]:hidden">
                                                                                ▸
                                                                                Other
                                                                                panels
                                                                                (
                                                                                {
                                                                                    otherTypePresets.length
                                                                                }

                                                                                )
                                                                            </summary>
                                                                            <div className="mt-1.5 space-y-1.5">
                                                                                {otherTypePresets.map(
                                                                                    (
                                                                                        preset,
                                                                                    ) =>
                                                                                        renderTypePresetRow(
                                                                                            sampleIndex,
                                                                                            preset,
                                                                                        ),
                                                                                )}
                                                                            </div>
                                                                        </details>
                                                                    ) : null}
                                                                </section>
                                                            ) : null}

                                                            {scopedPackages.length >
                                                            0 ? (
                                                                <section className="space-y-1.5">
                                                                    <div className="min-w-0">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <Sparkles
                                                                                className="size-3.5 text-nppc-700"
                                                                                aria-hidden
                                                                            />
                                                                            <h3 className="text-sm font-semibold text-nppc-900">
                                                                                Recommended
                                                                                Packages
                                                                            </h3>
                                                                        </div>
                                                                        <p className="mt-0.5 text-[11px] text-slate-500">
                                                                            Start
                                                                            here
                                                                            —
                                                                            expand
                                                                            a
                                                                            package
                                                                            to
                                                                            see
                                                                            included
                                                                            tests.
                                                                        </p>
                                                                    </div>
                                                                    <div className="space-y-2">
                                                                        {packageList.map(
                                                                            (
                                                                                pkg,
                                                                            ) =>
                                                                                renderPackageRow(
                                                                                    sampleIndex,
                                                                                    pkg,
                                                                                ),
                                                                        )}
                                                                    </div>
                                                                    {!isAquaClassification &&
                                                                    suggestedPackages.length >
                                                                        0 &&
                                                                    otherPackages.length >
                                                                        0 ? (
                                                                        <details className="group">
                                                                            <summary className="cursor-pointer text-xs font-medium text-nppc-700 hover:underline [&::-webkit-details-marker]:hidden">
                                                                                ▸
                                                                                Other
                                                                                packages
                                                                                (
                                                                                {
                                                                                    otherPackages.length
                                                                                }

                                                                                )
                                                                            </summary>
                                                                            <div className="mt-1.5 space-y-2">
                                                                                {otherPackages.map(
                                                                                    (
                                                                                        pkg,
                                                                                    ) =>
                                                                                        renderPackageRow(
                                                                                            sampleIndex,
                                                                                            pkg,
                                                                                        ),
                                                                                )}
                                                                            </div>
                                                                        </details>
                                                                    ) : null}
                                                                </section>
                                                            ) : null}
                                                        </>
                                                    ) : (
                                                        <section className="space-y-1.5">
                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-1.5">
                                                                    <ListChecks
                                                                        className="size-3.5 text-nppc-700"
                                                                        aria-hidden
                                                                    />
                                                                    <h3 className="text-sm font-semibold text-nppc-900">
                                                                        Individual
                                                                        Tests
                                                                        {individualCount >
                                                                        0
                                                                            ? ` · ${individualCount} selected`
                                                                            : ''}
                                                                    </h3>
                                                                </div>
                                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                                    Browse the
                                                                    catalog for
                                                                    analyses not
                                                                    covered by a
                                                                    package.
                                                                </p>
                                                            </div>
                                                            <IntakeIndividualTestCatalog
                                                                categories={
                                                                    scopedCategories
                                                                }
                                                                query={
                                                                    testsQuery
                                                                }
                                                                onQueryChange={
                                                                    setTestsQuery
                                                                }
                                                                activeCategory={
                                                                    activeCategory
                                                                }
                                                                onActiveCategoryChange={
                                                                    setActiveCategory
                                                                }
                                                                selectedTypeIds={
                                                                    new Set(
                                                                        sample.analysis_type_ids,
                                                                    )
                                                                }
                                                                packageCoveredIds={
                                                                    packageCoveredIds
                                                                }
                                                                onToggleType={(
                                                                    id,
                                                                ) =>
                                                                    toggleType(
                                                                        sampleIndex,
                                                                        id,
                                                                    )
                                                                }
                                                            />
                                                        </section>
                                                    )}
                                                </>
                                            );
                                        })()}

                                        <section className="space-y-1.5 border-t border-slate-100 pt-2">
                                            <div className="flex items-center gap-1.5">
                                                <PenLine
                                                    className="size-3.5 text-nppc-700"
                                                    aria-hidden
                                                />
                                                <h3 className="text-sm font-semibold text-nppc-900">
                                                    Other / Special Test Request
                                                </h3>
                                            </div>
                                            <Textarea
                                                id="other_tests"
                                                rows={2}
                                                className="min-h-[2.75rem] w-full rounded-lg border-slate-200 bg-white py-1.5 text-sm"
                                                value={otherTests}
                                                onChange={(e) =>
                                                    setOtherTests(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Describe an analysis that is not listed…"
                                            />
                                        </section>
                                    </div>

                                    <aside className="hidden self-start lg:sticky lg:top-0 lg:block">
                                        <TestsRequestSummary
                                            packages={scopedPackages}
                                            samples={sampleSelectionSummaries}
                                            catalogItems={
                                                catalogItemsForSummary
                                            }
                                            otherTests={otherTests}
                                            estimatedTotal={estimatedTotal}
                                            customerName={
                                                customer.customer_name
                                            }
                                            variant="sidebar"
                                        />
                                    </aside>
                                </div>

                                <div className="lg:hidden">
                                    <button
                                        type="button"
                                        className="flex w-full items-center justify-between rounded-lg border border-nppc-900/20 bg-nppc-50 px-3 py-2 text-xs font-medium text-nppc-900"
                                        aria-expanded={mobileSummaryOpen}
                                        onClick={() =>
                                            setMobileSummaryOpen(
                                                (open) => !open,
                                            )
                                        }
                                    >
                                        <span>
                                            {totalSelectedAnalyses} analyses · ₱
                                            {estimatedTotal.toFixed(2)}
                                        </span>
                                        <span>
                                            {mobileSummaryOpen
                                                ? 'Hide'
                                                : 'View selected'}
                                        </span>
                                    </button>
                                    {mobileSummaryOpen ? (
                                        <div className="mt-1.5">
                                            <TestsRequestSummary
                                                packages={scopedPackages}
                                                samples={
                                                    sampleSelectionSummaries
                                                }
                                                catalogItems={
                                                    catalogItemsForSummary
                                                }
                                                otherTests={otherTests}
                                                estimatedTotal={estimatedTotal}
                                                customerName={
                                                    customer.customer_name
                                                }
                                                variant="inline"
                                            />
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        )}

                        {step === 4 && (
                            <div className="flex w-full flex-col gap-2 pb-0.5">
                                <IntakeStepHeader
                                    title="Review Request"
                                    badge="Step 5 of 5 · Final verification"
                                    hint="Confirm requester, sampling, and selected analyses before submission."
                                    className="shrink-0 [&_h2]:text-lg sm:[&_h2]:text-xl [&_p]:mt-0.5"
                                />

                                <div className="grid gap-2 lg:grid-cols-[minmax(0,1.55fr)_minmax(260px,0.78fr)] lg:items-start">
                                    <div className="min-w-0 space-y-2">
                                        <ReviewCard
                                            title="Requester Information"
                                            icon={UserRound}
                                            onEdit={() => setStep(0)}
                                        >
                                            <div className="grid gap-x-3 gap-y-2 sm:grid-cols-2 xl:grid-cols-5">
                                                <ReviewRow
                                                    label="Customer"
                                                    icon={UserRound}
                                                    value={
                                                        customer.customer_name
                                                    }
                                                />
                                                <ReviewRow
                                                    label="Contact"
                                                    icon={Phone}
                                                    value={
                                                        customer.customer_contact
                                                    }
                                                />
                                                <ReviewRow
                                                    label="Email"
                                                    icon={Mail}
                                                    value={
                                                        customer.customer_email
                                                    }
                                                />
                                                <ReviewRow
                                                    label="Ownership"
                                                    icon={Tag}
                                                    value={ownershipType}
                                                />
                                                <ReviewRow
                                                    label="Address"
                                                    icon={MapPin}
                                                    value={
                                                        customer.customer_address
                                                    }
                                                    className="sm:col-span-2 xl:col-span-1"
                                                />
                                                <ReviewRow
                                                    label="Company"
                                                    icon={Building2}
                                                    value={
                                                        customer.company_name
                                                    }
                                                    className="sm:col-span-2 xl:col-span-5"
                                                />
                                            </div>
                                        </ReviewCard>

                                        {isLaboratoryServices ? (
                                            <ReviewCard
                                                title="Laboratory services"
                                                icon={FlaskConical}
                                                onEdit={() => setStep(0)}
                                            >
                                                <p className="text-sm text-slate-600">
                                                    No samples or tests at
                                                    intake. Receiving will add
                                                    the specific laboratory
                                                    services and prices on the
                                                    Job Order.
                                                </p>
                                            </ReviewCard>
                                        ) : (
                                        <ReviewCard
                                            title="Job & Sampling Specifications"
                                            icon={FlaskConical}
                                            onEdit={() => setStep(1)}
                                        >
                                            <div className="grid gap-x-3 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
                                                <ReviewRow
                                                    label="Classification"
                                                    value={joinedClassification}
                                                />
                                                <ReviewRow
                                                    label="Sampling Site"
                                                    value={samplingSite}
                                                />
                                                <ReviewRow
                                                    label="Sampling"
                                                    value={[
                                                        samplingDate,
                                                        samplingTime,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                />
                                                <ReviewRow
                                                    label="Sample Source"
                                                    value={resolvedSampleSource}
                                                />
                                                <ReviewRow
                                                    label="Collected By"
                                                    value={sampleCollectedBy}
                                                />
                                                <ReviewRow
                                                    label="Storage Temp"
                                                    value={sampleStorageTemp}
                                                />
                                                {needsSpecimen ? (
                                                    <div className="sm:col-span-2 xl:col-span-3">
                                                        <IntakeField
                                                            label="Specimen"
                                                            htmlFor="specimen_review"
                                                            error={
                                                                errors?.specimen
                                                            }
                                                        >
                                                            <Input
                                                                id="specimen_review"
                                                                className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm"
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
                                                                            (
                                                                                o,
                                                                            ) =>
                                                                                o.value ===
                                                                                paymentTerms,
                                                                        )?.label
                                                                      : null,
                                                              ]
                                                                  .filter(
                                                                      Boolean,
                                                                  )
                                                                  .join(' · ')
                                                            : ''
                                                    }
                                                />
                                            </div>
                                        </ReviewCard>
                                        )}

                                        {!isLaboratoryServices ? (
                                        <ReviewCard
                                            title="Registered Samples & Tests"
                                            icon={TestTube2}
                                            onEdit={() => setStep(2)}
                                        >
                                            <ul className="space-y-2">
                                                {samples.map(
                                                    (sample, index) => {
                                                        const summary =
                                                            sampleSelectionSummaries[
                                                                index
                                                            ];
                                                        const covered =
                                                            new Set<number>();
                                                        const packageNames: string[] =
                                                            [];
                                                        summary?.package_ids.forEach(
                                                            (pkgId) => {
                                                                const pkg =
                                                                    packages.find(
                                                                        (row) =>
                                                                            row.id ===
                                                                            pkgId,
                                                                    );
                                                                if (pkg?.name) {
                                                                    packageNames.push(
                                                                        pkg.name,
                                                                    );
                                                                }
                                                                pkg?.analysis_type_ids.forEach(
                                                                    (id) => {
                                                                        if (
                                                                            summary.analysis_type_ids.includes(
                                                                                id,
                                                                            )
                                                                        ) {
                                                                            covered.add(
                                                                                id,
                                                                            );
                                                                        }
                                                                    },
                                                                );
                                                            },
                                                        );
                                                        const paramLines =
                                                            summary?.analysis_type_ids.map(
                                                                (id) => {
                                                                    const meta =
                                                                        typePriceMap.get(
                                                                            id,
                                                                        );
                                                                    return {
                                                                        id,
                                                                        name:
                                                                            meta?.name ??
                                                                            `Test #${id}`,
                                                                        price:
                                                                            meta?.price ??
                                                                            '0',
                                                                        included:
                                                                            covered.has(
                                                                                id,
                                                                            ),
                                                                    };
                                                                },
                                                            ) ?? [];
                                                        const sampleTitle =
                                                            packageNames[0] ||
                                                            sample.description.trim() ||
                                                            sample.matrix.trim() ||
                                                            null;

                                                        return (
                                                            <li
                                                                key={index}
                                                                className="overflow-hidden rounded-lg border border-slate-200"
                                                            >
                                                                <details
                                                                    open={
                                                                        samples.length ===
                                                                        1
                                                                    }
                                                                    className="group"
                                                                >
                                                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-2 bg-sky-50/80 px-2.5 py-2 [&::-webkit-details-marker]:hidden">
                                                                        <div className="min-w-0">
                                                                            <p className="truncate text-sm font-semibold text-nppc-900">
                                                                                Sample{' '}
                                                                                {index +
                                                                                    1}
                                                                                {sampleTitle
                                                                                    ? `: ${sampleTitle}`
                                                                                    : ''}
                                                                            </p>
                                                                            <p className="mt-0.5 truncate text-[11px] text-slate-500">
                                                                                {[
                                                                                    resolveSampleClassification(
                                                                                        sample,
                                                                                    ) ||
                                                                                        null,
                                                                                    sample.sample_code.trim()
                                                                                        ? `#${sample.sample_code.trim()}`
                                                                                        : null,
                                                                                    sample.matrix ||
                                                                                        null,
                                                                                    sample.quantity
                                                                                        ? `${sample.quantity}${sample.unit ? ` ${sample.unit}` : ''}`
                                                                                        : null,
                                                                                ]
                                                                                    .filter(
                                                                                        Boolean,
                                                                                    )
                                                                                    .join(
                                                                                        ' · ',
                                                                                    ) ||
                                                                                    'No sample meta'}
                                                                            </p>
                                                                        </div>
                                                                        <div className="flex shrink-0 items-center gap-2">
                                                                            {summary ? (
                                                                                <span className="font-heading text-sm font-bold text-nppc-900 tabular-nums">
                                                                                    ₱
                                                                                    {summary.subtotal.toFixed(
                                                                                        2,
                                                                                    )}
                                                                                </span>
                                                                            ) : null}
                                                                            <ChevronDown
                                                                                className="size-3.5 text-slate-400 transition group-open:rotate-180"
                                                                                aria-hidden
                                                                            />
                                                                        </div>
                                                                    </summary>
                                                                    <div className="space-y-2 border-t border-slate-100 bg-white p-2.5">
                                                                        {paramLines.length >
                                                                        0 ? (
                                                                            <ul className="grid gap-1 sm:grid-cols-2">
                                                                                {paramLines.map(
                                                                                    (
                                                                                        line,
                                                                                    ) => (
                                                                                        <li
                                                                                            key={
                                                                                                line.id
                                                                                            }
                                                                                            className="flex items-center justify-between gap-2 rounded border border-slate-200/80 bg-slate-50/50 px-2 py-1 text-[11px]"
                                                                                        >
                                                                                            <span className="min-w-0 truncate font-medium text-slate-800">
                                                                                                {
                                                                                                    line.name
                                                                                                }
                                                                                                {line.included ? (
                                                                                                    <span className="ml-1 font-normal text-emerald-700">
                                                                                                        (incl.)
                                                                                                    </span>
                                                                                                ) : null}
                                                                                            </span>
                                                                                            <span className="shrink-0 text-slate-600 tabular-nums">
                                                                                                ₱
                                                                                                {Number(
                                                                                                    line.price,
                                                                                                ).toFixed(
                                                                                                    2,
                                                                                                )}
                                                                                            </span>
                                                                                        </li>
                                                                                    ),
                                                                                )}
                                                                            </ul>
                                                                        ) : (
                                                                            <p className="text-[11px] text-slate-500">
                                                                                {index ===
                                                                                    0 &&
                                                                                otherTests.trim()
                                                                                    ? 'Covered by other test request'
                                                                                    : 'No analyses selected'}
                                                                            </p>
                                                                        )}
                                                                        <button
                                                                            type="button"
                                                                            className="text-[11px] font-medium text-nppc-700 hover:underline"
                                                                            onClick={() =>
                                                                                setStep(
                                                                                    3,
                                                                                )
                                                                            }
                                                                        >
                                                                            Edit
                                                                            analyses
                                                                            →
                                                                        </button>
                                                                    </div>
                                                                </details>
                                                            </li>
                                                        );
                                                    },
                                                )}
                                            </ul>
                                            {otherTests.trim() ? (
                                                <p className="mt-2 border-t border-slate-100 pt-2 text-sm text-slate-700">
                                                    <span className="font-medium">
                                                        Other:{' '}
                                                    </span>
                                                    {otherTests.trim()}
                                                </p>
                                            ) : null}
                                        </ReviewCard>
                                        ) : null}
                                    </div>

                                    <aside className="space-y-2 lg:sticky lg:top-0">
                                        {isLaboratoryServices ? (
                                            <IntakeAlert
                                                variant="info"
                                                title="Laboratory services"
                                                className="px-2.5 py-2 text-xs"
                                            >
                                                <p className="mt-1 text-slate-700">
                                                    Receiving will list and
                                                    price the services on your
                                                    Job Order. No sample tests
                                                    are selected at intake.
                                                </p>
                                            </IntakeAlert>
                                        ) : (
                                            <TestsRequestSummary
                                                packages={scopedPackages}
                                                samples={
                                                    sampleSelectionSummaries
                                                }
                                                catalogItems={
                                                    catalogItemsForSummary
                                                }
                                                otherTests={otherTests}
                                                estimatedTotal={estimatedTotal}
                                                customerName={
                                                    customer.customer_name
                                                }
                                                variant="sidebar"
                                            />
                                        )}
                                        <IntakeAlert
                                            variant="info"
                                            title="What happens next"
                                            className="px-2.5 py-2 text-xs"
                                        >
                                            <ol className="mt-1 list-decimal space-y-1 pl-4 text-slate-700">
                                                <li>
                                                    Your request is received.
                                                </li>
                                                <li>
                                                    Receiving reviews pricing
                                                    and prepares the Request for
                                                    Analysis (RFA).
                                                </li>
                                                <li>
                                                    The laboratory processes
                                                    your samples.
                                                </li>
                                                <li>
                                                    Results are released after
                                                    authorized review.
                                                </li>
                                            </ol>
                                        </IntakeAlert>
                                        <p className="rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 py-2 text-[10px] leading-snug text-slate-500">
                                            Receiving may adjust final pricing.
                                            Payment is settled at reception ·
                                            Sample integrity is verified at
                                            drop-off.
                                        </p>
                                    </aside>
                                </div>
                            </div>
                        )}
                    </div>

                    <IntakeStickyFooter
                        hint={
                            step > 1 && stepError(step) ? (
                                <IntakeAlert variant="warning">
                                    {stepError(step)}
                                </IntakeAlert>
                            ) : null
                        }
                    >
                        <div className="grid grid-cols-2 items-center gap-2 sm:grid-cols-[1fr_auto_1fr] sm:gap-3">
                            <div className="justify-self-start">
                                {step === 0 ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9 gap-1.5 border-slate-200 px-4 text-sm"
                                        onClick={requestExit}
                                    >
                                        <ArrowLeft
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                        Back to Dashboard
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9 gap-1.5 border-slate-200 px-4 text-sm"
                                        disabled={submitting}
                                        onClick={goBack}
                                    >
                                        <ArrowLeft
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                        {isLaboratoryServices && step === 4
                                            ? 'Back to Customer'
                                            : step === 1
                                              ? 'Back to Customer'
                                              : step === 2
                                                ? 'Back to Details'
                                                : step === 3
                                                  ? 'Back to Samples'
                                                  : 'Back to Tests'}
                                    </Button>
                                )}
                            </div>
                            <p className="order-first col-span-2 text-center text-xs text-slate-500 sm:order-none sm:col-span-1 sm:whitespace-nowrap">
                                {isLaboratoryServices
                                    ? step === 0
                                        ? 'Laboratory services · Customer details'
                                        : 'Laboratory services · Review and confirm'
                                    : step === 0 && !stepError(step)
                                      ? 'Step 1 of 5 · Enter contact details'
                                      : step === 0
                                        ? 'Step 1 of 5'
                                        : step === 1 && !stepError(step)
                                          ? 'Step 2 of 5 · Complete sampling specs to continue'
                                          : step === 1
                                            ? 'Step 2 of 5'
                                            : step === 2
                                              ? `${samples.length} sample${samples.length === 1 ? '' : 's'} listed`
                                              : step === 3
                                                ? 'Step 4 of 5 · Select tests and packages'
                                                : 'Step 5 of 5 · Review and confirm'}
                            </p>
                            <div className="justify-self-end">
                                {step < steps.length - 1 ? (
                                    <Button
                                        type="button"
                                        className="h-9 gap-2 bg-[#1d3b91] px-5 text-sm font-semibold hover:bg-[#112747] sm:min-w-40"
                                        onClick={goNext}
                                    >
                                        {step === 0 && isLaboratoryServices
                                            ? 'Continue to Review'
                                            : step === 0
                                              ? 'Continue to Step 2: Details'
                                              : step === 1
                                                ? 'Continue to Samples'
                                                : step === 2
                                                  ? 'Continue to Test Selection'
                                                  : 'Continue to Review'}
                                        <ArrowRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        className="h-9 gap-2 bg-[#1d3b91] px-5 text-sm font-semibold hover:bg-[#112747] sm:min-w-40"
                                        disabled={submitting}
                                        onClick={submit}
                                    >
                                        {submitting
                                            ? 'Submitting…'
                                            : isLaboratoryServices
                                              ? 'Submit Laboratory Services Request'
                                              : 'Submit Request for Analysis'}
                                        {!submitting ? (
                                            <ArrowRight className="size-4" />
                                        ) : null}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </IntakeStickyFooter>
                </div>
            </IntakePageShell>

            <ConfirmDialog
                open={exitConfirmOpen}
                onOpenChange={setExitConfirmOpen}
                title="Exit this request?"
                description="Your unfinished intake details will not be saved. You can start a new request from the intake portal."
                confirmLabel="Exit without saving"
                cancelLabel="Keep editing"
                variant="destructive"
                onConfirm={() => {
                    setExitConfirmOpen(false);
                    router.visit('/intake');
                }}
            />

            <ConfirmDialog
                open={submitConfirmOpen}
                onOpenChange={setSubmitConfirmOpen}
                title="Submit request for analysis?"
                description="Please confirm that requester, sampling, and selected tests are correct. Receiving may still adjust final pricing."
                confirmLabel="Submit request"
                cancelLabel="Review again"
                processing={submitting}
                processingLabel="Submitting…"
                onConfirm={confirmSubmit}
            />
        </>
    );
}

IntakeWizard.layout = null;
