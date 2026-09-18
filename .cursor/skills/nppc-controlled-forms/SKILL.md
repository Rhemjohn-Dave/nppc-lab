---
name: nppc-controlled-forms
description: Adds or changes NPPC controlled PDF forms (RFA, job order, result sheets), Form Designer fields, package bindings, and dynamic test matrices. Use when the user mentions controlled forms, form designer, PDF overlay, RFA, result form, FO3/FO4/FO5, field mapping, report_layout, or official LSP forms.
---

# NPPC controlled PDF forms

## Quick decision

| Goal | Path |
|------|------|
| New/changed **printed** lab document | Controlled Forms module + PDF upload |
| New **intake** or **admin** screen field | LIMS UI forms skill (`nppc-lims-ui-forms`) — not this skill |
| Package shows wrong rows/dashes | Check `report_layout` (`controlled_form` vs `dynamic_matrix`) and resolver |

Read **`docs/CONTROLLED_FORMS.md`** and **`docs/DYNAMIC_CONTROLLED_FORMS.md`** before editing fill/resolver code.

## Workflow: new result or RFA form

```
Progress:
- [ ] 1. Registry / DB shell (catalog, migration if seeded)
- [ ] 2. Official PDF in resources/forms/official/
- [ ] 3. Admin upload + activate revision
- [ ] 4. Form Designer mappings (whitelist keys only)
- [ ] 5. If sheet has Methods/References footer → map Multiline `results.test_methods_references`
- [ ] 6. Bind package or analysis types
- [ ] 7. Preview + PHPUnit if resolver/filler changed
```

### Step 1 — Registry

- Extend **`OfficialAnalysisCatalog`** / package seeds via migration when adding catalog entries.
- Package result forms: set **`report_layout`**:
  - **`controlled_form`** — fixed slots; waived tests print `-`
  - **`dynamic_matrix`** — one matrix region; only selected tests as rows

### Step 2 — PDF asset

- Store source under **`resources/forms/official/`** (see README there).
- Admin uploads through **`ControlledFormAdminController`**; canonical PDF normalized for designer.

### Step 3 — Designer fields

- Allowed data keys: **`config/controlled_form_sources.php`** only.
- Optional designer presets: **`config/*_form_fields.php`** (copy structure from `result_fo4_form_fields.php`, `rfa_form_fields.php`, etc.).
- **Methods/References footer:** Multiline **`results.test_methods_references`** (selected tests only; `result_method` → Procedures → catalog). Prefer blank PDF methods area; `options.cover` only to blank obsolete printed methods ink.
**RFA-specific:**

- Sample lines: `sample_code_N`, `control_number_N` (1–8 left, 9–16 right; not `samples[]` for underline layout).
- Billing: `bill_param_N`, `bill_price_N`, `bill_total_N` (1–10 left, 11–20 right), `billing_total` / `billing_total_right` (right total when lines > 10).

**Dynamic matrix:**

- Add one field type **Dynamic test matrix** in designer; configure columns (method, acceptable values, etc.) per form doc.

### Step 4 — Runtime pipeline

Do not duplicate fill logic. Trace:

1. `AnalysisResultReportResolver` / job order resolver → form revision
2. `FieldValueResolver` → field values
3. `ControlledPdfFiller` → overlay + matrix draw

### Step 5 — Verify

- Admin preview + analyst preview endpoint
- **Calibration download** + populated PDF **download** (not only the on-screen preview) — overlays must match designer (`form-designer-pdf-parity` rule)
- Tests touching packages/forms if behavior changed
- **`docs/CONTROLLED_FORMS_EXPANSION.md`** for Aqua/General JO and catalog examples
- After Designer calibration: `php artisan controlled-forms:export-blueprints --form=CODE` so blueprint `page` mm matches FPDI

## Additional reference

- Field binding table and modules: [reference.md](reference.md)
