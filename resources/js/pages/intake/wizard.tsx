import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CircleHelp,
    Droplets,
    Fish,
    FlaskConical,
    GraduationCap,
    MoreHorizontal,
    Plus,
    Sprout,
    Waves,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import FormSection from '@/components/intake/form-section';
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
import IntakeStepper from '@/components/intake/intake-stepper';
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

const CLASSIFICATION_BLURBS: Record<string, string> = {
    Aqua: 'Aquaculture water and related samples',
    Potability: 'Drinking-water analysis',
    Wastewater: 'Wastewater analysis',
    Agriculture: 'Agricultural samples',
    'Academic/Research': 'Research-related requests',
    Others: 'Other laboratory requests',
};

const CLASSIFICATION_ICONS: Record<string, LucideIcon> = {
    Aqua: Fish,
    Potability: Droplets,
    Wastewater: Waves,
    Agriculture: Sprout,
    'Academic/Research': GraduationCap,
    Others: MoreHorizontal,
};

type SampleDraft = {
    sample_code: string;
    description: string;
    matrix: string;
    quantity: string;
    unit: string;
    remarks: string;
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
    package_ids: [],
    analysis_type_ids: [],
    preset_keys: [],
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
                'min-h-9 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition',
                active
                    ? 'border-nppc-900 bg-nppc-900 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500',
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
            className="h-8 border-nppc-900/25 text-nppc-900 hover:bg-nppc-50"
            onClick={onClick}
        >
            Edit
        </Button>
    );
}

function ReviewCard({
    title,
    onEdit,
    children,
    first = false,
}: {
    title: string;
    onEdit: () => void;
    children: ReactNode;
    first?: boolean;
}) {
    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200 bg-white p-4 shadow-xs',
                !first && 'mt-0',
            )}
        >
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                <h3 className="font-heading text-sm font-semibold text-nppc-900">
                    {title}
                </h3>
                <ReviewEditButton onClick={onEdit} />
            </div>
            {children}
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
                'flex min-h-[52px] cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-xs transition',
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
                className="size-4 shrink-0 accent-nppc-900"
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
                const subtotal = sample.analysis_type_ids.reduce(
                    (sum, id) =>
                        sum + Number(typePriceMap.get(id)?.price || 0),
                    0,
                );

                return {
                    label:
                        sample.sample_code.trim() || `Sample ${index + 1}`,
                    package_ids: sample.package_ids,
                    analysis_type_ids: sample.analysis_type_ids,
                    subtotal,
                    names: sample.analysis_type_ids
                        .map((id) => typePriceMap.get(id)?.name)
                        .filter(Boolean) as string[],
                    packageNames: sample.package_ids
                        .map(
                            (id) =>
                                packages.find((pkg) => pkg.id === id)?.name,
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
    }, [
        categories,
        classification,
        classificationOther,
        packages,
        samples,
    ]);

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

        const allowedPackageIds = new Set(
            scopedPackages.map((pkg) => pkg.id),
        );
        const allowedPresetKeys = new Set(
            type_presets.map((preset) => preset.key),
        );

        setSamples((current) => {
            let changed = false;
            const next = current.map((sample) => {
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
    }, [
        isAquaClassification,
        scopedCategories,
        scopedPackages,
        type_presets,
    ]);

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

        return sample.analysis_type_ids.filter((id) => !covered.has(id))
            .length;
    }

    /** Open the catalog automatically when returning with catalog-only picks. */
    const browseAutoOpenRef = useRef(false);
    useEffect(() => {
        if (step !== 3 || browseAutoOpenRef.current) {
            return;
        }

        const toOpen: Record<number, boolean> = {};
        let any = false;
        samples.forEach((sample, index) => {
            if (individualSelectedCountFor(sample) > 0) {
                toOpen[index] = true;
                any = true;
            }
        });

        if (any) {
            browseAutoOpenRef.current = true;
            setBrowseOpenBySample((current) => ({ ...current, ...toOpen }));
        }
    }, [step, samples, scopedPackages, type_presets]);

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
                        ...new Set([
                            ...sample.analysis_type_ids,
                            ...memberIds,
                        ]),
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

    function toggleTypePreset(sampleIndex: number, preset: IntakeTypePreset) {
        setActiveTestSample(sampleIndex);
        setSamples((current) =>
            current.map((sample, i) => {
                if (i !== sampleIndex) {
                    return sample;
                }

                const memberIds = preset.analysis_type_ids;
                const selected = sample.preset_keys.includes(preset.key);

                if (selected) {
                    const keep = keptTypeIdsExcluding(sample, memberIds, {
                        exceptPresetKey: preset.key,
                    });

                    return {
                        ...sample,
                        preset_keys: sample.preset_keys.filter(
                            (key) => key !== preset.key,
                        ),
                        analysis_type_ids: sample.analysis_type_ids.filter(
                            (id) => !memberIds.includes(id) || keep.has(id),
                        ),
                    };
                }

                return {
                    ...sample,
                    preset_keys: [...sample.preset_keys, preset.key],
                    analysis_type_ids: [
                        ...new Set([
                            ...sample.analysis_type_ids,
                            ...memberIds,
                        ]),
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

            const memberIds = preset.analysis_type_ids;
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
                estimatedPrice={presetEstimatedPrice(preset)}
                onTogglePreset={() => toggleTypePreset(sampleIndex, preset)}
                onToggleMember={(typeId) =>
                    toggleTypePresetMember(sampleIndex, preset, typeId)
                }
            />
        );
    }

    function updateSample(
        index: number,
        key: SampleFieldKey,
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
                    scope.querySelector<HTMLElement>(
                        '[aria-invalid="true"]',
                    ) ??
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
                onFinish: () => setSubmitting(false),
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
                    onStepSelect: setStep,
                }}
            >
            <IntakeStepper
                steps={steps}
                step={step}
                onStepSelect={setStep}
            />

            <div
                key={step}
                data-intake-wizard
                className="intake-enter min-w-0 space-y-3"
            >
                    {errors?.analysis_type_ids ? (
                        <IntakeAlert
                            variant="error"
                            className="mb-1"
                            data-intake-error=""
                        >
                            {errors.analysis_type_ids}
                        </IntakeAlert>
                    ) : null}

                    {step === 0 && (
                        <div className="w-full space-y-4 pb-4">
                            <IntakeStepHeader
                                title="Customer"
                                badge="Step 1 of 5 · Contact"
                                hint="Enter your contact information."
                            />

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.75fr)] lg:items-start">
                                <IntakePanel title="Contact details">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
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
                                                className={cn(
                                                    'h-10 w-full border-slate-200 bg-white text-sm',
                                                    !!stepError(0) &&
                                                        !customer.customer_name.trim() &&
                                                        'border-rose-300 focus-visible:border-rose-300 focus-visible:ring-rose-200',
                                                )}
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
                                                className={cn(
                                                    'h-10 w-full border-slate-200 bg-white text-sm',
                                                    !!stepError(0) &&
                                                        !customer.customer_email.trim() &&
                                                        'border-rose-300 focus-visible:border-rose-300 focus-visible:ring-rose-200',
                                                )}
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
                                            hint="(optional)"
                                        >
                                            <Input
                                                id="customer_contact"
                                                type="tel"
                                                autoComplete="tel"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                            hint="(optional)"
                                        >
                                            <Input
                                                id="company_name"
                                                type="text"
                                                autoComplete="organization"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                        <IntakeField
                                            label="Address"
                                            htmlFor="customer_address"
                                            hint="(optional)"
                                            className="md:col-span-2"
                                        >
                                            <Textarea
                                                id="customer_address"
                                                rows={3}
                                                className="min-h-[5rem] w-full border-slate-200 bg-white py-2 text-sm"
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
                                        <div className="md:col-span-2">
                                            <p className="mb-1.5 text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                                Ownership{' '}
                                                <span className="font-normal normal-case tracking-normal">
                                                    (optional)
                                                </span>
                                            </p>
                                            <div
                                                className="flex flex-wrap gap-2"
                                                role="group"
                                                aria-label="Type of ownership"
                                            >
                                                {options.ownership.map(
                                                    (item) => (
                                                        <ChoiceChip
                                                            key={item}
                                                            active={
                                                                ownershipType ===
                                                                item
                                                            }
                                                            onClick={() =>
                                                                setOwnershipType(
                                                                    ownershipType ===
                                                                        item
                                                                        ? ''
                                                                        : item,
                                                                )
                                                            }
                                                        >
                                                            {item}
                                                        </ChoiceChip>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </IntakePanel>

                                <IntakePanel title="Customer summary">
                                    <dl className="space-y-2.5">
                                        {(
                                            [
                                                [
                                                    'Customer',
                                                    customer.customer_name.trim() ||
                                                        'Not provided',
                                                ],
                                                [
                                                    'Email',
                                                    customer.customer_email.trim() ||
                                                        'Not provided',
                                                ],
                                                [
                                                    'Contact',
                                                    customer.customer_contact.trim() ||
                                                        'Not provided',
                                                ],
                                                [
                                                    'Company',
                                                    customer.company_name.trim() ||
                                                        'Not provided',
                                                ],
                                                [
                                                    'Ownership',
                                                    ownershipType.trim() ||
                                                        'Not provided',
                                                ],
                                            ] as const
                                        ).map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="min-w-0"
                                            >
                                                <dt className="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                                    {label}
                                                </dt>
                                                <dd className="truncate text-sm leading-snug text-slate-800">
                                                    {value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </IntakePanel>
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="w-full space-y-3">
                            <IntakeStepHeader
                                title="Samples"
                                badge="Step 3 of 5 · Specimens"
                                hint={
                                    samples.length > 1
                                        ? `${samples.length} samples in this request. Same classification only — other types need a new request.`
                                        : 'Add the samples you are submitting today for laboratory analysis.'
                                }
                                action={
                                    <span className="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-[11px] font-medium text-nppc-800">
                                        <CircleHelp
                                            className="size-3.5 text-nppc-700"
                                            aria-hidden
                                        />
                                        Turnaround: 5–7 business days
                                    </span>
                                }
                            />

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.9fr)] lg:items-start">
                                <div className="min-w-0 space-y-3">
                                    <div className="space-y-3 rounded-xl bg-slate-50/80 p-3 sm:p-4">
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
                                                removable={samples.length > 1}
                                                onToggleExpand={() =>
                                                    setExpandedSample(
                                                        (current) =>
                                                            current === index
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
                                                    });
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
                                                                current > index
                                                            ) {
                                                                return (
                                                                    current - 1
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
                                                                current === null
                                                            ) {
                                                                return null;
                                                            }

                                                            return current >
                                                                index
                                                                ? current - 1
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
                                            className="h-9 gap-1.5 bg-nppc-50 px-3.5 text-xs font-semibold text-nppc-900 hover:bg-nppc-100"
                                            onClick={() => {
                                                nextSampleFocusRef.current =
                                                    samples.length;
                                                setExpandedSample(
                                                    samples.length,
                                                );
                                                setSamples((current) => [
                                                    ...current,
                                                    emptySample(),
                                                ]);
                                            }}
                                        >
                                            <Plus className="size-3.5" />
                                            Add another sample
                                        </Button>
                                    </div>

                                    <IntakeSamplesTip />
                                </div>

                                <aside className="sticky top-2 hidden self-start lg:block">
                                    <TestsRequestSummary
                                        packages={scopedPackages}
                                        samples={sampleSelectionSummaries}
                                        catalogItems={catalogItemsForSummary}
                                        otherTests={otherTests}
                                        estimatedTotal={estimatedTotal}
                                        customerName={customer.customer_name}
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
                                        setMobileSummaryOpen((open) => !open)
                                    }
                                >
                                    <span>
                                        {samples.length} sample
                                        {samples.length === 1 ? '' : 's'} · ₱
                                        {estimatedTotal.toFixed(2)}
                                    </span>
                                    <span>
                                        {mobileSummaryOpen
                                            ? 'Hide'
                                            : 'View summary'}
                                    </span>
                                </button>
                                {mobileSummaryOpen ? (
                                    <div className="mt-1.5 rounded-lg border border-slate-200 bg-white p-3">
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
                                            variant="inline"
                                            mode="samples"
                                        />
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="w-full space-y-4 pb-4">
                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(360px,1fr)] lg:items-start">
                                <div className="min-w-0 space-y-3">
                                    <IntakeStepHeader
                                        title="Request details"
                                        badge="Step 2 of 5 · Job specs & sampling site"
                                        hint="Specify sample classification, source, collection timeline, and billing terms."
                                    />

                                    <FormSection title="Classification" first>
                                        <div
                                            className="flex flex-wrap gap-2"
                                            role="group"
                                            aria-label="Sample classification"
                                        >
                                            {options.classifications.map(
                                                (item) => {
                                                    const active =
                                                        classification === item;
                                                    const Icon =
                                                        CLASSIFICATION_ICONS[
                                                            item
                                                        ] ?? FlaskConical;

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
                                                            aria-pressed={
                                                                active
                                                            }
                                                            onClick={() => {
                                                                setClassification(
                                                                    item,
                                                                );
                                                                setWastewaterSource(
                                                                    '',
                                                                );
                                                                setWastewaterSourceOther(
                                                                    '',
                                                                );
                                                                if (
                                                                    item !==
                                                                    'Others'
                                                                ) {
                                                                    setClassificationOther(
                                                                        '',
                                                                    );
                                                                    setClassificationOtherSpecify(
                                                                        '',
                                                                    );
                                                                }
                                                            }}
                                                            className={cn(
                                                                'inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                                                active
                                                                    ? 'border-nppc-900 bg-nppc-900 text-white shadow-sm'
                                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500',
                                                            )}
                                                        >
                                                            <Icon
                                                                className="size-3.5 shrink-0"
                                                                aria-hidden
                                                            />
                                                            {item}
                                                        </button>
                                                    );
                                                },
                                            )}
                                        </div>
                                        {attemptedContinue &&
                                        !classification ? (
                                            <p
                                                className="mt-1.5 border-l-2 border-rose-300 py-1 pl-2 text-xs font-medium text-rose-700"
                                                role="alert"
                                                data-intake-error=""
                                            >
                                                Please select a classification.
                                            </p>
                                        ) : null}
                                        {classification === 'Others' && (
                                            <div className="grid grid-cols-1 gap-2.5 pt-1 md:grid-cols-2">
                                                <IntakeField
                                                    label="Parameter category"
                                                    htmlFor="classification_other"
                                                    required
                                                    error={
                                                        errors?.classification
                                                    }
                                                >
                                                    <Select
                                                        value={
                                                            classificationOther ||
                                                            undefined
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) => {
                                                            setClassificationOther(
                                                                value,
                                                            );
                                                            if (
                                                                !isGenericOtherCategory(
                                                                    value,
                                                                )
                                                            ) {
                                                                setClassificationOtherSpecify(
                                                                    '',
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <SelectTrigger
                                                            id="classification_other"
                                                            className="h-10 w-full rounded-lg border-slate-200 bg-white text-sm shadow-none focus-visible:border-nppc-900 focus-visible:ring-nppc-900/20"
                                                        >
                                                            <SelectValue placeholder="Select category…" />
                                                        </SelectTrigger>
                                                        <SelectContent
                                                            position="popper"
                                                            align="start"
                                                            className="max-h-80 w-[var(--radix-select-trigger-width)]"
                                                        >
                                                            {(
                                                                options.other_classification_options ??
                                                                []
                                                            ).map((label) => (
                                                                <SelectItem
                                                                    key={label}
                                                                    value={
                                                                        label
                                                                    }
                                                                >
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
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
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </IntakeField>
                                                )}
                                            </div>
                                        )}
                                    </FormSection>
                                </div>

                                <IntakePanel
                                    title="Request snapshot"
                                    action={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-6 border-nppc-900/25 px-2 text-[11px] text-nppc-900 hover:bg-nppc-50"
                                            onClick={() => setStep(0)}
                                        >
                                            Edit
                                        </Button>
                                    }
                                >
                                    <dl className="grid grid-cols-2 gap-x-3 gap-y-2">
                                        {(
                                            [
                                                [
                                                    'Customer',
                                                    customer.customer_name.trim() ||
                                                        '—',
                                                ],
                                                [
                                                    'Email',
                                                    customer.customer_email.trim() ||
                                                        '—',
                                                ],
                                                [
                                                    'Company',
                                                    customer.company_name.trim() ||
                                                        '—',
                                                ],
                                                [
                                                    'Ownership',
                                                    ownershipType.trim() || '—',
                                                ],
                                                [
                                                    'Classification',
                                                    classification
                                                        ? resolvedClassification ||
                                                          classification
                                                        : 'Not selected',
                                                ],
                                                [
                                                    'Payment',
                                                    PAYMENT_MODE_OPTIONS.find(
                                                        (o) =>
                                                            o.value ===
                                                            paymentMode,
                                                    )?.label ?? 'Not selected',
                                                ],
                                            ] as const
                                        ).map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="min-w-0"
                                            >
                                                <dt className="text-[9px] font-semibold tracking-wider text-slate-500 uppercase">
                                                    {label}
                                                </dt>
                                                <dd className="truncate text-xs leading-tight text-slate-800">
                                                    {value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </IntakePanel>
                            </div>

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(360px,1fr)] lg:items-start">
                                <div className="space-y-3">
                                <IntakePanel
                                    title="Sampling details"
                                    description="Document custody on-site conditions, personnel, and timestamp."
                                >
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <IntakeField
                                            label="Sampling date"
                                            htmlFor="sampling_date"
                                            hint="(YYYY-MM-DD)"
                                        >
                                            <Input
                                                id="sampling_date"
                                                type="date"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                            className="md:col-span-2"
                                        >
                                            <Input
                                                id="sample_collected_by"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
                                                value={sampleCollectedBy}
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
                                                hint="Printed on Food / Proximate result sheets when mapped in Form Designer."
                                                className="md:col-span-2"
                                            >
                                                <Input
                                                    id="specimen"
                                                    className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                            label="Sampling site"
                                            htmlFor="sampling_site"
                                            error={errors?.sampling_site}
                                        >
                                            <Input
                                                id="sampling_site"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                            label="Sample storage temp."
                                            htmlFor="sample_storage_temp"
                                            hint="(as received)"
                                        >
                                            <Input
                                                id="sample_storage_temp"
                                                className="h-10 w-full border-slate-200 bg-white text-sm"
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
                                                    <div className="space-y-2 rounded-lg border border-slate-100 bg-slate-50/60 p-2.5">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <Label>
                                                                Field data
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
                                                                Water in sterile
                                                                bottle
                                                            </span>
                                                        </label>
                                                        <Textarea
                                                            id="field_data"
                                                            rows={2}
                                                            className="min-h-0 bg-white py-2 text-sm"
                                                            placeholder="Other potability notes or site observations (optional)"
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
                                                        className="h-9 w-full border-dashed border-nppc-900/40 text-xs text-nppc-900 hover:bg-nppc-50 sm:w-auto"
                                                        onClick={() =>
                                                            setPotabilityOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="size-3.5" />
                                                        {hasPotabilityData
                                                            ? 'Edit potability field data'
                                                            : 'Add potability field data'}
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </IntakePanel>

                                    <IntakePanel
                                        title="Sample source"
                                        description="Where the specimen was obtained."
                                    >
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
                                                className="mt-2 h-10 text-sm"
                                                placeholder="Please specify sample source"
                                                value={wastewaterSourceOther}
                                                onChange={(e) =>
                                                    setWastewaterSourceOther(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </IntakePanel>
                                </div>

                                <div className="space-y-3">
                                    <IntakePanel
                                        title="Payment method"
                                        description="How settlement will be handled at reception."
                                    >
                                        <div className="flex flex-col gap-2">
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
                                            <div className="mt-2 space-y-2 border-t border-slate-200 pt-2">
                                                <Label>
                                                    Payment terms *{' '}
                                                    <span className="font-normal text-slate-500">
                                                        (required for Billing /
                                                        Partial)
                                                    </span>
                                                </Label>
                                                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
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
                                                        {errors.payment_terms}
                                                    </IntakeAlert>
                                                ) : null}
                                            </div>
                                        )}
                                    </IntakePanel>

                                    <IntakeAlert variant="info">
                                        All microbiology samples must reach the
                                        analytical holding chamber within{' '}
                                        <strong>24 hours of collection</strong>{' '}
                                        under verified cold chain conditions
                                        (&lt; 10°C).
                                    </IntakeAlert>
                                </div>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="w-full space-y-3">
                            <IntakeStepHeader
                                title="Select Tests"
                                badge="Step 4 of 5 · Test parameters"
                                hint={
                                    samples.length > 1
                                        ? 'Each sample is billed separately · …A / …B on forms.'
                                        : 'Select packages or individual analyses for this sample.'
                                }
                                action={
                                    <span className="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-[11px] font-medium text-nppc-800">
                                        <CircleHelp
                                            className="size-3.5 text-nppc-700"
                                            aria-hidden
                                        />
                                        Turnaround: 5–7 business days
                                    </span>
                                }
                            />

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.9fr)] lg:items-start">
                                <div className="min-w-0 space-y-3">
                                    {(() => {
                                        const sampleIndex = activeTestSample;
                                        const sample =
                                            samples[sampleIndex] ?? samples[0];
                                        if (!sample) {
                                            return null;
                                        }

                                        const individualCount =
                                            individualSelectedCountFor(sample);
                                        const missingSelection =
                                            attemptedContinue &&
                                            !sampleHasSelection(
                                                sample,
                                                sampleIndex,
                                            );
                                        const packageCoveredIds = new Set<number>();
                                        sample.package_ids.forEach((pkgId) => {
                                            const pkg = packages.find(
                                                (row) => row.id === pkgId,
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
                                        });
                                        const sampleMeta = [
                                            sample.description.trim() || null,
                                            sample.quantity.trim()
                                                ? `${sample.quantity.trim()}${sample.unit.trim() ? ` ${sample.unit.trim()}` : ''}`
                                                : null,
                                            sample.matrix.trim() || null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ');

                                        return (
                                            <>
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <IntakeSampleSwitcher
                                                        samples={samples.map(
                                                            (row, index) => ({
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
                                                            samples.length === 1
                                                                ? sampleMeta
                                                                : sampleMeta ||
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
                                                            Same as Sample 1
                                                        </button>
                                                    ) : null}
                                                </div>

                                                {missingSelection ? (
                                                    <IntakeAlert
                                                        variant="error"
                                                        data-intake-error=""
                                                    >
                                                        Select at least one
                                                        package or test for this
                                                        sample
                                                        {sampleIndex === 0
                                                            ? ', or describe another test below.'
                                                            : '.'}
                                                    </IntakeAlert>
                                                ) : null}

                                                {type_presets.length > 0 && (
                                                    <div className="space-y-1.5">
                                                        <p className="text-xs font-semibold text-slate-900">
                                                            Test panels
                                                        </p>
                                                        <div className="space-y-1.5">
                                                            {(suggestedTypePresets.length >
                                                            0
                                                                ? suggestedTypePresets
                                                                : type_presets
                                                            ).map((preset) =>
                                                                renderTypePresetRow(
                                                                    sampleIndex,
                                                                    preset,
                                                                ),
                                                            )}
                                                        </div>
                                                        {suggestedTypePresets.length >
                                                            0 &&
                                                            otherTypePresets.length >
                                                                0 && (
                                                                <details className="group">
                                                                    <summary className="cursor-pointer text-xs font-medium text-nppc-700 hover:underline [&::-webkit-details-marker]:hidden">
                                                                        Other
                                                                        panels (
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
                                                            )}
                                                    </div>
                                                )}

                                                {scopedPackages.length > 0 && (
                                                    <div className="space-y-1.5">
                                                        <p className="text-xs font-semibold text-slate-900">
                                                            Recommended packages
                                                        </p>
                                                        <p className="text-[11px] text-slate-500">
                                                            Start here — expand
                                                            a package to see
                                                            included tests
                                                        </p>
                                                        <div className="space-y-2">
                                                            {(isAquaClassification
                                                                ? scopedPackages
                                                                : suggestedPackages.length >
                                                                    0
                                                                  ? suggestedPackages
                                                                  : scopedPackages
                                                            ).map((pkg) =>
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
                                                                0 && (
                                                                <details className="group">
                                                                    <summary className="cursor-pointer text-xs font-medium text-nppc-700 hover:underline [&::-webkit-details-marker]:hidden">
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
                                                            )}
                                                    </div>
                                                )}

                                                <div className="space-y-1.5 border-t border-slate-100 pt-2.5">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <div className="min-w-0">
                                                            <p className="text-xs font-semibold text-slate-900">
                                                                Individual
                                                                tests
                                                                {individualCount >
                                                                0
                                                                    ? ` · ${individualCount} selected`
                                                                    : ''}
                                                            </p>
                                                            <p className="text-[11px] text-slate-500">
                                                                Secondary —
                                                                packages cover
                                                                most requests
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8 border-nppc-900/30 px-2.5 text-xs text-nppc-900 hover:bg-nppc-50"
                                                            aria-expanded={
                                                                browseTestsOpen
                                                            }
                                                            onClick={() =>
                                                                setBrowseTestsOpen(
                                                                    (open) =>
                                                                        !open,
                                                                )
                                                            }
                                                        >
                                                            {browseTestsOpen
                                                                ? 'Hide catalog'
                                                                : 'View individual tests'}
                                                        </Button>
                                                    </div>

                                                    {browseTestsOpen && (
                                                        <IntakeIndividualTestCatalog
                                                            categories={
                                                                scopedCategories
                                                            }
                                                            query={testsQuery}
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
                                                    )}
                                                </div>
                                            </>
                                        );
                                    })()}

                                    <FormSection title="Other / special test request">
                                        <Textarea
                                            id="other_tests"
                                            rows={2}
                                            className="w-full border-slate-200/80 bg-slate-50"
                                            value={otherTests}
                                            onChange={(e) =>
                                                setOtherTests(e.target.value)
                                            }
                                            placeholder="Describe an analysis that is not listed…"
                                        />
                                    </FormSection>
                                </div>

                                <aside className="sticky top-2 hidden self-start lg:block">
                                    <TestsRequestSummary
                                        packages={scopedPackages}
                                        samples={sampleSelectionSummaries}
                                        catalogItems={catalogItemsForSummary}
                                        otherTests={otherTests}
                                        estimatedTotal={estimatedTotal}
                                        customerName={customer.customer_name}
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
                                        setMobileSummaryOpen((open) => !open)
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
                                {mobileSummaryOpen && (
                                    <div className="mt-1.5 rounded-lg border border-slate-200 bg-white p-3">
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
                                            variant="inline"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {step === 4 && (
                        <div className="w-full space-y-3">
                            <IntakeStepHeader
                                title="Review your submission"
                                badge="Step 5 of 5 · Final verification"
                                hint="Double-check your information before submitting."
                            />

                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.9fr)] lg:items-start">
                                <div className="min-w-0 space-y-2">
                                    <ReviewCard
                                        title="Customer information"
                                        first
                                        onEdit={() => setStep(0)}
                                    >
                                        <div className="grid gap-x-3 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
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
                                    </ReviewCard>

                                    <ReviewCard
                                        title="Job & sampling specifications"
                                        onEdit={() => setStep(1)}
                                    >
                                        <div className="grid gap-x-3 gap-y-2 sm:grid-cols-2 xl:grid-cols-3">
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
                                                            className="h-11 w-full border-slate-200 bg-white text-sm"
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
                                    </ReviewCard>

                                    <ReviewCard
                                        title="Registered samples & tests"
                                        onEdit={() => setStep(2)}
                                    >
                                        <ul className="space-y-2">
                                            {samples.map((sample, index) => {
                                                const summary =
                                                    sampleSelectionSummaries[
                                                        index
                                                    ];
                                                const covered =
                                                    new Set<number>();
                                                summary?.package_ids.forEach(
                                                    (pkgId) => {
                                                        const pkg =
                                                            packages.find(
                                                                (row) =>
                                                                    row.id ===
                                                                    pkgId,
                                                            );
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

                                                return (
                                                    <li
                                                        key={index}
                                                        className="rounded-md border border-slate-100 bg-slate-50/40 p-2.5"
                                                    >
                                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                                            <div className="min-w-0">
                                                                <div className="flex flex-wrap items-center gap-1.5">
                                                                    <p className="text-sm font-medium text-nppc-900">
                                                                        Sample{' '}
                                                                        {index +
                                                                            1}
                                                                        {sample.description.trim()
                                                                            ? `: ${sample.description.trim()}`
                                                                            : ''}
                                                                    </p>
                                                                    {sample.sample_code.trim() ? (
                                                                        <span className="rounded bg-nppc-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-nppc-800 ring-1 ring-nppc-200">
                                                                            #
                                                                            {
                                                                                sample.sample_code
                                                                            }
                                                                        </span>
                                                                    ) : null}
                                                                </div>
                                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                                    {[
                                                                        sample.matrix ||
                                                                            null,
                                                                        sample.quantity
                                                                            ? `${sample.quantity}${sample.unit ? ` ${sample.unit}` : ''}`
                                                                            : null,
                                                                        sample.remarks ||
                                                                            null,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            ' · ',
                                                                        ) ||
                                                                        'No additional sample details'}
                                                                </p>
                                                            </div>
                                                            {summary ? (
                                                                <span className="font-heading text-sm font-bold tabular-nums text-nppc-900">
                                                                    ₱
                                                                    {summary.subtotal.toFixed(
                                                                        2,
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                        {paramLines.length >
                                                        0 ? (
                                                            <ul className="mt-1.5 grid gap-1 sm:grid-cols-2">
                                                                {paramLines.map(
                                                                    (line) => (
                                                                        <li
                                                                            key={
                                                                                line.id
                                                                            }
                                                                            className="flex items-center justify-between gap-2 rounded border border-slate-200/80 bg-white px-2 py-1 text-[11px]"
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
                                                                            <span className="shrink-0 tabular-nums text-slate-600">
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
                                                            <p className="mt-1.5 text-[11px] text-slate-500">
                                                                {index === 0 &&
                                                                otherTests.trim()
                                                                    ? 'Covered by other test request'
                                                                    : 'No analyses selected'}
                                                            </p>
                                                        )}
                                                        <button
                                                            type="button"
                                                            className="mt-1.5 text-[11px] font-medium text-nppc-700 hover:underline"
                                                            onClick={() =>
                                                                setStep(3)
                                                            }
                                                        >
                                                            Edit analyses →
                                                        </button>
                                                    </li>
                                                );
                                            })}
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
                                </div>

                                <aside className="sticky top-2 self-start space-y-2">
                                    <TestsRequestSummary
                                        packages={scopedPackages}
                                        samples={sampleSelectionSummaries}
                                        catalogItems={catalogItemsForSummary}
                                        otherTests={otherTests}
                                        estimatedTotal={estimatedTotal}
                                        customerName={customer.customer_name}
                                        variant="sidebar"
                                    />
                                    <div className="rounded-xl border border-nppc-100 bg-nppc-50/60 px-3 py-3 text-[11px] leading-snug text-nppc-900">
                                        <p className="text-[10px] font-semibold tracking-wider text-nppc-700 uppercase">
                                            What happens next
                                        </p>
                                        <ol className="mt-1.5 list-decimal space-y-1 pl-4 text-slate-700">
                                            <li>Your request is received.</li>
                                            <li>
                                                Receiving reviews pricing and
                                                prints the RFA.
                                            </li>
                                            <li>
                                                The lab processes your analyses.
                                            </li>
                                            <li>
                                                Results are released after Head
                                                review.
                                            </li>
                                        </ol>
                                    </div>
                                    <p className="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2 text-[10px] leading-snug text-slate-500">
                                        Payment is settled at reception. Sample
                                        integrity is verified at drop-off.
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
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 gap-1.5 border-slate-200 px-5 text-sm"
                            disabled={step === 0 || submitting}
                            onClick={() => setStep((s) => Math.max(0, s - 1))}
                        >
                            <ArrowLeft className="size-3.5" aria-hidden />
                            {step === 1
                                ? 'Back to Customer'
                                : step === 2
                                  ? 'Back to Details'
                                  : step === 3
                                    ? 'Back to Samples'
                                    : step === 4
                                      ? 'Back to Tests'
                                      : 'Back'}
                        </Button>
                    </div>
                    {(step === 0 && !stepError(step)
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
                                  ? `${samples.length} sample${samples.length === 1 ? '' : 's'} · ${totalSelectedAnalyses} tests configured`
                                  : step === 4
                                    ? 'Ready for final submit'
                                    : null) ? (
                        <p className="col-span-2 order-first text-center text-xs text-slate-500 sm:col-span-1 sm:order-none sm:whitespace-nowrap">
                            {step === 0 && !stepError(step)
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
                                          ? `${samples.length} sample${samples.length === 1 ? '' : 's'} · ${totalSelectedAnalyses} tests configured`
                                          : 'Ready for final submit'}
                        </p>
                    ) : (
                        <span className="hidden sm:block" aria-hidden />
                    )}
                    <div className="justify-self-end">
                        {step < steps.length - 1 ? (
                            <Button
                                type="button"
                                className="h-10 gap-2 bg-nppc-900 px-5 text-sm font-semibold hover:bg-nppc-700 sm:min-w-44"
                                onClick={goNext}
                            >
                                {step === 2
                                    ? 'Continue to Test Selection'
                                    : step === 3
                                      ? 'Continue to Review'
                                      : 'Continue'}
                                <ArrowRight className="size-4" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                className="h-10 gap-2 bg-nppc-900 px-5 text-sm font-semibold hover:bg-nppc-700 sm:min-w-44"
                                disabled={submitting}
                                onClick={submit}
                            >
                                {submitting
                                    ? 'Submitting…'
                                    : 'Submit Request for Analysis'}
                                {!submitting ? (
                                    <ArrowRight className="size-4" />
                                ) : null}
                            </Button>
                        )}
                    </div>
                </div>
            </IntakeStickyFooter>
        </IntakePageShell>
        </>
    );
}

IntakeWizard.layout = null;
