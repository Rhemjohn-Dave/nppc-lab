# UI Performance Guardrails

These rules keep workspace polish from making the app feel slower.

## Layout density
- Authenticated pages use **`LimsWorkspace`** / `limsPageShellFluid`; Intake uses `limsPageShellWide` (`max-w-[1680px]`).
- Prefer compact section gaps (`gap-4`), horizontal grids, and fewer nested cards over shrinking fonts.
- Shared primitives: `NextActionPanel`, `WorkflowTimeline`, `LimsSection` under `resources/js/components/lims/`.

## Queue and archive screens
- Receiving, Head Analysis, History, and Analyst use **server pagination**.
- Do not replace those lists with large always-mounted client tables.
- Search is **debounced** (~350ms). Prefer that over live filtering of already-paginated server data.
- Auto-refresh via Reverb (`LabQueueUpdated` → `router.reload` of list props) should not remount heavy row components or run extra animations on each update.
- Analyst result-entry, job-order sheet, and report preview pause live queue reloads while those surfaces are open (including complete/discard confirms).
- Analyst workspace is a **compact test work queue table** (not job-order cards); Job Order drawer and result modal hold detail. Completed results are view-only; corrections require Head return.

## Heavy views stay lazy
- PDF/report previews fetch only when opened.
- Overlay result preview: JSON manifest skips field-value mapping; filled overlay PDFs are cached ~5 minutes (cache key includes field layout + revision touch on Form Designer save). Responses use `Cache-Control: private, no-store`. Preview still uses a blob URL in the iframe (Chrome PDF viewers often skip iframe `load`, which can trap a covering spinner).
- The full printable Request for Analysis form is for print/review/detail pages, not dashboard cards or expandable queue rows.

## Payload size
- Analyst tasks are grouped and paged by job order on the server.
- Controlled Forms index does not send unused analysis catalogs.
- Prefer trimming Inertia props over adding more client-side filtering.

## Designer
- The Form Designer is a specialized full-viewport workspace.
- Keep field overlays, PDF rendering, and history snapshots local to that page.
- Do not embed the designer or full PDF canvas in other admin lists.

## Backend performance audit
- Run `php artisan nppc:performance-audit --seed` or `php artisan test --group=performance` after changes to dashboard payloads, queue controllers, or PDF generation.
- See `docs/PERFORMANCE_AUDIT.md` for budgets, duplicate-query warnings, and how to fix regressions.

## Loading and skeletons
- **Full page navigation** (sidebar links, pagination without `preserveState`) uses `NavigationLoadingShell` with a layout skeleton via `useInertiaNavigation` `isInitialLoading`.
- **Partial reloads** (queue filters, search with `preserveState`, Reverb `router.reload` with `only`) use `isRefreshing` only: the workspace stays visible and `WorkspaceHeader` shows a small “Updating workspace…” chip — no skeleton overlay or content fade.
- **PDF preview** uses `LoadingState` (branded spinner + document shimmer) — fetch only when the dialog opens.
- **Form Designer** and full PDF canvas pages do not use navigation skeletons; keep loading local to those workspaces.
- Prefer `NavigationLoadingShell` + shared `Spinner` / `Skeleton` over one-off spinners on queue and dashboard pages.
- Inertia top progress bar uses NPPC blue (`#1A3694`); configured in `resources/js/app.tsx`.
- `useInertiaNavigation` ignores **prefetch** visits (sidebar links use `prefetch` on hover). Prefetch must not trigger skeletons on the current page.
- Navigation state is shared via `InertiaNavigationProvider` in `layouts/app-layout.tsx` (inside the Inertia page tree) so queue pages and the loading shell never desync.

## Shared UI
- Queue screens reuse `WorkspaceHeader`, `QueueFilterBar`, `SummaryStat`, and `QueueRangeNote`.
- Analyst job sheet, status badges, and work-queue table live under `resources/js/components/analyst/`.
- Add visual polish through those primitives instead of copying heavier markup into each page.
