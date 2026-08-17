import { Head, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type RoleOption = {
    id: string;
    label: string;
};

type Props = {
    roles: RoleOption[];
    selected: string[];
};

export default function HistoryAccessAdmin({ roles, selected }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const form = useForm({
        roles: selected,
    });

    function toggle(roleId: string, checked: boolean) {
        const next = checked
            ? [...new Set([...form.data.roles, roleId])]
            : form.data.roles.filter((role) => role !== roleId);

        form.setData('roles', next);
    }

    return (
        <>
            <Head title="History access" />
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        History visibility
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Choose which roles can see the History page in the
                        sidebar. Admin always keeps access.
                    </p>
                    {flash?.success && (
                        <p className="mt-2 text-sm text-emerald-700">
                            {flash.success}
                        </p>
                    )}
                </div>

                <form
                    className="max-w-xl space-y-4 rounded-xl border bg-white p-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/admin/history-access');
                    }}
                >
                    <div className="space-y-3">
                        {roles.map((role) => {
                            const checked = form.data.roles.includes(role.id);
                            const locked = role.id === 'admin';

                            return (
                                <label
                                    key={role.id}
                                    className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-[#5282D3]/50"
                                >
                                    <Checkbox
                                        checked={checked || locked}
                                        disabled={locked}
                                        onCheckedChange={(value) =>
                                            !locked &&
                                            toggle(role.id, value === true)
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block font-medium text-[#1A3694]">
                                            {role.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {locked
                                                ? 'Always visible for Admin'
                                                : `Show History in the sidebar for ${role.label}`}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>

                    {form.errors.roles && (
                        <p className="text-sm text-red-600">
                            {form.errors.roles}
                        </p>
                    )}

                    <div className="flex items-center justify-between gap-3 border-t pt-4">
                        <Label className="text-sm text-muted-foreground">
                            {form.data.roles.length} role
                            {form.data.roles.length === 1 ? '' : 's'} selected
                        </Label>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                        >
                            {form.processing ? 'Saving…' : 'Save visibility'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

HistoryAccessAdmin.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/users' },
        { title: 'History access', href: '/admin/history-access' },
    ],
};
