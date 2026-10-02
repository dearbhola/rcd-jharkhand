# Phase 3 — Master Data (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **83 passed, 504 assertions** (Phases 1–3) |
| Real-browser run (Chrome via puppeteer-core) | Sign in; road page; road → section dropdown and section checkboxes (fetched); select-all; role → officer filter; assignment submit; inline category edit; division → sub-division filter. **No JavaScript errors.** |
| Every master screen as admin | 200 |
| JE | can view, can't edit |
| Citizen | 403 on all master screens |
| Code style | clean |

## Also in this round: SMS-OTP parked (D8)

Citizen self-registration and forgot-password need an SMS provider. Both are behind the setting `auth.sms_otp_enabled` (off): the routes return 404 and the login page shows "Contact your RCD office administrator". The code and tests remain. Turning it on is a settings change once a gateway is plugged into `OtpSender`.

## Screens

| Module | What it does |
|---|---|
| **Divisions** | Divisions with nested sub-divisions, create/edit, active/inactive. |
| **Categories** | Tabs for: asset types (point/line), damage-category tree per asset type (two levels), severities (unique rank, colour), road categories. Inline edit; deactivate, never delete. Codes are generated from names if left blank. |
| **Roads** | Filterable list (text, division → sub-division, category, status). Create/edit with chainage typed in **km** (stored as metres). |
| **Road detail** | Details, geometry version history, and a sections table showing **contractor, maintenance status and JE/AE/EE in force today**. Also add/edit/split sections, assets on the road, and contract history. |
| **Section detail** | JE/AE/EE today, full responsibility history (who, from, to, recorded by), contracts covering the section. |
| **Assets** | Filterable list, create/edit by road + km. The section is derived from the chainage. Coordinates come from the road geometry unless surveyed coordinates are entered. The detail page shows JE/AE/EE (asset override, otherwise the section's). |
| **Contractors** | List with contract counts and "in maintenance today", create/edit (PAN/GSTIN validated), detail with contracts and user accounts. |
| **Contracts** | List filtered by maintenance state (active / not started / expired). Create/edit with the reminder that only maintenance dates decide responsibility. |
| **Contract detail** | Maintenance-status panel, **road coverage** (add by road / section / km range, end with a reason, history kept), documents (private storage, SHA-256, type and size checked). |
| **Responsibility** | Overview of sections with JE/AE/EE today (filter by division, sub-division, road, officer, "incomplete mapping"). Assign form: road → tick sections → role → officer (filtered by role) → effective date + order reference. |

## Business rules enforced (domain services, tested)

| Rule | Service |
|---|---|
| Sections lie inside the road's chainage and never overlap | `RoadSectionService` |
| Split keeps the original section (and its history). The new section inherits the JE/AE/EE in force. Geometry is re-cut. | `RoadSectionService::split` |
| **One contractor per chainage at any time**: no two active mappings may overlap in both chainage and dates (test and production data both checked). Adjacent ranges may have different contractors. | `ContractMappingService` (road row locked) |
| Mappings are only ended or shortened with a reason, never deleted or extended. Extending needs a new mapping. | `ContractMappingService::end` |
| Assigning an officer closes the previous row the day before and inserts a new one. Back-dating before the current start is refused. The officer must hold the role and be active. Re-assigning the same officer is a no-op. | `ResponsibilityService::assign` |
| Asset-level JE/AE/EE overrides the section per role | `ResponsibilityService::forAsset` |
| Only users with `testdata.include` can flag records as test | `MasterDataRequest::payload` |
| All changes audited (master-data diffs + explicit events: `road_section.split`, `contract.mapped`, `contract.mapping_ended`, `contract.document_uploaded`, `responsibility.assigned`) | `Auditable` + `AuditLogger` |

## Notes

- **Asset-level responsibility overrides** work in the domain and are tested, but have no screen yet. The assignment screen covers sections, which is the normal case. I'll add asset overrides if RCD needs them.
- **Geometry drawing and import** (and therefore placing new roads on the map) is Phase 4. Until a road has geometry, its sections and assets have no coordinates, and the road page warns about this.
- Large lists are paginated, and road → section data is fetched on demand rather than embedded.
