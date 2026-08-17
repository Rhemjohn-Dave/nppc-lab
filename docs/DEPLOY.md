# Deployment — NPPC Lab LMS

## Target environment

- Ubuntu 22.04/24.04 LTS
- Nginx + PHP-FPM 8.3+
- MySQL 8
- Redis (cache + queues)
- Node 22 (build assets on CI or deploy host)
- Supervisor for `queue:work`
- Cron for Laravel scheduler

## Application setup

```bash
cd /var/www/nppc-lab
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# Configure MySQL + Redis + SMTP in .env
php artisan migrate --force --seed
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Recommended `.env` production values

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
MAIL_MAILER=smtp
```

Use Microsoft 365 / Google Workspace SMTP credentials for institutional mail delivery.

## Nginx (sketch)

```nginx
server {
    listen 80;
    server_name lab.example.com;
    root /var/www/nppc-lab/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Queue worker (Supervisor)

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

Database notifications still require a running queue worker. Customer `ResultsReadyMail` is sent synchronously when a job becomes ready for pickup.

## Scheduler

Cron entry:

```cron
* * * * * cd /var/www/nppc-lab && php artisan schedule:run >> /dev/null 2>&1
```

`routes/console.php` includes a daily LIMS reminder stub for future calibration/maintenance modules.

## PDF / Excel

- DomPDF archival downloads: `/receiving/{id}/pdf`, `/head/{id}/pdf`
- Laravel Excel is installed for future catalog/inventory import-export modules

## CI

GitHub Actions workflow `.github/workflows/tests.yml` runs `composer setup` then `composer ci:check` (lint + types + PHPUnit).
