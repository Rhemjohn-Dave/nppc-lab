# Official NPPC controlled form sources

Source Word files for dual Job Orders and food/special result sheets.
These are **reference uploads only** — they are not auto-activated.

## Job Orders

| File | Variant | Official code | Internal `form_code` |
|---|---|---|---|
| `JOB ORDER Issue 11 09012026.pdf` (+ `.docx`) | General | LSP 7.1 FO1 Issue 11 | `NPPC-LAB-FRM-001` |
| `aqua job order issue 3 2026.pdf` (+ `.docx`) | Aqua | LSP 7.1 FO4 Issue 03 | `NPPC-LAB-FRM-AQUA` |

Runtime selection: classification contains **Aqua** (and not Potability/Wastewater) → Aqua form; otherwise → General.

General Issue 11 PDF includes **Sampling site** (free text) plus **Payment mode** (Cash / Billing·Partial / Check) and **Payment terms** (15 / 30 days) overlays (`config/rfa_form_fields.php`). Capture these at kiosk intake; Aqua form is unchanged for now.

## Analysis result sheets

| File | Internal `form_code` | Package code |
|---|---|---|
| PC Wastewater result Form Issue 18 09012026 blank.pdf (+ `.doc`) | `LSP-7.8-FO2` | *(types-only / individual pay)* |
| PC Drinking Water Test Result Form Issue 7 09012026.docx/.pdf | `LSP-7.8-FO37` | `PKG-DW-PHYSICO` (dynamic matrix) |
| PC OLD Drinking Water Test Result Form Issue 11 blank.pdf | `LSP-7.8-FO3` | *(types-only / individual pay)* |
| *(seeded)* `lsp-7.8-fo4-micro-non-drinking-water.pdf` | `LSP-7.8-FO4` | `PKG-MIC-NDW` |
| *(seeded)* `lsp-7.8-fo5-micro-drinking-water.pdf` | `LSP-7.8-FO5` | `PKG-MIC-DW` |
| Proximate Analysis Result Form.pdf (+ `.doc`) | `LSP-7.8-F016-PROX` | *(types-only / individual pay)* |
| micro food test result form issue4 07152026.pdf (+ `.doc`) | `LSP-7.8-FO26` | *(types-only / individual pay)* |
| FOOD MICRO SUGAR Test Result form Issue4 07152026.pdf (+ `.docx`) | `LSP-7.8-FO27` | *(types-only / individual pay)* |
| water activity test result form - blank.pdf (+ `.doc`) | `LSP-7.8-F016-WA` | *(types-only / individual pay)* — blank table; with-table `.doc`/`.pdf` is reference only |
| water activity test result form.doc (+ `.pdf`) | `LSP-7.8-F016-WA` | *(reference with printed table)* |
| Nitrite Test Result Form-blank.pdf (+ `.doc`) | `LSP-7.8-F016-NO2` | *(types-only / individual pay)* — blank table; with-table `.doc`/`.pdf` is reference only |
| Nitrite Test Result Form.doc (+ `.pdf`) | `LSP-7.8-F016-NO2` | *(reference with printed table)* |
| Chloramphenicol Test Result Form-blank.pdf (+ `.doc`) | `LSP-7.8-F016-CAP` | *(types-only / individual pay)* — blank table; with-table `.doc`/`.pdf` is reference only |
| Chloramphenicol Test Result Form.doc (+ `.pdf`) | `LSP-7.8-F016-CAP` | *(reference with printed table)* |
| phytochemical qualitative test result form.doc | `LSP-7.8-F016-PHYTO` | `PKG-PHYTO` |
| Milk Sample Test Result Form.pdf (+ `.doc`) | `LSP-7.8-F016-MILK` | *(types-only / individual pay)* — **reference only**; Form Designer upload is authoritative once attached |

Several printed sheets share official label **LSP 7.8 F016**. Distinct internal codes avoid collisions in Document Control.

`LSP-7.8-FO4` and `LSP-7.8-FO5` ship field blueprints at `config/result_fo4_form_fields.php` and `config/result_fo5_form_fields.php` (registered in `config/controlled_form_blueprints.php`). Fresh installs seed those overlay boxes automatically; Form Designer → **Import blueprint** reloads them after you upload the PDF.

`LSP-7.8-FO2` (Physico-Chemical Test Results — Wastewater, Issue 18) is **individual / per-test**: no package binding, types-only dynamic matrix (`config/result_ww_physico_form_fields.php`). Any non-empty subset of its bound WW/SA panel tests resolves this form. Source blank: `PC Wastewater result Form Issue 18 09012026 blank.pdf`.

`LSP-7.8-FO37` (Drinking Water Physico-Chemical Issue 7) ships with a **blank-table** PDF under this folder. The dynamic matrix draws Tests | Method | Results | Acceptable Values | Remarks (`config/result_dw_physico_form_fields.php`); package `PKG-DW-PHYSICO` uses `report_layout = dynamic_matrix`. Do **not** confuse with FO5 drinking-water **microbiology**.

`LSP-7.8-FO3` (Drinking Water Physico-Chemical Issue 11 OLD) is the **individual / per-test** sheet: no package binding, types-only dynamic matrix (`config/result_dw_old_fo3_form_fields.php`). Any non-empty subset of its bound DW tests resolves this form; package DW jobs still prefer FO37. Source blank: `PC OLD Drinking Water Test Result Form Issue 11 blank.pdf` (from the without-table DOCX).

## Admin steps

1. Admin → Controlled Forms → open the seeded shell (or create with matching `form_code` / Job Order variant).
2. Upload the matching file from this folder.
3. Map overlays in Form Designer.
4. Activate the revision.
5. Confirm package `form_code` / binding matches.
