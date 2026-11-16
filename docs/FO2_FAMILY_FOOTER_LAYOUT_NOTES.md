# FO2-family footer layout notes (reference for other forms)

Working notes from calibrating **FO2 / FO2A / FO2B** result PDFs (Issue 18 wastewater physico). Use this when fixing footers on other controlled forms (FO3, FO37, FO28, etc.).

**Code:** `app/Services/ControlledPdfFiller.php`  
**Related:** `docs/DYNAMIC_CONTROLLED_FORMS.md`  
**Configs:** `config/result_ww_physico*_form_fields.php`

---

## 1. Anchor chain (do not skip)

| Step | Anchors to | Rule |
|------|------------|------|
| Matrix | Designer Y | Draws first; exposes measured bottom |
| Lab conditions / references / NOTES | **Matrix bottom** | `anchor.below: ww_fo2*_matrix` |
| Signatories (first row) | **NOTES ink bottom** | `anchor.below: fo2_notes` + `offset_mm` — **not** the matrix |
| Later signatory (FO2A Noted) | **Prior signatory ink** | Prefer chain under measured Reviewed bottom, not a large NOTES offset |
| Test Report (`fo2_form_meta`) | Stack / stamp pin | `max(stack + gap, pinnedAboveStamp)` |
| Page stamp | Under meta or pin | Follows Test Report; do not leave a huge dead band |

**Important:** Signatories must track **measured NOTES ink** (`fieldContentBottomY['fo2_notes']`), not designer NOTES `y`/`h` alone. Expand NOTES write height so the last note line always prints before signatories.

---

## 2. Short vs tall sheets

| Mode | Typical tests | Behavior |
|------|---------------|----------|
| **Short** | ~6–12 | Matrix nudge + taller rows / +1 pt fonts when spare ≥ ~24 mm; chrome packs under NOTES |
| **Tall** | ~17–19 | Prefer NOTES→signatory gap floor **1.5 mm**; shrink shared first-row gap only (do not collapse second-row onto first); optional **−1 pt** on footer fonts |

Detect tall from: NOTES (+ FIELD DATA) + signatory stack + meta gap vs pinned Test Report Y.

**Do not** use the old FO2 20 mm short-sheet signatory nudge on notes-bottom FO2 forms — it parks signatures in the wrong band.

---

## 3. Per-form decisions (current calibrated values)

### FO2 (Wastewater / River)

- Signatories: Reviewed / Noted / Certified under NOTES
- Preferred NOTES→first signatory gap: **6 mm**
- Tall: tighten toward **1.5 mm** floor; meta follows stack if still tight

### FO2A (Abstracted water)

- Slots: **Reviewed | Certified** on one row; **Noted By** under Reviewed
- Footer pin / page stamp drop vs FO2/FO2B: **~7 mm** (`FO2A_FOOTER_DROP_MM`)
- Few tests: Reviewed/Certified extra drop **+5 mm** under NOTES
- **Noted By gap under Reviewed ink:**
  - Few / non-tall: **7 mm**
  - Tall (~19 tests): **3 mm**
- Implementation: draw Reviewed/Certified first, then re-anchor Noted to `below: fo2_signatory_reviewed` with that gap. **Remove `above: fo2_form_meta`** for that draw so tall sheets do not park Noted near Test Report.

### FO2B (Coastal)

- NOTES left column (~**110 mm** wide); **FIELD DATA** right column
- Matrix FIELD DATA offset under table: **18 mm** (same band as NOTES when side-by-side)
- **Few / non-tall:** stack FIELD DATA **under NOTES** (keep Designer x/w; Y = NOTES ink + **7 mm**)
- **Tall:** keep FIELD DATA beside NOTES; if Designer Y would clip past Test Report reserve, **raise into NOTES band** — never clamp height to **0**
- Signatories under **max(NOTES ink, FIELD DATA ink)** so Certified does not cover FIELD DATA
- Tall: **−1 pt** on lab conditions, references, NOTES, FIELD DATA, signatories, Test Report
- Live heals matter: wrong `fo2_form_meta` offset (e.g. 90 vs 33) breaks short-sheet detection and stacking

---

## 4. Patterns to reuse on other forms

1. **Chain under measured ink**  
   Second-row labels (Noted, second certifier, etc.) should follow the **previous block’s content bottom**, not a large fixed offset from the matrix/notes that drifts after shrink.

2. **Never zero-clip required content**  
   Soft ceilings must **raise Y** or shrink fonts — not set `h = 0`.

3. **Shrink only the shared base gap**  
   When packing under Test Report, subtract overflow from the **minimum** NOTES→first-row offset only. Subtracting full overflow from every offset collapses rows onto each other.

4. **Short vs tall branching**  
   Use spare-under-pin and/or tall detection — not row count alone. Real rows with Date/Time sublines are taller than synthetic test rows.

5. **Heal live Designer offsets**  
   Config blueprints and live DB drift. After layout fixes, add a heal migration for the active revision (offsets, widths, meta pin). Re-print after migrate.

6. **Page stamp and Test Report move together**  
   If you drop the footer band, lower the stamp pin and meta pin the same amount for that form only (form-code-specific constant).

7. **Verify both ends**  
   Always re-print a **few-test** and a **full-test** PDF. Short and tall paths diverge.

---

## 5. Constants (ControlledPdfFiller)

| Constant | Role (approx.) |
|----------|----------------|
| `FO2_NOTES_TO_SIGNATORY_GAP_MM` | Preferred NOTES→first signatory (**6**) |
| `FO2_NOTES_TO_SIGNATORY_GAP_TIGHT_MM` | Tall floor (**1.5**) |
| `FO2_SIGNATORY_TO_META_GAP_MM` | Last signatory→Test Report (**5**) |
| `FO2_TALL_CHROME_FONT_REDUCE_PT` | Tall footer font shrink (**1**) |
| `FO2B_FIELD_DATA_BELOW_NOTES_GAP_MM` | Short FO2B FIELD DATA under NOTES (**7**) |
| `FO2A_FOOTER_DROP_MM` | FO2A stamp/meta drop (**7**) |
| `FO2A_SHORT_FIRST_ROW_DROP_MM` | Few-test Reviewed/Certified drop (**5**) |
| `FO2A_SHORT_NOTED_EXTRA_GAP_MM` | Few-test Noted under Reviewed (**7**) |
| `FO2A_TALL_NOTED_EXTRA_GAP_MM` | Tall Noted under Reviewed (**3**) |
| `PAGE_STAMP_BOTTOM_MM` | Default stamp from page bottom (**26**; FO2A reduced by footer drop) |

---

## 6. Checklist when fixing another form

- [ ] What is the **true** parent of each footer block (matrix vs notes vs prior signatory)?
- [ ] Is Y based on **measured ink** or designer box height?
- [ ] Few-test and full-test both printed?
- [ ] Required blocks (FIELD DATA, notes) never clipped to zero height?
- [ ] Multi-row signatories: relative gaps survive tall shrink?
- [ ] Test Report + page stamp stay a consistent pair above the page edge?
- [ ] Live revision healed if Designer/DB offsets drifted?
- [ ] Targeted tests + `php artisan test --filter=…` for the form?

---

## 7. Session changelog (2026-11-15)

- FO2B: FIELD DATA visible on tall sheets; stack under NOTES on short; −1 pt tall chrome; gaps/heals for meta offset and signatory drift
- FO2A: footer drop 7 mm; Noted chained under Reviewed ink; few-test Reviewed drop +5 mm; Noted gaps **7 mm** (few) / **3 mm** (tall)
- Shared: notes-bottom signatory chain; no Test Report clamp pulling Noted downward on tall FO2A
