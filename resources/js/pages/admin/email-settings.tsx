import { Head, useForm, usePage } from '@inertiajs/react';
import {
    EmailStatusBadge,
    formatEmailTime,
    type EmailLogSummary,
} from '@/components/email/customer-email-log';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceHeader from '@/components/workspace-header';
import { limsPanel } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type Settings = {
    enabled: boolean;
    host: string;
    port: number;
    encryption: 'tls' | 'ssl' | 'none';
    username: string;
    from_address: string;
    from_name: string;
    has_password: boolean;
    configured: boolean;
    last_test_status: 'ok' | 'failed' | null;
    last_test_at: string | null;
    last_test_error: string | null;
};

type Props = {
    settings: Settings;
    env_mailer: string;
    recent: EmailLogSummary[];
};

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="mt-1 text-xs text-red-600">{message}</p>
    ) : null;
}

function StatusBadge({ settings }: { settings: Settings }) {
    if (!settings.configured) {
        return (
            <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
                Not configured
            </span>
        );
    }

    if (settings.last_test_status === 'ok') {
        return (
            <span className="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">
                Configured · last test OK
            </span>
        );
    }

    if (settings.last_test_status === 'failed') {
        return (
            <span className="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-800">
                Configured · last test failed
            </span>
        );
    }

    return (
        <span className="rounded-md bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-800">
            Configured · not tested
        </span>
    );
}

export default function EmailSettingsAdmin({
    settings,
    env_mailer,
    recent,
}: Props) {
    const { auth } = usePage().props as {
        auth: { user?: { email?: string | null } | null };
    };

    const form = useForm({
        enabled: settings.enabled,
        host: settings.host,
        port: settings.port || 587,
        encryption: settings.encryption,
        username: settings.username,
        password: '',
        from_address: settings.from_address,
        from_name: settings.from_name,
    });

    const testForm = useForm({
        to: auth.user?.email ?? '',
    });

    return (
        <>
            <Head title="Email settings" />
            <LimsWorkspace>
                <WorkspaceHeader
                    title="Email settings"
                    description="Outgoing mail server used for customer emails (request received, results ready) and password resets. Saved here — no .env edits needed."
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                    <form
                        className={cn('space-y-5 p-5', limsPanel)}
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.put('/admin/email-settings', {
                                preserveScroll: true,
                                onSuccess: () => form.setData('password', ''),
                            });
                        }}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <label className="flex items-center gap-2 text-sm font-semibold text-nppc-900">
                                <Checkbox
                                    checked={form.data.enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData('enabled', checked === true)
                                    }
                                />
                                Use these SMTP settings
                            </label>
                            <StatusBadge settings={settings} />
                        </div>

                        {!form.data.enabled ? (
                            <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                Admin SMTP is off. The server&apos;s .env
                                mailer (<strong>{env_mailer}</strong>) is used
                                {env_mailer === 'log'
                                    ? ' — emails are only written to the log file and not delivered.'
                                    : '.'}
                            </p>
                        ) : null}

                        <section className="space-y-3">
                            <h3 className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Server
                            </h3>
                            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_9rem]">
                                <div>
                                    <Label htmlFor="host">SMTP host</Label>
                                    <Input
                                        id="host"
                                        className="mt-1"
                                        placeholder="smtp.office365.com"
                                        value={form.data.host}
                                        onChange={(e) =>
                                            form.setData('host', e.target.value)
                                        }
                                    />
                                    <FieldError message={form.errors.host} />
                                </div>
                                <div>
                                    <Label htmlFor="port">Port</Label>
                                    <Input
                                        id="port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        className="mt-1"
                                        value={form.data.port}
                                        onChange={(e) =>
                                            form.setData(
                                                'port',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <FieldError message={form.errors.port} />
                                </div>
                                <div>
                                    <Label htmlFor="encryption">
                                        Encryption
                                    </Label>
                                    <select
                                        id="encryption"
                                        className="mt-1 flex h-9 w-full rounded-md border border-input bg-white px-3 text-sm"
                                        value={form.data.encryption}
                                        onChange={(e) =>
                                            form.setData(
                                                'encryption',
                                                e.target
                                                    .value as Settings['encryption'],
                                            )
                                        }
                                    >
                                        <option value="tls">
                                            STARTTLS (587)
                                        </option>
                                        <option value="ssl">SSL/TLS (465)</option>
                                        <option value="none">None</option>
                                    </select>
                                    <FieldError
                                        message={form.errors.encryption}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="username">Username</Label>
                                    <Input
                                        id="username"
                                        className="mt-1"
                                        autoComplete="off"
                                        placeholder="lab@yourdomain.com"
                                        value={form.data.username}
                                        onChange={(e) =>
                                            form.setData(
                                                'username',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError message={form.errors.username} />
                                </div>
                                <div>
                                    <Label htmlFor="password">
                                        Password / app password
                                    </Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        className="mt-1"
                                        autoComplete="new-password"
                                        placeholder={
                                            settings.has_password
                                                ? 'Saved — leave blank to keep'
                                                : 'Not set'
                                        }
                                        value={form.data.password}
                                        onChange={(e) =>
                                            form.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError message={form.errors.password} />
                                </div>
                            </div>
                        </section>

                        <section className="space-y-3">
                            <h3 className="text-[11px] font-bold tracking-wider text-slate-500 uppercase">
                                Sender
                            </h3>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="from_address">
                                        From address
                                    </Label>
                                    <Input
                                        id="from_address"
                                        type="email"
                                        className="mt-1"
                                        placeholder="nppclab@gmail.com"
                                        value={form.data.from_address}
                                        onChange={(e) =>
                                            form.setData(
                                                'from_address',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError
                                        message={form.errors.from_address}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="from_name">From name</Label>
                                    <Input
                                        id="from_name"
                                        className="mt-1"
                                        placeholder="NPPC Analytical & Diagnostic Laboratory"
                                        value={form.data.from_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'from_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError message={form.errors.from_name} />
                                </div>
                            </div>
                        </section>

                        <div className="flex justify-end border-t border-slate-100 pt-4">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-nppc-900 hover:bg-nppc-700"
                            >
                                {form.processing ? 'Saving…' : 'Save settings'}
                            </Button>
                        </div>
                    </form>

                    <div className="space-y-4">
                        <form
                            className={cn('space-y-3 p-5', limsPanel)}
                            onSubmit={(e) => {
                                e.preventDefault();
                                testForm.post('/admin/email-settings/test', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <h3 className="font-heading text-sm font-semibold text-nppc-900">
                                Send test email
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Save settings first, then send a test to confirm
                                the server accepts mail.
                            </p>
                            <div>
                                <Label htmlFor="test_to">Send to</Label>
                                <Input
                                    id="test_to"
                                    type="email"
                                    className="mt-1"
                                    value={testForm.data.to}
                                    onChange={(e) =>
                                        testForm.setData('to', e.target.value)
                                    }
                                    required
                                />
                                <FieldError message={testForm.errors.to} />
                            </div>
                            {settings.last_test_status === 'failed' &&
                            settings.last_test_error ? (
                                <p className="rounded-md bg-rose-50 px-2.5 py-2 text-xs text-rose-800">
                                    Last test failed (
                                    {formatEmailTime(settings.last_test_at)}):{' '}
                                    {settings.last_test_error}
                                </p>
                            ) : null}
                            <Button
                                type="submit"
                                variant="outline"
                                className="w-full"
                                disabled={testForm.processing}
                            >
                                {testForm.processing
                                    ? 'Sending…'
                                    : 'Send test email'}
                            </Button>
                        </form>

                        <div className={cn('space-y-2 p-5', limsPanel)}>
                            <h3 className="font-heading text-sm font-semibold text-nppc-900">
                                Recent email activity
                            </h3>
                            {recent.length === 0 ? (
                                <p className="text-xs text-slate-500">
                                    No emails yet.
                                </p>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {recent.map((log) => (
                                        <li
                                            key={log.id}
                                            className="flex items-start justify-between gap-2 py-2 text-xs"
                                            title={log.error ?? undefined}
                                        >
                                            <div className="min-w-0">
                                                <p className="font-semibold text-slate-800">
                                                    {log.type_label}
                                                    {log.reference_no ? (
                                                        <span className="ml-1 font-mono font-normal text-slate-500">
                                                            {log.reference_no}
                                                        </span>
                                                    ) : null}
                                                </p>
                                                <p className="truncate text-slate-500">
                                                    {log.to ?? '—'} ·{' '}
                                                    {formatEmailTime(
                                                        log.sent_at ??
                                                            log.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <EmailStatusBadge
                                                status={log.status}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </LimsWorkspace>
        </>
    );
}

EmailSettingsAdmin.layout = {
    breadcrumbs: [{ title: 'Email settings', href: '/admin/email-settings' }],
};
