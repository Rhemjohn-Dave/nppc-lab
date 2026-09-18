# NPPC Laboratory Management System Overview

## Purpose of This Document
This document gives a full overview of the system for both:
- non-technical staff who need to understand how the platform is used
- technical staff who need to understand how the modules, roles, and workflows are implemented

It covers:
- user roles and responsibilities
- major modules of the system
- end-to-end business flow
- controlled forms flow
- technical architecture

Operational rules described here match the lab as of September 2026: Head **JO approval** after costing (before print / send to analysts), package designated analysts, least-busy **soft** assignment when sending to analysts (qualified analysts may encode teammate-suggested lines on shared PCs; encode takes ownership), Send to Head then Head **result** release, wet-sign paper after dates are frozen, Receiving printing JO/RFA copies after JO approval (and reprints after result release), and controlled-form binding for RFA (global), package result sheets, and standalone-test sheets (with package member opt-out printing as `-`). Payment / cashier settlement stays outside LMIS.

---

## Part 1. Non-Technical Overview

### What the System Is
The NPPC Laboratory Management System is used to manage the complete laboratory workflow from customer submission up to result release, review, and document generation.

In simple terms, the system helps the laboratory:
- receive customer requests
- record samples and requested analyses (including analysis packages)
- assign work fairly among analysts who are qualified for each test
- track progress of laboratory testing
- preview official result overlays on screen
- print Request for Analysis (RFA) and result forms at the right time
- release results in a controlled and traceable way

**Ready for pickup** is based only on **released results**. The Job Order / RFA print does not change that status. When the customer collects results, they receive two papers: the wet-signed result form and the JO/RFA that Receiving printed after Head JO approval (and may reprint after result release).

### Main Users of the System
The system has four main user groups:
- Admin
- Receiving
- Analyst
- Head Analysis

Each group has its own responsibilities.

### 1. Admin
Admin users manage the setup and control side of the system.

They are responsible for:
- managing user accounts
- managing procedures and prices
- managing **analysis packages** (which tests are included, and who is the designated analyst / signatory; the linked result form is shown read-only here)
- assigning analysts to analysis types (who may encode each test)
- managing who can access history
- managing control numbers
- managing controlled forms and revisions: **RFA** (Job Order), **package result sheets** (FO4, FO5, and future uploads), and **standalone test** result sheets
- reviewing controlled forms, print history, and audit logs

Admins can also access the other operational areas of the system when needed.

### 2. Receiving
Receiving staff handle submitted job orders after customers send requests.

They are responsible for:
- reviewing submitted job orders
- checking sample and analysis details
- updating prices and quantities (costing)
- waiting for **Head JO approval** before printing or sending to analysts
- printing **3 JO/RFA copies** after JO approval (customer, accounting, Head file) and wet-signing the working copy
- **sending the job to analysts** (assigns tests; starts analysis — samples were already on hand at intake)
- reprinting JO/RFA copies after Head has **released results** (customer packet)

Receiving is the bridge between intake and laboratory processing. JO/RFA print unlocks after Head approves the Job Order (`jo_approved`). Payment proof ("receipt") is handled outside LMIS. Ready for pickup is never set by RFA print.

### 3. Analyst
Analysts encode the laboratory work suggested to them or available via their Admin Assignments qualifications.

They are responsible for:
- viewing analyses assigned to them (per test)
- entering test results (including Passed/Failed where the catalog requires it)
- saving drafts while work is ongoing
- completing analysis lines
- the **designated package analyst** consolidating the job and sending it to Head when every result is encoded
- **previewing** the filled result form on screen before Send to Head
- **correcting** encoded results and the **printed analyst name/PRC** after Send to Head and after Head Release (before wet-sign print), without waiting for Head to return the line
- **printing** the dated result form only after Head Release, then wet-signing it with Head

Print and download of the official result sheet stay off until Head releases. Preview stays on so the designated analyst can check the overlay before sending. Head **return** still sends lines back into the analysis queue when a full re-encode cycle is needed; routine value/signatory fixes before print do not require return.

Analysts encode lines suggested to them or any open line for types they are qualified for (Admin → Assignments), after Receiving has sent the job to analysts. First encode takes ownership of the line; later corrections keep that assignee.

### 4. Head Analysis
Head Analysis has **two distinct checkpoints**:

1. **Approve Job Order** (Step 1) — after Receiving costing; unlocks JO print ×3 and send to analysts
2. **Release results** (Step 4) — after analysts encode and Send to Head; sets Ready for pickup

They are responsible for:
- approving priced Job Orders so Receiving can print and start analysis
- reviewing encoded results on the job screen
- releasing completed result outputs (system “sign / release”)
- returning analyses for correction when needed
- reviewing completed records in history

Head does **not** print the RFA (Receiving does). After result Release:
- the job becomes Ready for pickup
- Report date and Release date on the result sheet fill from that moment (`Asia/Manila`)
- the customer email still goes out if an email is on file
- Receiving reprints the reviewed RFA
- the designated analyst prints the dated result form for wet signatures

Ink on paper is the legal signature. System Release freezes dates; it does not draw a signature image.

---

## Main System Modules

### 1. Public Intake (kiosk)
Customer requests begin here.

The intake area captures:
- customer details (lookup from saved unique customers or a previous job)
- **sample classification first** (Aqua vs Potability / Wastewater / Agriculture / Academic / Others) — this chooses General vs Aqua Job Order and **strictly filters** packages/tests
- sample information (**Sample type** = water/soil/food/prawn; not the PDF dynamic test matrix)
- requested analyses from the **NPPC price-list catalog**, including **analysis packages**
- optional **standalone tests** that are not part of a package
- for **Wastewater**, a **Physico-chem panel** preset (`LSP-7.8-FO2` Issue 18) that selects the panel’s tests as **individual-pay** lines (not an analysis package; members can be unchecked)

**Aqua scope:** when classification is Aqua, only aquaculture packages/tests (plus shared `both` items such as Metals and Chloramphenicol) appear. Non-Aqua classifications hide Aqua-only Soil/Water/Prawn packages.

Selecting a package adds its members by default. The customer may **uncheck** package tests they do not need. Unchecked members stay on the package’s result form as `-`; only checked tests become work lines for Receiving and analysts. Selecting a package does **not** assign the package signatory to every line. Assignment happens at Receiving.

After submission, the job enters the internal workflow and the customer is remembered for later kiosk lookup.

### 2. Dashboard
The dashboard gives a high-level summary of system activity: counts by status, recent job orders, and role-based next actions.

### 3. Receiving Module
Used to process new jobs: price → await Head JO approval → print JO ×3 → send to analysts; later reprint after result release.

It handles:
- queue: needs pricing / awaiting Head JO / ready for analysts / results released
- pricing (status `pending_jo_approval`)
- print JO/RFA after `jo_approved` (default 3 copies: customer, accounting, Head file; max 20)
- send to analysts only when status is `jo_approved`
- **Results released** list: reprint JO copies after Head result release

JO/RFA print unlocks after Head JO approval (`jo_approved_at` / status). Payment stays outside LMIS.

### 4. Analyst Module
Used to encode assigned tests and, for the designated analyst, to send the job to Head.

The workspace is a **compact test work queue**: clickable summary counts, filters/sort, then a sticky-header table of assigned tests. Enter Result opens the result modal directly. When every line is complete and the user may submit, the row’s primary action is **Send to Head** (opens analyst name/PRC when a result form is bound). Preview stays secondary; the Job Order reference icon opens a side sheet for full context. `?job={id}` deep-links open that sheet (e.g. from assignment notifications).

It handles:
- task lists (your lines only, unless admin)
- drafts and completion
- **Send to Head** (package signatory, after every line is complete)
- **Preview result form** on screen once all matched results are complete
- **Print result form** after Head Release (dated overlay; Print/Download unlock then)

### 5. Head Analysis Module
Used for **JO approval** (after costing) and later to review and release **results**.

The sidebar uses a **parent/child** nav: **Head Analysis** → **JO approval** (`/head/jo`) and **Results** (`/head/results`). `/head` redirects to JO approval.

Each list has reference tables with Open / Download for the JO PDF (JO approval → Approved) or analysis result form (Results when the overlay is ready). Head download is for review only — Receiving still prints JO ×3; analysts print dated results after release.

It handles:
- JO approval queue (`pending_jo_approval`) — Approve JO unlocks Receiving print / send to analysts
- Approved JO archive (view/download JO form)
- unsigned results queue (Pending review)
- signed/released jobs today (view/download result form)
- return selected lines for correction
- Release results: Ready for pickup, customer email, Report/Release dates
- on-screen **analysis result** controlled form for result release (preview before Release; dated print after)
- JO approval screen shows Job Order / RFA only (not the result sheet)

Do not confuse **Approve JO** with **Release results**.

### 6. History Module
Searchable completed (ready-for-pickup) jobs. Access depends on Admin → History access.

### 7. Admin Management Modules
- users
- procedures and prices
- analysis packages (tests + designated analyst; linked result form is display-only)
- analyst assignments (who may encode which type)
- history access
- control numbers

### 8. Controlled Forms and Document Control
Official printable laboratory forms (upload-first PDF overlay).

Includes:
- controlled form registry
- revision management and Form Designer
- print history
- audit logs

**Where forms are tagged (always on Controlled Forms, not on Packages):**

| Upload | Category / binding | When used |
| --- | --- | --- |
| RFA | Job Order (no package) | Every job — Receiving / History RFA print |
| Package result sheet (FO4, FO5, …) | Analysis Result → select **Package** | Whenever that package is on the job (full or partial members) |
| Standalone result sheet | Analysis Result → select analysis type(s) only | Job’s selected tests match that type set and no package form already applies |

Official FO4 / FO5 PDFs live under `resources/forms/` and must still be uploaded, bound to the matching package, mapped, and activated. Other result PDFs follow the same upload → bind → map → activate path.

---

## End-to-End Business Flow

Completing tests does **not** make a job Ready for pickup. Encoding stays In analysis until the designated analyst sends the job to Head, and Head releases the results.

### Step 1. Customer submits a request
Intake (kiosk) captures customer, samples, packages and/or standalone tests. For a package, members start checked; the customer may uncheck some. The system creates a job order, assigns a reference number, stores only **checked** tests as analysis lines, records waived package members for the result sheet, and stores a unique customer for later lookup.

### Step 2. Receiving prices the request
Receiving updates quantities and prices. The job becomes **Pending JO approval**.

### Step 3. Head approves the Job Order
Head reviews costing and clicks **Approve JO**. That:
- sets `jo_approved_at` / `jo_approved_by`
- status becomes **JO approved**
- unlocks Receiving JO/RFA print (default 3 copies) and **Send to analysts**

Payment (cashier / billing) stays **outside** LMIS after print.

### Step 4. Receiving prints JO copies and sends to analysts
Receiving prints 3 JO copies (customer, accounting, Head file), then clicks **Send to analysts**. Samples were already captured at intake; this step:
- records lab-acceptance time (`received_at` / Receipt time on result sheets, Asia/Manila)
- moves the job to In analysis
- **suggests each test line** to a qualified analyst (soft `assigned_to`)

Assignment uses Admin → Assignments. Among people checked for that test type, the line is **suggested** to whoever has the **fewest open tasks** (`assigned_to`). Within the same send, counts update after each line so a multi-test package is spread when more than one person can do those tests. If nobody is qualified, the line stays unassigned. The package signatory is **not** used at this step.

`assigned_to` is a soft suggestion for shared lab PCs: any analyst qualified for that analysis type may open and encode the line. The first person who saves a draft or completes it takes ownership (`assigned_to` updates to them). Unqualified analysts stay blocked.

### Step 5. Analysts encode results
Each analyst sees lines suggested to them **and** other open lines for types they are qualified for. Drafts and completes. Completing a line does **not** email the customer and does **not** send the job to Head.

Encode fields:
- **Result value** is always required.
- **Pass / Fail** is required for FO5 (and other non-FO4 result sheets); FO4 (`LSP-7.8-FO4`) hides it and prints the measured MPN only.
- **Method** prefills from Procedures (`analysis_types.method`) and stays editable but optional. FO4 coliforms seed as `Multiple Tube Fermentation Technique* 9221, SMEWW`.

### Step 6. Designated analyst sends to Head
When every line is complete:
- the designated package analyst previews the result form on screen
- they click **Send to Head**
- the job becomes **Pending review**
- Head is notified in-app

If the job has no package, any assigned analyst on that job may send it. Print and download of the official result PDF stay disabled.

### Step 7. Head reviews and releases results
Head reviews encoded values. They may return selected lines **before release** (job returns to In analysis). After release, return is locked.

When they click Release:
- status becomes **Ready for pickup** (results only; JO print has no bearing)
- `reviewed_at` is set (Report date and Release date on the result sheet)
- the customer email is sent if an address exists

### Step 8. Paper packet (wet signatures)
1. Designated analyst prints the **dated result form**, wet-signs it, and Head wet-signs it.
2. Receiving may reprint the **JO/RFA** for the customer packet if needed.
3. The customer collects both papers with the result.

### Step 9. History
Ready-for-pickup jobs remain searchable in History.

---

## Two documents (results vs RFA)

| Document | Who prints in the system | When | What it means |
| --- | --- | --- | --- |
| Result form (package sheet or standalone sheet) | Designated analyst (package) or assigned analyst | After Head result Release | Released results; dates filled; then wet-signed. Package sheets keep all slots; unchecked members print as `-` |
| JO / RFA | Receiving | After Head **JO approval** (reprint after result release) | Job Order copies for customer / accounting / Head; not a released results document |

Head **Approve JO** unlocks print / send to analysts. Head **Release results** unlocks Ready for pickup and result PDF print. Head does not print the RFA.

---

## Controlled Forms Flow

### What Controlled Forms Are
Controlled Forms are official documents whose layout must be preserved.

Examples:
- Request for Analysis (RFA)
- package result forms (FO4, FO5, and any future package sheets you upload)
- standalone-test result forms for tests sold outside packages

### How Controlled Forms Work
1. Admin creates the controlled form record.
2. Admin uploads the source PDF (or the file from `resources/forms/`).
3. The system prepares a canonical PDF.
4. Admin opens the Form Designer (default field font 11).
5. Admin maps fields and binds the form:
   - **Job Order** → dual RFA templates (`job_order_variant`): **General** LSP 7.1 FO1 Issue 11 (`NPPC-LAB-FRM-001`) or **Aqua** LSP 7.1 FO4 Issue 03 (`NPPC-LAB-FRM-AQUA`), selected by sample classification via `ControlledForm::jobOrderFormFor($job)`. RFA PDF download uses the active controlled-form overlay only (no DomPDF Blade fallback).
   - **Analysis Result + Package** → that package’s result sheet; still used when the customer unchecks some package tests
   - **Analysis Result + analysis types only** → standalone test sheet (no package); at most one form per exact type set
6. Admin saves the layout, previews, and activates the revision.
7. The active revision is used for real overlay generation.

**Rules that matter in daily use:**
- Tag forms only under Admin → Controlled Forms. Packages admin only **shows** the linked result form (read-only).
- At most one Analysis Result form may be bound to a given package.
- Runtime resolve: job package → bound form first; otherwise exact standalone type combination; otherwise individual DomPDF fallback.
- Unchecked package members print as `-` on the package sheet; FO4 prints measured MPN values (no Pass/Fail encode); FO5 prints measured + Interpretation Pass/Fail after encoding and release rules.

Printed server timestamps (receipt, examination, report/release) use `Asia/Manila`. Sampling date/time stay as entered on the kiosk.

### Why This Matters
Official layouts stay consistent, revisions are tracked, values land in the correct positions, and selling a package always drives the correct result sheet even when the customer opts out of some members.

### Important Note About PDF Sizes
The system uses the actual uploaded PDF page size (A4, short bond, long/folio). Design on the real sheet before activation.

---

## Good Practice for Non-Technical Staff
- Always verify the uploaded form before using it in the designer.
- Always preview before activating a revision.
- If the official document layout changes, create or update the revision.
- Do not assume an old field layout will still match a changed PDF.
- Treat the active revision as the approved version for use in operations.
- Bind package result sheets to the **package** on Controlled Forms; bind standalone sheets to the analysis type(s); leave RFA as Job Order only.
- Preview the result overlay before Send to Head; print it only after Head Release so dates are on the paper.
- Do not treat the RFA as a released results document.
- On the kiosk, unchecked package tests are intentional opt-outs and will appear as `-` on the package result form.

---

## Part 2. Technical Overview

## Core Technology Stack

### Backend
- `Laravel 13`
- `PHP`
- `MySQL 8` for production
- `SQLite` as local default
- `Spatie Laravel Permission`
- `FPDI + TCPDF` for PDF import and output generation
- `DomPDF`
- `Laravel Excel`

### Frontend
- `Inertia.js`
- `React 19`
- `TypeScript`
- `Tailwind CSS 4`
- `shadcn/ui`

### Infrastructure / Operations
- `Redis` for cache, queues, and sessions in production
- `Supervisor` for queue workers
- `Nginx + PHP-FPM`
- scheduled tasks via cron

---

## Technical User Roles and Access Model

### Roles
- `admin`
- `receiving`
- `analyst`
- `head_analysis`

### Access Behavior
- `admin` is allowed through role checks across workspaces
- `receiving` is limited to intake-processing, send to analysts (after JO approval), and JO/RFA print after JO approval
- `analyst` encodes suggested or qualified open lines (soft assignment; encode takes ownership); package signatory may preview combined overlays and submit for review; official print/download requires `reviewed_at`
- `head_analysis` approves Job Orders after costing, then reviews Pending review jobs and releases results; RFA print routes on Head return 403 (Receiving prints RFA)

### History Access
Configurable (Admin → History access). Default orientation: `admin` and `head_analysis`; may be granted to `receiving` or `analyst`.

---

## Job statuses (results path)

| Status | Meaning |
| --- | --- |
| `draft_submitted` | Intake submitted |
| `pending_jo_approval` | Receiving saved pricing; waiting for Head JO approval |
| `jo_approved` | Head approved JO; Receiving may print ×3 and send to analysts |
| `priced` | Legacy alias after old costing (migrated to `pending_jo_approval`) |
| `in_analysis` | Sent to analysts; encoding (or returned from Head) |
| `pending_review` | Designated analyst sent to Head for result release |
| `ready_for_pickup` | Head released results (`reviewed_at` set) |

Completing all lines does **not** leave `in_analysis`. Submit-for-review is a separate action.

---

## Technical Module Map

### 1. Intake Module
Creates the job order, samples, analysis lines for **selected** tests only, and optional package links. Upserts `customers` for kiosk lookup. Package selection stores `job_order_packages` with `selected_type_ids` / `waived_type_ids`; only checked members become `job_order_analyses` (Pending, no assignee). Waived members are not work lines but still occupy result-form slots as `-`.

### 2. Dashboard Module
Operational counts and role-specific next steps.

### 3. Receiving Module
Prices lines → status `pending_jo_approval`. After Head JO approval, `JobOrderService::receive()` requires `jo_approved`. Assignment uses `AnalystAssignmentPicker`: open-task counts (Assigned / Pending / In progress / Returned on In analysis jobs) among users on `analysis_assignments` for that type; lowest load, then lower user id; in-batch increment. RFA `print`/`pdf` abort unless `canPrintRfa()` (from `jo_approved` onward). Index chip `reviewed` lists `ReadyForPickup` jobs with `reviewed_at`. Copies via `?copies=` (1–20, default 3) with labels Customer / Accounting / Head file.

### 4. Analyst Module
Queue includes lines suggested to the user (`assigned_to`) and other open lines for types on their Admin Assignments matrix (`can_work`). Draft/complete takes ownership. Package signatory (or admin) may view combined report JSON for preview. `can_preview` when the overlay is complete; `can_print` only when `reviewed_at` is set. Combined PDF `?print=1` and non-inline individual PDF download are forbidden until print is allowed. `submitForReview` requires all lines complete and package signatory (or any assignee if no package). After release, `releasedResultPrintsFor()` lists jobs the signatory may print.

### 5. Head Analysis Module
Sidebar parent **Head Analysis** with children **JO approval** (`/head/jo`) and **Results** (`/head/results`). JO chips: awaiting / approved (download JO PDF). Results chips: awaiting release / released today (preview/download result overlay). Queues `pending_jo_approval` for **Approve JO** (`approveJobOrder` / batch). Queues `pending_review` for result **Release**. `sign()` sets `ready_for_pickup`, `reviewed_at`, `reviewed_by`, sends `ResultsReadyMail` to the customer when an email exists, and sends in-app `ResultsReleased` to the package signatory (or line assignees) plus Receiving/admin. Return (before release only) clears selected results back to In analysis, clears `result_signatories`, and notifies the line assignee via `AnalysisReturned`. After release, return is blocked. Head RFA **print** aborts (Receiving prints); Head may **download** JO/result PDFs for review. Head **pdf** serves the controlled JO/RFA overlay. On result release, Head reviews the **analysis result** controlled form (`result-report` / `combined-pdf`): `can_preview` when the overlay is ready (before release); `can_print` / `?print=1` only after `reviewed_at`. RFA is not the released-results document.

### 6. History Module
Ready-for-pickup jobs; signed vs unsigned filters on `reviewed_at`.

### 7. Admin Setup Modules
Users, catalog/prices, packages (`signatory_user_id`; linked result form via `controlled_forms.analysis_package_id`, shown read-only), assignments matrix, history access, control numbers.

### 8. Controlled Forms / Document Control
Upload-first overlay. Binding modes:
- Job Order → classification-aware RFA (`ControlledForm::jobOrderFormFor($job)` → General FO1 or Aqua FO4)
- Analysis Result + `analysis_package_id` → package sheet (unique per package)
- Analysis Result + type set only → standalone sheet (unique `combination_key` among non-package forms)

Resolve order in `AnalysisResultReportResolver`: package-bound active form first, then standalone `combination_key`, else unavailable / individual DomPDF. `FieldValueResolver` fills package slots in binding order; waived/unchecked members print as `-`.

---

## Technical Business Flow

### 1. Intake
Creates job order, samples, analyses for selected tests only, optional `job_order_packages` rows (with `selected_type_ids` / `waived_type_ids` when package members are unchecked), `Customer::rememberFromIntake()`. Status `draft_submitted`.

### 2. Pricing
Receiving updates line prices. Status `pending_jo_approval`. First pricing save also stamps `received_at` / `received_by` (JO **Received by** date — samples already on hand).

### 3. Head JO approval
`JobOrderService::approveJobOrder()`. Status `jo_approved`. `jo_approved_at` / `jo_approved_by` set. Notifies Receiving (and admin) via `JobOrderJoApproved`. Unlocks JO print and send to analysts.

### 4. Send to analysts (code: `receive`)
UI label **Send to analysts**. Suggests each line via picker (soft `assigned_to`) and sets status `in_analysis`. Does **not** overwrite `received_at` if already stamped at pricing (fallback only if null). `TaskAssigned` soft-suggestion notifications. Only allowed from `jo_approved`.

### 5. Encode
Line statuses: pending → assigned → in_progress → completed (or returned). Soft assignment: qualified analysts may encode teammate-suggested lines; draft/complete takes ownership and emits `LabQueueUpdated` for analyst boards.

### 6. Submit for review
`JobOrderService::submitForReview()`. Status `pending_review`. `JobOrderPendingReview` notifications. No customer email.

### 7. Release results
`JobOrderService::sign()`. Status `ready_for_pickup`. `reviewed_at` now. Customer email if present.

### 7. Print gates
- Result overlay preview: allowed for designated analyst when all results complete (`can_preview`).
- Result print/download: `can_print` / `reviewed_at`.
- RFA: Receiving only, `reviewed_at` required.

### 8. History
Ready-for-pickup archive.

---

## Controlled Forms Technical Flow

### 1. Controlled Form Creation
Admin creates a record (code, name, category, revision). For Analysis Result forms, bind either a package **or** one or more analysis types. Uniqueness: one form per package; one standalone form per exact type set.

### 2. Upload and Canonicalization
Supported: `pdf`, `doc`, `docx`. Stores original file and canonical PDF.

### 3. PDF Inspection
Page count and millimetre width/height for designer and filler.

### 4. Designer Mapping
Place fields in PDF space and map data sources. Package binding exposes ordered `test_N_*` slots for that package’s members.

### 5. Save and Revisioning
Per-revision mappings. New revisions may copy PDF, fields, or import a blueprint. Binding a package may sync display `form_code` onto the package for kiosk labels.

### 6. Preview and Generation
`FieldValueResolver` + `ControlledPdfFiller` (overlay). Package jobs keep the package sheet even when some members are waived (`-`). Standalone jobs match by exact type set.

### 7. Activation
Active revision is the operational version.

### 8. Traceability
Print logs, audit logs, and form revision hashes.

---

## Important Technical Design Rules

### UI Performance
Operational screens paginate on the server, debounce search, keep PDF previews lazy, and avoid heavy printable forms in queues. See `docs/UI_PERFORMANCE.md`.

### Service-Driven Logic
Domain logic lives in services, for example:
- `JobOrderService` — intake (including package member opt-out), receive, encode, submit, release
- `AnalystAssignmentPicker` — least-open-queue soft suggestion at send; qualified override + ownership on encode
- `AnalysisResultReportResolver` — package-first then standalone form match; signatory preview access
- `ControlledFormService` — form create/update, bindings, uniqueness
- `FieldValueResolver` / `ControlledPdfFiller` — overlay values (including `-` for waived slots)
- reference-number generation

### Inertia-Driven Frontend
Laravel routes and permissions; React pages receive server props. Roles, notifications, and flash state come through Inertia middleware. Session `success` / `error` / `warning` / `info` flashes (and typed `Inertia::flash('toast', …)`) surface as Sonner toasts via `useFlashToast`. Destructive confirms use the shared `ConfirmDialog` (`resources/js/components/confirm-dialog.tsx`). In-app LMS notifications use the `database` channel plus Laravel Reverb `broadcast` so the header bell can update in real time when `php artisan reverb:start` is running (see `docs/DEPLOY.md`). Receiving, Analyst, and Head queue boards subscribe to private `lab.queue.*` channels and reload list props on `LabQueueUpdated` instead of interval polling.

### Revision-Based Documents
Upload-first overlays. The active revision is the approved operational version.

### Page Size Integrity
Match stored millimetres to the canonical PDF (`A4` 210×297, Letter 215.9×279.4, Folio 215.9×330.2) or overlays misalign.

### Timezone
Lab print timestamps use `config('app.lab_timezone')` / `NPPC_LAB_TIMEZONE` (`Asia/Manila`). Sampling wall-clock stays as entered.

---

## Suggested Reference Areas for Developers

### General Entry Points
- `README.md`
- `routes/web.php`
- `resources/js/components/app-sidebar.tsx`

### Workflow and Roles
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Support/HistoryAccess.php`
- `app/Services/JobOrderService.php`
- `app/Services/AnalystAssignmentPicker.php`
- `app/Services/AnalysisResultReportResolver.php`
- `app/Support/AnalysisResultReport.php`

### Admin and Controlled Forms
- `app/Http/Controllers/Admin/ControlledFormAdminController.php`
- `app/Http/Controllers/Admin/AnalysisPackageAdminController.php`
- `app/Services/ControlledFormService.php`
- `app/Services/ControlledFormStorage.php`
- `app/Services/ControlledDocumentGenerator.php`
- `app/Services/FieldValueResolver.php`
- `app/Services/AnalysisResultReportResolver.php`
- `app/Models/JobOrderPackage.php`

### Form Designer Frontend
- `resources/js/pages/admin/form-designer.tsx`
- `resources/js/components/form-designer/designer-header.tsx`
- `resources/js/components/form-designer/field-library.tsx`
- `resources/js/components/form-designer/properties-panel.tsx`
- `resources/js/components/form-designer/canvas-toolbar.tsx`

### Workspaces
- `resources/js/pages/analyst/index.tsx`
- `resources/js/pages/receiving/index.tsx`
- `resources/js/pages/head/show.tsx`
- `resources/js/pages/rfa/print.tsx`

---

## Summary
The system is a role-based laboratory workflow from kiosk intake through pricing, Head JO approval, JO print ×3, send to analysts (smart assignment), analysis, designated-analyst send to Head, Head release of **results**, then paper wet-sign and optional Receiving JO reprints. Payment stays outside LMIS.

Ready for pickup follows released results only. The JO/RFA is printed after JO approval and handed over with the result; it is not a released results document.

Controlled forms cover three bindings: global RFA, one result sheet per package (partial member opt-out prints `-`), and standalone sheets for tests sold without a package. Forms are tagged in Document Control; Packages only display the link.

Technically it is Laravel + Inertia + React, with service-driven workflow and upload-first, revision-based PDF overlays for official forms.
