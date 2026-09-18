import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

type SharedFlash = {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
    toast?: FlashToast | null;
};

function showToast(type: FlashToast['type'], message: string): void {
    const text = message.trim();
    if (!text) {
        return;
    }

    toast[type](text);
}

function showSessionFlash(flash: SharedFlash | null | undefined): void {
    if (!flash) {
        return;
    }

    if (flash.toast?.message) {
        showToast(flash.toast.type, flash.toast.message);

        return;
    }

    if (flash.success) {
        showToast('success', flash.success);
    }
    if (flash.error) {
        showToast('error', flash.error);
    }
    if (flash.warning) {
        showToast('warning', flash.warning);
    }
    if (flash.info) {
        showToast('info', flash.info);
    }
}

/**
 * Surfaces Inertia flash messages as Sonner toasts.
 *
 * Uses router events only (no usePage) so it can run from the global
 * Toaster mounted via createInertiaApp `withApp`, outside the page tree.
 * Prefers explicit `Inertia::flash('toast', …)` over session success/error keys.
 */
export function useFlashToast(): void {
    const skipSessionFlash = useRef(false);

    useEffect(() => {
        const offFlash = router.on('flash', (event) => {
            const eventFlash = event.detail.flash as SharedFlash | undefined;
            const data = eventFlash?.toast;

            if (!data?.message) {
                return;
            }

            skipSessionFlash.current = true;
            showToast(data.type, data.message);
        });

        const offSuccess = router.on('success', (event) => {
            if (skipSessionFlash.current) {
                skipSessionFlash.current = false;

                return;
            }

            const flash = (event.detail.page.props as { flash?: SharedFlash }).flash;
            showSessionFlash(flash);
        });

        return () => {
            offFlash();
            offSuccess();
        };
    }, []);
}
