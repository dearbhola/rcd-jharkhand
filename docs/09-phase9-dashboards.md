# Phase 9 — Dashboards, Settings & Search (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **181 passed, 1,211 assertions** (Phases 1–9) |
| Visual check (Chrome, 1366 px) | EE, JE, contractor, citizen and admin dashboards with demo data; no JS errors |
| Problems found visually and fixed | EE contractor table squeezed (columns cut off) → full-width row. JE tiles and stage labels wrapping or truncated → responsive grid. Chart bars collapsing when the page resized mid-animation → animation off. Contractor's 7th tile stretched → fixed grid. |
| Chart palette | Validated with the data-viz validator (blue/orange; all checks pass: CVD ΔE 24.7, contrast ≥ 3:1) |

## Dashboards (§35)

Each user lands on the dashboard for their highest role; multi-role users get a switcher. Every number comes from workflow and report records (nothing is typed in). Aggregates are cached for 60 s per user, role and test-data mode; task lists are always live. Most tiles link to the matching filtered list.

| Role | Shows |
|---|---|
| **Citizen / RCD staff** | Submitted, in progress, repaired & closed, not accepted; my reports with status |
| **Contractor** | Firm and contracts in maintenance. Tiles: New, In progress, Awaiting inspection, Reopened, Rejected (30 d), Overdue, Completed. Assigned repairs with due/overdue; recent rejections with reasons. |
| **JE** | New in jurisdiction (7 d), citizen reports to validate, repairs to inspect, rejected by me, overdue in jurisdiction, delegated to me; my tasks (incl. "for <officer>" when standing in); my roads (sections, km); open reports by stage; map link |
| **AE** | Pending AE reviews, forwarded to EE, rejected by me, overdue, open in jurisdiction; tasks; roads; stages |
| **EE** | Pending final approvals, approved (30 d), rejected by EE (30 d), overdue, open in division; **reported vs closed (6 months) chart** with table view; open by stage; open by severity; **contractors in division** (open / overdue / rejected / closed); road-condition map link |
| **Admin** | Master-data counts; open, overdue, **reports blocked by missing JE/AE/EE mapping**, **sections with incomplete mapping**; open by stage; data & system health (roads without geometry, failed external notifications); recent audit activity |

Jurisdiction = reports where the officer is the current JE/AE/EE.

## System settings (`/admin/settings`, `settings.manage`)

- All business tunables in one place, grouped: location & GPS, evidence, duplicates, workflow, delegation, security & sign-in, notifications, mobile sync, map.
- Typed inputs: yes/no, numbers (no negatives), text, JSON lists (validated).
- Only changed values are saved. Each change is audited with old and new values, and the page shows who changed it and when.
- Takes effect immediately (cache flushed); tested by changing the reporting radius.

## Search (§53)

- **Header search box** on every page, plus `/search`.
- Result groups, each shown only with the matching permission:
  - report numbers (respecting report visibility);
  - roads (code, name, number);
  - assets;
  - contractors (name, code, registration);
  - contracts (contract or agreement no.);
  - users (name, email, mobile, employee code).
- **"RCD-005 14.2"** jumps to reports on that road within ±0.5 km.
- **Report list filters added:** road, km range, contractor, contract no., reporter (name or mobile), category. Together with status, severity, asset type, division, flags and date range, that covers every criterion in §53.

## Demo activity data

`DemoActivitySeeder` (runs after the demo masters; never in production or automated tests; disable with `SEED_DEMO_ACTIVITY=false`):
- Drives **36 reports** over ~2½ months through the **real** `ReportService` and `WorkflowEngine` with simulated time.
- Every step is taken by the actual task holder.
- Produces closed, rejected-then-closed, AE- and EE-rejected, invalid, department-route, overdue and in-progress cases, with real evidence, SLA timers (≈⅓ breached), escalations, notifications and audit entries.
- `php artisan migrate:fresh --seed` takes about 15 s.
