# Intake & catalog audit questions

Use this with the NPPC price list (`NPPC_Test_Parameters_and_Prices.docx`).  
**Matrix on intake** = sample type (water / soil / food / prawn), not the Dynamic Test Matrix on result PDFs.

Reply in chat with answers `1`–`15`, or edit the **Answers** section below.

---

## Questions

### A. Job order axis (Aqua vs non-Aqua)

1. When classification = **Aqua**, hide **all** non-Aqua packages and individual tests (no “Other packages”)? (Yes / No)
2. When classification is Potability / Wastewater / Agriculture / etc., hide **all** Aqua-only tests/packages? (Yes / No)
3. Can any test belong to **both** scopes (e.g. Chloramphenicol, Metals)? If yes, list them or say “same price list row appears under both.”

### B. Catalog structure (from the price list)

4. Should intake category headings match the **price list section titles** exactly (Waste Water, Drinking Water, Soil Analysis - Aquaculture, …), replacing today’s Microbiological / Physico-Chemical / PCR groupings?
5. **Chemicals / Reagents** — include in intake as selectable items, admin-only catalog, or omit from the system for now?
6. **Sample Preparation** rows (Proximate, Nutrifacts, Metals, etc.) — treat as billable line items customers can select, or auto-add when related tests are chosen, or omit?
7. Handwritten / price-unclear rows — omit until price known, or list at price `0` / “TBD” for selection?

### C. Packages vs individual tests

8. Which price-list packages must exist as selectable packages? At minimum confirm:
   - Drinking Water physico-chemical package (₱4,900)
   - Bacteriological HPC/TC/FC (₱300)
   - Existing Aqua packages (mic / PCR) — keep, rename, or rebuild from Soil/Water/Prawn Aqua lists?
9. May customers still pick **individual tests** outside packages, or package-only for some sections (e.g. drinking water)?
10. Unchecking members inside a package — keep current behavior (still package result sheet; unchecked print “-”)?

### D. Matrix and sample fields

11. Keep **Matrix** as free text, or change to a fixed list (Water / Soil / Food / Prawn / Other)? Prefer renaming the label to **Sample type**?
12. For Aqua, should Matrix be required / auto-filled from sample source (Sea / Brackish / River)?
13. Is **customer sample code** required at intake, or optional until receiving?

### E. Intake flow / UX

14. Should **classification** move earlier (before samples) because it gates the whole catalog?
15. Anything on intake today that is unused or confusing (ownership, sterile bottle, field data, “other tests” free text) that you want removed or required?

---

## Answers (interim defaults applied in code)

Until you override these, the app uses:

| # | Interim answer |
|---|----------------|
| 1 | **Yes** — Aqua shows only Aqua (and `both`) packages/tests |
| 2 | **Yes** — non-Aqua hides Aqua-only packages/tests |
| 3 | **Both**: Metals section + Chloramphenicol; everything else is aqua-only or non-aqua-only by price-list section |
| 4 | **Yes** — category headings match price-list section titles |
| 5 | **Omit** Chemicals / Reagents from customer intake |
| 6 | **Selectable** billable line items (priced Sample Preparation rows) |
| 7 | **Omit** rows with unclear / missing prices (Temperature FREE → ₱0) |
| 8 | Add DW physico (₱4,900) + DW bacteriological (₱300); rebuild Aqua packages from Soil / Water / Prawn aqua lists; keep FO4/FO5 / food / proximate result-linked packages |
| 9 | **Yes** — individual tests always allowed |
| 10 | **Keep** uncheck → “-” on package result sheet |
| 11 | Rename label to **Sample type**; keep free text |
| 12 | **Optional**; do not auto-fill from sample source |
| 13 | **Optional** at intake |
| 14 | **Yes** — Details (classification) before Samples |
| 15 | Keep ownership / sterile bottle / field data / other tests for now |

Override any row above when you have final client decisions.
