# Deployment gaps — NPPC Lab LMIS

Snapshot as of September 2026.

This file lists **verified** bugs, missing functions, and operational gaps that make the system imperfect for production. It is a go/no-go inventory, not a how-to guide.

- **How to deploy:** [DEPLOY.md](DEPLOY.md)
- **How the lab workflow works:** [SYSTEM_OVERVIEW.md](SYSTEM_OVERVIEW.md)

Only items checkable in code or docs are listed. Intentional out-of-scope product choices are called out separately so they are not treated as defects.

---

## Blockers

Must fix or complete before staff go-live.

### 1. Official controlled-form PDFs are not activated by seed

Seed creates form **shells** only. Job Order / RFA print and package/standalone **result** print need admin upload of the official PDF, Form Designer mapping, and activate. Without that, RFA download returns **422** and result overlays are unavailable.

**Evidence:** `database/seeders/DatabaseSeeder.php`, `app/Support/RfaPdfExporter.php`, `resources/forms/official/README.md`, `docs/CONTROLLED_FORMS_EXPANSION.md`

### 2. Default seed passwords are `password`

First install (`migrate --seed`) creates `admin@nppc.local`, `receiving@nppc.local`, `analyst@nppc.local`, `head@nppc.local` (and related accounts) with password `password`. Unsafe if left unchanged on a public host.

**Evidence:** `database/seeders/DatabaseSeeder.php`, `docs/DEPLOY.md`

---

## High

Security, data integrity, or broken critical paths.

### 3. Public Fortify registration is enabled

Unauthenticated users can open `/register` and create accounts (no role assignment in that flow). Unsuitable for a lab-only intranet unless intentionally wanted and hardened.

**Evidence:** `config/fortify.php` (`Features::registration()`), `tests/Feature/Auth/RegistrationTest.php`

### 4. Laravel starter welcome page is still the public entry

`welcome.tsx` is the default “Let’s get started” Laravel marketing page with Register links. Production should redirect guests to login (or a lab kiosk), not the starter kit.

**Evidence:** `resources/js/pages/welcome.tsx`, `resources/js/app.tsx` (`VITE_APP_NAME` fallback `'Laravel'`)

### 5. `nppc:reset-operational-data` has no production guard

Command deletes job orders / results / related PDFs; `--force` skips confirm. Also ensures demo analyst accounts. Running this on production is catastrophic.

**Evidence:** `app/Console/Commands/ResetOperationalDataCommand.php`

### 6. Unmatched tests fall back to DomPDF “Internal draft” sheets

When no analysis-result controlled form matches (package or type combination), the system can serve an individual DomPDF draft labeled internal / pending official form — **not** the official FO4/FO5 (or other) sheet. Easy to mistake for a released document if bindings are incomplete.

**Evidence:** `app/Services/AnalysisResultReportResolver.php`, `app/Support/AnalysisResultPdfExporter.php`, `docs/SYSTEM_OVERVIEW.md`

### 7. Result-release email can fail after status is already updated

`JobOrderService::sign()` sets `ready_for_pickup` / `reviewed_at`, then sends `ResultsReadyMail` synchronously with no try/catch or outbox. SMTP failure can 500 **after** the release is committed.

**Evidence:** `app/Services/JobOrderService.php` (`sign`), `docs/DEPLOY.md`

### 8. Admin Prices UI cannot edit `catalog_scope` or `result_mode`

Backend model and intake/analyst behavior depend on these fields. Admin → Procedures & prices has no controls; new procedures get defaults only. Ops cannot correctly mark Aqua/`both` or Pass/Fail encoding modes from the UI.

**Evidence:** `resources/js/pages/admin/prices.tsx` vs `app/Models/AnalysisType.php`

### 9. Intake (and receiving pricing) validation errors are weakly surfaced

Intake wizard mainly surfaces `errors.analysis_type_ids`; many customer/sample field failures lack inline `InputError`. Receiving pricing relies on flash toasts rather than field-level `form.errors`. Failed submits look like “nothing happened.”

**Evidence:** `resources/js/pages/intake/wizard.tsx`, `resources/js/pages/receiving/show.tsx`

### 10. Proxy / production-check / SQLite hygiene gaps

- `trustProxies(at: '*')` trusts all `X-Forwarded-*` proxies (`bootstrap/app.php`).
- `nppc:production-check` does **not** enforce MySQL 8 + Redis even though DEPLOY docs require them; `QUEUE_CONNECTION=database` can pass.
- `database/database.sqlite` is not gitignored — easy to commit a local DB.

**Evidence:** `bootstrap/app.php`, `app/Console/Commands/ProductionCheckCommand.php`, `.gitignore`, `.env.example`, `docs/DEPLOY.md`

---

## Medium

Incomplete admin/ops, UX, or reliability — not always show-stoppers, but not production-polished.

### 11. Head and History eager-load PDF iframes

Head show and History show fetch/embed controlled PDFs on page load. UI performance guidance prefers lazy preview (open dialog / user action). Heavy on first paint and bandwidth.

**Evidence:** `resources/js/pages/head/show.tsx`, `resources/js/pages/history/show.tsx`, `docs/UI_PERFORMANCE.md`

### 12. Queue realtime is a silent no-op without Reverb

If Echo/Reverb is unset, `useLabQueueRealtime` does nothing with no UI warning. Boards will not live-update; staff may think the app is broken.

**Evidence:** `resources/js/hooks/use-lab-queue-realtime.ts`, `resources/js/app.tsx`, `docs/DEPLOY.md`

### 13. Status heal / JO approval data migrations

Heal migration rewrites JO approval desync with empty `down()`. JO approval migration remaps `priced` → `pending_jo_approval`. Fine for upgrades once; unusual as ongoing schema story and signals prior production-shaped bugs.

**Evidence:** `database/migrations/2026_09_06_235700_heal_jo_approval_status_desync.php`, `database/migrations/2026_09_06_190000_add_jo_approval_to_job_orders.php`

### 14. Catalog and FO4/FO5 registry still “interim”

FO4/FO5 effective dates are `TBD` in the official catalog. Intake catalog audit answers (e.g. chemicals/reagents omitted, unclear prices) are marked overrideable — product decisions not fully locked.

**Evidence:** `app/Support/OfficialAnalysisCatalog.php`, `docs/INTAKE_CATALOG_AUDIT_QUESTIONS.md`

### 15. Dynamic food / matrix go-live still depends on admin PDF upload

Feature code and tests exist; demo/production still need the official matrix-capable PDF uploaded, bound, and activated. Plan DoD checkboxes in docs may lag the code.

**Evidence:** `docs/DYNAMIC_FOOD_ANALYSIS_RESULT_PDF.md`, `docs/DYNAMIC_CONTROLLED_FORMS.md`, `tests/Feature/DynamicTestMatrixReportTest.php`

### 16. Scheduler LIMS reminder is an empty stub

Scheduled “LIMS reminder” does nothing useful yet. Calibration / maintenance modules are not built.

**Evidence:** `routes/console.php`, `docs/DEPLOY.md`

### 17. `.env.example` is local-oriented

Defaults include SQLite, database session/queue/cache drivers, `MAIL_MAILER=log`, `APP_DEBUG=true`. Easy to mis-deploy if DEPLOY.md is skipped.

**Evidence:** `.env.example`

### 18. History show still framed as RFA-with-results

History detail embeds/labels the Job Order / RFA path. Official released document is the **analysis result** controlled form (analyst print after Head release). Risk of handing customers the wrong paper narrative from History.

**Evidence:** `resources/js/pages/history/show.tsx`, `docs/SYSTEM_OVERVIEW.md`

### 19. ~~Sidebar still says “Signing queue”~~ (fixed)

Sidebar now labels the module **Head Analysis**. The Head index uses primary tabs **JO approval** / **Results**.

**Evidence:** `resources/js/components/app-sidebar.tsx`, `resources/js/pages/head/index.tsx`

### 20. `other_tests` is notes-only

Intake free-text “other tests” can appear on RFA data but is not a billable/assigned analysis line. Easy for staff to assume it creates work queue items.

**Evidence:** `resources/js/pages/intake/wizard.tsx`, `docs/INTAKE_CATALOG_AUDIT_QUESTIONS.md`

### 21. Admin packages aqua visibility is not first-class in UI

Intake filters packages via server-side aqua/catalog visibility; package admin UI does not clearly manage that scope the way prices should manage `catalog_scope`.

**Evidence:** `resources/js/pages/admin/packages.tsx`

### 22. RFA PDF actor fallback to first user

If there is no request user and no receiver, exporter uses `User::query()->firstOrFail()` — fragile on empty or odd installs.

**Evidence:** `app/Support/RfaPdfExporter.php`

### 23. CI is SQLite in-memory only

PHPUnit does not exercise MySQL-specific behavior that production will use.

**Evidence:** `phpunit.xml`, `docs/DEPLOY.md`

### 24. No production APM / load-test story

Performance audit docs explicitly do not replace Sentry/Pulse/load tests.

**Evidence:** `docs/PERFORMANCE_AUDIT.md`

---

## Low

Polish, dead code, documentation debt.

### 25. Dead / unused frontend leftovers

- `RequestForAnalysisForm` default export largely unused (type-only imports remain)
- `PlaceholderPattern`, `FlashAlert` appear unused

**Evidence:** `resources/js/components/request-for-analysis-form.tsx`, `resources/js/components/ui/placeholder-pattern.tsx`, `resources/js/components/flash-alert.tsx`

### 26. No React ErrorBoundary

PDF failures are handled locally; uncaught render errors have no branded fallback.

### 27. Doc / price inconsistency

Non-technical FO5 setup copy vs seeder package prices (e.g. DW vs NDW amounts) can disagree.

**Evidence:** `docs/CONTROLLED_FORMS_NON_TECHNICAL.md`, `database/seeders/DatabaseSeeder.php`

### 28. Dynamic-food implementation plan checklists unchecked

Docs lag feature tests — documentation debt, not proof that matrix code is missing.

**Evidence:** `docs/DYNAMIC_FOOD_ANALYSIS_IMPLEMENTATION_PLAN.md`

### 29. Legacy RFA blueprint fallback

When `form_code` map is incomplete, controlled-form service can fall back to legacy blueprints.

**Evidence:** `app/Services/ControlledFormService.php`

---

## Known out of scope (intentional)

These are product decisions, not accidental omissions:

| Item | Notes |
| --- | --- |
| Payment / cashier / billing settlement | Outside LMIS; Receiving/Head copy says payment stays outside |
| Multi-customer samples on one JO | Documented questions only; model is one customer per JO |
| Home online customer submission | Kiosk-at-office intake only |
| Full LIMS instrument integration | Not in this system |
| Calibration / maintenance modules | Scheduler stub only |
| Chemicals / Reagents on customer intake | Intentionally omitted from kiosk catalog |
| Auto-seeding official controlled-form PDF binaries | Admin must upload from `resources/forms/official/` |
| Replacing uploaded PDF with Blade/HTML in production | Overlay path is canonical |

**Evidence:** `docs/SYSTEM_OVERVIEW.md`, `docs/MULTI_CUSTOMER_JOB_ORDER_QUESTIONS.md`, `docs/INTAKE_CATALOG_AUDIT_QUESTIONS.md`, `docs/CONTROLLED_FORMS.md`

---

## Suggested go-live checklist

1. Production `.env`: MySQL 8, Redis (cache/queue/session as per DEPLOY), real SMTP, `APP_DEBUG=false`, HTTPS.
2. `php artisan migrate --force --seed` **once** → change **all** seed passwords → never re-seed on routine deploys.
3. Disable or lock down public registration; replace starter welcome with login/kiosk entry.
4. Upload, map, and activate General + Aqua JO/RFA forms and every needed result form (FO4, FO5, food/micro standalone, etc.); smoke-test print/preview paths.
5. Confirm no unmatched jobs will ship DomPDF “Internal draft” sheets as customer documents.
6. Run Reverb + queue worker per DEPLOY; run `nppc:production-check`; treat check as necessary but not sufficient (MySQL/Redis still must be set manually).
7. Do **not** run `nppc:reset-operational-data` on production; add a guard before go-live if the command remains in the tree.
8. Back up database + `storage/app/private`.
9. Prefer fixing High items 7–9 (mail failure, admin catalog fields, validation UX) before heavy staff use.
10. Decide History vs analyst print story so customers receive the **result** form + Receiving JO copies, not RFA-as-results.

---

## Related docs

- [DEPLOY.md](DEPLOY.md) — install and server runbook
- [SYSTEM_OVERVIEW.md](SYSTEM_OVERVIEW.md) — workflow and module behavior
- [UI_PERFORMANCE.md](UI_PERFORMANCE.md) — frontend performance guardrails
- [CONTROLLED_FORMS.md](CONTROLLED_FORMS.md) / [CONTROLLED_FORMS_EXPANSION.md](CONTROLLED_FORMS_EXPANSION.md) — form registry and admin setup
