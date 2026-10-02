# Phase 8 — SLA, Escalation & Notifications (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **171 passed, 1,135 assertions** (Phases 1–8) |
| Browser check (Chrome) | Notification bell shows the unread count; the notification centre renders Unread/Read/All with type icons. The SLA admin page renders without JS errors. |
| Bug found visually and fixed | The escalation-rule editor stacked every field vertically and clipped its help text: Bootstrap's `list-group-item` overrode the grid. Re-laid out full width. |
| Scheduler | `rcd:delegations` every minute, `rcd:sla-scan` every 5 minutes |

## SLA timers (started in Phase 6, completed here)

- A timer runs for each stage with an SLA:
  - JE validation;
  - contractor response (start repair);
  - repair;
  - JE review, AE review, EE approval.
- Hours come from the **most specific active rule**: category > asset type > severity > stage default. They're copied onto the timer, so later rule edits never rewrite history.
- The report page has an **SLA panel**: each stage's due time, "Running · due in…", "Overdue by…", or the result "On time / Late" with duration, plus any reminders or escalations it triggered.
- **My Tasks** sorts by due time and shows "Due in…" / "Overdue by…".

## Escalation (`rcd:sla-scan`, `EscalationService`)

Default rules (all editable):

| When | Action | Who |
|---|---|---|
| 4 h before due | Reminder | task holder |
| At due time | Breach recorded + escalation level 1 | task holder + **AE** |
| 24 h after due | Escalation level 2 | task holder + **EE** |

- **Each rule fires at most once per timer**, enforced by a unique database key, so overlapping or repeated scans can't double-notify (tested).
- **No reminder at the start of a timer:** reminders are skipped when the SLA is shorter than the reminder lead time, so they never fire as soon as the timer starts.
- **Escalation targets are delegation-aware:** if the AE is on leave, the stand-in AE is notified (tested).
- **Breaches** are recorded on the timer (`breached_at`) whether or not a rule exists, and when a stage completes late.
- **Every reminder or escalation is audited** (`sla.remind`, `sla.escalate`) with recipients.
- The scan only touches open timers due within the reminder horizon, in batches of 200, using the `(completed_at, due_at)` index.

## Notifications

**Events covered:**
- new task;
- reassignment;
- contractor assignment;
- repair submitted / review required;
- repair rejected (to the contractor, with reason);
- EE approval required;
- EE rejection;
- SLA approaching / breached / escalated;
- delegation started / ended (to both people);
- every report status change (to the reporter);
- repair approved (to the contractor).

**Notification centre** (`/notifications`, bell in the header):
- Unread / Read / All;
- clicking a notification marks it read and opens the related report or task;
- "Mark all as read";
- only same-site links are followed (an external or `//` link is refused, tested).

**Channels:**
- **In-app is always on.**
- External channels are separate drivers, each switched on in **System Settings** and used only for the events listed in `notifications.external_events` (default: new or reassigned tasks, SLA breach/escalation, delegation start):

| Channel | Destination | Status |
|---|---|---|
| Email | user's email | Ready. Uses Laravel mail; configure SMTP in `.env` (`MAIL_*`). |
| SMS | user's mobile | Gateway interface + log driver. Plug in the provider when chosen (same pending decision as OTP, D5/D8). |
| WhatsApp | user's mobile | Gateway interface + log driver; provider TBD. |
| Push | device token | Gateway interface + log driver; needs the mobile app (on hold, D7). |

- **Every external send is recorded** in `notification_deliveries` as sent or failed, with the error. A failing provider never blocks the action or the in-app notification (tested).
- Notifications are **queued and dispatched only after the database transaction commits**, so a rolled-back action never notifies anyone. They carry plain values (report id, number, status, link) rather than the model, so queue workers don't depend on re-loading data.

## SLA & escalation admin (`/admin/sla`, permission `sla.manage`)

- **Escalation rules** (shown at the top): when (before/after due, hours), remind or escalate, to which officer (JE/AE/EE), the stage (or all), whether the task holder is also notified, level, active.
- **SLA rules by stage:** edit hours and status inline, and add specific rules by asset type / category / severity. Duplicate active rules are refused, a category must belong to its asset type, and an escalation must notify someone.
- All edits are audited.

## Production notes

- Run the scheduler: `* * * * * php artisan schedule:run` (cron).
- Use a real queue (`QUEUE_CONNECTION=database` or `redis`) with `php artisan queue:work`. Local `.env` uses `sync`.
