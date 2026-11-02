# Deploy exact tests + controlled forms (existing server)

Use this when an **already-running** NPPC Lab server should match this repo’s analysis catalog and plotted controlled forms (same FO2/FO26/F016 plots and official PDFs as local).

For a brand-new VPS (OS, Nginx, MySQL, first `migrate --seed`), use [DEPLOY.md](DEPLOY.md) instead.

**Default:** keep existing job orders. Forms and catalog are refreshed in place.

---

## What this brings onto the server

| Piece | How it lands |
|-------|----------------|
| Analysis types, packages, prices | Idempotent heal via `DatabaseSeeder` catalog/package steps (optional full seed — see step 4) |
| Field plots + sole revision `1` | Migration `2026_10_27_130000_deploy_all_plotted_controlled_forms_sole_rev` + `ControlledFormDefaultsSeeder` |
| Official PDFs | Files under `resources/forms/official/` attached by `ControlledFormDefaultsSeeder` |

**Forms covered** (`ExportControlledFormBlueprintsCommand::TARGETS`):

- Job Order: `NPPC-LAB-FRM-001` (General RFA), `NPPC-LAB-FRM-AQUA`
- Results: `LSP-7.8-FO2`, `FO2A`, `FO3`, `FO37`, `FO4`, `FO5`, `F016-PROX`, `F016-MILK`, `F016-WA`, `F016-CAP`, `F016-NO2`, `FO26`, `FO27`

**Milk (`LSP-7.8-F016-MILK`):** repo PDF is reference-only. If the server already has a Designer-uploaded PDF, seed will **not** replace it.

---

## Do not

- `php artisan migrate:fresh` / `db:wipe` on this server
- Delete `storage/app/private` without a backup
- Blindly re-run `php artisan migrate --seed` on every routine pull (use the steps below)

---

## 1. Backup

Replace paths/DB names if yours differ.

```bash
cd /var/www/nppc-lab
sudo mkdir -p /var/backups/nppc-lab
sudo mysqldump -u nppc -p nppc | gzip > /var/backups/nppc-lab/nppc-$(date +%Y%m%d-%H%M).sql.gz
sudo tar -czf /var/backups/nppc-lab/private-$(date +%Y%m%d-%H%M).tar.gz -C storage/app private
```

Keep both archives until you have verified Controlled Forms and a sample print.

---

## 2. Code

```bash
cd /var/www/nppc-lab
git fetch origin
git pull   # or checkout the branch that has this exact forms work
composer install --no-dev --optimize-autoloader
```

Keep the existing `.env`. Confirm `APP_ENV=production` and `APP_DEBUG=false`.

---

## 3. Migrate

```bash
cd /var/www/nppc-lab
php artisan migrate --force
```

This runs the sole-revision deploy migration: each TARGET form collapses to active revision **`1`**, imports committed `config/*_form_fields.php` blueprints, syncs page metrics, and applies signatory slots/PRC. It does **not** wipe job orders.

---

## 4. Re-apply exact forms (and optionally catalog)

### Narrow path (forms + PDFs only) — preferred

Requires `admin@nppc.local` to exist (defaults seeder no-ops without it).

```bash
cd /var/www/nppc-lab
php artisan db:seed --class=ControlledFormDefaultsSeeder --force
```

This attaches official PDFs from `resources/forms/official/` (except Milk when a server PDF already exists), re-imports blueprints onto revision `1`, and activates that revision.

### Exact tests / packages (catalog heal)

If intake prices, package members, or form shells must match this repo as well:

```bash
cd /var/www/nppc-lab
php artisan db:seed --class=DatabaseSeeder --force
```

`DatabaseSeeder` heals users (does not reset existing passwords), catalog, packages, JO/result form shells, then calls `ControlledFormDefaultsSeeder` again. It does **not** delete job orders. Prefer this once after a major catalog/forms cutover; do not make it a weekly habit.

---

## 5. Front-end assets and caches

Vite’s Wayfinder plugin runs `php artisan wayfinder:generate --with-form` during `npm run build`. That command must succeed as the **deploy user** (the account that owns the app tree and runs `npm`), and must be able to write `resources/js/routes` and `resources/js/actions`.

```bash
cd /var/www/nppc-lab

# Clear any stale config/route caches before generating types / building
php artisan optimize:clear

# Fail fast with a readable PHP error (do not skip if this fails)
php artisan wayfinder:generate --with-form

npm ci
npm run build

sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
sudo php artisan queue:restart
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

If `wayfinder:generate` fails with permission errors, the deploy user cannot write under the app root (often after an accidental `chown -R www-data` on the whole tree). Restore deploy-user ownership, keep runtime dirs for PHP-FPM:

```bash
sudo chown -R "$USER":www-data /var/www/nppc-lab
sudo chown -R www-data:www-data /var/www/nppc-lab/storage /var/www/nppc-lab/bootstrap/cache
```

Restart Supervisor workers if `queue:restart` alone is not enough:

```bash
sudo supervisorctl restart all
```
---

## 6. Calibri fonts (PDF bold)

Overlays need Calibri on Linux. See [Controlled forms / PDFs](DEPLOY.md#controlled-forms--pdfs) in DEPLOY.md.

```bash
sudo mkdir -p /var/www/nppc-lab/storage/app/fonts
# copy calibri.ttf + calibrib.ttf from Windows Fonts into that folder
sudo chown -R www-data:www-data /var/www/nppc-lab/storage/app/fonts
```

---

## 7. Verify

```bash
cd /var/www/nppc-lab
php artisan nppc:production-check
```

In the UI (Admin → Controlled Forms), for each TARGET code:

- Active revision is **`1`**
- PDF is attached (Milk may keep a prior Designer upload)
- Form Designer shows the expected overlays / dynamic matrix

Spot-check preview/print for **FO26**, **FO2**, and **F016-MILK** (or the forms your lab uses most). Confirm Analyst **Signatories** appears for multi-sample ready JOs when a result form is bound.

---

## 8. Optional: wipe job orders and reset control numbers

Only if you want a clean operational slate (users, forms, catalog kept):

```bash
cd /var/www/nppc-lab
php artisan nppc:reset-operational-data --force
```

That deletes all job orders / results / generated docs and resets `reference_counters` so the next control number starts at `YY-0001`.

Optional demo JOs (local/staging only — not for live customer traffic):

```bash
php artisan demo:seed-result-jobs --fresh
php artisan demo:seed-result-jobs --full --fresh
```

---

## After Designer edits on a workstation

Before the next server pull:

```bash
php artisan controlled-forms:export-blueprints --pdfs
# commit updated config/*_form_fields.php and any new official PDFs
```

For Milk, prefer fields-only export (`--form=LSP-7.8-F016-MILK` without forcing a PDF overwrite of a calibrated upload). Then on the server, repeat steps 2–5 and 7.

---

## Related docs

- Full stack / first install: [DEPLOY.md](DEPLOY.md)
- Go-live gaps: [DEPLOYMENT_GAPS.md](DEPLOYMENT_GAPS.md)
- Form Designer / matrices: [DYNAMIC_CONTROLLED_FORMS.md](DYNAMIC_CONTROLLED_FORMS.md)
- Official PDF filenames: [resources/forms/official/README.md](../resources/forms/official/README.md)
