# RCD ROAD & ASSET MONITORING SYSTEM

## Master Prompt for Full-Stack AI Development Agent

You are the lead software architect, backend developer, web frontend developer, mobile developer, GIS developer, DevOps engineer, database architect, QA engineer, and UI/UX designer for this project.

Build a complete production-ready **RCD Road & Asset Monitoring System** for a Road Construction Department.

The system must support:

* Road and road-section master management
* GIS road geometry creation and management
* Road/asset location detection using GPS
* Contractor mapping
* JE/AE/EE responsibility mapping
* Maintenance-period based contractor responsibility
* Citizen reporting
* RCD staff reporting
* Contractor repair workflow
* JE/AE field inspection and review
* JE/AE rejection and reopening
* EE final approval/rejection
* Task delegation when JE/AE is unavailable
* GPS and timestamped photographic/video evidence
* Offline mobile operation
* SLA and escalation management
* Contractor performance reporting
* Historical maintenance/repair records
* Role-specific dashboards
* Complete audit trail
* Notifications
* Reports and exports

The system must be designed so that the underlying data model can support future integration with official RCD GIS/project systems.

---

# 1. TECHNOLOGY STACK

## Backend

Use:

* Laravel 12
* PHP 8.3+
* MySQL 8+
* Laravel Sanctum for mobile/API authentication
* RESTful API architecture
* Laravel queues/jobs where appropriate
* Laravel scheduler for SLA/escalation processing
* Laravel notifications
* Laravel filesystem abstraction
* Proper service/repository/domain separation where useful

Do NOT use:

* Spatie Permission
* Tailwind CSS

RBAC must be implemented manually.

---

# 2. WEB FRONTEND

Use:

* Laravel Blade
* Bootstrap 5.x
* Bootstrap Icons
* Vanilla JavaScript where practical
* AJAX/fetch for interactive operations
* DataTables or an equivalent table component where appropriate
* Leaflet or another suitable open-source GIS map library

The UI must be modern, responsive, professional, government-enterprise oriented, and usable on desktop/tablet.

Do not use Tailwind.

---

# 3. MOBILE APPLICATION

Build a mobile application suitable for:

* JE
* AE
* EE
* Contractor
* RCD staff
* Citizen

Prefer Flutter for the mobile application unless there is a strong architectural reason to use another technology.

The mobile application must support:

* Android initially
* GPS
* Camera
* Photo capture
* Video capture
* Local offline database/storage
* Offline report creation
* Offline evidence capture
* Synchronization when connectivity returns
* Retry mechanism for failed uploads
* GPS accuracy information
* Timestamp capture
* Secure authentication
* Push notifications
* Role-specific screens
* Map-based road detection

Do not assume continuous internet connectivity.

---

# 4. CORE PRINCIPLE

The central entity of the application is the:

# ROAD / ROAD SECTION / ASSET

Never determine workflow merely from the user who created a report.

The system must determine responsibility using:

```text
GPS Location
    ↓
Road / Asset
    ↓
Road Section
    ↓
Chainage
    ↓
Active Contract
    ↓
Maintenance Period
    ↓
Contractor
    ↓
JE
    ↓
AE
    ↓
EE
    ↓
Workflow
```

The system must automatically resolve the appropriate workflow.

---

# 5. ROAD MASTER

Create a complete Road Master module.

A road should contain:

* Road ID
* Road Code
* Road Name
* Road Number
* Road Category
* Division
* Sub-Division
* District
* Start location
* End location
* Total length
* Start chainage
* End chainage
* GIS geometry
* Status
* Description
* Created by
* Updated by
* Audit information

Road geometry must initially be created/imported through the application's administrative GIS tools because official GIS data is not currently available.

The system must support:

* Drawing road geometry on a map
* Editing geometry
* Splitting road into sections
* Viewing road geometry
* Chainage assignment
* GIS coordinate storage
* Import/export of GIS data where practical
* GeoJSON support where practical

---

# 6. ROAD SECTIONS

A road can have multiple sections.

Example:

```text
RCD-001

0 km -------- 10 km -------- 20 km -------- 30 km
       S01            S02            S03
```

Create:

`road_sections`

with:

* Road ID
* Section code
* Start chainage
* End chainage
* Length
* GIS geometry
* Status

The system must determine the relevant road section from GPS/chainage.

---

# 7. ASSET MANAGEMENT

Do not design the application exclusively around potholes.

Create a generic Asset model.

Asset types must initially include:

* Road
* Bridge
* Culvert
* Guard Wall
* Retaining Wall
* Drain
* Causeway
* Road Furniture
* Other

Each asset should support:

* Asset code
* Asset type
* Name
* Road
* Road section
* Chainage
* GPS
* GIS geometry
* Description
* Status
* Contractor
* JE
* AE
* EE

The category/subcategory system must be configurable by administrators.

---

# 8. DAMAGE/ISSUE CATEGORIES

Create configurable categories.

Example:

```text
ROAD
    Pothole
    Crack
    Rutting
    Surface Damage
    Shoulder Damage
    Drainage Problem
    Encroachment
    Other

BRIDGE
    Expansion Joint
    Deck Damage
    Railing
    Bearing
    Approach Road
    Other

GUARD WALL
    Collapse
    Crack
    Tilt
    Drainage
    Other
```

Do not hard-code categories.

Administrators must be able to add/edit/deactivate categories.

---

# 9. CONTRACT MANAGEMENT

Create a Contract Master.

Contract fields should include:

* Contract ID
* Contract number
* Contractor
* Agreement number
* Agreement date
* Work order date
* Contract start date
* Contract end date
* Maintenance start date
* Maintenance end date
* Contract value
* Status
* Documents
* Remarks

IMPORTANT:

Contractor responsibility for maintenance must be determined using:

```text
maintenance_start_date
maintenance_end_date
```

Do not use the general contract end date to determine maintenance responsibility.

---

# 10. CONTRACT-ROAD MAPPING

Map contracts to road sections.

Create:

`contract_road_sections`

It must support:

* Contract
* Road
* Road section
* Start chainage
* End chainage
* Effective date
* Status

There will be only one active contractor for a particular road section at a particular point in time.

However, historical contracts must remain available.

---

# 11. RESPONSIBILITY MAPPING

Map:

```text
Road Section
    ↓
Contractor
JE
AE
EE
```

Create responsibility assignment tables with effective dates.

Do not overwrite historical assignments.

Example:

```text
01-Apr-2026 → 30-Jun-2026
JE = JE001

01-Jul-2026 → 31-Dec-2026
JE = JE002
```

Historical reports must retain the correct historical responsibility.

---

# 12. USERS AND RBAC

Implement manual RBAC.

Initial roles:

* Super Admin
* Admin
* EE
* AE
* JE
* Contractor
* RCD Staff
* Citizen

Permission structure should support modules and actions.

Example:

```text
road.view
road.create
road.update
road.delete

contract.view
contract.create
contract.update

report.create
report.view
report.review
report.reject

workflow.assign
workflow.reassign

approval.approve
approval.reject
```

Use database-driven permissions.

Do not use Spatie Permission.

---

# 13. FIELD REPORTING

A report must normally be created from the physical location.

Production workflow:

```text
Open Mobile App
    ↓
GPS
    ↓
Find nearest RCD road/asset
    ↓
Verify location
    ↓
Allow reporting
```

Users must not manually select an arbitrary road and create a field report from somewhere else.

Store:

* Latitude
* Longitude
* GPS accuracy
* Captured timestamp
* Road
* Road section
* Asset
* Chainage
* Reporter
* Reporter role
* Category
* Severity
* Description

GPS coordinates must come from the device.

---

# 14. LOCATION VALIDATION

The system must determine whether the user is sufficiently close to the mapped road/asset.

The permitted distance must be configurable.

For example:

```text
REPORT_LOCATION_RADIUS
REVIEW_LOCATION_RADIUS
```

Do not hard-code the final value.

If outside the permitted area:

```text
You are not currently within the permitted reporting area.
```

Allow test-mode override for authorized users.

---

# 15. CITIZEN REPORTING

Citizens and other RCD staff can create reports.

A citizen does not need to know:

* Road code
* Chainage
* Contractor
* JE
* AE
* EE

The system must automatically resolve:

```text
GPS
 ↓
Road
 ↓
Road Section
 ↓
Chainage
 ↓
JE
AE
EE
Contractor
```

Citizen reports must first go to the assigned JE for validation.

Workflow:

```text
Citizen
 ↓
JE Validation
 ├── Invalid → Close with mandatory reason
 └── Valid
       ↓
Contract/Maintenance check
       ↓
Contractor or department workflow
```

---

# 16. REPORT EVIDENCE

Evidence is mandatory for field reports.

Support:

* Photos
* Videos

Configuration must control:

* Maximum number of photos
* Maximum number of videos
* Maximum image size
* Maximum video size
* Maximum video duration
* Allowed file types
* Compression quality

Do not hard-code these limits.

---

# 17. GPS AND TIMESTAMPED EVIDENCE

Every field photo/video must record:

* Latitude
* Longitude
* GPS accuracy
* Date
* Time
* User
* Device
* Report ID
* Road
* Chainage

Create watermarked display copies.

Example watermark:

```text
RCD ROAD MONITORING

Date: 02-Oct-2026
Time: 11:42:18 IST

Latitude: XXXXX
Longitude: XXXXX

Road: RCD-001
Chainage: 14.250 KM
```

Also retain the original file separately.

Do not rely only on the watermark.

---

# 18. EVIDENCE DATABASE MODEL

Create an evidence entity rather than storing fixed photo/video fields on the report.

Suggested fields:

```text
id
report_id
workflow_action_id
type
file_path
mime_type
file_size
latitude
longitude
gps_accuracy
captured_at
device_id
hash
created_at
```

Use file hashes to detect duplicate uploads where appropriate.

---

# 19. DAMAGE SEVERITY

Create configurable severity levels:

* Low
* Medium
* High
* Critical

Severity must influence SLA and escalation rules.

---

# 20. MAINTENANCE WORKFLOW

If the report is within an active maintenance period:

```text
Report
 ↓
Contractor
 ↓
Repair
 ↓
JE/AE Inspection
 ↓
EE Final Approval
```

If there is no active maintenance responsibility:

```text
Report
 ↓
JE/AE
 ↓
Departmental action / appropriate workflow
```

The workflow engine must be configurable.

---

# 21. CONTRACTOR WORKFLOW

Contractor receives assigned repair tasks.

Contractor dashboard:

```text
New
In Progress
Repair Completed
Awaiting Inspection
Rejected
Reopened
Completed
Overdue
```

Contractor marks the repair completed only after providing required evidence.

Repair completion should include:

* Repair description
* Photos
* Optional video
* GPS
* Timestamp
* Comments

---

# 22. JE/AE REVIEW

JE or AE can perform the field review.

Either JE OR AE can review.

Review must require physical location verification.

```text
Task
 ↓
Navigate to location
 ↓
GPS verification
 ↓
Capture inspection photo/video
 ↓
Review
```

The reviewer must not be able to complete a production field inspection from an arbitrary location.

---

# 23. JE/AE REJECTION

Both JE and AE can reject a repair at their stage.

Rejection requires a mandatory comment/reason.

Example:

```text
Status:
REJECTED_BY_JE

Reason:
Repair not completed according to required condition.
```

Then:

```text
REJECTED
 ↓
REOPENED
 ↓
CONTRACTOR
 ↓
REPAIR
 ↓
REVIEW
```

Never overwrite previous repair attempts.

---

# 24. REPAIR ATTEMPTS

Each repair attempt must be independently recorded.

Example:

```text
Repair Attempt 1
 ↓
Rejected by JE

Repair Attempt 2
 ↓
Rejected by AE

Repair Attempt 3
 ↓
Accepted
 ↓
EE
```

Each attempt must retain:

* Contractor
* Date/time
* Evidence
* GPS
* Comments
* Reviewer
* Rejection reason
* Status

---

# 25. EE FINAL APPROVAL

EE performs final approval.

EE has:

```text
Approve
Reject
```

Rejection requires mandatory reason.

If EE rejects:

```text
EE Reject
 ↓
Reopen
 ↓
Contractor
 ↓
Repair
 ↓
JE/AE Review
 ↓
EE Final Approval
```

EE does not replace JE/AE inspection.

---

# 26. WORKFLOW ENGINE

Do not hard-code workflow logic into individual controllers.

Create:

```text
workflow_definitions
workflow_steps
workflow_instances
workflow_actions
workflow_assignments
workflow_history
```

Every transition must be recorded.

Example:

```text
REPORTED
ASSIGNED
IN_PROGRESS
REPAIR_SUBMITTED
UNDER_REVIEW
REJECTED
REOPENED
FORWARDED_TO_EE
EE_REJECTED
APPROVED
CLOSED
```

Workflow must be extensible.

---

# 27. TASK DELEGATION

Road responsibility and task responsibility are different concepts.

Do not change the permanent JE/AE mapping when someone is on leave.

Example:

```text
Road Primary JE = JE001

JE001 on leave

Task #123
Current Assignee = JE007
```

Create a delegation system.

Support:

* Start date
* End date
* Primary user
* Replacement user
* Role
* Reason
* Automatic task routing
* Manual task reassignment

---

# 28. EXISTING TASKS DURING LEAVE

Support configuration for:

* Transfer all pending tasks
* Transfer only new tasks
* Selectively transfer tasks

Every reassignment must be audited.

Never delete the original assignment.

---

# 29. SLA MANAGEMENT

Create configurable SLA rules.

Example configuration:

```text
Asset Type
Category
Severity
Contractor Response Hours
Repair Hours
Review Hours
Approval Hours
```

The actual values must be configurable by authorized administrators.

System must calculate:

* Due date
* Remaining time
* Overdue duration

---

# 30. ESCALATION

Implement automated escalation.

Example:

```text
SLA approaching
 ↓
Reminder

SLA breached
 ↓
Escalate to AE

Further breach
 ↓
Escalate to EE
```

Escalation rules must be configurable.

Use Laravel scheduler/queues.

---

# 31. NOTIFICATIONS

Support notifications for:

* New task
* Task reassignment
* Contractor assignment
* Repair submitted
* JE/AE review required
* Repair rejected
* EE approval required
* EE rejection
* SLA approaching
* SLA breached
* Delegation
* Report status change

Build notification abstraction so email/SMS/WhatsApp/push can be added independently.

---

# 32. OFFLINE MOBILE OPERATION

Offline mode is mandatory in architecture.

Mobile app must support:

```text
No Internet
 ↓
GPS
 ↓
Create report
 ↓
Capture evidence
 ↓
Save locally
 ↓
PENDING SYNC
 ↓
Internet returns
 ↓
Upload
 ↓
Server validation
 ↓
Workflow creation
 ↓
SYNCED
```

Use a local database.

Every offline-created report must have a unique:

```text
client_uuid
```

Server must be idempotent and prevent duplicate synchronization.

Uploads must support retry.

Do not mark a report as successfully synchronized until the server confirms it.

---

# 33. OFFLINE SECURITY

Sensitive information must be protected locally.

Consider:

* Encrypted local storage
* Secure token storage
* Automatic logout
* Device binding where appropriate
* Secure upload
* File cleanup after successful synchronization

---

# 34. MAP DASHBOARD

Map must be a major part of the application.

Support:

* Road layers
* Road sections
* Asset layers
* Damage markers
* Open issues
* Repairing issues
* Rejected issues
* Closed issues
* Contractor filter
* JE filter
* AE filter
* EE filter
* Division filter
* Severity filter
* Date filter

Clicking a road should show:

```text
Road
 ↓
Section
 ↓
Contract
 ↓
Contractor
 ↓
JE
 ↓
AE
 ↓
EE
 ↓
Open Issues
 ↓
Repair History
```

---

# 35. DASHBOARDS

Create role-specific dashboards.

## Citizen

* My Reports
* Report status
* Submitted reports
* Closed reports

## Contractor

* Assigned repairs
* New
* In progress
* Submitted for inspection
* Rejected
* Reopened
* Overdue
* Completed

## JE

* My roads
* New reports
* Citizen reports awaiting validation
* Contractor repairs awaiting inspection
* Rejected repairs
* Overdue tasks
* Map
* Delegated tasks

## AE

* Roads under responsibility
* Pending reviews
* Rejected repairs
* Forwarded to EE
* Overdue tasks
* Map

## EE

* Division overview
* Pending final approvals
* Approved
* Rejected
* Overdue
* Contractor performance
* Road condition map

## Admin

* Roads
* Assets
* Contracts
* Contractors
* Users
* Divisions
* Reports
* Workflow
* SLA
* Audit logs
* System configuration
* GIS management

---

# 36. ROAD CONDITION HISTORY

Maintain historical records.

For each road:

```text
Road
 ↓
2025 reports
 ↓
2026 reports
 ↓
Repair history
 ↓
Inspection history
 ↓
Recurring problem areas
```

Allow filtering by:

* Year
* Category
* Severity
* Contractor
* Section

---

# 37. CONTRACTOR PERFORMANCE

Create a Contractor Performance Management module.

Performance must be derived from actual system events.

Track:

* Total contracts
* Active contracts
* Completed contracts
* Roads maintained
* Total repair tasks
* Completed tasks
* Open tasks
* SLA compliance
* SLA breaches
* Average response time
* Average repair time
* JE rejection count
* AE rejection count
* EE rejection count
* Reopened repairs
* Repair attempts
* Repeat defects
* Overdue tasks

Do not allow arbitrary manual manipulation of calculated metrics.

---

# 38. CONTRACTOR PERFORMANCE REPORT

Generate reports by:

* Contractor
* Contract
* Division
* Road
* Road section
* Financial year
* Maintenance period

Each metric must be traceable back to source records.

Example:

```text
28 Reopened Repairs
```

must be clickable and show the underlying 28 reports.

---

# 39. CONTRACT COMPLETION REPORT

At maintenance-period completion, generate a contract performance report containing:

* Contract details
* Contractor
* Roads covered
* Road sections
* Maintenance period
* Total defects
* Repairs completed
* SLA performance
* Rejections
* Reopened repairs
* Repeat defects
* Average repair time
* Evidence summary
* Inspection history
* Final status

The report should be exportable to PDF and Excel.

Contractor performance information may be used by authorized RCD officials as part of future procurement/contract-management processes, subject to applicable departmental procurement rules.

Do not automatically award or recommend a future contract unless an explicitly configured official procurement rule requires it.

---

# 40. REPORTS

Create reporting module with:

* Daily reports
* Weekly reports
* Monthly reports
* Financial-year reports
* Road-wise reports
* Contractor-wise reports
* JE-wise reports
* AE-wise reports
* EE-wise reports
* Division-wise reports
* Damage-category reports
* Severity reports
* SLA reports
* Rejection reports
* Reopening reports
* Contractor performance reports

Support:

* PDF
* Excel
* CSV

---

# 41. AUDIT TRAIL

Audit is mandatory.

Every important action must record:

* User ID
* User name
* Role
* Action
* Entity
* Entity ID
* Previous value where applicable
* New value where applicable
* IP
* Device information where available
* Timestamp
* Comment/reason

Audit:

* Report creation
* Assignment
* Reassignment
* Repair submission
* Review
* Rejection
* Approval
* Contract changes
* Road mapping
* User changes
* Delegation
* Workflow transitions

Nothing important should be silently overwritten.

---

# 42. TEST MODE

Create a system-wide test-data mechanism.

Every relevant record must support:

```text
is_test
```

or equivalent environment/data classification.

Test reports must be clearly marked:

```text
TEST
```

Dashboards and official reports must exclude test data by default.

Authorized users may enable:

```text
Include Test Data
```

for testing.

Never allow test records to accidentally appear in official reports.

---

# 43. DUPLICATE REPORT DETECTION

Implement duplicate detection.

If multiple reports are submitted near the same:

* Road
* Chainage
* GPS location
* Category
* Time period

show possible duplicate warning.

Do not automatically delete reports.

Allow JE to merge/link duplicates where appropriate.

Maintain audit history.

---

# 44. SECURITY

Implement:

* Sanctum authentication
* Strong password policies
* Role/permission checks
* API throttling
* Validation
* File validation
* MIME validation
* Maximum upload size
* Secure file storage
* Authorization policies
* CSRF protection
* SQL injection protection
* XSS protection
* Rate limiting
* Secure API endpoints
* Audit logging

Never trust client-provided GPS, role, user ID, contractor ID, JE ID, AE ID or EE ID.

The server must resolve and validate these values.

---

# 45. DATABASE DESIGN

Design a normalized relational schema.

At minimum consider:

```text
users
roles
permissions
role_permissions

divisions
sub_divisions

roads
road_sections
road_geometries

asset_types
asset_categories
assets

contractors
contracts
contract_road_sections

responsibility_assignments

reports
report_categories
report_severities

evidences
repair_attempts
inspections

workflow_definitions
workflow_steps
workflow_instances
workflow_actions
workflow_assignments
workflow_history

delegations

sla_rules
sla_instances
escalations

notifications

audit_logs

system_settings

sync_records
```

Do not blindly create every table above if a better normalized architecture exists. Review relationships and avoid redundant data.

---

# 46. API DESIGN

Create versioned API:

```text
/api/v1/
```

Organize APIs by domain:

```text
/auth
/roads
/road-sections
/assets
/contracts
/contractors
/reports
/evidence
/workflows
/tasks
/inspections
/delegations
/dashboards
/notifications
/sync
/settings
```

Document APIs using OpenAPI/Swagger where practical.

---

# 47. MOBILE API REQUIREMENTS

Mobile APIs must support:

* Login
* User profile
* Assigned tasks
* Nearby roads
* Nearby assets
* Create report
* Upload evidence
* Submit repair
* Submit inspection
* Approve/reject where authorized
* Task reassignment
* Offline synchronization
* Notifications
* Dashboard data

API responses must be optimized for mobile.

---

# 48. DATABASE TRANSACTIONS

Use transactions for important workflow transitions.

For example:

```text
Contractor submits repair
 ↓
Create repair attempt
 ↓
Store evidence
 ↓
Update workflow
 ↓
Create assignment
 ↓
Create notification
 ↓
Audit
```

This must not leave the system in a partially updated state.

---

# 49. CONCURRENCY

Handle cases where two users attempt to process the same task.

For example:

```text
JE opens task
AE opens same task
```

The server must prevent invalid duplicate workflow transitions.

Use appropriate database locking/optimistic concurrency.

---

# 50. UI/UX

Use a clean government enterprise UI.

Bootstrap 5.

Desktop:

```text
Sidebar
Header
Breadcrumb
Page content
Dashboard cards
Tables
Filters
Maps
```

Mobile:

```text
Bottom navigation
Large action buttons
Map
Camera
Task cards
Status indicators
```

Field operations must minimize typing.

---

# 51. FIELD REPORT UX

The report process should be approximately:

```text
1. Open app
2. GPS detected
3. Road detected
4. Asset/category selected
5. Capture photo
6. Capture optional/required video
7. Select severity
8. Add short description
9. Submit
```

Avoid unnecessary fields.

---

# 52. FIELD REVIEW UX

Review process:

```text
1. Open task
2. Navigate to location
3. GPS verification
4. View original evidence
5. Capture current photo
6. Capture video if required
7. Enter inspection comment
8. Accept / Reject
9. Submit
```

If rejecting, the rejection reason is mandatory.

---

# 53. SEARCH AND FILTERING

Provide powerful search/filtering for administrators.

Search by:

* Report number
* Road code
* Road name
* Asset code
* Contractor
* User
* Chainage
* Category
* Status
* Severity
* Date
* Contract number

---

# 54. NOTIFICATION CENTER

Every user should have a notification center.

Support:

```text
Unread
Read
All
```

Clicking a notification should open the relevant task/report.

---

# 55. FILE STORAGE

Design storage so it can later move from local storage to object storage such as S3-compatible storage.

Do not hard-code storage implementation into business logic.

Use Laravel filesystem abstraction.

---

# 56. PERFORMANCE

The application may eventually contain:

* Thousands of roads
* Large GIS geometries
* Millions of photographs/videos
* Large audit logs
* Large workflow histories

Design for scale.

Use:

* Pagination
* Indexes
* Queues
* Background processing
* Lazy loading
* Optimized GIS queries
* Thumbnail generation
* Appropriate database indexes
* Aggregated dashboard queries/cache where necessary

Do not load entire datasets into the browser.

---

# 57. GIS DATABASE DECISION

Initial development may use MySQL if practical.

However, architect the GIS layer so that migration to PostgreSQL/PostGIS is possible if advanced spatial queries become necessary.

Do not tightly couple business logic to a single GIS database implementation.

---

# 58. DEVELOPMENT APPROACH

Do not attempt to generate the entire application blindly in one pass.

Work in phases.

## Phase 1

Architecture and database:

* ERD
* database schema
* migrations
* seeders
* models
* relationships

## Phase 2

Authentication/RBAC:

* users
* roles
* permissions
* login
* API authentication

## Phase 3

Master data:

* divisions
* roads
* road sections
* assets
* categories
* contractors
* contracts
* responsibility mappings

## Phase 4

GIS:

* road geometry
* map
* chainage
* GPS lookup
* nearby-road detection

## Phase 5

Reporting:

* damage reports
* evidence
* GPS validation
* citizen reports

## Phase 6

Workflow:

* contractor repair
* JE review
* AE review
* EE approval
* rejection
* reopening
* repair attempts

## Phase 7

Delegation:

* leave
* temporary assignments
* reassignment
* assignment history

## Phase 8

SLA:

* rules
* timers
* escalation
* notifications

## Phase 9

Dashboards:

* citizen
* contractor
* JE
* AE
* EE
* admin

## Phase 10

Contractor performance:

* metrics
* historical performance
* contract completion report

## Phase 11

Mobile:

* authentication
* map
* field reporting
* camera
* GPS
* offline database
* sync

## Phase 12

Testing/security/performance:

* automated tests
* API tests
* workflow tests
* authorization tests
* offline sync tests
* file upload tests
* GIS tests
* concurrency tests

---

# 59. TESTING REQUIREMENTS

Create automated tests for all critical business rules.

At minimum:

### Location

* Report outside permitted radius
* Report inside permitted radius
* GPS accuracy handling

### Contract

* Maintenance period active
* Maintenance period expired
* Contract history

### Workflow

* Contractor repair
* JE acceptance
* JE rejection
* AE acceptance
* AE rejection
* EE approval
* EE rejection
* Reopening

### Delegation

* JE leave
* AE leave
* Task reassignment
* Historical assignment

### Evidence

* File limits
* File types
* GPS metadata
* Timestamp
* Duplicate upload

### Offline

* Create offline report
* Synchronize
* Failed synchronization
* Retry
* Duplicate synchronization

### Authorization

Verify that users cannot perform actions outside their permissions.

---

# 60. SEED DATA

Create realistic development seed data:

* 2 divisions
* 4 sub-divisions
* 20 roads
* multiple road sections
* multiple assets
* contractors
* contracts
* JE/AE/EE users
* categories
* severity levels
* workflow configuration
* SLA configuration

Clearly mark all seed data as test data.

---

# 61. DEMO SCENARIO

The application must work end-to-end with this scenario:

```text
Citizen reports pothole
 ↓
GPS detects RCD road
 ↓
System identifies road section
 ↓
System identifies JE/AE/EE
 ↓
System identifies active maintenance contract
 ↓
Contractor receives task
 ↓
Contractor repairs road
 ↓
Contractor captures GPS/photo/video
 ↓
JE visits location
 ↓
JE captures inspection evidence
 ↓
JE rejects repair
 ↓
Mandatory rejection reason
 ↓
Task reopened
 ↓
Contractor repairs again
 ↓
JE/AE accepts
 ↓
Task forwarded to EE
 ↓
EE reviews
 ↓
EE approves
 ↓
Report CLOSED
 ↓
Contractor performance metrics updated
 ↓
Complete audit history available
```

This scenario must be fully functional before declaring the MVP complete.

---

# 62. DELIVERABLES

Produce:

### Backend

* Laravel project
* migrations
* models
* services
* policies
* controllers
* API controllers
* requests
* resources
* jobs
* notifications
* commands
* scheduler
* tests
* seeders

### Web

* Blade layouts
* Bootstrap UI
* dashboards
* CRUD modules
* GIS screens
* workflow screens
* reports
* administration screens

### Mobile

* Flutter project
* authentication
* dashboards
* maps
* field reporting
* camera
* GPS
* evidence management
* offline database
* synchronization
* notifications

### Documentation

Create:

* Architecture document
* ERD
* API documentation
* Installation guide
* Environment configuration
* Database setup
* Deployment guide
* Mobile build guide
* User roles/permissions documentation
* Workflow documentation
* GIS documentation
* Offline synchronization documentation
* Testing documentation

---

# 63. CODING STANDARDS

Follow:

* SOLID principles
* Clean architecture where appropriate
* DRY
* Laravel conventions
* PSR standards
* Meaningful class/method names
* Proper validation
* Proper authorization
* Database transactions
* No duplicated business logic
* No magic constants
* Configurable system settings
* Proper exception handling
* Logging

Do not put business logic directly into Blade templates.

Do not put large business workflows directly into controllers.

---

# 64. IMPORTANT BUSINESS RULES

These rules are mandatory:

1. Road/asset responsibility determines workflow.
2. GPS determines the field location.
3. User cannot arbitrarily select a road for a production field report.
4. Maintenance period determines contractor responsibility.
5. One active contractor per road section at a given time.
6. Historical contracts must remain preserved.
7. Road responsibility mappings must preserve history.
8. Citizen reports go to the mapped JE for validation.
9. Contractor repairs require evidence.
10. JE/AE review requires physical location verification.
11. JE can reject.
12. AE can reject.
13. EE performs final approval.
14. EE rejection requires a reason.
15. JE/AE rejection requires a reason.
16. Rejected repairs reopen the workflow.
17. Every repair attempt must be preserved.
18. JE/AE delegation must not destroy the permanent mapping.
19. Task reassignment must be audited.
20. GPS and timestamps must be captured for field evidence.
21. Offline operation must be supported.
22. Test data must be clearly separated from production data.
23. SLA must be configurable.
24. Escalation must be configurable.
25. Contractor performance must be derived from actual records.
26. Performance metrics must be traceable to source records.
27. Official procurement decisions must remain subject to configured departmental/procurement rules.
28. Audit history must never be silently overwritten or deleted.

---

# 65. IMPORTANT DEVELOPMENT INSTRUCTION

Before writing code:

1. Analyze the complete requirements.
2. Identify contradictions or missing business rules.
3. Propose the final architecture.
4. Produce the ERD.
5. Produce the workflow/state diagram.
6. Produce the API architecture.
7. Produce the mobile offline-sync architecture.
8. Produce the GIS architecture.
9. Produce the implementation plan.
10. Wait for approval before beginning large-scale implementation if operating interactively.

When implementation begins, implement one phase at a time.

After every phase:

* Run tests
* Fix errors
* Verify migrations
* Verify authorization
* Verify UI
* Verify API
* Document completed work

Never claim a feature is complete without testing it.

---

# FINAL OBJECTIVE

Build a production-grade **RCD Road & Asset Monitoring, Repair, Inspection, Approval, GIS and Contractor Performance Management System**.

The system must be:

* GIS-first
* Mobile-first for field operations
* Offline-capable
* Evidence-driven
* Workflow-driven
* Role-based
* Auditable
* Configurable
* Secure
* Scalable
* Maintainable

The final system should allow RCD officials to answer:

> Where is the problem?

> Which road/asset is affected?

> Which section is responsible?

> Which contractor is responsible?

> Which JE/AE/EE is responsible?

> Who reported it?

> When was it reported?

> What evidence was captured?

> When did the contractor respond?

> What repair was performed?

> Who inspected it?

> Was it rejected?

> Why was it rejected?

> How many times was it repaired?

> Who finally approved it?

> Was the repair completed within SLA?

> What is the road's maintenance history?

> What is the contractor's documented performance?

> What tasks are currently pending, overdue, delegated or escalated?

Every answer must be traceable to actual records in the system.
