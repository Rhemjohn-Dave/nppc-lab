export type ReceivingOrderLike = {
    id: number;
    status: string;
    status_label?: string;
    reviewed?: boolean;
    reviewed_at?: string | null;
    jo_approved?: boolean;
    jo_approved_at?: string | null;
    jo_approver_name?: string | null;
    can_print_rfa?: boolean;
    can_receive?: boolean;
    created_at?: string | null;
    received_at?: string | null;
};

export type ReceivingNextAction = {
    title: string;
    steps: string[];
    primaryLabel: string;
    primaryHref: string;
    printHref: string | null;
    printLabel: string | null;
    emphasize: boolean;
};

export type TimelineStepState = 'done' | 'current' | 'todo';

export type ReceivingTimelineStep = {
    id: string;
    label: string;
    state: TimelineStepState;
    detail?: string | null;
};

const PRINT_SESSION_PREFIX = 'receiving:printed:';

export function printSessionKey(jobOrderId: number): string {
    return `${PRINT_SESSION_PREFIX}${jobOrderId}`;
}

export function hasPrintedThisSession(jobOrderId: number): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return sessionStorage.getItem(printSessionKey(jobOrderId)) === '1';
    } catch {
        return false;
    }
}

export function markPrintedThisSession(jobOrderId: number): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        sessionStorage.setItem(printSessionKey(jobOrderId), '1');
    } catch {
        // Ignore quota / private-mode failures.
    }
}

export function receivingStatusLabel(order: ReceivingOrderLike): string {
    if (order.reviewed || order.reviewed_at) {
        return 'Results released';
    }

    switch (order.status) {
        case 'draft_submitted':
            return 'Needs pricing';
        case 'pending_jo_approval':
        case 'priced':
            return 'Awaiting Head approval';
        case 'jo_approved':
            return 'JO approved';
        case 'in_analysis':
            return 'Sent to analysts';
        case 'pending_review':
            return 'In analysis / Head review';
        case 'ready_for_pickup':
            return 'Results released';
        default:
            return order.status_label || order.status;
    }
}

export function receivingStatusBadgeClass(status: string, reviewed = false): string {
    if (reviewed || status === 'ready_for_pickup') {
        return 'border-sky-200 bg-sky-50 text-sky-900';
    }

    if (status === 'jo_approved') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (status === 'pending_jo_approval' || status === 'priced') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    if (status === 'draft_submitted') {
        return 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700';
}

export function nextAction(
    order: ReceivingOrderLike,
    options?: { printedThisSession?: boolean },
): ReceivingNextAction {
    const printed = options?.printedThisSession ?? hasPrintedThisSession(order.id);
    const canPrint = Boolean(order.can_print_rfa);
    const canReceive = Boolean(order.can_receive);
    const printHref = canPrint
        ? `/receiving/${order.id}/print?copies=3`
        : null;
    const printLabel = canPrint
        ? printed || Boolean(order.reviewed || order.reviewed_at)
            ? 'Reprint JO ×3'
            : 'Print JO ×3'
        : null;

    if (order.reviewed || order.reviewed_at || order.status === 'ready_for_pickup') {
        return {
            title: 'Results released',
            steps: canPrint
                ? ['Reprint JO copies for the customer packet if needed.']
                : ['No Receiving action required.'],
            primaryLabel: 'Open',
            primaryHref: `/receiving/${order.id}`,
            printHref,
            printLabel,
            emphasize: false,
        };
    }

    if (order.status === 'draft_submitted') {
        return {
            title: 'Needs pricing',
            steps: ['Enter quantities and unit prices, then save.'],
            primaryLabel: 'Price',
            primaryHref: `/receiving/${order.id}`,
            printHref: null,
            printLabel: null,
            emphasize: false,
        };
    }

    if (
        order.status === 'pending_jo_approval' ||
        order.status === 'priced'
    ) {
        return {
            title: 'Awaiting Head JO approval',
            steps: [
                'Head must approve the Job Order before print or send.',
            ],
            primaryLabel: 'Open',
            primaryHref: `/receiving/${order.id}`,
            printHref: null,
            printLabel: null,
            emphasize: false,
        };
    }

    if (order.status === 'jo_approved' || canReceive) {
        return {
            title: 'JO approved',
            steps: [
                printed
                    ? 'JO print opened this session — send to analysts when ready.'
                    : 'Print 3 JO copies (customer, accounting, Head).',
                'Send to analysts from the Job Order screen.',
            ],
            primaryLabel: 'Open & Send →',
            primaryHref: `/receiving/${order.id}`,
            printHref,
            printLabel,
            emphasize: true,
        };
    }

    if (
        order.status === 'in_analysis' ||
        order.status === 'pending_review'
    ) {
        return {
            title: 'With analysts',
            steps: [
                'Analysis is in progress. Reprint JO if needed.',
            ],
            primaryLabel: 'Open',
            primaryHref: `/receiving/${order.id}`,
            printHref,
            printLabel,
            emphasize: false,
        };
    }

    return {
        title: receivingStatusLabel(order),
        steps: [],
        primaryLabel: 'Open',
        primaryHref: `/receiving/${order.id}`,
        printHref,
        printLabel,
        emphasize: false,
    };
}

export function timelineSteps(
    jobOrder: ReceivingOrderLike,
    options?: { printedThisSession?: boolean },
): ReceivingTimelineStep[] {
    const printed = options?.printedThisSession ?? hasPrintedThisSession(jobOrder.id);
    const status = jobOrder.status;
    const awaitingHead =
        status === 'pending_jo_approval' || status === 'priced';
    const needsPricing = status === 'draft_submitted';
    const joApproved =
        Boolean(jobOrder.jo_approved_at) ||
        status === 'jo_approved' ||
        ['in_analysis', 'pending_review', 'ready_for_pickup'].includes(status);
    const sentToAnalysts = [
        'in_analysis',
        'pending_review',
        'ready_for_pickup',
    ].includes(status);
    const released =
        Boolean(jobOrder.reviewed_at) || status === 'ready_for_pickup';
    const pricedOrBeyond =
        !needsPricing || Boolean(jobOrder.received_at);

    const steps: ReceivingTimelineStep[] = [
        {
            id: 'submitted',
            label: 'Request received',
            state: 'done',
            detail: jobOrder.created_at
                ? `Submitted ${jobOrder.created_at}`
                : null,
        },
        {
            id: 'priced',
            label: 'Price confirmed',
            state: needsPricing
                ? 'current'
                : pricedOrBeyond
                  ? 'done'
                  : 'todo',
            detail:
                !needsPricing && jobOrder.received_at
                    ? `Costing recorded ${jobOrder.received_at}`
                    : needsPricing
                      ? 'Enter and save pricing'
                      : null,
        },
        {
            id: 'head_approved',
            label: 'Head approved',
            state: awaitingHead
                ? 'current'
                : joApproved
                  ? 'done'
                  : needsPricing
                    ? 'todo'
                    : 'todo',
            detail: jobOrder.jo_approved_at
                ? `Approved ${jobOrder.jo_approved_at}${
                      jobOrder.jo_approver_name
                          ? ` · ${jobOrder.jo_approver_name}`
                          : ''
                  }`
                : awaitingHead
                  ? 'Waiting for Head JO approval'
                  : null,
        },
        {
            id: 'print',
            label: 'Print JO ×3',
            state: released || sentToAnalysts
                ? 'done'
                : joApproved
                  ? printed
                      ? 'done'
                      : 'current'
                  : 'todo',
            detail:
                joApproved && printed
                    ? '✓ JO print opened this session'
                    : joApproved
                      ? 'Print customer, accounting, and Head copies'
                      : null,
        },
        {
            id: 'send',
            label: 'Send to analysts',
            state: sentToAnalysts
                ? 'done'
                : joApproved
                  ? 'current'
                  : 'todo',
            detail: sentToAnalysts
                ? 'Assigned to analysts'
                : joApproved
                  ? 'Open the Job Order and send when copies are ready'
                  : null,
        },
        {
            id: 'analysis',
            label: 'Analysis',
            state: released
                ? 'done'
                : sentToAnalysts
                  ? 'current'
                  : 'todo',
            detail: null,
        },
        {
            id: 'released',
            label: 'Results released',
            state: released ? 'done' : 'todo',
            detail: jobOrder.reviewed_at
                ? `Released ${jobOrder.reviewed_at}`
                : null,
        },
    ];

    return steps;
}
