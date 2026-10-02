# Phase 1 — Architecture & Database (completed 02-Oct-2026)

## Verified

| Check | Result |
|---|---|
| `migrate:fresh --seed` on MySQL 8.4 | clean |
| `migrate:rollback` (all migrations) | clean |
| Automated tests (`php artisan test`) | **28 passed, 209 assertions** |
| Code style (`pint`) | clean |

Not yet built: HTTP/UI/API (Phase 2+), so authorisation is verified only at the model/permission level.

## Running locally

PHP 8.4 is used for this project only. The global `php` (8.2) is below the minimum and fails with a Composer platform error. Composer is pinned to `platform.php = 8.3.0`, so dependencies install on any PHP 8.3+ server:

```bash
cd backend
alias php84=/opt/homebrew/opt/php/bin/php
php84 artisan migrate:fresh --seed     # schema + reference data + demo data (non-production only)
php84 artisan test                     # uses database rcd_test
```

Databases: `rcd` (dev) and `rcd_test` (tests), owned by MySQL user `rcd`. The credentials are in `backend/.env` only.

## Schema (as built)

Changes from the plan's ERD are marked ★.

| Area | Tables |
|---|---|
| RBAC | `roles`, `permissions` (module.action), `role_permissions`, `user_roles` |
| Organisation | `districts` ★, `divisions`, `sub_divisions` |
| Roads & GIS | `road_categories` ★, `roads`, `road_geometries` (versioned, immutable), `road_chainage_markers` ★ (km-stone calibration), `road_sections`, `gis_features` ★ |
| Assets | `asset_types`, `issue_categories` (tree per asset type), `severities`, `assets` |
| Contracts | `contractors`, `contracts`, `contract_documents` ★, `contract_road_sections` |
| Responsibility | `responsibility_assignments` (section or asset scope, dated, never overwritten) |
| Identity | `devices` ★, `otp_verifications` ★, `personal_access_tokens` (Sanctum) |
| Reports | `reports`, `report_responsibilities` ★ (routing history per report), `report_links` (duplicates) |
| Workflow | `workflow_definitions`, `workflow_steps`, `workflow_transitions` ★, `workflow_instances` (with `version` for optimistic locking), `workflow_assignments`, `workflow_actions` (= history) |
| Repairs | `repair_attempts`, `inspections`, `evidences` (polymorphic: report / repair attempt / inspection) |
| Delegation | `delegations` |
| SLA | `sla_rules`, `sla_instances`, `escalation_rules` ★, `escalations` |
| Notifications | `notifications` (Laravel), `notification_deliveries` ★ (per channel) |
| Platform | `audit_logs`, `system_settings`, `sync_records` |

Key decisions:

- **`gis_features`**: business tables store GeoJSON. The GIS engine keeps a spatially indexed SRID-4326 copy here. MySQL spatial indexes need NOT NULL columns, and this keeps the PostGIS swap confined to `App\Domain\Gis\MysqlGisEngine`.
- **`report_responsibilities`**: when routing changes (e.g. maintenance expired → department flow, D4), a new row is added and the old one is kept.
- **`workflow_history` merged into `workflow_actions`** (append-only).
- **Chainage** is stored as integer metres.

## Enforced in code

| Rule | Where |
|---|---|
| Test data hidden unless included. Production default is exclude; official exports force exclude. | `HasTestFlag` + `ExcludeTestDataScope` + `TestDataMode` |
| Master-data changes audited with old/new values. Passwords and hidden fields never logged. | `Auditable` trait → `AuditLogger` |
| History can't be edited or deleted: audit logs, workflow actions, geometry versions, repair attempts (except decision fields), inspections, evidence (except processing fields), assignments (except closing) | `AppendOnly` trait, `RoadGeometry` guards |
| Maintenance responsibility from the maintenance dates only. Draft/terminated contracts never confer responsibility. | `Contract::isMaintenanceActiveOn()` |
| Contract and JE/AE/EE resolution by date (history-aware) | `ContractRoadSection::covering()`, `ResponsibilityAssignment::effectiveOn()` |
| No magic constants | `system_settings` (47 keys) via `App\Support\Settings` |
| Stable polymorphic names (`road`, `report`, …) | morph map in `AppServiceProvider` |

## Workflow definitions seeded (v1)

- `CONTRACTOR_MAINTENANCE`: `PENDING_VALIDATION → ASSIGNED → IN_PROGRESS → JE_REVIEW → AE_REVIEW → EE_APPROVAL → CLOSED`. Any review rejection goes to `REOPENED`, then back through repair and review. Also `INVALID_CLOSED` and `MERGED_DUPLICATE`.
- `DEPARTMENT` (placeholder, D4): `PENDING_VALIDATION → DEPARTMENT_PENDING → CLOSED`.

Transitions declare `requires_reason / requires_evidence / requires_location`, plus guard and effect keys that the Phase 6 engine will implement.

## Demo data (all `is_test = 1`, non-production only)

Synthetic road alignments (not surveyed). Default password: `Rcd@Demo2026` (override with `SEED_USER_PASSWORD`).

| Login | Role |
|---|---|
| superadmin@rcd.test / admin@rcd.test | Super Admin / Admin |
| ee.dn@rcd.test, ee.ds@rcd.test | EE (North / South division) |
| ae.sdn1@ … ae.sds2@rcd.test | AE (one per sub-division) |
| je001@ … je008@rcd.test | JE (two per sub-division); je009, je010 are reserves for delegation |
| contractor1@ … contractor6@rcd.test | Contractor users (one per company) |
| staff1@rcd.test | RCD staff |
| mobiles 9500000001–3 | Citizens (no email) |

Scenarios built in:

| Roads | Scenario |
|---|---|
| RCD-001…014 | Active maintenance → contractor workflow |
| RCD-015…017 | Maintenance expired 31-Mar-2026 → department flow |
| RCD-018…020 | No contract |
| RCD-001 | Earlier contract (2021–24) with another contractor |
| RCD-001 / S01 | JE changed 01-Jul-2026 |
| RCD-002 | Last section maintained by a different contractor |

## Pulled forward from Phase 4

`App\Domain\Gis`: `GeoMath`, `LineString` (length, locate, slice, simplify), `GisEngine` + `MysqlGisEngine`, `RoadGeometryService`. Seeding real geometry needed them. They are covered by unit tests and a spatial-index feature test.

## Environment notes

- `ffmpeg` (Homebrew) is broken: missing `libx265`. Run `brew reinstall ffmpeg` before Phase 5.
- Flutter SDK + Android SDK are needed before Phase 11.
