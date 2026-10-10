import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    processingLabel?: string;
    variant?: 'default' | 'destructive';
    processing?: boolean;
    error?: ReactNode;
    onConfirm: () => void;
    children?: ReactNode;
};

/**
 * Confirm modal for destructive / irreversible actions.
 * Uses Dialog (not AlertDialog) so it can safely stack over an already-open Dialog
 * (e.g. Analyst encode-result → Complete confirm).
 */
export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    processingLabel = 'Working…',
    variant = 'default',
    processing = false,
    error = null,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (processing && !next) {
                    return;
                }

                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>

                {children}

                {error ? (
                    <p className="text-sm text-rose-700" role="alert">
                        {error}
                    </p>
                ) : null}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={
                            variant === 'destructive' ? 'destructive' : 'default'
                        }
                        disabled={processing}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            if (processing) {
                                return;
                            }
                            onConfirm();
                        }}
                    >
                        {processing ? processingLabel : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
