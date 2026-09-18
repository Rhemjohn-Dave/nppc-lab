# Controlled forms reference (NPPC LIMS)

## Key PHP classes

| Class | Role |
|-------|------|
| `ControlledFormAdminController` | CRUD, designer save, preview |
| `ControlledFormService` | Revision workflow helpers |
| `ControlledFormStorage` | Upload, canonical PDF |
| `ControlledPdfFiller` | Production PDF output |
| `FieldValueResolver` | Maps source keys → strings for overlay |
| `AnalysisResultReportResolver` | Which result form applies to a job |
| `ControlledForm::jobOrderFormFor()` | General vs Aqua JO |

## Frontend

- Form Designer: `resources/js/pages/admin/form-designer.tsx`

## Config files

- `config/controlled_form_sources.php` — whitelisted mapper keys
- `config/controlled_form_blueprints.php` — designer blueprints if used
- `config/rfa_form_fields.php`, `rfa_aqua_form_fields.php` — JO/RFA preset fields
- `config/result_fo4_form_fields.php`, `result_fo5_form_fields.php`, `result_dw_*` — result presets

## Docs (repo)

- `docs/CONTROLLED_FORMS.md` — technical overview
- `docs/DYNAMIC_CONTROLLED_FORMS.md` — matrix behavior
- `docs/CONTROLLED_FORMS_EXPANSION.md` — Aqua JO, packages, RFA grid notes
