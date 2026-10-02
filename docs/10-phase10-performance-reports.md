# Phase 10 — Contractor Performance, Road History, Reports & Exports (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **192 passed, 1,424 assertions** (Phases 1–10) |
| Traceability test | For **every** count metric, value = number of records in its drill-down. Averages = mean of the listed per-report hours. Compliance % = on-time timers ÷ listed timers. |
| Visual check (Chrome + generated PDF) | Performance table, contractor detail, drill-down, completion report, road history, report pages. Completion-report PDF inspected. |
| Bugs found and fixed | 1. **Route binding ran before the test-data choice**: in production, an admin who switched on test data could not open a test record by URL (404). The middleware now runs before route binding (regression test added). 2. Test-data mode is reset at the start of every request, so long-running workers never inherit it. 3. "SLA breaches" now includes tasks that are overdue right now, not only breaches already recorded. 4. On-screen tables were too wide; they now show headline columns with short headings, while exports keep every column. |

## Contractor performance (§37–38) — `/performance`

- **Comparison table:** every contractor with tasks, approved, open, overdue, SLA %, avg. repair time, reopened, rejections (JE · AE · EE), repeat defects.
  - **Every number is a link** to the records behind it (§38).
  - Filters: contractor, contract, division, road (and section), **financial year**, **contract's maintenance period**, date range.
- **Contractor page:** all 18 metrics in four groups (tasks, timeliness, quality, contracts), each clickable, plus a by-contract breakdown with a link to each contract's completion report.
- **Drill-down:** the exact reports, repair attempts, SLA timers, contracts or roads behind a figure, with the figure's definition and value shown.

**Metrics** (all derived; there is no way to edit them):

| Metric | Definition |
|---|---|
| Repair tasks | Reports actually handed to the contractor (contractor workflow assignment + responsibility naming the contractor) |
| Completed / Open / Overdue | Approved & closed / still with the contractor workflow / open with a contractor-stage SLA past due |
| SLA breaches / compliance | Tasks with a breached response or repair SLA (incl. overdue now) / completed contractor-stage timers finished on time |
| Avg. response / repair time | Assignment → repair started / assignment → first repair submitted (per-report hours listed in the drill-down) |
| Repair attempts, JE/AE/EE rejections, reopened | From the preserved repair-attempt records |
| Repeat defects | Same category reported again on the same road within `performance.repeat_chainage_m` (100 m) and `performance.repeat_window_days` (90 d) after an earlier report there was approved |
| Contracts (total / active / completed), roads maintained | From contracts and their coverage |

Every page and export carries the note that this information **does not award, rank or recommend contractors** (§39).

## Contract completion report (§39) — contract page → "Completion report"

- **Contents:**
  - contract details and contractor;
  - roads and sections covered;
  - the maintenance period;
  - all metrics for that period;
  - the evidence summary (photos/videos by stage);
  - inspection history and the list of defects;
  - **final status** ("ended with N open tasks", "all completed", or "in progress, N days left");
  - the procurement disclaimer.
- **Exports:** **PDF** (formatted report), **Excel** (sheets: summary, tasks, inspections) and CSV.
- **Announced automatically** (`rcd:contract-completions`, daily 06:30): when a maintenance period ends, the division's EE(s) and administrators are notified, once (audited).

## Road condition history (§36) — road page → "Condition history"

- Reports per year (reported / closed) and totals.
- **Recurring problem areas:** chainage bands (`performance.hotspot_bin_m`, 500 m) with at least `performance.hotspot_min_reports` (2) reports, with the damage types and a link to those reports.
- Report list, repair history (attempts, outcome, rejecting stage) and inspection history.
- Filters: year, category, severity, contractor, section.

## Reports module (§40) — `/analytics`

- **Period reports:** daily, weekly, monthly, financial-year.
- **Breakdowns:** road-wise, contractor-wise, JE-wise, AE-wise, EE-wise, division-wise, damage-category, severity.
  - Each shows reported / open / closed / invalid / overdue now / repair rejections / avg. hours to close.
- **SLA report:** per stage — timers, completed, on time, breached, compliance, avg. hours.
- **Rejection report:** every rejection with stage, officer, contractor and reason.
- **Reopening report:** reports reopened after rejection, with attempts and rejecting stages.
- **Contractor performance report:** see above.
- **Filters:** date range, division. All are aggregated in SQL.

## Exports & test data (§40, §42)

- Every report and drill-down exports to **CSV** (UTF-8, opens in Excel), **Excel (.xlsx)** and **PDF**. The file contains the same rows as the screen, with the filters and generation time.
- Exporting needs `analytics.export` (EE, Admin).
- **Official exports always exclude test data.** Only a user with `testdata.include` can tick "Include test data"; the file is then marked **"TEST DATA INCLUDED — NOT AN OFFICIAL REPORT"** on every page (tested). Because all demo data is test data, an official export from the demo environment is empty; that's by design.

## New settings (System Settings → Performance & history)

`performance.repeat_window_days` (90), `performance.repeat_chainage_m` (100), `performance.hotspot_bin_m` (500), `performance.hotspot_min_reports` (2).

## Scheduler (production cron runs all)

| Command | When |
|---|---|
| `rcd:delegations` | every minute |
| `rcd:sla-scan` | every 5 minutes |
| `rcd:contract-completions` | daily 06:30 |
