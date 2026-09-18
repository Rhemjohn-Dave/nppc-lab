# Agent guide — NPPC LIMS

This repository is the **NPPC Laboratory Management System** (Laravel + Inertia + React).

## Start here

1. **`docs/SYSTEM_OVERVIEW.md`** — roles, JO approval, analyst assignment, result release.
2. **`.cursor/rules/`** — automatic coding conventions (core rule always applies).
3. **`.cursor/skills/`** — deep workflows when building forms:
   - **`nppc-controlled-forms`** — PDF controlled forms, Form Designer, RFA/result sheets.
   - **`nppc-lims-ui-forms`** — React/Inertia screens (intake wizard, admin CRUD).

## Two kinds of “forms”

| Kind | Examples | Guide |
|------|----------|--------|
| **LIMS UI forms** | Intake wizard, admin package editor | Rule: `lims-ui-forms.mdc` · Skill: `nppc-lims-ui-forms` |
| **Controlled PDF forms** | RFA, JO print, FO4/FO5 result overlays | Rule: `controlled-pdf-forms.mdc` · Skill: `nppc-controlled-forms` |

Do not mix them: PDF mapping uses whitelisted keys in `config/controlled_form_sources.php`; intake uses HTTP validation + Inertia props.

## Commands

```bash
php artisan test --filter=SomeTest
npm run build
```

## Change discipline

- Minimize scope; match existing services and components.
- Do not change lab workflow, pricing formulas, or API contracts unless explicitly requested.
- Commit only when the user asks.
