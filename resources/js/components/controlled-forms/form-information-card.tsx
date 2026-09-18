import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type FormInfoData = {
    name: string;
    department: string;
    description: string;
};

type Props = {
    editing: boolean;
    data: FormInfoData;
    errors: Partial<Record<keyof FormInfoData, string>>;
    processing: boolean;
    onEdit: () => void;
    onCancel: () => void;
    onChange: (field: keyof FormInfoData, value: string) => void;
    onSave: () => void;
};

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-0.5 text-sm break-words text-slate-900">
                {value.trim() !== '' ? value : '—'}
            </p>
        </div>
    );
}

export default function FormInformationCard({
    editing,
    data,
    errors,
    processing,
    onEdit,
    onCancel,
    onChange,
    onSave,
}: Props) {
    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                    Form information
                </h2>
                {!editing ? (
                    <Button type="button" size="sm" variant="outline" onClick={onEdit}>
                        Edit
                    </Button>
                ) : null}
            </div>

            {editing ? (
                <form
                    className="grid gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSave();
                    }}
                >
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor="form-name">Form name</Label>
                            <Input
                                id="form-name"
                                value={data.name}
                                onChange={(event) => onChange('name', event.target.value)}
                            />
                            {errors.name ? (
                                <p className="text-xs text-red-600">{errors.name}</p>
                            ) : null}
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="form-department">Department</Label>
                            <Input
                                id="form-department"
                                value={data.department}
                                onChange={(event) =>
                                    onChange('department', event.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="form-description">Description</Label>
                        <Textarea
                            id="form-description"
                            value={data.description}
                            rows={2}
                            onChange={(event) =>
                                onChange('description', event.target.value)
                            }
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={onCancel}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                        >
                            {processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </div>
                </form>
            ) : (
                <div className="space-y-2">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <Field label="Form name" value={data.name} />
                        <Field label="Department" value={data.department || 'Laboratory'} />
                    </div>
                    <Field label="Description" value={data.description} />
                </div>
            )}
        </section>
    );
}
