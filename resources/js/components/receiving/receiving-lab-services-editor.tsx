import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type LabServiceDraft = {
    id?: number | null;
    name: string;
    quantity: number;
    unit_price: number;
};

type Props = {
    jobOrderId: number;
    initialServices: LabServiceDraft[];
    discountPercent: number;
    canEdit: boolean;
    onSaved?: () => void;
};

function money(value: number) {
    return `₱${value.toFixed(2)}`;
}

export default function ReceivingLabServicesEditor({
    jobOrderId,
    initialServices,
    discountPercent,
    canEdit,
    onSaved,
}: Props) {
    const [services, setServices] = useState<LabServiceDraft[]>(
        initialServices.length > 0
            ? initialServices
            : [{ name: '', quantity: 1, unit_price: 0 }],
    );
    const [discount, setDiscount] = useState(Number(discountPercent || 0));
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const subtotal = useMemo(
        () =>
            services.reduce(
                (sum, row) =>
                    sum +
                    Math.max(1, Number(row.quantity) || 1) *
                        Math.max(0, Number(row.unit_price) || 0),
                0,
            ),
        [services],
    );
    const discountAmount =
        Math.round(subtotal * (Math.max(0, Math.min(100, discount)) / 100) * 100) /
        100;
    const total = Math.max(0, subtotal - discountAmount);

    function updateRow(index: number, patch: Partial<LabServiceDraft>) {
        setServices((rows) =>
            rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    function addRow() {
        setServices((rows) => [
            ...rows,
            { name: '', quantity: 1, unit_price: 0 },
        ]);
    }

    function removeRow(index: number) {
        setServices((rows) =>
            rows.length <= 1 ? rows : rows.filter((_, i) => i !== index),
        );
    }

    function save(savePricing: boolean) {
        setError(null);
        if (services.some((row) => !row.name.trim())) {
            setError('Every service needs a name.');
            return;
        }

        setProcessing(true);
        router.patch(
            `/receiving/${jobOrderId}/lab-services`,
            {
                services: services.map((row) => ({
                    id: row.id ?? null,
                    name: row.name.trim(),
                    quantity: Math.max(1, Number(row.quantity) || 1),
                    unit_price: Math.max(0, Number(row.unit_price) || 0),
                })),
                save_pricing: savePricing,
                discount_percent: discount,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onSaved?.(),
                onError: (errors) => {
                    const first =
                        errors.services ||
                        errors['services.0.name'] ||
                        Object.values(errors)[0];
                    setError(
                        typeof first === 'string'
                            ? first
                            : 'Could not save laboratory services.',
                    );
                },
            },
        );
    }

    return (
        <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div>
                <h2 className="text-sm font-semibold text-slate-900">
                    Laboratory services
                </h2>
                <p className="mt-0.5 text-xs text-slate-500">
                    Add free-text services and unit prices. These print on the
                    Job Order billing parameters.
                </p>
            </div>

            <div className="space-y-2">
                {services.map((row, index) => (
                    <div
                        key={row.id ?? `new-${index}`}
                        className="grid gap-2 rounded-lg border border-slate-100 bg-slate-50/60 p-2.5 sm:grid-cols-[minmax(0,1.6fr)_5rem_7rem_auto]"
                    >
                        <Input
                            value={row.name}
                            disabled={!canEdit || processing}
                            placeholder="Service name"
                            className="h-9 bg-white text-sm"
                            onChange={(e) =>
                                updateRow(index, { name: e.target.value })
                            }
                        />
                        <Input
                            type="number"
                            min={1}
                            value={row.quantity}
                            disabled={!canEdit || processing}
                            className="h-9 bg-white text-sm"
                            onChange={(e) =>
                                updateRow(index, {
                                    quantity: Number(e.target.value) || 1,
                                })
                            }
                        />
                        <Input
                            type="number"
                            min={0}
                            step="0.01"
                            value={row.unit_price}
                            disabled={!canEdit || processing}
                            className="h-9 bg-white text-sm"
                            onChange={(e) =>
                                updateRow(index, {
                                    unit_price: Number(e.target.value) || 0,
                                })
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-9 px-2 text-rose-700"
                            disabled={
                                !canEdit || processing || services.length <= 1
                            }
                            onClick={() => removeRow(index)}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ))}
            </div>

            {canEdit ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-8 gap-1.5 text-xs"
                    disabled={processing}
                    onClick={addRow}
                >
                    <Plus className="size-3.5" />
                    Add service
                </Button>
            ) : null}

            <div className="grid gap-2 border-t border-slate-100 pt-3 sm:grid-cols-3">
                <div className="text-xs text-slate-600">
                    Subtotal{' '}
                    <span className="font-semibold text-slate-900">
                        {money(subtotal)}
                    </span>
                </div>
                <label className="flex items-center gap-2 text-xs text-slate-600">
                    Discount %
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        step="0.01"
                        value={discount}
                        disabled={!canEdit || processing}
                        className="h-8 w-20 bg-white text-sm"
                        onChange={(e) =>
                            setDiscount(Number(e.target.value) || 0)
                        }
                    />
                </label>
                <div className="text-xs text-slate-600 sm:text-right">
                    Total{' '}
                    <span className="font-semibold text-slate-900">
                        {money(total)}
                    </span>
                </div>
            </div>

            {error ? (
                <p className="text-xs font-medium text-rose-700">{error}</p>
            ) : null}

            {canEdit ? (
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs"
                        disabled={processing}
                        onClick={() => save(false)}
                    >
                        Save services
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        className="h-8 bg-[#1d3b91] text-xs hover:bg-[#112747]"
                        disabled={processing}
                        onClick={() => save(true)}
                    >
                        Save &amp; send for JO approval
                    </Button>
                </div>
            ) : (
                <p className="text-xs text-slate-500">
                    Services are locked after JO approval.
                </p>
            )}
        </div>
    );
}
