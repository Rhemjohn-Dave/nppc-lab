# Deployment (Apache) — NPPC Lab LMS

First-time install using **Apache 2.4 + PHP-FPM** on **Ubuntu 24.04** (or 22.04). Prefer **Nginx** if you can — see [DEPLOY.md](DEPLOY.md).

Controlled-form PDFs live in `storage/app/private`. Back that directory up with the database.

Known go-live gaps: [DEPLOYMENT_GAPS.md](DEPLOYMENT_GAPS.md). Form refresh on an existing server: [DEPLOY_SERVER_FORMS.md](DEPLOY_SERVER_FORMS.md). Routine `git pull` deploys: [DEPLOY.md — Subsequent deploys](DEPLOY.md#subsequent-deploys).

Replace `lab.example.com`, the DB password, and the git remote with your values.

## Stack

| Piece | Version / notes |
|--------|------------------|
| OS | **Ubuntu 24.04 LTS** (recommended) or 22.04 LTS |
| Web | **Apache 2.4** + `mod_proxy_fcgi` (not `mod_php`) |
| PHP | **8.3** CLI + FPM: `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`, `mysql`, `redis` |
| DB | **MySQL 8.0+** |
| Redis | Queue, cache, session |
| Node.js | **22** (asset build only) |
| Composer | 2 |
| Supervisor | Queue worker + Reverb |

---

## 1. Install Apache + app packages

If Nginx is installed and you are switching to Apache, stop it so ports 80/443 are free:

```bash
sudo systemctl disable --now nginx 2>/dev/null || true
```

Install **Apache**, PHP-FPM, MySQL, Redis, and helpers:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server redis-server git unzip curl supervisor \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-redis
```

**Composer:**

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

**Node.js 22** (NodeSource):

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # expect v22.x
```

**Enable Apache modules** (PHP via FPM socket, not `libapache2-mod-php`):

```bash
sudo a2enmod rewrite headers proxy proxy_http proxy_wstunnel ssl
sudo a2enmod proxy_fcgi setenvif
sudo a2enconf php8.3-fpm
sudo systemctl enable --now apache2 mysql redis-server php8.3-fpm
sudo systemctl status apache2 --no-pager
```

You should see `apache2.service` **active (running)**. If `Unit apache2.service not found`, the `apache2` package did not install — re-run the `apt install -y apache2 ...` block and check for apt errors.

---

## 2. MySQL database and user

```bash
sudo mysql
```

```sql
CREATE DATABASE nppc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nppc'@'127.0.0.1' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON nppc.* TO 'nppc'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Use a long random password; put the same value in `.env` as `DB_PASSWORD`.

---

## 3. Application code

```bash
sudo mkdir -p /var/www/nppc-lab
sudo chown -R "$USER":www-data /var/www/nppc-lab
cd /var/www/nppc-lab
git clone <YOUR_REPO_URL> .
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

The repo pins Composer’s `platform.php` to **8.3.6** so `composer.lock` stays installable on Ubuntu’s PHP 8.3. Do not regenerate the lock on PHP 8.4 without that platform pin.

---

## 4. Production `.env` (minimum)

Edit `/var/www/nppc-lab/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lab.example.com
NPPC_LAB_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nppc
DB_USERNAME=nppc
DB_PASSWORD=STRONG_PASSWORD_HERE

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=lab.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=nppclab@gmail.com

LOG_LEVEL=error
```

Generate Reverb credentials with `php artisan reverb:install` (or set `REVERB_*` manually). Rebuild front-end assets after `VITE_REVERB_*` are set so they bake into the JS bundle.

**Preferred for SMTP:** configure **Admin → Email Settings** after first login ([EMAIL_SETTINGS.md](EMAIL_SETTINGS.md)). Keep `APP_KEY` stable.

### LAN / IP access (HTTP, no TLS yet)

When staff open the app as `http://192.168.x.x` (or any LAN IP) instead of a public HTTPS hostname:

1. **`VITE_REVERB_HOST` must be that LAN IP** (or a DNS name every browser can resolve). Never leave `localhost` / `127.0.0.1` after `npm run build` — the browser would open WebSockets on *the user’s PC*, not the lab server.
2. Set `APP_URL` to the same URL staff use, and **`SESSION_SECURE_COOKIE=false`** on plain HTTP (otherwise CSRF/session cookies break and lab-conditions `PUT` returns **419**).
3. Keep Apache’s `/app` → `127.0.0.1:8080` proxy (vhost above) and Reverb listening on `0.0.0.0:8080`.

Example (replace the IP with yours):

```env
APP_URL=http://192.168.192.114
SESSION_SECURE_COOKIE=false

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=nppc-lab
REVERB_APP_KEY=nppc-local-key
REVERB_APP_SECRET=<same as installed>
REVERB_HOST=192.168.192.114
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY=nppc-local-key
VITE_REVERB_HOST=192.168.192.114
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Then rebuild and restart so the JS bundle picks up the new host:

```bash
cd /var/www/nppc-lab
sudo php artisan config:clear && sudo php artisan config:cache
sudo npm ci && sudo npm run build
sudo supervisorctl restart nppc-lab-reverb
sudo systemctl reload apache2 php8.3-fpm
```

Hard-refresh browsers (or clear cache). DevTools → Network / console should show `ws://192.168.192.114:...`, not `localhost`. Prefer same-host path `/app` later under TLS (`REVERB_PORT`/`VITE_REVERB_PORT` = `443`, `SCHEME=https`); for plain HTTP LAN, host+port `8080` or proxied `/app` both work if the firewall allows it.

**Lab conditions save (analyst result footer):** `PUT /analyst/job-orders/{id}/result-footer` needs a working session (see `SESSION_SECURE_COOKIE` above) and, for FO2–FO37 sheets, **Temperature °C**, **Humidity %**, and **Test Report #**. Empty required fields → **422**; broken Secure cookie on HTTP → **419**. WebSocket noise does not block this PUT.

**FIELD DATA block:** shown only for **LSP-7.8-FO2B** or a first sample whose description is Coastal Water. FO2 / FO2A / FO3 / FO37 correctly show lab atmosphere + Test Report # only — no FIELD DATA UI.

---

## 5. Permissions, migrate, seed once, build

**Fix ownership before migrate/seed/build.** If `storage/logs` or `storage/app/private` is not writable, artisan fails with *Permission denied* on `laravel.log`, seed cannot create controlled-form dirs, and `npm run build` fails when Wayfinder runs `php artisan wayfinder:generate`.

```bash
cd /var/www/nppc-lab

# Deploy user owns the tree; www-data (Apache/PHP-FPM) can write runtime dirs
sudo chown -R "$USER":www-data /var/www/nppc-lab
mkdir -p storage/logs storage/app/private storage/framework/{cache,sessions,views}
touch storage/logs/laravel.log
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
```

Confirm `.env` has production values (see §4), especially:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lab.example.com
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
```

(`MAIL_MAILER=log` fails `nppc:production-check`. Prefer Admin → Email Settings after login.)

Then:

```bash
cd /var/www/nppc-lab
php artisan migrate --force --seed
php artisan nppc:production-check
php artisan wayfinder:generate --with-form
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Seed accounts (`admin@nppc.local`, `receiving@nppc.local`, `analyst@nppc.local`, `head@nppc.local`) all start with password `password`. **Change every one before staff log in.**

`--seed` is **first install only**. Do not seed on routine deploys.

LibreOffice is optional. If the production check warns it is missing, upload **PDFs** for controlled forms (DOCX conversion will be rejected).

Ensure `calibri.ttf` and `calibrib.ttf` are in `storage/app/fonts/` (see [DEPLOY.md — Controlled forms / PDFs](DEPLOY.md#controlled-forms--pdfs)).

---

## 6. Apache site

Create `/etc/apache2/sites-available/nppc-lab.conf`:

```apache
<VirtualHost *:80>
    ServerName lab.example.com
    Redirect permanent / https://lab.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName lab.example.com
    DocumentRoot /var/www/nppc-lab/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/lab.example.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/lab.example.com/privkey.pem

    <Directory /var/www/nppc-lab/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        FallbackResource /index.php
    </Directory>

    # PDF uploads / overlays
    LimitRequestBody 16777216

    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"

    # Laravel Reverb (WebSockets) — same path as Nginx /app
    ProxyPreserveHost On
    ProxyPass        /app http://127.0.0.1:8080/app
    ProxyPassReverse /app http://127.0.0.1:8080/app
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/app/(.*)$ ws://127.0.0.1:8080/app/$1 [P,L]

    # PHP-FPM 8.3
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Deny hidden files (except ACME)
    <DirectoryMatch "^\.|/\.">
        Require all denied
    </DirectoryMatch>

    ErrorLog ${APACHE_LOG_DIR}/nppc-lab-error.log
    CustomLog ${APACHE_LOG_DIR}/nppc-lab-access.log combined
</VirtualHost>
```

If Let’s Encrypt is not installed yet, start with HTTP-only (comment out the SSL vhost and `Redirect`, serve `:80` with the same `DocumentRoot` / PHP / `/app` blocks), then run Certbot (step 7) so it can obtain certificates and rewrite the vhost.

Enable the site (**Ubuntu / Debian**):

```bash
sudo a2dissite 000-default.conf
sudo a2ensite nppc-lab.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

**RHEL-family:** drop the same VirtualHost into `/etc/httpd/conf.d/nppc-lab.conf`, then:

```bash
sudo apachectl configtest
sudo systemctl reload httpd
```

Optional: if `php8.3-fpm` conf already registers handlers globally and you see double-handler warnings, keep `a2enconf php8.3-fpm` and drop the `<FilesMatch>` block from the vhost (or the reverse — one PHP handler only).

---

## 7. TLS (Certbot)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d lab.example.com
```

Confirm `APP_URL=https://lab.example.com` and `SESSION_SECURE_COOKIE=true`, then:

```bash
cd /var/www/nppc-lab
php artisan config:cache
php artisan nppc:production-check
```

The app forces HTTPS and trusts `X-Forwarded-*` when present. Health check: `GET /up`.

---

## 8. PHP-FPM limits

Edit `/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 16M
memory_limit = 256M
```

```bash
sudo systemctl reload php8.3-fpm
```

---

## 9. Supervisor (queue + Reverb) and cron

Customer emails are queued — the worker **must** run. Reverb serves live queue/bell updates on `/app`.

`/etc/supervisor/conf.d/nppc-lab-worker.conf`:

```ini
[program:nppc-lab-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nppc-lab/artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/nppc-lab/storage/logs/worker.log
```

`/etc/supervisor/conf.d/nppc-lab-reverb.conf`:

```ini
[program:nppc-lab-reverb]
command=php /var/www/nppc-lab/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/nppc-lab/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Scheduler crontab (root or deploy user):

```cron
* * * * * cd /var/www/nppc-lab && php artisan schedule:run >> /dev/null 2>&1
```

---

## 10. Smoke checks

- `curl -fsS https://lab.example.com/up` returns OK
- Log in as admin; open Controlled Forms; upload/preview a PDF
- `redis-cli ping` → `PONG`; Supervisor programs `RUNNING`
- `storage/app/private` writable by `www-data`
- Print a result sheet and confirm Calibri bold headers

---

## Subsequent deploys

Same application steps as Nginx hosts — see [DEPLOY.md — Subsequent deploys](DEPLOY.md#subsequent-deploys). Do not `--seed`. Do not `migrate:fresh` / `db:wipe` on production.

After pulling form plot changes, use [DEPLOY_SERVER_FORMS.md](DEPLOY_SERVER_FORMS.md) when you need the server plots/PDFs to match this repo.

---

## Troubleshooting

### Storage / logs permission denied

Symptom: `Failed to open stream: Permission denied` on `storage/logs/laravel.log`, or *Unable to create a directory at …/storage/app/private/controlled_forms/…*, and `nppc:production-check` reports `storage/app/private` / `storage/logs` not writable.

```bash
cd /var/www/nppc-lab
sudo chown -R "$USER":www-data /var/www/nppc-lab
mkdir -p storage/logs storage/app/private storage/framework/{cache,sessions,views}
touch storage/logs/laravel.log
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;
php artisan nppc:production-check
```

### `nppc:production-check` FAIL items

| FAIL | Fix in `.env` (then `php artisan config:clear`) |
|------|--------------------------------------------------|
| `APP_ENV=production` | `APP_ENV=production` |
| `APP_URL uses https` | `APP_URL=https://your-domain` (not `http://`) |
| Mail configured | `MAIL_MAILER=smtp` + host/user/pass, or Admin → Email Settings |
| `storage/… is writable` | Run the permission block above |

LibreOffice warning is non-blocking if you upload PDF controlled forms.

### `npm run build` / Wayfinder fails

Symptom: Vite plugin `@laravel/vite-plugin-wayfinder` → `php artisan wayfinder:generate --with-form` fails (often after a log/storage permission error).

```bash
cd /var/www/nppc-lab
# Fix storage first (see above), then:
sudo chown -R "$USER":www-data resources/js
php artisan wayfinder:generate --with-form -vvv
npm run build
```

### Apache-specific

| Symptom | Check |
|---------|--------|
| 403 on `/` | `DocumentRoot` must be `.../public`; `Require all granted` on that directory |
| 404 on every route except `/` | `mod_rewrite` / `AllowOverride All` / `FallbackResource /index.php` |
| `.php` downloads or 503 | `php8.3-fpm` running; socket `/run/php/php8.3-fpm.sock`; only one PHP handler |
| Live updates dead | Reverb Supervisor up; Apache `/app` proxy + websocket rewrite; `VITE_REVERB_*` rebuilt |
| WS to `localhost:8080` from phones/other PCs | `VITE_REVERB_HOST` still baked as `localhost` — set LAN IP/hostname, `npm run build`, hard-refresh (see [LAN / IP access](#lan--ip-access-http-no-tls-yet)) |
| Lab conditions save 419 on HTTP | `SESSION_SECURE_COOKIE=false`, `APP_URL=http://…`, then `php artisan config:cache` + reload PHP-FPM |
| Lab conditions save 422 | Fill Temp, Humidity, Test Report # (and FIELD DATA when FO2B/Coastal) |
| FIELD DATA missing on FO2/FO3/FO37 | Expected — only FO2B or Coastal Water sample shows that block |
| Port 80/443 conflict | Another site or Nginx may own the port — disable conflicting vhosts / stop Nginx if unused |
| `Unit apache2.service not found` | On Ubuntu: `sudo apt install -y apache2` then `sudo systemctl enable --now apache2`. On RHEL-family the unit is `httpd`, not `apache2`. |
