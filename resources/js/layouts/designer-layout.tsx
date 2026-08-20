import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Full-viewport layout for the Form Designer.
 *
 * Unlike AppSidebarLayout which uses SidebarInset (min-h-svh, grows with
 * content), this layout gives the content area exactly the viewport height
 * with overflow hidden so the three-panel designer workspace never creates
 * a page-level scrollbar.
 */
export default function DesignerLayout({ children }: { children: ReactNode }) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            {/* Mirrors SidebarInset but uses h-svh + overflow-hidden instead of min-h-svh */}
            <main
                className={cn(
                    'bg-background relative flex h-svh max-w-full flex-1 flex-col overflow-hidden',
                    'peer-data-[variant=inset]:h-[calc(100svh-(--spacing(4)))]',
                    'md:peer-data-[variant=inset]:m-2 md:peer-data-[variant=inset]:ml-0',
                    'md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow-sm',
                    'md:peer-data-[variant=inset]:peer-data-[state=collapsed]:ml-0',
                )}
            >
                {children}
            </main>
        </AppShell>
    );
}
