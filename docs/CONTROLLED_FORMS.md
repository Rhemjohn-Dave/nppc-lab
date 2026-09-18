# Controlled Forms Technical Overview

## Purpose
The Controlled Forms module lets admins upload a source document, design field overlays on top of the canonical PDF, activate a revision, and reuse that revision as the approved printable layout for operational forms such as job orders and related reports.

The PDF is the source of truth. The designer does not rebuild the form from scratch; it places dynamic fields on top of the uploaded document.

## Tech Stack
### Backend
- `Laravel 13`
- `PHP`
- `MySQL 8` in production, `SQLite` for local default
- `Inertia.js` for server-driven page delivery
- `FPDI + TCPDF` for PDF import and filled-PDF generation

### Frontend
- `React 19`
- `TypeScript`
- `Tailwind CSS 4`
- `shadcn/ui`

### Supporting Services
- `PdfCompatibilityNormalizer` to rewrite incompatible PDFs when needed
- `DocxToPdfConverter` for `DOC`/`DOCX` uploads
- `DocumentAuditLogger` for audit events
- `RevisionWorkflow` for revision status transitions

## Main Modules
### 1. Admin Controlled Forms
Admins manage the form registry, form metadata, analysis bindings, and revisions.

Key route area:
- `/admin/controlled-forms`

Primary controller:
- `app/Http/Controllers/Admin/ControlledFormAdminController.php`

Main responsibilities:
- create a controlled form
- create a new revision
- upload or replace a revision file
- open the designer
- save mapped fields
- preview output
- transition revision status

### Binding modes
| Category | Bind | Runtime resolve |
|----------|------|-----------------|
| Job Order | `job_order_variant` = `general` \| `aqua` | `ControlledForm::jobOrderFormFor($job)` — Aqua classification → `NPPC-LAB-FRM-AQUA` (LSP 7.1 FO4); otherwise → `NPPC-LAB-FRM-001` (LSP 7.1 FO1 Issue 11) |
| Analysis Result | Package | Job package → `analysis_package_id` (partial selection still matches; waived slots print `-` on **fixed-slot** packages) |
| Analysis Result | Package + `dynamic_matrix` | Same binding; form includes one **Dynamic test matrix** region — only selected tests render as rows (see `docs/DYNAMIC_CONTROLLED_FORMS.md`) |
| Analysis Result | Analysis types only | Exact `combination_key` when no package form applies |
| Analysis Result | Types-only + `dynamic_test_matrix` | After exact miss: job type IDs ⊆ form bound types (e.g. `LSP-7.8-FO3` Issue 11 OLD individual DW sheet, `LSP-7.8-FO2` Issue 18 wastewater physico, `LSP-7.8-FO26` / `LSP-7.8-FO27` food / sugar micro). Package forms still win first (e.g. `LSP-7.8-FO37`). |

Packages admin shows the linked form read-only. Tag forms in Controlled Forms, not via free-text package form codes.

Official Word sources for Aqua/General Job Orders and food/special result sheets live under `resources/forms/official/` (see README there). Several printed **F016** panels use distinct internal codes (`LSP-7.8-F016-PROX`, `-WA`, `-NO2`, `-CAP`, `-PHYTO`, `-MILK`) to avoid collisions.

### 2. Revision File Storage
Uploaded files are stored in two forms:
- original uploaded file
- canonical PDF used by the designer and filler

Primary service:
- `app/Services/ControlledFormStorage.php`

Responsibilities:
- store original upload
- convert `DOC`/`DOCX` to PDF when necessary
- persist canonical PDF
- inspect the PDF for page count and page size
- return canonical metadata such as width and height in millimetres

### 3. Form Designer
The designer is the admin-facing React workspace for placing and editing overlay fields on the uploaded PDF.

Main page:
- `resources/js/pages/admin/form-designer.tsx`

Supporting UI modules:
- `resources/js/components/form-designer/designer-header.tsx`
- `resources/js/components/form-designer/field-library.tsx`
- `resources/js/components/form-designer/properties-panel.tsx`
- `resources/js/components/form-designer/canvas-toolbar.tsx`
- `resources/js/components/form-designer/utils.ts`

Responsibilities:
- render the uploaded PDF page
- show the field library
- place fields by click or drag/drop flow
- select, move, and resize fields
- edit field properties
- preserve coordinates in PDF space
- save field mappings back to the backend

**Overlay transparency (house rule):** field boxes are **transparent by default**. Do not seed or enable white `options.cover` fills — they blank printed PDF text under the box (e.g. License # behind a PRC number). Prefer resizing or repositioning the field. `cover: true` is a rare opt-in only when blanking printed template ink is deliberate (e.g. obsolete static **Test Methods and References** replaced by `results.test_methods_references`). `ControlledPdfFiller` only paints a white rectangle when that flag is explicitly set.

**Dynamic test matrix (food / special analysis):** add field type **Dynamic test matrix** from the field library (one per revision). Draw the region where the test table should print. Map header and signature fields as usual. Row count follows selected intake tests at runtime.

### 4. Field Mapping and Persistence
Field overlays are stored as controlled form fields attached to a specific revision.

Primary service:
- `app/Services/ControlledFormService.php`

Responsibilities:
- create forms and revisions
- attach uploaded files
- copy canonical PDFs across revisions when appropriate
- copy prior field mappings into new revisions
- validate allowed data-source keys
- replace and persist the field list
- optionally import the RFA / FO4 / FO5 field blueprint for initial seeding

**Deploy defaults:** `ControlledFormDefaultsSeeder` (via `db:seed`) activates a sole revision **`1`** for each plotted default form using official PDFs under `resources/forms/official/` and blueprints in `config/*_form_fields.php` (see `config/controlled_form_blueprints.php`). **Milk (`LSP-7.8-F016-MILK`) is reference-only:** once a Form Designer PDF is attached, seed does not replace it. After Designer changes, refresh configs with `php artisan controlled-forms:export-blueprints --pdfs` before commit. Details: `docs/DEPLOY.md`.

### 5. Data Source Catalog
The field library is driven by an approved mapping catalog rather than ad hoc arbitrary keys.

Primary source:
- `FieldValueResolver::catalog(...)`

Responsibilities:
- expose valid field/data-source mappings per form category
- support job-order-specific and analysis-related fields
- keep designer mappings aligned with application data

### 6. Preview and PDF Filling
Preview uses real resolved data and renders the final printable document by placing dynamic values on top of the canonical PDF.

Key services used by the admin flow:
- `ControlledPdfFiller`
- `ControlledDocumentGenerator`

Responsibilities:
- read the canonical PDF
- resolve runtime values for mapped fields
- write text, checkboxes, and tables into the final document
- return the preview/downloadable PDF

## Key Workflow
1. Admin creates a controlled form.
2. Admin uploads a `PDF`, `DOC`, or `DOCX`.
3. The system stores the original file and produces a canonical PDF.
4. The system inspects the canonical PDF and stores:
   - `page_count`
   - `page_width_mm`
   - `page_height_mm`
5. Admin opens the Form Designer.
6. Admin places fields on the PDF and configures their mapping and appearance.
7. Admin saves the field layout to the current revision.
8. Admin previews using sample or real data.
9. Admin activates the revision.
10. The active revision is used by downstream report-generation flows.

## Supported File Inputs
### PDF
If the admin uploads a PDF:
- the original file is kept
- a canonical PDF copy is stored
- compatibility normalization is applied if needed
- page dimensions are read from the canonical PDF

### DOC / DOCX
If the admin uploads a Word file:
- the original file is kept
- the file is converted to PDF
- the converted PDF becomes the canonical PDF
- page dimensions are read from that canonical PDF

## Page Size Handling
### Source of Truth
The actual uploaded canonical PDF is the source of truth for page dimensions.

The system stores:
- `page_width_mm`
- `page_height_mm`

These values are read from the canonical PDF itself in:
- `app/Services/ControlledFormStorage.php`

### Common Sizes
- `A4` = `210.0 × 297.0 mm`
- `Letter / Short bond / 8.5 × 11 in` = `215.9 × 279.4 mm`
- `Folio / Long bond / 8.5 × 13 in` = `215.9 × 330.2 mm`

### Why This Matters
The designer stores field positions against the real PDF page size. If the stored page dimensions do not match the uploaded PDF, preview output can become misaligned.

The current implementation is intended to prevent that by using the canonical PDF dimensions once a real file exists.

### Designer / preview parity
Coordinate authority is always:

1. Canonical PDF on disk
2. FPDI metrics via `ControlledFormStorage::pageMetrics` / `inspectPdf` (stored as `page_width_mm` / `page_height_mm`)
3. Form Designer overlays in those millimetres (`canonical_page` prop; designer opens with `syncRevisionPageMetrics`)
4. `ControlledPdfFiller` using the same FPDI template size

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Preview modal looks tiny with dark side gutters | Browser iframe letterboxing | Use `PdfBlobCanvasViewer` fit-to-width (do not regress to iframe-only viewing) |
| Downloaded/printed overlays shifted vs designer | pdf.js mm ≠ FPDI mm, or stale `page_*_mm` | Sync metrics; stretch designer raster to FPDI mm; re-export blueprint |
| Whole layout scaled after blueprint import | Blueprint `page` copied from another form | Measure official PDF with FPDI; attach before import; export after calibration |

**Verify before activating a revision:** download the **calibration** PDF and a populated preview **file** — on-screen preview alone is not proof of alignment.

Shared frontend helpers: `resources/js/lib/controlled-pdf-viewport.ts`. Agent rule: `.cursor/rules/form-designer-pdf-parity.mdc`.

### Blueprint Import Note
There is still an optional RFA blueprint import path for seeded fields. However, blueprint defaults should not override the dimensions of an uploaded canonical PDF. The uploaded PDF dimensions must remain authoritative.

## Coordinate System
Fields are stored in PDF coordinate space, not arbitrary screen pixels.

This means:
- the database stores positions and sizes based on PDF dimensions
- the React designer scales those coordinates for display
- zoom changes only affect display scale
- saved coordinates remain stable

This separation is what allows the same revision to preview correctly even when the designer is viewed on different monitor sizes or zoom levels.

## Frontend Layout Model
The designer is implemented as a fixed-height three-panel workspace:
- left: field library
- center: PDF canvas
- right: properties panel

Important layout rules:
- parent containers use `height: 100%` or viewport-based height
- grid/flex parents use `min-height: 0`
- scrollable children use internal `overflow-y: auto` or `overflow: auto`
- the field library, PDF viewport, and properties panel scroll independently

This prevents large field lists from increasing the height of the PDF workspace.

## Current Route Surface
Controlled forms currently expose these main admin routes:
- list forms
- create form
- show form
- update form
- create revision
- upload revision file
- change revision status
- open form designer
- save fields
- import blueprint
- download canonical/original
- preview
- open calibration output

The route definitions live in:
- `routes/web.php`

## Official analysis result PDFs
Versioned source documents live under `resources/forms/`. Combined microbiological sheets:
- `lsp-7.8-fo4-micro-non-drinking-water.pdf` (official `LSP 7.8 FO4` → internal `LSP-7.8-FO4`) — bind to package `PKG-MIC-NDW` / `MB-02A` + `MB-02B`
- `lsp-7.8-fo5-micro-drinking-water.pdf` (official `LSP 7.8 FO5` → internal `LSP-7.8-FO5`) — bind to package `PKG-MIC-DW` / `PKG-DW-BACT` / `MB-02A` + `MB-02B` + `MB-01`

Physico-chemical:
- `PC Wastewater result Form Issue 18 09012026 blank.pdf` (official `LSP 7.8 FO2` Issue 18 → `LSP-7.8-FO2`) — types-only dynamic matrix (TEST | METHOD | RESULTS; Time/Date of Analysis under each test name from `completed_at`); WW/SA panel tests individual pay
- `PC Drinking Water Test Result Form Issue 7…` → `LSP-7.8-FO37` / `PKG-DW-PHYSICO`
- `PC OLD Drinking Water Test Result Form Issue 11 blank.pdf` → `LSP-7.8-FO3` types-only
- `micro food test result form issue4 07152026.pdf` → `LSP-7.8-FO26` types-only dynamic matrix (Tests | Control No.; selected tests only); Methods/References via `results.test_methods_references`
- `FOOD MICRO SUGAR Test Result form Issue4 07152026.pdf` → `LSP-7.8-FO27` types-only dynamic matrix (distinct `SM-*` codes; Tests | Control No.); Methods/References via `results.test_methods_references`

FO4 overlay mapping (Form Designer):
- **Coliform result cells** → `test_N_result` (measured MPN; analyst does **not** encode Pass/Fail on FO4)
- **Sample Description** → `results.sample_description` (filled from job **classification**, e.g. Wastewater)
- Procedure **Method** for `MB-02A` / `MB-02B` is seeded as `Multiple Tube Fermentation Technique* 9221, SMEWW` (editable, not required on encode)

FO5 overlay mapping (Form Designer):
- **Results of Analysis** → `test_N_measurement` (measured value encoded by the analyst)
- **Interpretation** → `test_N_result` (Passed / Failed — required on encode)

See `docs/CONTROLLED_FORMS_NON_TECHNICAL.md` for the bind steps. Auto-seeding those controlled forms is out of scope for the package slice.

## Important Domain Rules
- revisions are the unit of design and activation
- only editable statuses can be changed in the designer
- superseded or archived revisions should not be remapped
- allowed data-source keys are validated before save
- the active revision is the approved printable definition (Head/Analyst overlays use Active only — Activate Drafts after layout edits)
- saving Form Designer fields bumps the revision timestamp and busts the short-lived overlay PDF cache so Head preview matches immediately

## Related Models and Services
Likely core domain pieces involved in this area include:
- `ControlledForm`
- `ControlledFormRevision`
- `ControlledFormField`
- `ControlledFormService`
- `ControlledFormStorage`
- `ControlledPdfFiller`
- `FieldValueResolver`
- `RevisionWorkflow`

## Summary
The Controlled Forms feature is an upload-first PDF overlay system:
- upload the document first
- inspect and store its real dimensions
- design field overlays on top of that exact PDF
- preview with resolved data
- activate a revision for real operational use

Because the uploaded canonical PDF is the reference layout, the module should support `A4`, `Letter (8.5 × 11)`, and other page sizes, as long as the stored revision metadata continues to match the actual canonical PDF.
