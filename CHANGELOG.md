# Changelog

All notable changes to Kitloan are documented here. Versions follow [Semantic Versioning](https://semver.org/):
breaking changes bump the major version and are always called out explicitly, since they need extra care on
upgrade (see [Updating an existing instance](README.md#updating-an-existing-instance)).

## [1.2.0] - 2026-08-26

No breaking changes. All migrations in this release are additive; `down()` works for each. Upgrade with the
normal procedure in [Updating an existing instance](README.md#updating-an-existing-instance) — which is now a
single `kitloan:upgrade` command (see below).

### Added

- **Version awareness + one-command upgrade.** The running version is now a real value: `app/VERSION` /
  `config/version.php`, surfaced at `GET /health` (`"version"`), in the Administration tab bar, and recorded in
  the settings table once an upgrade completes. New `php artisan kitloan:upgrade` runs migrations, backfills
  new roles/settings, clears every compiled cache (config/routes/**views**/events) and re-caches, restarts
  queue workers, and stamps the installed version — idempotent, and refuses to run on an instance too old to
  upgrade directly. The Compose `migrate` service now runs it. Full walk-through in
  [docs/UPGRADING.md](docs/UPGRADING.md).
- **Iframe embedding allow-list** (Administration → Settings → Embedding). Off by default. When on, only the
  listed parent origins may frame the app (`Content-Security-Policy: frame-ancestors`), the session cookie is
  promoted to `SameSite=None; Secure` so an existing session survives inside a cross-site frame, and an
  embedded page (`?embed=1`) trims its chrome and attempts a **silent SSO sign-in** (`prompt=none`) before
  showing a login button — a visitor already signed in to the identity provider elsewhere lands straight on
  their bookings.
- **TOTP two-factor authentication for local (non-SSO) admin accounts.** Any account with a break-glass
  password *and* the administrator role must enrol (authenticator app QR + 8 single-use recovery codes) and is
  challenged for a code after the password on every local sign-in. Pure-SSO accounts are never prompted — the
  identity provider owns their MFA. Administration → Users shows 2FA status and can reset it for an admin who
  has lost their device.
- **Account lockout on repeated local-login failures.** After 10 failed attempts against one account (any
  IP, or 10 bad 2FA codes) the account is locked for 15 minutes via a stored `locked_until` stamp that
  survives a cache flush, an `auth.local_login_locked` audit event is written, and IT is emailed.
- **Delete across the Administration catalog.** Resource Pools, Locations and Booking Types can now be deleted
  (soft-delete — existing bookings and audit history are untouched; the item just stops appearing for new
  ones). Deleting a resource pool is refused while it still has upcoming bookings.
- **Users can be deleted** (soft-delete; bookings/audit preserved, the account can no longer sign in). Refused
  for your own account and the last enabled administrator.
- **Resource Pool JSON import/export**, including nested resources — Export/Import buttons on Administration →
  Resource Pools, with a worked example at `app/resources/examples/resource-pools.json`.
- **Bulk campus rename** (Administration → Locations → "Rename campus") — retitle or consolidate a campus
  across every location at once.
- **Configuration export / import** (Administration → Settings): "Export settings", "Export full
  configuration", and a sectioned "Import…" covering settings, locations, resource pools, booking types,
  schedule periods and approval rules. Secrets are never included; import is upsert-only and never deletes.
- **Searchable room picker** on the booking wizard and amend screens — the location list is now a filterable
  combobox instead of a long native `<select>`.

### Changed

- Amending an **already-approved** booking to request **more** units now always sends it back to "pending" for
  re-approval, regardless of the auto-approval thresholds. Reducing the quantity (or leaving it unchanged)
  keeps the existing approval. IT/admin amendments are unaffected.
- The local-login per-account brute-force limit dropped from 15 to 10 failures, and now applies a real lock
  (see Added) rather than only writing an audit event. The `auth.local_login_bruteforce_suspected` audit
  event is replaced by `auth.local_login_locked`.

### Fixed

- **"Quick fill from period" did nothing / threw `$wire is not defined`.** The `<select>` called the Alpine
  `$wire` magic from a bare `@change` handler, which only resolves inside an Alpine component scope — and a
  stale compiled Blade template on an un-migrated instance could still be the older `onchange=` version.
  Rebuilt as a plain Livewire `wire:model.live` binding with no Alpine involvement; `kitloan:upgrade` also
  now clears compiled views on every upgrade so a stale template can't linger.

## [1.1.0] - 2026-08-19

### Added

- Bulk CSV import for Locations (Administration → Locations), upserting by code.
- Preparation/return buffers now default to 15 minutes for newly created Resource Pools.
- 7am daily booking summary email, sent only on days with active bookings, with a per-user opt-out
  (Administration → Users → "Receives daily booking summary").
- Booking wizard: changing the start time now jumps the finish time to an hour later automatically, and a new
  booking's default date/time is picked intelligently from School Day Start/Finish (Administration → Settings)
  instead of always "now + 1 hour" — which could default to the middle of the night.
- School Periods (Administration → Periods): configurable, grouped timetable periods with a "quick fill from
  period" selector on both the booking wizard and the amend-booking screen.
- Local login password management moved into the GUI (Administration → Users: set/reset/clear per
  administrator), alongside the existing CLI command. A Settings toggle lets any administrator turn
  break-glass login on/off without server access, independent of the `LOCAL_LOGIN_ENABLED` infrastructure flag.
- A second rate limiter for local login, keyed on email address alone (independent of source IP), so a
  distributed attempt across rotating IPs is still caught; crossing 10 failures against one account now writes
  a distinct `auth.local_login_bruteforce_suspected` audit event.
- `LICENSE` (MIT).

### Fixed

- 500 error creating a new Location or User: a Livewire uniqueness-validation rule built an invalid
  `unique:table,column,` clause (empty ignore-ID) when there was no existing record to exclude.
- The "quick fill from period" control did nothing: it called the Alpine `$wire` magic property from a plain
  `onchange` HTML attribute, outside Alpine's directive scope where `$wire` is actually available.
- `admin:set-local-password` — and its new GUI equivalent — never actually saved the password. `password` is
  deliberately excluded from the User model's mass-assignment allowlist, so `update()` silently discarded it
  with no error. Both now use `forceFill()`.
- `DemoDataSeeder` is now also guarded against running in production when invoked directly
  (`db:seed --class=DemoDataSeeder`), not just via the top-level seeder that normally gates it.

## [1.0.0] - 2026-08-19

Initial public release.

### Added

- Resource Pools with `individual` (discrete tracked items) and `quantity` allocation modes.
- Booking workflow with conflict detection, preparation/return buffers, and row-locked concurrent-safe
  allocation.
- Approval logic with hard/soft constraints and auto-approval lead-time thresholds.
- OIDC authentication (generic, tested against Microsoft Entra ID) with local-account matching and
  auto-provisioning by allowed email domain.
- Emergency break-glass local admin login (off by default).
- Admin impersonation with full audit trail.
- Optional Snipe-IT integration: asset import, scheduled sync, outage-tolerant local snapshot.
- Email notifications (booking, approval request, daily summary) via queued jobs.
- IT dashboard and logistics day view; List view for all bookings.
- Docker Compose deployment: Nginx + PHP-FPM, PostgreSQL (MySQL/MariaDB also supported), queue and scheduler
  workers.
