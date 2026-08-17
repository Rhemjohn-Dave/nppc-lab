# NPPC Laboratory Management System

Greenfield LMS for **NPPC Analytical & Diagnostic Laboratory, Inc.**

## Stack

- Laravel 13 + Fortify + Sanctum
- Inertia.js 3 + React 19 + TypeScript
- shadcn/ui + Tailwind CSS 4
- Spatie Laravel Permission
- DomPDF, Laravel Excel, Recharts
- MySQL 8 (production) / SQLite (local default)
- Redis for cache/queues in production

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
# Ensure database/database.sqlite exists, or configure MySQL in .env
php artisan migrate --seed
npm install
npm run build
composer run dev
```

Open:

- Intake kiosk: `/intake`
- Staff login: `/login`

### Seed accounts

Password for all: `password`

| Role | Email |
|------|-------|
| Admin | `admin@nppc.local` |
| Receiving | `receiving@nppc.local` |
| Analyst | `analyst@nppc.local` |
| Head Analysis | `head@nppc.local` |

## Workflow

1. Customer submits at `/intake`
2. Receiving prices + prints Request for Analysis, then marks received (auto-assigns analysts)
3. Analyst enters required results and completes lines
4. When all analyses are complete, results release automatically (customer emailed) and Head signs finished files end of day (batch or one-by-one); Head can still return lines for correction
5. Admin manages users, prices, and analyst↔procedure assignments

## Ops

See [docs/DEPLOY.md](docs/DEPLOY.md) for Ubuntu + Nginx + PHP-FPM + Redis + queue/scheduler notes.
