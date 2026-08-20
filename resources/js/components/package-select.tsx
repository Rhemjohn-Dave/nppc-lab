import { Label } from '@/components/ui/label';

export type AnalysisPackageOption = {
    id: number;
    code: string;
    name: string;
    analysis_type_ids: number[];
};

type Props = {
    packages: AnalysisPackageOption[];
    value: number | '' | null;
    onChange: (packageId: number | null, typeIds: number[]) => void;
    disabled?: boolean;
    id?: string;
};

export default function PackageSelect({
    packages,
    value,
    onChange,
    disabled,
    id = 'analysis_package_id',
}: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Package</Label>
            <select
                id={id}
                className="h-9 rounded-md border px-3 text-sm"
                value={value ?? ''}
                disabled={disabled}
                onChange={(event) => {
                    const raw = event.target.value;
                    if (raw === '') {
                        onChange(null, []);
                        return;
                    }

                    const packageId = Number(raw);
                    const selected = packages.find((item) => item.id === packageId);
                    onChange(packageId, selected?.analysis_type_ids ?? []);
                }}
            >
                <option value="">No package — pick tests below</option>
                {packages.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name}
                    </option>
                ))}
            </select>
            <p className="text-xs text-muted-foreground">
                Choose a kiosk package to bind its tests and show named result
                boxes in the designer (for example Total Coliform result).
            </p>
        </div>
    );
}
