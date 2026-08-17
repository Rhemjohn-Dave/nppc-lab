import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type TemplateInfo = {
    has_source: boolean;
    has_fillable: boolean;
    original_name: string | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
    notes: string | null;
    field_count: number;
};

type Props = {
    template: TemplateInfo;
};

export default function FormTemplatesAdmin({ template }: Props) {
    const { flash, errors } = usePage().props as {
        flash?: { success?: string };
        errors?: Record<string, string>;
    };

    const form = useForm<{
        template: File | null;
        notes: string;
    }>({
        template: null,
        notes: template.notes ?? '',
    });

    const uploadedLabel = template.uploaded_at
        ? new Date(template.uploaded_at).toLocaleString()
        : null;

    return (
        <>
            <Head title="Form templates" />
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Form templates
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Upload the official flat Request for Analysis PDF. The
                        system stamps fillable fields onto it, then fills those
                        fields when staff download a job-order PDF.
                    </p>
                    {flash?.success && (
                        <p className="mt-2 text-sm text-emerald-700">
                            {flash.success}
                        </p>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Status</p>
                        <p className="mt-1 font-heading text-xl font-semibold text-[#1A3694]">
                            {template.has_source ? 'Template ready' : 'No upload'}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {template.has_fillable
                                ? 'Fillable PDF generated'
                                : 'Fillable PDF not generated yet'}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Current file</p>
                        <p className="mt-1 truncate font-heading text-lg font-semibold text-[#1A3694]">
                            {template.original_name ?? '—'}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {uploadedLabel
                                ? `Uploaded ${uploadedLabel}${template.uploaded_by ? ` by ${template.uploaded_by}` : ''}`
                                : 'Waiting for official soft copy'}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Field blueprint</p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {template.field_count}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Named fields stamped onto the PDF
                        </p>
                    </div>
                </div>

                <form
                    className="max-w-xl space-y-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/admin/form-templates', {
                            forceFormData: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="template">Flat PDF soft copy</Label>
                        <Input
                            id="template"
                            type="file"
                            accept="application/pdf,.pdf"
                            onChange={(event) => {
                                form.setData(
                                    'template',
                                    event.target.files?.[0] ?? null,
                                );
                            }}
                        />
                        {(errors?.template || form.errors.template) && (
                            <p className="text-sm text-red-600">
                                {errors?.template || form.errors.template}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            PDF only, max 10 MB. If you have Word, export to PDF
                            first. Field positions can be recalibrated after
                            upload.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes (optional)</Label>
                        <Input
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            placeholder="e.g. Revision 10 / LP 7.1 F01"
                        />
                    </div>

                    <Button type="submit" disabled={form.processing || !form.data.template}>
                        {form.processing ? 'Uploading…' : 'Upload and make fillable'}
                    </Button>
                </form>

                {template.has_source && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href="/admin/form-templates/source">
                                Download flat source
                            </a>
                        </Button>
                        {template.has_fillable && (
                            <Button variant="outline" asChild>
                                <a href="/admin/form-templates/fillable">
                                    Download fillable PDF
                                </a>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <a href="/admin/form-templates/sample">
                                Download sample filled PDF
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href="/admin/form-templates/calibration">
                                Download calibration grid
                            </a>
                        </Button>
                        <Button
                            variant="secondary"
                            type="button"
                            onClick={() =>
                                router.post('/admin/form-templates/regenerate')
                            }
                        >
                            Regenerate fillable
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
