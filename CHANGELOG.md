# Changelog

All notable changes to Kitloan are documented here. Versions follow [Semantic Versioning](https://semver.org/):
breaking changes bump the major version and are always called out explicitly, since they need extra care on
upgrade (see [Updating an existing instance](README.md#updating-an-existing-instance)).

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
