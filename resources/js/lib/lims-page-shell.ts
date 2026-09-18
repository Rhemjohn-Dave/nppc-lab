/**
 * Shared LIMS page layout tokens.
 *
 * Authenticated operational pages (queues, detail, admin lists): use Fluid
 * inside the app sidebar scroll area (`LimsWorkspace` / `limsPageShellFluid`).
 * Public Intake and wide admin dashboards: use Wide (`limsPageShellWide`).
 */

export const limsPageBackground = 'bg-[#f4f7fb]';

/** Wide content column (admin dashboard / public intake). */
export const limsPageShellWide =
    'mx-auto flex w-full max-w-[1680px] flex-col gap-4 px-4 sm:px-5 lg:px-6 xl:px-8';

/** Full-bleed fluid column inside the app sidebar scroll area. */
export const limsPageShellFluid = 'flex w-full flex-col gap-4 p-4';

/** Compact section stack gap (16px). */
export const limsSectionGap = 'gap-4';

/** Compact panel padding. */
export const limsPanelPadding = 'p-4';

/**
 * Sticky footer bleed so the bar spans the full white workspace card
 * when the card uses limsPageShellWide horizontal padding.
 */
export const limsStickyFooterBleed =
    '-mx-4 px-4 sm:-mx-5 sm:px-5 lg:-mx-6 lg:px-6 xl:-mx-8 xl:px-8';
