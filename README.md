# RCD Road & Asset Monitoring System

Road and asset monitoring for a Road Construction Department. GPS-located damage reports are routed automatically to the responsible contractor and JE/AE/EE, then tracked through repair, field inspection and approval, with photo/video evidence, SLAs, escalation and a full audit trail.

The full specification is in [requirement.md](requirement.md); the design and decisions are in [docs/00-architecture-plan.md](docs/00-architecture-plan.md).

## Status

| Phase | Scope | State |
|---|---|---|
| 1 | Database, models, seed data | ✅ |
| 2 | Authentication, manual RBAC, user/role admin | ✅ |
| 3 | Master data: divisions, roads & sections, assets, contractors, contracts, JE/AE/EE mapping | ✅ |
| 4 | GIS: geometry editor, chainage, GPS → road lookup, map | ✅ |
| 5 | Field reporting with GPS-stamped, watermarked evidence | ✅ |
| 6 | Repair workflow: contractor → JE → AE → EE, rejections, attempts | ✅ |
| 7 | Leave, delegation, reassignment | ✅ |
| 8 | SLA, escalation, notifications | ✅ |
| 9 | Role dashboards, system settings, search | ✅ |
| 10 | Contractor performance, road history, reports & exports | ✅ |
| 11 | Mobile app (Flutter) + API | on hold |
| 12 | Hardening, performance, documentation | next |

Each phase has a write-up in [docs/](docs/).

## Repository layout

```text
backend/   Laravel 12 web application (Blade + Bootstrap 5, MySQL 8)
docs/      Architecture, decisions and per-phase documentation
```

## Requirements

- PHP 8.3+ (with gd/FreeType, exif, intl, pdo_mysql, zip)
- MySQL 8+ (spatial indexes, SRID 4326)
- Composer 2, Node.js 20+
- Optional: ffmpeg/ffprobe (video thumbnails, duration checks, watermarks)

## Local setup

```bash
cd backend
cp .env.example .env            # set DB_* credentials
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate:fresh --seed   # reference data + demo data (non-production)
php artisan serve
```

Open http://localhost:8000. Demo accounts (all marked TEST) use the password from `SEED_USER_PASSWORD` (default `Rcd@Demo2026`):

| Login | Role |
|---|---|
| `admin@rcd.test` / `superadmin@rcd.test` | Admin / Super Admin |
| `ee.dn@rcd.test` | Executive Engineer |
| `ae.sdn1@rcd.test` | Assistant Engineer |
| `je001@rcd.test` | Junior Engineer |
| `contractor1@rcd.test` | Contractor |
| mobile `9500000001` | Citizen |

**Background processes in production:**
- the scheduler (cron): `* * * * * php artisan schedule:run`;
- a queue worker: `php artisan queue:work`.

## Tests

```bash
cd backend
php artisan test        # uses the rcd_test MySQL database (see phpunit.xml)
```
