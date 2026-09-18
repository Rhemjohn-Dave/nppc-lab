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
    signed_today: CheckCircle2,
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

const valueTones: Record<DashboardKpi['tone'], string> = {
    default: 'text-[#1A3694]',
    success: 'text-emerald-800',
    warning: 'text-amber-800',
    info: 'text-sky-800',
};

type Props = {
    kpi: DashboardKpi;
    compact?: boolean;
};

export default function KpiCard({ kpi, compact = false }: Props) {
    const Icon = kpiIcons[kpi.key] ?? FlaskConical;
    const hint = kpi.hint ?? kpiHints[kpi.key];

    return (
        <div
            className={cn(
                'flex gap-3 rounded-xl border border-slate-200/80 bg-white shadow-sm',
                compact ? 'min-h-[5.5rem] p-3' : 'p-4',
            )}
        >
            <div
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-lg bg-[#eef3fb] text-[#1A3694]',
                    compact ? 'size-8' : 'size-10',
                )}
                aria-hidden="true"
            >
                <Icon className={compact ? 'size-4' : 'size-5'} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {kpi.label}
                </p>
                <p
                    className={cn(
                        'font-heading font-semibold',
                        compact ? 'mt-0.5 text-xl' : 'mt-1 text-2xl',
                        valueTones[kpi.tone],
                    )}
                >
                    {kpi.value}
                </p>
                {!compact && hint && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {hint}
                    </p>
                )}
                {kpi.href && kpi.value > 0 && (
                    <Link
                        href={kpi.href}
                        className={cn(
                            'inline-block text-xs font-medium text-[#365BB0] hover:underline',
                            compact ? 'mt-1' : 'mt-2',
                        )}
                    >
                        View →
                    </Link>
                )}
            </div>
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
                'grid gap-3',
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
