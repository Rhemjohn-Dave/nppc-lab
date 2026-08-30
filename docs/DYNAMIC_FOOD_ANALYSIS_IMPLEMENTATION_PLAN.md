# Implementation Plan: Dynamic Test Matrix (Food / Special Analysis)

**Feature spec:** [`DYNAMIC_FOOD_ANALYSIS_RESULT_PDF.md`](./DYNAMIC_FOOD_ANALYSIS_RESULT_PDF.md)

**One-line summary:** Extend the existing controlled-form pipeline with a **Dynamic test matrix** field in Form Designer and a matrix render step in the PDF filler. Packages opt in via `report_layout = dynamic_matrix`.

**Estimated effort:** 3–5 dev days (backend + designer + tests), plus admin setup once the official PDF template is available.

---

## Principles

1. **No second admin workflow** — upload PDF, map fields, activate, bind to package (same as FO4/FO5).
2. **One new designer function** — draw a matrix region; row count is runtime from selected analyses.
3. **Water packages unchanged** — fixed slots + `-` for waived tests stay as-is.
4. **Reuse TCPDF/FPDI** — matrix rows render inside `ControlledPdfFiller` (same stack as today); avoid a parallel print URL unless needed for dev fallback.

---

## Phase 0 — Prerequisites (no code)

| # | Task | Owner | Done when |
|---|------|-------|-----------|
| 0.1 | Confirm official Food/Special result PDF (or Word export) | Lab admin | File ready for upload |
| 0.2 | List catalog tests + print order (e.g. 8 food parameters) | Lab admin | Documented in Procedures |
| 0.3 | Confirm v1 columns: TEST (name + method), Result, Remarks | Lab admin | Signed off |
| 0.4 | Read spec doc + this plan | Dev | — |

---

## Phase 1 — Package flag & data model

**Goal:** Packages can declare `dynamic_matrix` vs `controlled_form`.

### Tasks

| # | Task | Files |
|---|------|-------|
| 1.1 | Add enum `AnalysisPackageReportLayout` (`controlled_form`, `dynamic_matrix`) | `app/Enums/AnalysisPackageReportLayout.php` |
| 1.2 | Migration: `analysis_packages.report_layout` string, default `controlled_form` | `database/migrations/...` |
| 1.3 | Cast + accessor on `AnalysisPackage` | `app/Models/AnalysisPackage.php` |
| 1.4 | Accept `report_layout` in package CRUD validation | `app/Http/Controllers/Admin/AnalysisPackageAdminController.php` |
| 1.5 | Packages admin UI: select “Result report layout” (Fixed slots / Dynamic matrix) | `resources/js/pages/admin/packages.tsx` |
| 1.6 | Expose `report_layout` in package list/detail API payload | Same controller + Inertia props |

### Acceptance criteria

- [ ] Existing packages default to `controlled_form` after migrate.
- [ ] Admin can set Food/Special package to `dynamic_matrix` and save.
- [ ] No change to water package behavior until matrix form is bound.

### Tests

- `tests/Feature/AnalysisPackageAdminTest.php` (or extend package tests): create/update with `report_layout`.

---

## Phase 2 — Dynamic test matrix field type (Form Designer)

**Goal:** Admins place one matrix region on the uploaded PDF.

### Backend

| # | Task | Files |
|---|------|-------|
| 2.1 | Add `ControlledFormFieldType::DynamicTestMatrix = 'dynamic_test_matrix'` | `app/Enums/ControlledFormFieldType.php` |
| 2.2 | Allow type in field save validation | `app/Services/ControlledFormService.php`, `ControlledFormAdminController` |
| 2.3 | Define default width/height (e.g. 170×60 mm) | Enum `defaultWidth` / `defaultHeight` |
| 2.4 | Store `matrix_config` JSON on field (columns, row height, show header, font) | `controlled_form_fields` — use existing `table_config` column or add `matrix_config` if cleaner |
| 2.5 | Validation: matrix fields only on `analysis_result` category forms; max one per revision (v1) | `ControlledFormService` |

**Suggested `matrix_config` (v1):**

```json
{
  "columns": [
    { "key": "test", "label": "TEST", "width_pct": 55 },
    { "key": "result", "label": "Result", "width_pct": 25 },
    { "key": "remarks", "label": "Remarks", "width_pct": 20 }
  ],
  "row_height_mm": 5,
  "header_row": true,
  "font_size": 8,
  "border": true
}
```

**Row keys resolved at runtime:**

| Key | Source |
|-----|--------|
| `test` | `{name}` + optional method from `analysisType` |
| `result` | `result_value` + unit |
| `remarks` | `result_remarks` |

### Frontend (Form Designer)

| # | Task | Files |
|---|------|-------|
| 2.6 | Add type to `fieldTypes` API response | Controller or shared TS types |
| 2.7 | Field library: “Dynamic test matrix” with distinct icon | `field-library.tsx` |
| 2.8 | Canvas: render matrix region as labeled dashed box (not data-source-driven) | `form-designer.tsx` |
| 2.9 | Properties panel: column toggles, row height, header row, font size | `properties-panel.tsx` |
| 2.10 | Drag/resize like other fields; no `data_source_key` required | `form-designer.tsx`, `utils.ts` |
| 2.11 | Calibration overlay: highlight matrix in distinct color | `ControlledPdfFiller::calibrationOverlay` |

### Acceptance criteria

- [ ] Admin can add exactly one Dynamic test matrix region on an Analysis Result form.
- [ ] Region saves position (mm), page, size, and `matrix_config`.
- [ ] Preview calibration PDF shows the matrix rectangle labeled.
- [ ] Static fields (customer, dates, signatures) still work unchanged on the same form.

### Tests

- Feature test: save revision with `dynamic_test_matrix` field; assert DB row + config JSON.

---

## Phase 3 — Value resolution (skip fixed slots for matrix)

**Goal:** Matrix packages do not fill `test_1_*` … `test_N_*` with dashes; matrix gets row array instead.

### Tasks

| # | Task | Files |
|---|------|-------|
| 3.1 | Detect matrix mode: revision has `dynamic_test_matrix` field **or** package `report_layout === dynamic_matrix` | `FieldValueResolver` |
| 3.2 | When matrix: **skip** `fillResultTestSlots` dash logic | `FieldValueResolver::forResult` |
| 3.3 | Build ordered analyses: selected `job_order_analyses` sorted by package member slot | New helper e.g. `OrderedAnalysisLines::forPackage($jobOrder, $package)` |
| 3.4 | Populate value bag key for matrix field name, e.g. `$bag['food_matrix'] = [ rows... ]` | `FieldValueResolver` |
| 3.5 | Sample/preview values: 4 fake rows for designer preview | `FieldValueResolver::sampleValues` |
| 3.6 | Pass package context into `forResult` when resolving from package-bound form | `AnalysisResultReportResolver`, `ControlledDocumentGenerator` |

### Acceptance criteria

- [ ] Job with 4 of 8 selected → value bag contains **4** matrix rows, no `test_5_*` keys.
- [ ] Water FO4/FO5 form without matrix field → `fillResultTestSlots` unchanged.
- [ ] Matrix rows include name, result, unit, remarks for completed lines.

### Tests

- Unit/feature: `FieldValueResolver` with matrix revision + partial package selection → 4 rows, no dash slots.

---

## Phase 4 — PDF matrix renderer

**Goal:** `ControlledPdfFiller` draws the dynamic table inside the designer region.

### Tasks

| # | Task | Files |
|---|------|-------|
| 4.1 | Branch in `writeField`: if type `dynamic_test_matrix`, call `writeDynamicTestMatrix` | `ControlledPdfFiller.php` |
| 4.2 | Implement `writeDynamicTestMatrix`: optional header row + N data rows using TCPDF `Cell`/`MultiCell` | Same file |
| 4.3 | Column widths from `width_pct` × region width; respect `row_height_mm` | Same |
| 4.4 | Wrap long test names in TEST column (`MultiCell`) | Same |
| 4.5 | Optional borders via `Rect` / line drawing | Same |
| 4.6 | If matrix extends below region height: clip or reduce font (v1: clip + log warning; v2: page break) | Same |
| 4.7 | Ensure matrix renders **after** template import on correct page | Existing page loop |

### Acceptance criteria

- [ ] Preview PDF shows header/signatures from overlays + table with correct row count.
- [ ] 4 selected tests → 4 body rows (+ optional header).
- [ ] No empty rows for waived package members.

### Tests

- Extend `tests/Feature/ControlledFormTest.php` or `AnalysisResultPdfTest.php`: generate PDF bytes; assert row count via text extraction or snapshot helper if available.

---

## Phase 5 — Report resolver & print wiring

**Goal:** Matrix packages use the same combined preview/download flow as FO4/FO5.

### Tasks

| # | Task | Files |
|---|------|-------|
| 5.1 | Load package `report_layout` when resolving package form | `AnalysisResultReportResolver::matchingPackageControlledForm` |
| 5.2 | For `dynamic_matrix`: require bound form + matrix field on active revision | Resolver |
| 5.3 | Set `ordered` analyses = selected lines only (not full package slot list) | `orderedAnalysesForIds` or matrix-specific ordering |
| 5.4 | Add `renderer: matrix` on `AnalysisResultReport` **or** new `KIND_MATRIX` | `app/Support/AnalysisResultReport.php` |
| 5.5 | Analyst preview/print: matrix uses `ControlledPdfFiller` path (same as `KIND_COMBINED`) | `AnalystController.php` |
| 5.6 | Head preview/print: same | `ReviewController.php` |
| 5.7 | Admin controlled-form preview with sample job: matrix rows visible | `ControlledFormAdminController::preview` |

### Acceptance criteria

- [ ] Analyst can preview matrix report when all selected lines complete.
- [ ] Head can preview/print after sign-off (same gates as combined).
- [ ] Water micro job still resolves to fixed-slot combined report.

### Tests

- `tests/Feature/AnalysisResultPdfTest.php`:
  - 8-member package, `dynamic_matrix`, 4 selected, all complete → 200 PDF.
  - Assert PDF text contains 4 test names, not all 8.
  - Water package regression: waived slot still shows `-`.

---

## Phase 6 — Admin setup & seed data

**Goal:** Repeatable lab configuration for Food/Special.

### Tasks

| # | Task | Files |
|---|------|-------|
| 6.1 | Seed or document 8 food analysis types with codes/methods | `DatabaseSeeder` or `docs/` |
| 6.2 | Seed Food/Special package: 8 members, `report_layout = dynamic_matrix` | Seeder |
| 6.3 | Create controlled form `FOOD-RESULT` (or bind after PDF upload) | Manual + optional seeder stub |
| 6.4 | Bind form to package (`controlled_forms.analysis_package_id`) | Admin workflow doc |
| 6.5 | Update `CONTROLLED_FORMS.md` binding section with matrix note | `docs/CONTROLLED_FORMS.md` |

### Acceptance criteria

- [ ] Fresh migrate + seed can demo intake → 4 tests → preview with matrix (once PDF uploaded).
- [ ] Runbook documents upload + designer steps for production.

---

## Phase 7 — Dev fallback (optional, time-boxed)

**Goal:** Develop/tests before official PDF exists.

| # | Task | Files |
|---|------|-------|
| 7.1 | Standalone Blade `analysis-result-matrix.blade.php` + `AnalysisResultPdfExporter` path | Only if blocked on template |
| 7.2 | Gate behind `config('nppc.matrix_pdf_fallback')` or missing canonical PDF | Config |
| 7.3 | Remove or disable fallback once controlled form is active | Cleanup task |

**Do not ship fallback to production** if the official PDF is available.

---

## Implementation order (sprint checklist)

```
[ ] Phase 1 — report_layout flag + Packages UI
[ ] Phase 2 — dynamic_test_matrix field type + designer
[ ] Phase 3 — FieldValueResolver matrix rows
[ ] Phase 4 — ControlledPdfFiller writeDynamicTestMatrix
[ ] Phase 5 — resolver + Analyst/Head wiring
[ ] Phase 6 — seed + docs
[ ] Phase 7 — fallback (only if needed)
```

---

## File touch list (expected)

| Layer | Files |
|-------|-------|
| Enum / model | `AnalysisPackageReportLayout.php`, `ControlledFormFieldType.php`, `AnalysisPackage.php` |
| Migration | `add_report_layout_to_analysis_packages.php` |
| Services | `FieldValueResolver.php`, `ControlledPdfFiller.php`, `AnalysisResultReportResolver.php`, `ControlledFormService.php` |
| Support | `AnalysisResultReport.php` |
| Controllers | `AnalysisPackageAdminController.php`, `AnalystController.php`, `ReviewController.php`, `ControlledFormAdminController.php` |
| Frontend | `packages.tsx`, `form-designer.tsx`, `field-library.tsx`, `properties-panel.tsx`, `controlled-forms.ts` |
| Tests | `AnalysisResultPdfTest.php`, `ControlledFormTest.php`, package admin tests |
| Docs | `CONTROLLED_FORMS.md`, spec doc (already done) |

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Matrix taller than designer region | v1: document max rows; v2: auto-shrink font or continuation page |
| Long test names overflow column | `MultiCell` in TEST column; truncate with ellipsis as last resort |
| Multiple matrix fields on one form | v1: validate max 1; reject save with clear error |
| Package has `dynamic_matrix` but no matrix field on form | Resolver returns `KIND_UNAVAILABLE` with actionable message |
| Official PDF has pre-printed table lines | Upload version **without** fixed test rows; matrix draws in blank area |

---

## Definition of done

- [ ] Customer selects 4 of 8 at intake → lab runs 4 analyses (existing).
- [ ] Printed report shows **4 table rows** only; waived tests absent.
- [ ] Header, customer block, **Analyzed by**, **Approved by** come from controlled-form overlays on uploaded PDF.
- [ ] FO4/FO5 water reports unchanged (regression tests pass).
- [ ] Admin setup documented; feature tests green in CI.

---

## Out of scope (v1)

- Per-sample result columns (multiple specimens × tests).
- Form Designer editing of individual row templates.
- Changing waived-slot dash behavior on water forms.
- Pixel-perfect match before official PDF is uploaded.

---

## Related documents

- [Dynamic Food / Special Analysis Results PDF](./DYNAMIC_FOOD_ANALYSIS_RESULT_PDF.md) — product/architecture spec
- [Controlled Forms Technical Overview](./CONTROLLED_FORMS.md) — existing designer & filler behavior
- [System Overview](./SYSTEM_OVERVIEW.md) — analyst/head print flow
