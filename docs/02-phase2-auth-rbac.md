# Phase 2 — Authentication & RBAC (web) — completed 02-Oct-2026

The mobile app and the `/api/v1` API are on hold (D7), so this phase covers the **web** only. The Sanctum package is installed but unused until the API phase.

## Verified

| Check | Result |
|---|---|
| Automated tests | **59 passed, 365 assertions** (Phase 1 + Phase 2) |
| Smoke test on a running server | Every page returns 200 for an admin; a citizen gets 403 on admin pages; 404 page works |
| Visual check (headless Chrome) | Login, user list and permission matrix render correctly |
| Code style (`pint`) | clean |

## Built

### Sign-in and accounts
- **Sign-in** with email, mobile or employee code. Lockout after `auth.max_login_attempts` failures for `auth.lockout_minutes`, per identifier + IP. There is also an IP rate limit. Success, failure, lockout and blocked (inactive) attempts are all audited.
- **Citizen self-registration** (D5): name + mobile → OTP → password → account with the CITIZEN role, signed in automatically.
- **Forgot password** via mobile OTP, for everyone. It never reveals whether a number is registered. A reset revokes all other sessions.
- **OTP rules (settings):** length, validity, maximum attempts, resend cooldown. Codes are stored hashed, and a new code invalidates earlier ones. Rate limits: 10 per hour per IP and 5 per hour per mobile.
- **SMS:** sent through the `OtpSender` interface. The `log` driver writes the code to `storage/logs/laravel.log` and refuses to run in production. Plugging in a real gateway means adding one class and setting `RCD_OTP_DRIVER`.

### Passwords and sessions
- **Password policy:** minimum length from settings, upper- and lower-case letters, a number and a symbol.
- **Forced change for staff** when an administrator set the password (new account or reset) or it is older than `auth.password_expiry_days`. Citizens are exempt from expiry.
- **Accounts suspended mid-session** are signed out on their next request.
- **Sessions:** 30-minute lifetime, encrypted, stored in the database. Session ID is regenerated on sign-in and password change, and other sessions are removed on password change.
- **Security headers:** X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy (allows geolocation and camera for field reporting), and HSTS over HTTPS.

### Roles and permissions (manual RBAC)
- `Gate::before`: a dotted ability (`road.view`) is checked against the database permissions. Other abilities fall through to policies. Super Admin passes everything.
- Route middleware: `permission:x.y` (or `a|b` for either), `active`, `password.current`.
- Blade uses the standard `@can('report.create')`.
- The sidebar is built from `config/navigation.php`. An item appears only when its route exists **and** the user holds a matching permission, so later modules show up automatically.

### Administration screens
- **Users:** search and filter (name, email, mobile, code; role; status); create, edit and view; status change with a mandatory reason; temporary password with a mandatory reason; the account's audit history.
  - Only a Super Admin can grant the Super Admin role or edit a Super Admin's account.
  - Contractor-role users must be linked to a contractor, and only contractor users can be.
  - Admins can't remove their own roles or change their own status.
  - Role changes are audited explicitly (old and new role lists).
- **Roles & permissions:** permission matrix grouped by module with select-all, and custom roles.
  - Super Admin is implicit-all and not editable.
  - You can't remove `role.manage` from a role you hold.
  - Every change is audited with the added and removed permission keys.
- **Audit log:** filter by action prefix, user, entity, ID and date range. A detail page shows old and new values field by field.
- **Profile:** your own account and effective permissions.
- **Test-data toggle:** only for users with `testdata.include`, remembered for the session, audited. Lists filter accordingly, and a striped banner shows while test data is included.

### UI foundation
- Bootstrap 5.3 + Bootstrap Icons, built with Vite. No Tailwind, no jQuery.
- Layout: fixed navy sidebar (collapses on tablet and phone), top bar with breadcrumbs, test-data toggle, notification bell (appears once Phase 8 adds the route), user menu.
- Shared components: `x-app-layout`, `x-guest-layout`, `x-page-header`, `x-test-badge`, `x-status-badge`.
- Error pages: 403, 404, 419, 429, 500.
- Small vanilla JS helpers (`resources/js/ui.js`): a CSRF-aware `fetch` wrapper, confirm-before-submit, auto-submitting filter forms, double-submit guard, select-all checkboxes.

## Try it

```bash
cd backend
/opt/homebrew/opt/php/bin/php artisan migrate:fresh --seed
npm run build
/opt/homebrew/opt/php/bin/php artisan serve
```

Open http://localhost:8000. Sign in as `admin@rcd.test` / `Rcd@Demo2026`, or register as a citizen (the OTP appears in `storage/logs/laravel.log`).

## Deferred

- Sanctum token login for the mobile API (with the API phase, D7).
- A Content-Security-Policy header (Phase 12, once the map and tile sources are fixed).
