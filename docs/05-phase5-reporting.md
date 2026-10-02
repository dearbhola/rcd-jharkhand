# Phase 5 — Field Reporting & Evidence (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **132 passed, 765 assertions** (Phases 1–5) |
| Real phone-browser run (Chrome, 390×844, simulated GPS, real JPEGs) | A citizen gets a fix → road/section/km detected → picks damage and severity → takes 2 photos → submits → report page with watermarked thumbnails. A second attempt at the same spot shows the duplicate warning. **No JS errors, no failed requests.** |
| Watermark (inspected visually) | Matches §17: RCD ROAD MONITORING [TEST], report no., date and time (IST), lat/long ± accuracy, road, chainage, reporter |
| Code style | clean |

## Reporting a problem (`/reports/create`)

Designed for a phone at the site; also works on desktop.

1. **Location.** The browser's GPS is watched continuously, with an accuracy badge. Once accuracy is within `gps.max_accuracy_m`, the server checks the point and shows **"RCD-005 · Section S01 · km 7.398"**. Away from any road it shows *"You are not currently within the permitted reporting area."* There is no road picker: users cannot choose a road (§13).
2. **What is damaged.** Road, plus the detected nearby asset type (e.g. Bridge) if there is one. Damage categories are large buttons, and the severity buttons are colour-coded.
3. **Photos/video** go straight to the camera (`capture=environment`), with previews and remove buttons. The limits come from settings.
4. **Description** (optional) → **Submit**.

**Duplicate warning:** after a category is chosen, the form warns if similar open reports exist nearby. It never blocks; the reporter can still submit.

**Test-mode override:** users with `report.location_override`, when the `location.test_override_enabled` setting is on, can pick the point on a map. Such reports are **always marked TEST** and flagged `location_override`.

**Idempotent submit:** each form carries a `client_uuid`, kept across retries. Re-sending after a dropped connection returns the same report instead of creating a second one.

## Server rules (`ReportService`)

| Rule | Behaviour |
|---|---|
| Evidence validated **before** anything is written | Real MIME from file bytes (a PDF renamed `.jpg` is refused). Size and count are checked against settings; the same file twice in one submission is refused. |
| GPS fix | Accuracy ≤ `gps.max_accuracy_m` (30 m default). Capture time not in the future (5 min tolerance) and no older than `gps.max_capture_age_hours`. Over 30 min old → flag `delayed_submission`. |
| Location | Road, section, chainage, asset, contractor and JE/AE/EE are **resolved on the server** from the GPS (Phase 4 resolver). Any road or chainage sent by the client is ignored (tested). |
| Asset reports | Only if that asset type is mapped within the radius; the category must belong to the asset type. |
| Responsibility snapshot | A `report_responsibilities` row holds contract, contractor, maintenance status, JE/AE/EE and route (contractor/department) as of submission. Later mapping changes don't rewrite it. |
| Missing JE/AE/EE | The report is accepted and flagged `responsibility_gap`, so admins can fix the mapping. |
| Numbering | `RCD/2026-27/000001`: per financial year (April–March), race-safe row-locked counter. |
| Test data | Reports on test roads, or made with the override, are `is_test`. |
| Duplicates | Same category within `duplicate.radius_m` and `duplicate.window_hours`, not closed → flag `possible_duplicate`. Never auto-merged or deleted; the JE merges in Phase 6. |
| Atomic | Report + responsibility + evidence + audit in one transaction. `ReportSubmitted` is raised after commit, and the Phase 6 workflow starts from it. |

## Evidence (`EvidenceService`, `ProcessEvidence` job)

- **Original stored unmodified** on the private `evidence` disk (`storage/app/evidence`; set `RCD_EVIDENCE_DISK` to an S3-compatible disk later), with SHA-256, GPS, accuracy, capture time and uploader.
- **Watermarked display copy** (max 1920 px, quality from settings) and a **thumbnail**, created by a queued job. Text uses the bundled DejaVu font (licence in `resources/fonts`).
- **Flags** recorded per file:
  - photo EXIF GPS > 250 m from the report GPS;
  - EXIF time > 60 min from capture time;
  - same file already used in another report;
  - video length unverified;
  - video not processed.
- **Access** goes through the app only (no public URLs). Viewers of a report see display copies and thumbnails. **Originals** additionally need `evidence.view_original` (EE, Admin), and each download is audited.
- **Video:** accepted and stored. The browser checks length before upload. Server-side length checking and burned-in watermarks need **ffmpeg/ffprobe**, which are currently **broken on this Mac** (missing `libx265`), so videos are flagged `duration_unverified` / `video_not_processed` and shown unwatermarked. Run `brew reinstall ffmpeg` and they're processed automatically.

## Who can see a report

| Viewer | Sees |
|---|---|
| Super Admin / Admin | all |
| Reporter | own reports |
| JE / AE / EE | reports where they are the responsible officer |
| Contractor users | their firm's reports **after JE validation** |
| Anyone else | 403 |

Task assignees and delegates are added in Phases 6–7.

## Screens

- **Reports list:** filters for report no./road, status, severity, asset type, division, flags (possible duplicate, mapping gap, delayed, override) and date range.
- **Report detail:** map with point and accuracy circle on the section, location facts (km, distance from centre line, captured/received times), evidence gallery with per-file flags, responsibility snapshot, possible duplicates (officers only), history.
- **Map:** the **Damage reports** layer is live, coloured by severity. Filters: status group (open / repairing / under review / closed), severity and date range. The road panel shows the open-issue count with a link.

## Local setup notes

- `.env` uses `QUEUE_CONNECTION=sync` locally, so watermarks are created immediately. In production use `database`/`redis` and run `php artisan queue:work`.
- Six evidence files from my browser test remain in `backend/storage/app/evidence/` (the DB was reseeded, so they're orphaned). An automatic safety check blocked me from deleting them; remove them yourself if you like.
