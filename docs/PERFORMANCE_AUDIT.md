# Performance Audit

This project includes automated **performance audits** to catch slow hot paths and redundant database work before they reach production.

## What it checks

| Check | Why it matters |
|-------|----------------|
| Dashboard payloads (admin, receiving, analyst, head) | Large Inertia props and extra queries slow every login |
| HTTP pages (`/dashboard`, `/receiving`, `/analyst`, `/head`) | End-to-end request time + query count |
| `FieldValueResolver` sample values | Controlled-form value mapping must stay cheap |
| Controlled form PDF preview | FPDI/TCPDF preview is CPU-heavy — regressions show up here |
| Dynamic matrix PDF preview | Matrix row measurement adds cost — tracked separately |
| Duplicate SQL detection | Same query repeated ≥4 times flags likely N+1 or redundant loops |

Budgets live in **`config/performance_audit.php`**. Override any limit with env vars, e.g.:

```env
PERFORMANCE_AUDIT_DASHBOARD_ADMIN_MAX_MS=800
PERFORMANCE_AUDIT_DUPLICATE_QUERY_THRESHOLD=5
```

## Run locally

### Artisan report (recommended for ad-hoc audits)

```bash
php artisan nppc:performance-audit --seed
```

Options:

- `--seed` — seed the database first (required on empty dev DB)
- `--fail-on-warn` — fail when duplicate-query warnings appear (stricter CI)

Example output:

```text
| Check                      | Status | Duration | Queries | Payload | Notes |
| Dashboard payload (admin)  | PASS   | 42.1 ms  | 28      | 18.2 KB | —     |
| Controlled form PDF preview| PASS   | 890.3 ms | 12      | —       | —     |
```

### PHPUnit (CI / regression gate)

```bash
php artisan test --group=performance
```

Or run the full class:

```bash
php artisan test tests/Feature/SystemPerformanceAuditTest.php
```

## Interpreting failures

### Duration exceeded

- **Dashboard** — look for uncached counts, missing `with()` eager loads, or unbounded `limit()` in `DashboardPayloadBuilder`.
- **HTTP pages** — compare controller queries vs service layer; prefer server pagination (see `docs/UI_PERFORMANCE.md`).
- **PDF preview** — normal to be slower (1–4s on SQLite). Failures usually mean a large regression (extra passes, repeated font embedding, duplicate `fill()` calls).

### Query count exceeded

- Add eager loading (`with`, `loadMissing`).
- Merge multiple `count()` queries where possible.
- Avoid resolving the same relation inside loops.

### Duplicate SQL warnings

Example:

```text
Same SQL ran 6 times: select * from "controlled_form_revisions" where ...
```

Typical fixes:

- Eager-load the relation once.
- Cache a count outside a loop.
- Remove redundant service calls in the same request.

### Payload too large

- Trim Inertia props — dashboard preview tables should use small `limit()` values (currently 5–8 rows).
- Do not embed PDF bytes or large catalogs in page props.
- See **`docs/UI_PERFORMANCE.md`** for frontend guardrails.

## Related code

| File | Role |
|------|------|
| `app/Support/Performance/PerformanceAuditor.php` | Runs all benchmarks |
| `app/Support/Performance/PerformanceMeasurement.php` | Single check result + budget comparison |
| `config/performance_audit.php` | Time/query/payload budgets |
| `app/Console/Commands/PerformanceAuditCommand.php` | `nppc:performance-audit` |
| `tests/Feature/SystemPerformanceAuditTest.php` | PHPUnit regression tests |
| `docs/UI_PERFORMANCE.md` | UI/payload rules for queue and designer screens |

## What this does *not* replace

- Browser profiling (Lighthouse, React DevTools) for client-side jank
- Production APM (Sentry, Datadog, Laravel Pulse) for real-user latency
- Load testing under concurrent analysts

Use this audit as a **fast, local regression net** during development and in CI.

## Adding a new benchmark

1. Add a method to `PerformanceAuditor::run()`.
2. Register a budget in `config/performance_audit.php`.
3. Extend `SystemPerformanceAuditTest` if the check needs fixtures.
4. Document the check in this file.

Keep benchmarks **idempotent** and safe on seeded data — they should not mutate production records when run via `nppc:performance-audit` on staging.
