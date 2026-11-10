import { router } from '@inertiajs/react';
import { Check, ChevronsUpDown, Package, Plus, Search, TestTube2 } from 'lucide-react';
import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type AnalysisCatalogPackage = {
    id: number;
    name: string;
    code?: string | null;
    default_price?: string | number | null;
    analysis_type_ids: number[];
    classifications: string[];
};

export type AnalysisCatalogType = {
    id: number;
    code: string;
    name: string;
    default_price?: string | number | null;
    category?: string | null;
    category_label?: string | null;
};

type Props = {
    jobOrderId: number;
    sampleId: number;
    sampleClassification: string | null | undefined;
    jobClassification: string | null | undefined;
    isBulk: boolean;
    existingPackageIds: number[];
    existingTypeIds: number[];
    packages: AnalysisCatalogPackage[];
    analysisTypes: AnalysisCatalogType[];
};

function matchesClassification(
    tags: string[],
    classification: string | null | undefined,
): boolean {
    if (tags.length === 0) {
        return true;
    }
    const value = (classification ?? '').trim().toLowerCase();
    if (value === '') {
        return false;
    }
    return tags.some((tag) => value.includes(String(tag).toLowerCase()));
}

function money(value: string | number | null | undefined): string | null {
    if (value == null || value === '') {
        return null;
    }
    const amount = Number(value);
    if (!Number.isFinite(amount)) {
        return null;
    }
    return `₱${amount.toFixed(2)}`;
}

function SearchablePicker<T extends { id: number }>({
    label,
    icon,
    placeholder,
    searchPlaceholder,
    emptyLabel,
    items,
    value,
    onChange,
    disabled,
    getLabel,
    getSearchText,
    renderOption,
}: {
    label: string;
    icon: ReactNode;
    placeholder: string;
    searchPlaceholder: string;
    emptyLabel: string;
    items: T[];
    value: string;
    onChange: (next: string) => void;
    disabled?: boolean;
    getLabel: (item: T) => string;
    getSearchText: (item: T) => string;
    renderOption: (item: T, selected: boolean) => ReactNode;
}) {
    const listId = useId();
    const rootRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const selected = items.find((item) => String(item.id) === value) ?? null;

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return items;
        }
        return items.filter((item) =>
            getSearchText(item).toLowerCase().includes(needle),
        );
    }, [items, query, getSearchText]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onPointerDown = (event: MouseEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    useEffect(() => {
        if (open) {
            searchRef.current?.focus();
        }
    }, [open]);

    return (
        <div ref={rootRef} className="relative min-w-0 flex-1">
            <p className="mb-1.5 text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                {label}
            </p>
            <button
                type="button"
                disabled={disabled || items.length === 0}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listId}
                onClick={() => {
                    if (disabled || items.length === 0) {
                        return;
                    }
                    setOpen((current) => !current);
                    setQuery('');
                }}
                className={cn(
                    'flex h-11 w-full items-center gap-2.5 rounded-xl border bg-white px-3 text-left shadow-xs transition',
                    'border-slate-200 hover:border-slate-300 hover:bg-slate-50/80',
                    'focus-visible:border-nppc-600 focus-visible:ring-2 focus-visible:ring-nppc-600/20 focus-visible:outline-none',
                    open && 'border-nppc-600 ring-2 ring-nppc-600/20',
                    (disabled || items.length === 0) &&
                        'cursor-not-allowed opacity-60',
                )}
            >
                <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    {icon}
                </span>
                <span className="min-w-0 flex-1">
                    {selected ? (
                        <span className="block truncate text-sm font-medium text-slate-900">
                            {getLabel(selected)}
                        </span>
                    ) : (
                        <span className="block truncate text-sm text-slate-500">
                            {items.length === 0 ? emptyLabel : placeholder}
                        </span>
                    )}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-slate-400" />
            </button>

            {open ? (
                <div
                    id={listId}
                    role="listbox"
                    className="absolute right-0 bottom-full left-0 z-50 mb-1.5 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg ring-1 ring-black/5"
                >
                    <div className="border-b border-slate-100 p-2">
                        <div className="relative">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400"
                                aria-hidden
                            />
                            <Input
                                ref={searchRef}
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder={searchPlaceholder}
                                className="h-9 border-slate-200 bg-slate-50 pl-8"
                            />
                        </div>
                    </div>
                    <div className="nppc-scroll max-h-56 overflow-y-auto overscroll-contain p-1.5">
                        {filtered.length === 0 ? (
                            <div className="px-3 py-6 text-center">
                                <p className="text-sm font-medium text-slate-800">
                                    No matches
                                </p>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Try another name or code.
                                </p>
                            </div>
                        ) : (
                            filtered.map((item) => {
                                const selectedItem =
                                    String(item.id) === value;
                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        role="option"
                                        aria-selected={selectedItem}
                                        className={cn(
                                            'flex w-full items-start gap-2.5 rounded-lg px-2.5 py-2 text-left transition',
                                            selectedItem
                                                ? 'bg-nppc-50 text-nppc-950'
                                                : 'hover:bg-slate-50',
                                        )}
                                        onClick={() => {
                                            onChange(String(item.id));
                                            setOpen(false);
                                            setQuery('');
                                        }}
                                    >
                                        <span className="mt-0.5 flex size-4 shrink-0 items-center justify-center">
                                            {selectedItem ? (
                                                <Check className="size-3.5 text-nppc-700" />
                                            ) : null}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            {renderOption(item, selectedItem)}
                                        </span>
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

export default function ReceivingAddAnalyses({
    jobOrderId,
    sampleId,
    sampleClassification,
    jobClassification,
    isBulk,
    existingPackageIds,
    existingTypeIds,
    packages,
    analysisTypes,
}: Props) {
    const [packageId, setPackageId] = useState('');
    const [typeId, setTypeId] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const classification =
        (sampleClassification && sampleClassification.trim() !== ''
            ? sampleClassification
            : jobClassification) ?? '';

    const availablePackages = useMemo(
        () =>
            packages.filter(
                (pkg) =>
                    !existingPackageIds.includes(pkg.id) &&
                    matchesClassification(pkg.classifications, classification),
            ),
        [packages, existingPackageIds, classification],
    );

    const availableTypes = useMemo(
        () =>
            analysisTypes.filter((type) => !existingTypeIds.includes(type.id)),
        [analysisTypes, existingTypeIds],
    );

    function addPackage() {
        if (!packageId) {
            return;
        }
        setError(null);
        setProcessing(true);
        router.post(
            `/receiving/${jobOrderId}/analyses/packages`,
            {
                analysis_package_id: Number(packageId),
                sample_id: isBulk ? undefined : sampleId,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setPackageId(''),
                onError: (errors) => {
                    setError(
                        errors.analysis_package_id ??
                            errors.sample_id ??
                            errors.analyses ??
                            'Could not add package.',
                    );
                },
            },
        );
    }

    function addType() {
        if (!typeId) {
            return;
        }
        setError(null);
        setProcessing(true);
        router.post(
            `/receiving/${jobOrderId}/analyses/types`,
            {
                analysis_type_id: Number(typeId),
                sample_id: isBulk ? undefined : sampleId,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setTypeId(''),
                onError: (errors) => {
                    setError(
                        errors.analysis_type_id ??
                            errors.sample_id ??
                            errors.analyses ??
                            'Could not add test.',
                    );
                },
            },
        );
    }

    if (availablePackages.length === 0 && availableTypes.length === 0) {
        return (
            <p className="px-4 py-2 text-[11px] text-slate-500">
                No more packages or tests available to add
                {isBulk ? ' for all samples' : ' for this sample'}.
            </p>
        );
    }

    return (
        <div className="relative z-30 space-y-3 overflow-visible border-t border-slate-200 bg-gradient-to-b from-slate-50/80 to-white px-4 py-3.5">
            <div>
                <p className="text-sm font-semibold text-slate-900">
                    Add forgotten package or test
                </p>
                <p className="mt-0.5 text-[11px] text-slate-500">
                    Search and add items the customer missed
                    {isBulk ? '. Changes apply to every sample on this bulk order.' : '.'}
                </p>
            </div>

            {error ? (
                <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700" role="alert">
                    {error}
                </p>
            ) : null}

            <div className="grid gap-3 overflow-visible lg:grid-cols-2">
                <div className="relative z-10 overflow-visible rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
                    <SearchablePicker
                        label="Package"
                        icon={<Package className="size-3.5" />}
                        placeholder="Choose a package…"
                        searchPlaceholder="Search packages by name or code…"
                        emptyLabel="No packages left to add"
                        items={availablePackages}
                        value={packageId}
                        onChange={setPackageId}
                        disabled={processing}
                        getLabel={(pkg) => pkg.name}
                        getSearchText={(pkg) =>
                            `${pkg.name} ${pkg.code ?? ''} ${pkg.classifications.join(' ')}`
                        }
                        renderOption={(pkg) => {
                            const price = money(pkg.default_price);
                            const count = pkg.analysis_type_ids.length;
                            return (
                                <>
                                    <span className="block truncate text-sm font-medium text-slate-900">
                                        {pkg.name}
                                    </span>
                                    <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                        {pkg.code ? (
                                            <span className="font-mono text-sky-800">
                                                {pkg.code}
                                            </span>
                                        ) : null}
                                        <span>
                                            {count} {count === 1 ? 'test' : 'tests'}
                                        </span>
                                        {price ? <span>{price}</span> : null}
                                    </span>
                                </>
                            );
                        }}
                    />
                    <Button
                        type="button"
                        size="sm"
                        className="mt-2.5 h-9 w-full"
                        disabled={processing || !packageId}
                        onClick={addPackage}
                    >
                        <Plus className="size-3.5" />
                        Add package
                    </Button>
                </div>

                <div className="relative z-20 overflow-visible rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
                    <SearchablePicker
                        label="Individual test"
                        icon={<TestTube2 className="size-3.5" />}
                        placeholder="Choose a test…"
                        searchPlaceholder="Search tests by name or code…"
                        emptyLabel="No tests left to add"
                        items={availableTypes}
                        value={typeId}
                        onChange={setTypeId}
                        disabled={processing}
                        getLabel={(type) => type.name}
                        getSearchText={(type) =>
                            `${type.name} ${type.code} ${type.category_label ?? ''} ${type.category ?? ''}`
                        }
                        renderOption={(type) => {
                            const price = money(type.default_price);
                            return (
                                <>
                                    <span className="block truncate text-sm font-medium text-slate-900">
                                        {type.name}
                                    </span>
                                    <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                        <span className="font-mono text-nppc-800">
                                            {type.code}
                                        </span>
                                        {type.category_label ? (
                                            <span>{type.category_label}</span>
                                        ) : null}
                                        {price ? <span>{price}</span> : null}
                                    </span>
                                </>
                            );
                        }}
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="mt-2.5 h-9 w-full"
                        disabled={processing || !typeId}
                        onClick={addType}
                    >
                        <Plus className="size-3.5" />
                        Add test
                    </Button>
                </div>
            </div>
        </div>
    );
}
