# Phase 6 — Repair Workflow (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **146 passed, 932 assertions** (Phases 1–6), including the full **§61 demo scenario** as one test |
| Multi-user browser run (Chrome) | Citizen reports → JE sees it in My Tasks → validates → a **stale second JE tab is refused** ("already processed by someone else. Reload") → contractor starts repair → submitting without photos is refused ("At least 2 photo(s) required") → submits with photos at the site → JE rejects without reason (refused) → rejects with reason and inspection photo → the report shows *Reopened*, *Attempt 1 · Rejected (JE)*, the reason, evidence and the full timeline. No application JS errors. |
| Code style | clean |

## The flow (decisions D2–D4)

```text
Report ─► PENDING_VALIDATION (JE)
            ├─ invalidate (reason) ─► INVALID_CLOSED
            ├─ merge duplicate (reason, original) ─► MERGED_DUPLICATE
            └─ validate ─► maintenance active?  ── no ──► DEPARTMENT_PENDING (JE) ─ close (reason) ─► CLOSED
                                │ yes
                                ▼
          ASSIGNED (contractor) ─ start ─► IN_PROGRESS ─ submit repair* ─► JE_REVIEW*
          REOPENED (contractor) ◄──────────── reject (reason*) ─────────────┤
                                                            accept* ─► AE_REVIEW* ─ reject (reason*) ─► REOPENED
                                                                          accept* ─► EE_APPROVAL ─ reject (reason) ─► REOPENED
                                                                                        approve ─► CLOSED
   * = GPS within the configured radius of the report site + photo evidence required
```

- **Officer reports skip validation.** Reports filed by a JE, AE or EE are auto-validated by the system (setting `workflow.auto_validate_roles`). They go straight to the contractor, or to the department; the mapped JE does the JE review later. *This is my reading of D2 ("if AE reports damage automatically JE will be assigned from the mapping"); tell me if you meant the AE's report should go to the JE for validation instead. It's a setting change.*
- **Maintenance expiry (D4).** Each time work would go to the contractor (validation, any rejection), the maintenance period is re-checked. If it has expired, the report moves to the **department flow**: a new responsibility row (`maintenance_expired`), the old workflow instance `superseded`, a new one at `DEPARTMENT_PENDING`. Nothing is overwritten (tested).
- **Department flow** is still the agreed placeholder: the JE validates, then any mapped JE/AE/EE closes it with a reason.
- **EE doesn't replace JE/AE inspection.** After an EE rejection, the next repair goes back through JE → AE → EE (tested).

## Engine (`App\Domain\Workflow`)

| Piece | What it does |
|---|---|
| `WorkflowEngine::transition()` | One DB transaction. Locks the instance row and compares its `version` with the version the user's page was showing (mismatch → **409 WorkflowConflict**). Checks the transition exists at the current step, then the role allowed by the transition **and** the permission (`report.validate`, `repair.submit`, `report.review`, `report.reject`, `approval.approve`, `approval.reject`, …). Enforces the mandatory reason, runs the guards, writes an **append-only `workflow_actions`** row, runs the effects in order, attaches evidence, advances the instance and report status, and audits `workflow.<action>`. |
| Guards | `assignee.is_actor` (only the current task holder), `location.within_review_radius` / `within_repair_radius` (GPS accuracy + distance to the report site; test-mode override allowed for authorised users and flagged), `evidence.*_requirements` (settings-driven), `duplicate.target_present`, `actor.mapped_engineer`. |
| Effects | Record inspection (decision, GPS, distance, evidence); create / accept / reject / approve repair attempt; re-check maintenance and re-route; hand the task to the step role, the contractor, the same people, or nobody; start/stop SLA timers; close; link duplicate; notify assignees / reporter / contractor (after commit). |
| `WorkflowStarter` | Starts a workflow **inside the report-submission transaction** (a report can never exist without one), picks the definition from the resolved route, and assigns the initial holder. |
| `AssigneeResolver` | Step role → the officer **currently** mapped to the section or asset (so a JE transferred mid-task hands over automatically; tested). The report's original snapshot is kept for history. Contractor steps → the contractor's active users. Phase 7 delegation plugs in here. |
| `Registry` | Maps guard/effect keys stored in the database to classes. Admins can re-wire steps and transitions in data. |

**Records kept, never overwritten:**
- every transition (`workflow_actions`);
- every repair attempt (number, contractor, submitter, description, GPS, evidence, outcome, rejecting stage, reason, reviewer);
- every inspection (stage, inspector, decision, comment, GPS, distance from site, verified or overridden, evidence);
- every assignment (who, from when to when, why it ended);
- responsibility re-routes;
- SLA timers (hours copied from the rule at start).

## Screens

- **My Tasks** (`/tasks`): the user's open tasks grouped by step, oldest due first, with "Due in…" / "Overdue by…" (SLA).
- **Report page:**
  - **Your action** panel with only the actions this user can take now. GPS acquisition shows live distance to the site where presence is required; camera capture where evidence is required; "Reason (mandatory)" where a reason is required.
  - **Repair attempts**, each with its evidence and the JE/AE/EE review decisions.
  - **Validation** record, the **workflow history** timeline, and who holds the task now.
- **Notifications:** stored in-app for the next assignees, the reporter (every status change, with the reason) and the contractor on approval. The notification centre and other channels are Phase 8.

## Phase-8 items already in place

SLA timers start and stop per stage, using the most specific rule (category > asset type > severity), and breaches are recorded when a stage completes late. **Escalation scanning, reminders and the notification centre come in Phase 8.**
