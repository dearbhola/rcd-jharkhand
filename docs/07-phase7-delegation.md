# Phase 7 — Leave, Delegation & Reassignment (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **160 passed, 1,047 assertions** (Phases 1–7) |
| Browser run (Chrome) | An AE creates a delegation: the duty selector filters both officer lists (10 of 16 engineer options shown for JE). Saving lands on the delegation page with history. |
| Bug found in the browser run and fixed | A delegation starting "now" was activated correctly, but the confirmation said "scheduled", because the service returned the pre-activation object. Fixed, and a regression assertion was added. |
| Scheduler | `rcd:delegations` runs every minute (`php artisan schedule:list`) |

## What it does (§27–28)

**Road responsibility and task responsibility are separate.** A delegation never changes the permanent JE/AE/EE mapping (tested). It only changes who receives tasks.

| Situation | Behaviour |
|---|---|
| Delegation in force (between start and end) | **New** tasks for the officer's duty go to the stand-in automatically (`DelegationResolver` inside `AssigneeResolver`). Chains are followed: A away → B, B away → C (max 3 hops). |
| Existing tasks: **all pending** | All open tasks move to the stand-in when the delegation starts. |
| Existing tasks: **new only** | Open tasks stay with the officer; only new ones go to the stand-in. |
| Existing tasks: **selective** | The delegation page lists the officer's open tasks with checkboxes; the supervisor transfers the chosen ones. |
| Delegation ends (automatically at its end time, or "End now") | With "return on end" (default), still-open delegated tasks go back to the officer; otherwise they stay with the stand-in. |
| Scheduled for later | Starts automatically (scheduler). If the scheduler is delayed, routing still honours the time window. Can be cancelled before it starts. |
| **Manual reassignment** (report page, `workflow.reassign`: AE, EE, Admin) | Move a task to another user who can do that step: same engineer role, or another user of the same contractor firm. Reason mandatory. |

**Every move:**
- ends the old assignment row with a reason (`delegated` / `reassigned` / `returned`) and never deletes it;
- creates a new row linked to the previous one, to the original holder, and to the delegation;
- is audited (`workflow.reassigned`) and notifies the new holder;
- **bumps the workflow version**, so a page the previous holder still has open can no longer act (409, tested).

**Checks when creating a delegation:**
- both people hold the duty and the stand-in is active;
- the stand-in is not the officer themselves;
- the end is after the start;
- no overlapping delegation for the officer;
- the stand-in is not away at the same time.

## Screens

- **Delegations** (`/delegations`):
  - managers see all; officers see the ones that involve them;
  - list with status, period, transfer mode and number of tasks moved;
  - **New delegation** form, filtered by duty, with a plain-language choice of what happens to existing tasks.
- **Delegation page:** details, end/cancel (reason required), selective transfer checklist, tasks handled through the delegation (holder, from, to, how it ended), and history.
- **Report page:**
  - "With: JE009 (for JE001)" shows when someone holds a task on another officer's behalf;
  - the **Reassign task** card appears for supervisors.

## Permissions (defaults, editable under Roles & Permissions)

| Role | Delegation |
|---|---|
| EE, AE, Admin | create/end delegations (`delegation.manage`), reassign tasks (`workflow.reassign`) |
| JE | view their own delegations |

Not yet restricted by jurisdiction: any AE can currently delegate any JE. If RCD wants AEs limited to their own sub-division's staff, that is a small rule to add. Tell me.
