# Deployment — NPPC Lab LMS

Target: **Ubuntu 24.04 LTS** (recommended) or **22.04 LTS**, Nginx, PHP-FPM 8.3+, MySQL 8, Redis, Node 22.

Controlled-form PDFs live in `storage/app/private`. Back that directory up with the database.

Known go-live gaps (blockers, bugs, missing functions): [DEPLOYMENT_GAPS.md](DEPLOYMENT_GAPS.md).

## Compatible Ubuntu versions

| Ubuntu | Status | Notes |
|--------|--------|--------|
| **24.04 LTS** | **Recommended** | Best match for PHP 8.3 packages; long support window |
| **22.04 LTS** | Supported | May need Ondřej Surý PHP PPA and NodeSource for PHP 8.3 + Node 22 |
| 20.04 | Not recommended | End of standard support; harder PHP 8.3 story |
| 18.04 | Do not use | Unsupported for this stack |

### Runtime stack (required)

- **PHP** `^8.3` (CLI + FPM) with extensions: `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`, `mysql`, `redis`
- **MySQL 8.0+** (prefer MySQL 8 over MariaDB)
- **Nginx**
- **Redis** (queue, cache, and session in production)
- **Node.js 22** (asset build only; not needed at runtime after `npm run build`)
- **Composer 2**
- **Supervisor** (queue worker + Reverb)

## Ubuntu 24.04 LTS + MySQL — first-time server setup

Copy-paste path for a fresh VPS. Replace `lab.example.com`, the DB password, and the git remote with your values.

### 1. Packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server redis-server git unzip curl supervisor \
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

Enable and start services:

```bash
sudo systemctl enable --now nginx mysql redis-server php8.3-fpm
```

### 2. MySQL database and user

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

### 3. Application code

```bash
sudo mkdir -p /var/www/nppc-lab
sudo chown -R "$USER":www-data /var/www/nppc-lab
cd /var/www/nppc-lab
git clone <YOUR_REPO_URL> .
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### 4. Production `.env` (minimum)

Edit `/var/www/nppc-lab/.env` — at least:

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
```

Then set Reverb and mail (see [`.env` production values](#env-production-values) below). Generate Reverb credentials with `php artisan reverb:install` (or set `REVERB_*` manually). Rebuild front-end assets after `VITE_REVERB_*` are set so they bake into the JS bundle.

### 5. Migrate, seed once, build

```bash
cd /var/www/nppc-lab
php artisan migrate --force --seed
php artisan nppc:production-check
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

Seed accounts (`admin@nppc.local`, `receiving@nppc.local`, `analyst@nppc.local`, `head@nppc.local`) all start with password `password`. **Change every one of those passwords before staff log in.**

`--seed` is **first install only**. Do not seed on routine deploys.

### 6. Nginx site + TLS

Create `/etc/nginx/sites-available/nppc-lab` using the [Nginx](#nginx) block below (set `server_name` and SSL paths). Enable and reload:

```bash
sudo ln -sf /etc/nginx/sites-available/nppc-lab /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d lab.example.com
```

Raise PHP upload limits for PDF overlays (`/etc/php/8.3/fpm/php.ini`):

```ini
upload_max_filesize = 16M
post_max_size = 16M
memory_limit = 256M
```

Then: `sudo systemctl reload php8.3-fpm`.

### 7. Supervisor (queue + Reverb) and cron

Add the Supervisor programs from [Queue worker](#queue-worker-supervisor) and [Reverb](#reverb-real-time-notifications-and-queues) below, then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Scheduler crontab (as root or the deploy user):

```cron
* * * * * cd /var/www/nppc-lab && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Smoke checks

- `curl -fsS https://lab.example.com/up` returns OK
- Log in as admin; open Controlled Forms; upload/preview a PDF
- Confirm Redis is up (`redis-cli ping`) and Supervisor workers are `RUNNING`
- Confirm `storage/app/private` is writable by `www-data`

## First install

If the OS packages, MySQL user, and Nginx are already in place, the application steps are:

```bash
cd /var/www/nppc-lab
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Set production values in `.env` (see below), then:

```bash
php artisan migrate --force --seed
php artisan nppc:production-check
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Seed accounts (`admin@nppc.local`, `receiving@nppc.local`, `analyst@nppc.local`, `head@nppc.local`) all start with password `password`. Change every one of those passwords before staff log in.

`--seed` is first install only. Re-running the seeder no longer resets passwords, prices, or analyst assignments, but still do not seed on routine deploys.

### Controlled form deploy defaults

`DatabaseSeeder` runs `ControlledFormDefaultsSeeder`, which for each plotted default form (Job Orders + FO2/FO3/FO37/FO4/FO5/F016-PROX/F016-MILK/F016-WA/F016-CAP/F016-NO2/FO26/FO27):

- keeps **only revision `1`** (deletes other revisions)
- attaches the official PDF from `resources/forms/official/` (**except** reference-only forms — see below)
- imports Form Designer fields from `config/*_form_fields.php`
- activates revision `1`

**Milk (`LSP-7.8-F016-MILK`):** the DOC/PDF under `resources/forms/official/` is **reference only**. Once a canonical PDF exists (e.g. uploaded in Form Designer), seed/heal **must not** replace it. Fresh installs may still bootstrap from the official PDF when no file is attached yet.

After you change plots in Form Designer locally, refresh committed defaults before deploy:

```bash
php artisan controlled-forms:export-blueprints --pdfs
```

Then commit the updated `config/*_form_fields.php` files and any new official PDFs. For Milk, prefer exporting fields only (`--form=LSP-7.8-F016-MILK` without forcing a PDF replace of a Designer-calibrated upload).

## Subsequent deploys

```bash
cd /var/www/nppc-lab
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Do not pass `--seed`. Do not run `migrate:fresh` or `db:wipe` on production.

## `.env` production values

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
DB_PASSWORD=

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

Use Microsoft 365 / Google Workspace SMTP for customer “results ready” mail. After HTTPS is live, run:

```bash
php artisan nppc:production-check
```

The app forces HTTPS and trusts `X-Forwarded-*` headers from Nginx. Health check: `GET /up`. Generate Reverb credentials with `php artisan reverb:install` (or set `REVERB_*` manually) and rebuild front-end assets so `VITE_REVERB_*` are baked in.

## Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name lab.example.com;
    root /var/www/nppc-lab/public;

    ssl_certificate     /etc/letsencrypt/live/lab.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/lab.example.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    client_max_body_size 16M;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /app {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:8080;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

server {
    listen 80;
    server_name lab.example.com;
    return 301 https://$host$request_uri;
}
```

PHP-FPM (`/etc/php/8.3/fpm/php.ini`) should allow PDF uploads and overlay generation:

```ini
upload_max_filesize = 16M
post_max_size = 16M
memory_limit = 256M
```

## Queue worker (Supervisor)

In-app LMS notifications write to the database and broadcast immediately (`BroadcastMessage` on the `sync` connection) so Reverb can push to the header bell without a queue worker. Customer `ResultsReadyMail` is also sent synchronously when a job becomes ready for pickup. Queue workers remain useful for other jobs.

Save as `/etc/supervisor/conf.d/nppc-lab-worker.conf`:

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

## Reverb (real-time notifications and queues)

Run Reverb beside the queue worker so the header bell and Receiving / Analyst / Head queue boards update without a page reload or timed poll. Workflow changes in `JobOrderService` broadcast `LabQueueUpdated` on private `lab.queue.*` channels; those pages do a one-shot Inertia partial reload.

Save as `/etc/supervisor/conf.d/nppc-lab-reverb.conf`:

```ini
[program:nppc-lab-reverb]
command=php /var/www/nppc-lab/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/nppc-lab/storage/logs/reverb.log
```

Locally, `composer dev` starts Reverb with the app server, queue listener, and Vite.

## Scheduler

```cron
* * * * * cd /var/www/nppc-lab && php artisan schedule:run >> /dev/null 2>&1
```

`routes/console.php` includes a daily LIMS reminder stub for future calibration/maintenance modules.

## Controlled forms / PDFs

- Canonical PDFs are stored on the `local` disk (`storage/app/private`). Include that path in backups.
- Optional LibreOffice Headless enables DOCX uploads. Without it, admins should upload PDF. Set `LIBREOFFICE_PATH` if `soffice` is not on `PATH`.
- Job-order PDFs: `/receiving/{id}/pdf`, `/head/{id}/pdf`. Combined result overlays use the active controlled-form revision.

## Backups

Daily:

- MySQL dump of the application database
- `storage/app/private` (controlled-form originals and canonical PDFs)
- `.env` (store separately from the code backup)

## CI

GitHub Actions workflow `.github/workflows/tests.yml` runs `composer setup` then `composer ci:check` (lint + types + PHPUnit).
