# Deployment — NPPC Lab LMS

Target: Ubuntu 22.04/24.04 LTS, Nginx, PHP-FPM 8.3+, MySQL 8, Redis, Node 22.

Controlled-form PDFs live in `storage/app/private`. Back that directory up with the database.

## First install

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

Database notifications need a running worker. Customer `ResultsReadyMail` is sent synchronously when a job becomes ready for pickup. Broadcast channels (`database` + `broadcast`) also need the worker so Reverb delivers bell updates.

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

## Reverb (real-time notifications)

Run Reverb beside the queue worker so the header bell updates without a page reload.

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
