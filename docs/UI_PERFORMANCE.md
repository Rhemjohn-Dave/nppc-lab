# UI Performance Guardrails

These rules keep workspace polish from making the app feel slower.

## Queue and archive screens
- Receiving, Head Analysis, History, and Analyst use **server pagination**.
- Do not replace those lists with large always-mounted client tables.
- Search is **debounced** (~350ms). Prefer that over live filtering of already-paginated server data.
- Auto-refresh via Reverb (`LabQueueUpdated` → `router.reload` of list props) should not remount heavy row components or run extra animations on each update.
- Analyst result-entry, job-order sheet, and report preview pause live queue reloads while those surfaces are open (including complete/discard confirms).
- Analyst workspace is a **compact test work queue table** (not job-order cards); Job Order drawer and result modal hold detail. Completed results are view-only; corrections require Head return.

## Heavy views stay lazy
- PDF/report previews fetch only when opened.
- The full printable Request for Analysis form is for print/review/detail pages, not dashboard cards or expandable queue rows.

## Payload size
- Analyst tasks are grouped and paged by job order on the server.
- Controlled Forms index does not send unused analysis catalogs.
- Prefer trimming Inertia props over adding more client-side filtering.

## Designer
- The Form Designer is a specialized full-viewport workspace.
- Keep field overlays, PDF rendering, and history snapshots local to that page.
- Do not embed the designer or full PDF canvas in other admin lists.

## Shared UI
- Queue screens reuse `WorkspaceHeader`, `QueueFilterBar`, `SummaryStat`, and `QueueRangeNote`.
- Analyst job sheet, status badges, and work-queue table live under `resources/js/components/analyst/`.
- Add visual polish through those primitives instead of copying heavier markup into each page.
