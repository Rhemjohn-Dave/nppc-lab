import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { limsPageShellFluid } from '@/lib/lims-page-shell';

type Props = {
    children: ReactNode;
    className?: string;
};

/**
 * Authenticated LIMS workspace — fluid column inside the sidebar scroll area.
 * Prefer this over ad-hoc `flex flex-col gap-5 p-4` wrappers.
 */
export default function LimsWorkspace({ children, className }: Props) {
    return (
        <div className={cn(limsPageShellFluid, className)}>{children}</div>
    );
}
