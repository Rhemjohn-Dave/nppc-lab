# Controlled Forms Expansion (Aqua + Food/Special)

This note summarizes the catalog and dual Job Order work delivered from the Controlled Forms Audit plan.

## Dual Job Orders

| Variant | Official | Internal code | When used |
|---|---|---|---|
| General | LSP 7.1 FO1 Issue 11 | `NPPC-LAB-FRM-001` | Default; Potability, Wastewater, Agriculture, Academic, Others |
| Aqua | LSP 7.1 FO4 Issue 03 | `NPPC-LAB-FRM-AQUA` | Classification contains **Aqua** and not Potability/Wastewater |

Resolver: `ControlledForm::jobOrderFormFor(JobOrder)` / `OfficialAnalysisCatalog::variantForClassification()`.

Intake shows Aqua sample sources (Sea / Brackish / River) when classification is Aqua; potability sterile-bottle field is hidden for Aqua.

## New analysis categories

PCR Analysis, Test Kits, Proximate Analysis, Phytochemical, Food / Special — seeded via migration + `OfficialAnalysisCatalog::definitions()`.

## Packages seeded

- `PKG-AQUA-WATER`, `PKG-AQUA-SOIL`, `PKG-AQUA-MIC`, `PKG-AQUA-PCR`
- `PKG-DW-PHYSICO` (₱4,900, `LSP-7.8-FO37` package dynamic matrix), `PKG-DW-BACT` (₱300 HPC/TC/FC), `PKG-MIC-NDW` (₱750 Total + Thermotolerant Coliform / FO4). Other catalog panels are **individual pay** (types-only result forms; legacy package rows stay inactive). `LSP-7.8-FO3` remains types-only Issue 11 DW physico; `LSP-7.8-FO2` is types-only Issue 18 wastewater physico; `LSP-7.8-FO26` / `LSP-7.8-FO27` are types-only food / sugar micro (Issue 4) with Tests | Control No. dynamic matrices.
- `PKG-PROXIMATE`, `PKG-MIC-FOOD`, `PKG-MIC-SUGAR`, `PKG-MILK`
- `PKG-WATER-ACTIVITY`, `PKG-FOOD-NITRITE`, `PKG-CAP`, `PKG-PHYTO`
- Existing `PKG-MIC-DW` / `PKG-MIC-NDW` unchanged

## Result form shells

Seeded Controlled Form shells (no PDF until admin upload) use distinct codes listed in `OfficialAnalysisCatalog::resultFormRegistry()` and `resources/forms/official/README.md`.

## RFA sample lines in Form Designer

For dual-column Job Order sample grids (Sample Code/Description + Control Number):

- Map **RFA sample line N** (`sample_code_N`) and **RFA control number line N** (`control_number_N`) — one pair per printed underline (N = 1…16; **1–8 left**, **9–16 right**).
- Do **not** use `samples[]` for that layout; `samples[]` is for a single vertical table region.

Control number suffix rule (`SampleControlNumber`):

| Samples on job | Printed control numbers |
|---|---|
| 1 | `reference_no` only (e.g. `2026-08`) |
| 2+ | `reference_no` + `A`, `B`, `C`… (e.g. `2026-08A`, `2026-08B`) |

The same values appear on the controlled-form overlay PDF used for Receiving/History print and preview. Job Order PDF download uses that overlay only (no DomPDF or HTML form fallback). The Samples table source also exposes a `control_number` column with these suffixes.

## RFA billing lines in Form Designer

For the dual-column Parameters / Price/Test / Total Cost grid:

- Map **RFA billing parameter/price/total line N** (`bill_param_N`, `bill_price_N`, `bill_total_N`) — lines **1–10** left column, **11–20** right.
- Map the left Total amount to **Billing total (RFA, left)** (`billing_total`) when ≤10 analysis lines.
- Map the right Total amount to **Billing total (RFA, right)** (`billing_total_right`) when lines spill past 10; the left amount is cleared. Both total fields are transparent (no white cover fill) so the printed underline stays visible.
- Do **not** use `analyses[]` for that paper layout; `analyses[]` is for a single vertical table.

Values come from analysis lines after Receiving sets prices (blank/zero until then).

## Remaining admin work

1. Upload official files from `resources/forms/official/` onto each shell.
2. Map designer fields and activate revisions (use RFA sample/control and billing line sources for Job Order grids).
3. Verify Receiving PDF uses Aqua vs General templates for sample jobs.
