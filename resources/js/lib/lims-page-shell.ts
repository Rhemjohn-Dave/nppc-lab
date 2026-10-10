/**
 * Shared LIMS page layout + brand class tokens.
 *
 * Authenticated operational pages (queues, detail, admin lists): use Fluid
 * inside the app sidebar scroll area (`LimsWorkspace` / `limsPageShellFluid`).
 * Public Intake and wide admin dashboards: use Wide (`limsPageShellWide`).
 *
 * Brand colors live in `resources/css/app.css` (`nppc-*`). Prefer those
 * utilities over raw hex in new code.
 *
 * Shadow tiers:
 * - Queue / list shells (`limsQueueShell`) → `shadow-xs`
 * - General settings panels (`limsPanel`) → `shadow-sm`
 */

/** Page canvas — matches `--color-nppc-page` / body background. */
export const limsPageBackground = 'bg-nppc-page';

/** Wide content column (admin dashboard / public intake). */
export const limsPageShellWide =
    'mx-auto flex w-full max-w-[1680px] flex-col gap-5 px-4 sm:px-5 lg:px-6 xl:px-8';

/**
 * Public intake wizard — tracks the monitor with minimal side gutters
 * (does not change admin dashboard `limsPageShellWide`).
 */
export const limsIntakeShell =
    'mx-auto flex w-full max-w-[1920px] flex-col gap-3 px-3 sm:px-4 lg:px-5 xl:px-6';

/** Full-bleed fluid column inside the app sidebar scroll area. */
export const limsPageShellFluid = 'flex w-full flex-col gap-5 p-4 sm:p-6';

/** Compact section stack gap (20px). */
export const limsSectionGap = 'gap-5';

/** Compact panel padding. */
export const limsPanelPadding = 'p-4';

/** Standard white content panel (settings / forms). */
export const limsPanel =
    'rounded-xl border border-slate-200 bg-white shadow-sm';

/** Queue / list table or KPI shell (Analyst-reference chrome). */
export const limsQueueShell =
    'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs';

/** Primary page H1 — matches WorkspaceHeader. */
export const limsTitle =
    'font-heading text-xl font-semibold text-nppc-900';

/** Inline text links / secondary actions. */
export const limsLink = 'font-medium text-nppc-900 hover:text-nppc-700';

/**
 * Sticky footer bleed so the bar spans the full white workspace card
 * when the card uses limsPageShellWide horizontal padding.
 */
export const limsStickyFooterBleed =
    '-mx-4 px-4 sm:-mx-5 sm:px-5 lg:-mx-6 lg:px-6 xl:-mx-8 xl:px-8';
