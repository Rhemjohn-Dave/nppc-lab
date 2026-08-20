import { router } from '@inertiajs/react';
import { echoIsConfigured, useEcho } from '@laravel/echo-react';
import { createElement, useCallback, useRef } from 'react';
import type { ReactNode } from 'react';

type QueueRole = 'receiving' | 'analyst' | 'head';

type LabQueueRealtimeProps = {
    role: QueueRole;
    only: string[];
    /** When true, skip reload (e.g. analyst dialog open). */
    pause?: boolean | (() => boolean);
};

function LabQueueRealtimeListener({
    role,
    only,
    pause,
}: LabQueueRealtimeProps): null {
    const onlyRef = useRef(only);
    onlyRef.current = only;
    const pauseRef = useRef(pause);
    pauseRef.current = pause;

    const onUpdated = useCallback(() => {
        const paused = pauseRef.current;
        if (typeof paused === 'function' ? paused() : paused) {
            return;
        }

        router.reload({ only: onlyRef.current });
    }, []);

    useEcho(
        `lab.queue.${role}`,
        '.LabQueueUpdated',
        onUpdated,
        [role, onUpdated],
    );

    return null;
}

/**
 * Listen for LabQueueUpdated on the role’s private Reverb channel and
 * partially reload Inertia list props (replaces timed queue polling).
 */
export function LabQueueRealtime(props: LabQueueRealtimeProps): ReactNode {
    if (!echoIsConfigured()) {
        return null;
    }

    return createElement(LabQueueRealtimeListener, props);
}
