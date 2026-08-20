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

Operational rules described here match the lab as of August 2026: package designated analysts, least-busy assignment at receive, Send to Head then Head Release, wet-sign paper after dates are frozen, Receiving (not Head) printing reviewed RFAs, and controlled-form binding for RFA (global), package result sheets, and standalone-test sheets (with package member opt-out printing as `-`).

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

**Ready for pickup** is based only on **released results**. The RFA does not change that status. When the customer collects results, they receive two papers: the wet-signed result form and the RFA that Receiving signed at sample receipt and Head reviewed.

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
- updating prices and quantities
- marking job orders as received (this assigns tests to analysts)
- wet-signing the working RFA on paper when samples are accepted
- printing extra RFA copies **after Head has released the results** (customer packet, Head file, accounting)

Receiving is the bridge between intake and laboratory processing. Official RFA reprints are blocked until Head has reviewed. The RFA is **not released**; it is **reviewed**. It never sets Ready for pickup.

### 3. Analyst
Analysts encode the laboratory work assigned to them.

They are responsible for:
- viewing analyses assigned to them (per test)
- entering test results (including Passed/Failed where the catalog requires it)
- saving drafts while work is ongoing
- completing analysis lines
- the **designated package analyst** consolidating the job and sending it to Head when every result is encoded
- **previewing** the filled result form on screen before Send to Head
- **printing** the dated result form only after Head Release, then wet-signing it with Head

Print and download of the official result sheet stay off until Head releases. Preview stays on so the designated analyst can check the overlay before sending.

Analysts only encode lines assigned to them, after Receiving has marked the job received.

### 4. Head Analysis
Head Analysis reviews and **releases results**.

They are responsible for:
- reviewing encoded results on the job screen
- releasing completed result outputs (system “sign / release”)
- returning analyses for correction when needed
- reviewing completed records in history

Head does **not** print the RFA. After Release:
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
- sample information
- requested analyses, including **analysis packages** (for example microbiological drinking-water vs non-drinking-water packages)
- optional **standalone tests** that are not part of a package

Selecting a package adds its members by default. The customer may **uncheck** package tests they do not need. Unchecked members stay on the package’s result form as `-`; only checked tests become work lines for Receiving and analysts. Selecting a package does **not** assign the package signatory to every line. Assignment happens at Receiving.

After submission, the job enters the internal workflow and the customer is remembered for later kiosk lookup.

### 2. Dashboard
The dashboard gives a high-level summary of system activity: counts by status, recent job orders, and role-based next actions.

### 3. Receiving Module
Used to process new jobs and later to reprint reviewed RFAs.

It handles:
- queue: needs pricing / ready to receive
- pricing and receive confirmation
- **Reviewed** list: reprint RFA copies after Head Release (default several copies; reprint as often as needed; practical max 20 per run)

RFA print in the system is blocked until Head has released (`reviewed_at`).

### 4. Analyst Module
Used to encode assigned tests and, for the designated analyst, to send the job to Head.

It handles:
- task lists (your lines only, unless admin)
- drafts and completion
- **Send to Head** (package signatory, after every line is complete)
- **Preview result form** on screen once all matched results are complete
- **Print result form** after Head Release (dated overlay; Print/Download unlock then)

### 5. Head Analysis Module
Used to review and release **results** (not to print RFA).

It handles:
- unsigned queue (Pending review)
- signed/released jobs today
- return selected lines for correction
- Release: Ready for pickup, customer email, Report/Release dates
- on-screen dated result preview after Release

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
Receiving updates quantities and prices. The job becomes Priced.

### Step 3. Receiving marks samples received
Receiving marks the job received. That:
- records who received it and when (Receipt time on result sheets, Asia/Manila)
- moves the job to In analysis
- **assigns each test line** to a qualified analyst

Assignment uses Admin → Assignments. Among people checked for that test type, the line goes to whoever has the **fewest open tasks**. Within the same receive, counts update after each line so a multi-test package is spread when more than one person can do those tests. If nobody is qualified, the line stays unassigned. The package signatory is **not** used at this step.

Receiving wet-signs the working RFA on paper when samples are accepted.

### Step 4. Analysts encode results
Each analyst sees only their assigned lines. Drafts and completes. Completing a line does **not** email the customer and does **not** send the job to Head.

### Step 5. Designated analyst sends to Head
When every line is complete:
- the designated package analyst previews the result form on screen
- they click **Send to Head**
- the job becomes **Pending review**
- Head is notified in-app

If the job has no package, any assigned analyst on that job may send it. Print and download of the official result PDF stay disabled.

### Step 6. Head reviews and releases
Head reviews encoded values. They may return selected lines (job returns to In analysis).

When they click Release:
- status becomes **Ready for pickup** (results only; RFA has no bearing)
- `reviewed_at` is set (Report date and Release date on the result sheet)
- the customer email is sent if an address exists

### Step 7. Paper packet (wet signatures)
1. Designated analyst prints the **dated result form**, wet-signs it, and Head wet-signs it.
2. Receiving reprints the **reviewed RFA** for the customer packet, Head file, and accounting.
3. The customer collects both papers with the result.

### Step 8. History
Ready-for-pickup jobs remain searchable in History.

---

## Two documents (results vs RFA)

| Document | Who prints in the system | When | What it means |
| --- | --- | --- | --- |
| Result form (package sheet or standalone sheet) | Designated analyst (package) or assigned analyst | After Head Release | Released results; dates filled; then wet-signed. Package sheets keep all slots; unchecked members print as `-` |
| RFA | Receiving | After Head review/release | Reviewed request form given **with** the result; not a released results document |

Head reviews results on screen and clicks Release. Head does not print the RFA.

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
   - **Job Order** → RFA (global; one form for every job; not tagged to a package)
   - **Analysis Result + Package** → that package’s result sheet; still used when the customer unchecks some package tests
   - **Analysis Result + analysis types only** → standalone test sheet (no package); at most one form per exact type set
6. Admin saves the layout, previews, and activates the revision.
7. The active revision is used for real overlay generation.

**Rules that matter in daily use:**
- Tag forms only under Admin → Controlled Forms. Packages admin only **shows** the linked result form (read-only).
- At most one Analysis Result form may be bound to a given package.
- Runtime resolve: job package → bound form first; otherwise exact standalone type combination; otherwise individual DomPDF fallback.
- Unchecked package members print as `-` on the package sheet; checked members print Pass/Fail (or the encoded value) after encoding and release rules.

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
- `receiving` is limited to intake-processing, receive, and post-review RFA print
- `analyst` encodes assigned lines; package signatory may preview combined overlays and submit for review; official print/download requires `reviewed_at`
- `head_analysis` reviews Pending review jobs and releases results; RFA print routes on Head return 403 (Receiving prints RFA)

### History Access
Configurable (Admin → History access). Default orientation: `admin` and `head_analysis`; may be granted to `receiving` or `analyst`.

---

## Job statuses (results path)

| Status | Meaning |
| --- | --- |
| `draft_submitted` | Intake submitted |
| `priced` | Receiving saved pricing |
| `in_analysis` | Received; analysts encoding (or returned from Head) |
| `pending_review` | Designated analyst sent to Head |
| `ready_for_pickup` | Head released results (`reviewed_at` set) |

Completing all lines does **not** leave `in_analysis`. Submit-for-review is a separate action.

---

## Technical Module Map

### 1. Intake Module
Creates the job order, samples, analysis lines for **selected** tests only, and optional package links. Upserts `customers` for kiosk lookup. Package selection stores `job_order_packages` with `selected_type_ids` / `waived_type_ids`; only checked members become `job_order_analyses` (Pending, no assignee). Waived members are not work lines but still occupy result-form slots as `-`.

### 2. Dashboard Module
Operational counts and role-specific next steps.

### 3. Receiving Module
Prices lines, then `JobOrderService::receive()`. Assignment uses `AnalystAssignmentPicker`: open-task counts (Assigned / Pending / In progress / Returned on In analysis jobs) among users on `analysis_assignments` for that type; lowest load, then lower user id; in-batch increment. RFA `print`/`pdf` abort unless `reviewed_at`. Index chip `reviewed` lists `ReadyForPickup` jobs with `reviewed_at`. Copies via `?copies=` (1–20, default 3).

### 4. Analyst Module
Queue filtered by `assigned_to`. Package signatory (or admin) may view combined report JSON for preview. `can_preview` when the overlay is complete; `can_print` only when `reviewed_at` is set. Combined PDF `?print=1` and non-inline individual PDF download are forbidden until print is allowed. `submitForReview` requires all lines complete and package signatory (or any assignee if no package). After release, `releasedResultPrintsFor()` lists jobs the signatory may print.

### 5. Head Analysis Module
Queues `pending_review`. `sign()` sets `ready_for_pickup`, `reviewed_at`, `reviewed_by`, and sends `ResultsReadyMail`. Return clears selected results back to In analysis. Head RFA print/pdf abort. Result overlay preview/print for Head is gated on `canPrint()` (after release).

### 6. History Module
Ready-for-pickup jobs; signed vs unsigned filters on `reviewed_at`.

### 7. Admin Setup Modules
Users, catalog/prices, packages (`signatory_user_id`; linked result form via `controlled_forms.analysis_package_id`, shown read-only), assignments matrix, history access, control numbers.

### 8. Controlled Forms / Document Control
Upload-first overlay. Binding modes:
- Job Order → global RFA (`ControlledForm::jobOrderForm()`)
- Analysis Result + `analysis_package_id` → package sheet (unique per package)
- Analysis Result + type set only → standalone sheet (unique `combination_key` among non-package forms)

Resolve order in `AnalysisResultReportResolver`: package-bound active form first, then standalone `combination_key`, else unavailable / individual DomPDF. `FieldValueResolver` fills package slots in binding order; waived/unchecked members print as `-`.

---

## Technical Business Flow

### 1. Intake
Creates job order, samples, analyses for selected tests only, optional `job_order_packages` rows (with `selected_type_ids` / `waived_type_ids` when package members are unchecked), `Customer::rememberFromIntake()`. Status `draft_submitted`.

### 2. Pricing
Receiving updates line prices. Status `priced`.

### 3. Receive
`received_by` / `received_at`. Each line `assigned_to` via picker. Status `in_analysis`. `TaskAssigned` notifications.

### 4. Encode
Line statuses: pending → assigned → in_progress → completed (or returned). Assignee-only (or admin).

### 5. Submit for review
`JobOrderService::submitForReview()`. Status `pending_review`. `JobOrderPendingReview` notifications. No customer email.

### 6. Release
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
- `AnalystAssignmentPicker` — least-open-queue assignment
- `AnalysisResultReportResolver` — package-first then standalone form match; signatory preview access
- `ControlledFormService` — form create/update, bindings, uniqueness
- `FieldValueResolver` / `ControlledPdfFiller` — overlay values (including `-` for waived slots)
- reference-number generation

### Inertia-Driven Frontend
Laravel routes and permissions; React pages receive server props. Roles, notifications, and flash state come through Inertia middleware. In-app LMS notifications use the `database` channel plus Laravel Reverb `broadcast` so the header bell can update in real time when `php artisan reverb:start` is running (see `docs/DEPLOY.md`).

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
The system is a role-based laboratory workflow from kiosk intake through pricing, receive (smart assignment), analysis, designated-analyst send to Head, Head release of **results**, then paper wet-sign and Receiving RFA reprints.

Ready for pickup follows released results only. The RFA is reviewed and handed over with the result; it is not a released results document.

Controlled forms cover three bindings: global RFA, one result sheet per package (partial member opt-out prints `-`), and standalone sheets for tests sold without a package. Forms are tagged in Document Control; Packages only display the link.

Technically it is Laravel + Inertia + React, with service-driven workflow and upload-first, revision-based PDF overlays for official forms.
