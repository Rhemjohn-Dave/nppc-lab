import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type EmailLogSummary = {
    id: number;
    type: string;
    type_label: string;
    to: string | null;
    status: 'queued' | 'sent' | 'failed' | 'skipped' | string;
    error: string | null;
    sent_at: string | null;
    created_at: string | null;
    reference_no?: string | null;
};

const STATUS_STYLES: Record<string, string> = {
    sent: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    queued: 'bg-sky-50 text-sky-800 ring-sky-200',
    failed: 'bg-rose-50 text-rose-800 ring-rose-200',
    skipped: 'bg-slate-100 text-slate-600 ring-slate-200',
};

const STATUS_LABELS: Record<string, string> = {
    sent: 'Sent',
    queued: 'Queued',
    failed: 'Failed',
    skipped: 'No email on file',
};

export function EmailStatusBadge({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset',
                STATUS_STYLES[status] ?? STATUS_STYLES.skipped,
            )}
        >
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

export function formatEmailTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

type Props = {
    logs: EmailLogSummary[];
    canResend?: boolean;
    className?: string;
};

/**
 * Customer email delivery history for one job order, with Resend on the latest row per type.
 */
export default function CustomerEmailLog({
    logs,
    canResend = false,
    className,
}: Props) {
    const [resendingId, setResendingId] = useState<number | null>(null);

    const latestIdByType = new Map<string, number>();
    for (const log of logs) {
        const current = latestIdByType.get(log.type);
        if (current == null || log.id > current) {
            latestIdByType.set(log.type, log.id);
        }
    }

    function resend(log: EmailLogSummary) {
        setResendingId(log.id);
        router.post(
            `/email-logs/${log.id}/resend`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setResendingId(null),
            },
        );
    }

    return (
        <div className={cn('space-y-2', className)}>
            <p className="text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                Customer emails
            </p>
            {logs.length === 0 ? (
                <p className="text-xs text-slate-500">
                    No customer emails yet.
                </p>
            ) : (
                <ul className="divide-y divide-slate-100 rounded-md border border-slate-200 bg-white">
                    {logs.map((log) => {
                        const isLatest = latestIdByType.get(log.type) === log.id;
                        const showResend =
                            canResend &&
                            isLatest &&
                            (log.status === 'failed' || log.status === 'sent');

                        return (
                            <li
                                key={log.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs"
                                title={log.error ?? undefined}
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold text-slate-800">
                                        {log.type_label}
                                    </p>
                                    <p className="truncate text-slate-500">
                                        {log.to ?? '—'} ·{' '}
                                        {formatEmailTime(
                                            log.sent_at ?? log.created_at,
                                        )}
                                    </p>
                                    {log.status === 'failed' && log.error ? (
                                        <p className="mt-0.5 line-clamp-2 text-rose-700">
                                            {log.error}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <EmailStatusBadge status={log.status} />
                                    {showResend ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="h-7 px-2 text-xs"
                                            disabled={resendingId != null}
                                            onClick={() => resend(log)}
                                        >
                                            {resendingId === log.id
                                                ? 'Resending…'
                                                : 'Resend'}
                                        </Button>
                                    ) : null}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
