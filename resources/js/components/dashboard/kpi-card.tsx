import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    Clock,
    FileCheck,
    FileText,
    FlaskConical,
    Inbox,
    Package,
    PenLine,
    RotateCcw,
    Shield,
    Wallet,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DashboardKpi } from './types';

const kpiIcons: Record<string, LucideIcon> = {
    active_forms: FileText,
    pending_revisions: FileCheck,
    draft_revisions: PenLine,
    audit_events_7d: Shield,
    new_requests: Inbox,
    needs_pricing: Inbox,
    awaiting_head: Clock,
    for_receiving: Package,
    ready_for_analysts: Package,
    received_today: CheckCircle2,
    reviewed: FileCheck,
    needs_action: AlertTriangle,
    in_progress: Clock,
    returned: RotateCcw,
    completed_today: CheckCircle2,
    waiting_review: ClipboardList,
    ready_to_sign: PenLine,
    returned_in_lab: RotateCcw,
    sales_today: Wallet,
    jobs_today: CheckCircle2,
};

const kpiHints: Record<string, string> = {
    needs_action: 'Assigned to you',
    in_progress: 'Started tests',
    returned: 'Needs correction',
    completed_today: 'Finished today',
    new_requests: 'Awaiting review',
    needs_pricing: 'Enter line prices',
    awaiting_head: 'JO with Head',
    for_receiving: 'Ready for analysts',
    ready_for_analysts: 'Print ×3 then send',
    waiting_review: 'Pending signature',
    ready_to_sign: 'Awaiting your sign-off',
};

const iconWells: Record<DashboardKpi['tone'], string> = {
    default: 'bg-nppc-50 text-nppc-900',
    success: 'bg-emerald-50 text-emerald-600',
    warning: 'bg-amber-50 text-amber-600',
    info: 'bg-violet-50 text-violet-600',
};

const badgeTones: Record<DashboardKpi['tone'], string> = {
    default: 'text-nppc-900',
    success: 'text-emerald-600',
    warning: 'text-amber-600',
    info: 'text-violet-600',
};

type Props = {
    kpi: DashboardKpi;
    compact?: boolean;
};

export default function KpiCard({ kpi, compact = false }: Props) {
    const Icon = kpiIcons[kpi.key] ?? FlaskConical;
    const hint = kpi.hint ?? kpiHints[kpi.key];
    const showLink = Boolean(kpi.href);

    return (
        <div
            className={cn(
                'rounded-xl border border-slate-200 bg-white shadow-xs transition-colors hover:border-slate-300',
                compact ? 'p-4' : 'p-4',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                    {kpi.label}
                </span>
                <div
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-md',
                        iconWells[kpi.tone],
                    )}
                    aria-hidden="true"
                >
                    <Icon className="size-4" />
                </div>
            </div>

            <div className="mt-2 flex items-baseline gap-2">
                <span className="font-heading text-2xl font-extrabold tracking-tight tabular-nums text-slate-900">
                    {kpi.value}
                </span>
                {kpi.badge ? (
                    <span
                        className={cn(
                            'text-xs font-medium',
                            badgeTones[kpi.tone],
                        )}
                    >
                        {kpi.badge}
                    </span>
                ) : null}
            </div>

            {hint ? (
                <p className="mt-1 text-xs text-slate-500">{hint}</p>
            ) : null}

            {(showLink || kpi.footnote) && (
                <div className="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-2.5 text-xs">
                    {showLink && kpi.href ? (
                        <Link
                            href={kpi.href}
                            className="inline-flex items-center gap-1 font-medium text-nppc-900 hover:underline"
                        >
                            View
                            <span aria-hidden="true">→</span>
                        </Link>
                    ) : (
                        <span className="text-slate-400">—</span>
                    )}
                    {kpi.footnote ? (
                        <span className="font-mono text-[10px] font-semibold text-slate-400">
                            {kpi.footnote}
                        </span>
                    ) : null}
                </div>
            )}
        </div>
    );
}

export function KpiGrid({
    kpis,
    compact = false,
}: {
    kpis: DashboardKpi[];
    compact?: boolean;
}) {
    if (kpis.length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'grid gap-4',
                compact
                    ? 'grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4'
                    : 'sm:grid-cols-2 xl:grid-cols-4',
            )}
        >
            {kpis.map((kpi) => (
                <KpiCard key={kpi.key} kpi={kpi} compact={compact} />
            ))}
        </div>
    );
}
