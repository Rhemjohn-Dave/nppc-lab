---
name: nppc-lims-ui-forms
description: Builds or refactors NPPC LIMS React/Inertia forms (intake wizard, admin CRUD, dashboards). Use when adding form fields, wizard steps, validation UX, FormSection layout, or catalog pickers — not for PDF controlled forms.
---

# NPPC LIMS UI forms

## Quick decision

| Goal | Where to work |
|------|----------------|
| Public kiosk request | `resources/js/pages/intake/wizard.tsx` + `IntakeController` validation |
| Admin catalog (types, packages) | `resources/js/pages/admin/*` + matching admin controllers |
| Printable official layout | Use **`nppc-controlled-forms`** skill instead |

## Workflow: add or change a form field

```
Progress:
- [ ] 1. Backend validation + model fillable/casts (if persisted)
- [ ] 2. Inertia props (HandleInertiaRequests only if global)
- [ ] 3. React state + submit payload keys
- [ ] 4. FormSection / layout + errors
- [ ] 5. Feature test for submit rules
```

### Step 1 — Backend

- Add rules in the relevant controller (`IntakeController`, admin controllers).
- **`nullable`** vs required must match product rules; empty strings often stored as `''` after trim in services (see `JobOrderService` patterns).

### Step 2 — Props

- Pass enums/options from controller as plain arrays/objects — avoid hardcoding catalog in React when server already sends categories/packages.

### Step 3 — React

- **Wizard steps:** keep navigation (`goNext`, `canContinue`, `attemptedContinue`) in the page.
- **Presentational blocks:** `resources/js/components/intake/*` — pass callbacks, do not fork toggle/submit logic.
- Use shadcn **`Input`**, **`Textarea`**, **`Checkbox`**, **`Label`**, **`Button`**.

### Step 4 — Layout

- Authenticated queues/detail/admin lists: wrap with **`LimsWorkspace`** (`limsPageShellFluid`) from `@/components/lims/lims-workspace`.
- Intake / wide dashboards: `limsPageBackground`, `limsPageShellWide` from `@/lib/lims-page-shell`.
- Dense operational sections: `LimsSection`, `NextActionPanel`, `WorkflowTimeline` under `@/components/lims/`.
- Intake sections: **`FormSection`** with `first` on the first block in a step (divider style — avoid nested cards).
- Sample **Description** is optional (never `Description *`; never require it to continue).
- Sticky footers: `limsStickyFooterBleed` when inside padded workspace cards.
- Prefer responsive grids and `gap-4` over large vertical whitespace; do not shrink fonts for density.

### Step 5 — Tests

- Intake/JO: `tests/Feature/JobOrderSamplingPaymentTest`, `AnalysisPackageTest`, etc.
- Run `npm run build` for TypeScript safety.

## Intake wizard conventions

- Step indices: 0 Customer, 1 Details, 2 Samples, 3 Tests, 4 Review (verify in file before renumbering).
- Tests step: packages + individual catalog; display-only grouping in summary must not alter API payload.
- Kiosk pages: `Page.layout = null`.

## Admin form conventions

- Match existing patterns: confirm dialogs, table pagination components, Inertia `router` with preserveScroll where used elsewhere.
- Package ↔ form link is **read-only** in packages UI; bind forms in Controlled Forms admin.

## Examples

**FormSection block:**

```tsx
<FormSection title="Sampling details" first>
  <Label htmlFor="sampling_site">Sampling site</Label>
  <Input id="sampling_site" value={samplingSite} onChange={...} />
  <InputError message={errors.sampling_site} />
</FormSection>
```

**Step guard:**

```tsx
if (step === 2) return samples.length > 0;
```

## Additional reference

- Workflow context: `docs/SYSTEM_OVERVIEW.md`
- UI performance notes: `docs/UI_PERFORMANCE.md`
