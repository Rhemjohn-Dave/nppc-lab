import { router } from '@inertiajs/react';
import { echoIsConfigured, useEcho } from '@laravel/echo-react';
import { createElement, Fragment, useCallback, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

type QueueRole = 'receiving' | 'analyst' | 'head';

/** Poll interval when Echo/Reverb is not configured (ms). */
export const LAB_QUEUE_POLL_MS = 30_000;

type LabQueueRealtimeProps = {
    role: QueueRole;
    only: string[];
    /** When true, skip reload (e.g. analyst dialog open / dirty form). */
    pause?: boolean | (() => boolean);
};

function isPaused(pause: LabQueueRealtimeProps['pause']): boolean {
    return typeof pause === 'function' ? pause() : Boolean(pause);
}

function useQueueReload(
    only: string[],
    pause: LabQueueRealtimeProps['pause'],
): () => void {
    const onlyRef = useRef(only);
    onlyRef.current = only;
    const pauseRef = useRef(pause);
    pauseRef.current = pause;

    return useCallback(() => {
        if (isPaused(pauseRef.current)) {
            return;
        }

        router.reload({ only: onlyRef.current });
    }, []);
}

function LabQueueEchoListener({
    role,
    only,
    pause,
}: LabQueueRealtimeProps): null {
    const onUpdated = useQueueReload(only, pause);

    useEcho(
        `lab.queue.${role}`,
        '.LabQueueUpdated',
        onUpdated,
        [role, onUpdated],
    );

    return null;
}

function LabQueuePollListener({
    only,
    pause,
}: Omit<LabQueueRealtimeProps, 'role'>): null {
    const onUpdated = useQueueReload(only, pause);

    useEffect(() => {
        const id = window.setInterval(onUpdated, LAB_QUEUE_POLL_MS);

        return () => window.clearInterval(id);
    }, [onUpdated]);

    return null;
}

/**
 * True when queue boards must poll because Vite/Echo has no Reverb key.
 * (Build-time: requires VITE_REVERB_APP_KEY in the asset bundle.)
 */
export function labQueueUsesPolling(): boolean {
    return !echoIsConfigured();
}

/**
 * Listen for LabQueueUpdated on the role’s private Reverb channel and
 * partially reload Inertia list props. When Echo is not configured, poll
 * instead so Analyst / Receiving / Head boards still update without F5.
 */
export function LabQueueRealtime(props: LabQueueRealtimeProps): ReactNode {
    const polling = labQueueUsesPolling();

    return createElement(
        Fragment,
        null,
        polling
            ? createElement(
                  'div',
                  {
                      className:
                          'shrink-0 border-b border-amber-200 bg-amber-50 px-4 py-1.5 text-center text-xs text-amber-950',
                      role: 'status',
                      'data-lab-queue-realtime': 'poll',
                  },
                  `Live updates offline — refreshing every ${LAB_QUEUE_POLL_MS / 1000}s. Start Reverb and set VITE_REVERB_* for instant queue updates.`,
              )
            : null,
        polling
            ? createElement(LabQueuePollListener, {
                  only: props.only,
                  pause: props.pause,
              })
            : createElement(LabQueueEchoListener, props),
    );
}
