# Changelog

All notable changes to Kitloan are documented here. Versions follow [Semantic Versioning](https://semver.org/):
breaking changes bump the major version and are always called out explicitly, since they need extra care on
upgrade (see [Updating an existing instance](README.md#updating-an-existing-instance)).

## [1.8.0] - 2026-09-03

No breaking changes, no migration. Two new seeded settings
(`scheduled_backups_enabled`, `backup_retention_count`) are added by `kitloan:upgrade`'s re-seed.

### Added

- **Encrypted backups.** A single archive of the whole database, the uploaded files and the configuration
  bundle — AES-256-CBC in OpenSSL's `Salted__` + PBKDF2 container, so a `.klbackup` file can be opened with
  the `openssl` CLI alone if the app is unavailable.
  - **On demand:** Administration → Settings → Backups → *Download backup now*.
  - **Scheduled:** an opt-in nightly (02:30) `kitloan:backup` that writes to `storage/app/backups` and
    prunes to a retention count. Refuses to enable until a passphrase is set
    (`KITLOAN_BACKUP_PASSPHRASE`, or a write-only field in Settings, stored encrypted at rest).
  - **Restore:** `php artisan kitloan:restore <file>` — shows the archive's metadata, confirms, then wipes
    and reloads every table (`--force` / `--skip-files`). The logical dump reloads tables in foreign-key
    order, so it is database-portable and needs no superuser rights; Postgres id sequences are
    fast-forwarded afterwards.
- Config **export / import** is now covered end-to-end by tests across every section (full round-trip,
  idempotency, older partial bundles) — no behaviour change, it already worked.

## [1.7.0] - 2026-09-02

No breaking changes. One additive migration (`2026_01_09_000000_add_theme_to_users_table`) adds
`users.theme` (`light` / `dark` / `system`, default `system`) with a working `down()`. `kitloan:upgrade`
runs it and re-seeds/re-stamps as usual.

### Added

- **Dark mode.** A per-account colour theme — **System** (follows the device / browser setting), **Light**
  or **Dark** — chosen on **My Profile → Appearance**. It applies across the whole app including the
  sign-in screen, is resolved before first paint (no flash), follows the OS while "System" is selected,
  and is remembered on the sign-in screen for next time. Notification emails are unaffected.

## [1.6.1] - 2026-09-02

Security hardening release. No breaking changes, no migration. Came out of a full review of the app's
auth, authorisation, input handling and headers — the notes on what was checked and what was deliberately
left are in [`docs/SECURITY-REVIEW.md`](docs/SECURITY-REVIEW.md).

### Fixed

- **Helpdesk ticket link could hold a `javascript:` URL (stored XSS).** The field was validated with a
  bare `url` rule, which PHP's URL filter accepts for `javascript://%0a…` and other non-web schemes; IT or
  a booked officer clicking it on the booking page would run script on the app origin. It's now restricted
  to `http`/`https` on every entry point, and both places it's rendered as a link go through a new
  `Booking::safeHelpdeskUrl()` guard so a pre-existing row can't render either. (Markdown emails were
  already safe — CommonMark drops unsafe-scheme links.)
- **HTML injection into notification emails.** Admin-editable email intro / policy-notice text is
  raw-rendered into the Markdown mail, and free-text tokens such as `{{ notes }}` carry user input — so a
  booking's notes could reach the email as live markup. Token *values* are now HTML-escaped in that
  context; the admin's own Markdown wording is unaffected, and subject lines (plain-text headers) keep raw
  values.
- **Site-logo upload accepted SVG.** An SVG can carry inline `<script>` and the logo is served from this
  origin and shown on the unauthenticated login page. Logo uploads are now PNG / JPG / WebP only.

### Changed

- **The app emits `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`
  itself.** Previously these were only present when a CDN / reverse proxy added them; a bare self-host had
  neither. An edge proxy's own values still take precedence.

## [1.6.0] - 2026-09-02

No breaking changes, no migration. `kitloan:upgrade` still re-seeds (adds the `show_developer_link`
setting on first run) and re-stamps the version.

### Added

- **The booking wizard previews auto-allocated units.** In "request a quantity" mode the resource grid now
  highlights the first N available units — the same ones submission will actually grab — and a header line
  reads "Auto-selecting N of M", turning amber when the pool can't cover the request. Previously the grid
  kept showing a stale hand-selection, so raising the number from 4 to 5 still looked like only 4 were
  chosen. Switching back from "pick specific items" carries the selected count into the quantity field
  instead of resetting to 1.
- **An "IT officer" icon.** A headset/support glyph, selectable in the Resource Pool icon list and applied
  automatically when a pool's kind is set to "IT staff". It also fronts the `officer` tag in the Users list
  and the IT-officer section of My Profile.
- **A "Built with Kitloan" footer link** back to the open-source project, shown by default and switchable
  under Administration → Settings → About this instance (seed default `KITLOAN_SHOW_DEVELOPER_LINK`).

### Changed

- **Click-to-edit rows on the rest of the admin tables.** Resource Pools, Locations and Periods rows now
  highlight on hover and open the edit modal on a click anywhere in the row (the per-row buttons still work
  on their own); audit-log entries tied to a booking became navigable links to that booking. The bookings
  lists were already clickable rows.
- **The Administration tab bar is the same width on every page.** Settings and Emails previously constrained
  the whole page — nav included — to a narrow column, so the tab bar jumped width between tabs. The
  constraint now sits below the nav.

## [1.5.1] - 2026-09-02

No breaking changes, no migration. `kitloan:upgrade` still re-seeds and re-stamps the version.

### Added

- **Set "bookable as an IT officer" from Administration → Users.** The Edit User modal now carries the
  toggle (shown when the role is IT Operator or Administrator), so an admin can make an IT officer bookable
  without that person doing it from their own **My Profile** — previously the only route, and impossible for
  an officer who is themselves an administrator. The change is audited (`users.officer_availability`) and the
  user list shows an `officer` tag beside the role.

### Changed

- **The Users list rows are clickable.** Hovering a row highlights it and a click anywhere opens the edit
  modal; the per-row action buttons still do their own thing. Previously only the small "Edit" link worked.

### Fixed

- **Demoting an IT officer to plain User now clears their "bookable" flag** (fail closed) and disables their
  officer resource rows, so they drop out of availability for new bookings. In-flight bookings keep their
  allocation, and the assigned officer can still action the approve/reject link routed to them.

## [1.5.0] - 2026-09-02

No breaking changes. One additive migration (`2026_01_08_000000_staff_bookings`): `resources.user_id`,
`resource_pools.kind` / `resource_pools.approval_route`, `bookings.helpdesk_url`, `users.bookable_as_officer`
— all nullable / defaulted, and `down()` drops each. `kitloan:upgrade` runs it and re-seeds the message
templates, so the new email keys (`booking.officer_assigned`, `booking.officer_updated`) backfill on upgrade.

### Added

- **Book an IT officer as a resource.** A new pool **kind**, "IT staff", whose bookable units are people
  rather than equipment. An IT officer or administrator opts in from the new self-service **My Profile**
  page ("Make me bookable as an IT officer"); any staff member can then book them for a time, room and
  issue ("Teams support", "exam room IT help", …) with no kit attached. Conflict detection, buffers,
  approval, the `.ics` invite, amend, reassign/substitute, the daily summary and every IT view work exactly
  as they do for equipment.
  - **Approvals per pool.** "Approvals go to" is either **the IT team** (default, unchanged — the
    `it_notification_address`) or **the assigned officer** (the booked officer gets the approve/reject
    email; a `user`-role officer can action it). The booked officer always gets an FYI with the calendar
    invite, and every officer booking is visible to all IT operators so a colleague can be swapped in if
    someone's on leave.
- **Helpdesk ticket link on a booking.** An optional URL field in the booking wizard and amend screen, and
  a quick inline "add / edit helpdesk link" on the booking detail page that the requestor, IT, **and** the
  booked officer can edit after the fact. It renders as a clickable link on the detail page, the public
  view, the notification / approval / amendment emails and the daily summary. New template tokens
  `{{ officer }}` and `{{ helpdesk_url }}`.
- **My Profile page** (`/profile`, linked from your name in the nav). Read-only account details, the
  "email me the daily booking summary" opt-out (previously admin-only), and the IT-officer opt-in.

### Fixed

- **Administration → Settings** rendered narrower than the sibling admin pages; it now matches
  (`max-w-3xl`).

## [1.4.0] - 2026-08-27

No breaking changes. One additive migration (`bookings.room_choice`, defaults to `room`); its `down()` works.
`kitloan:upgrade` runs it and re-seeds the message templates, so the new email keys backfill on upgrade.

### Fixed

- **A booking that auto-approved still emailed IT an "Approval needed" request** (with approve/reject links).
  IT now gets a short "auto-approved — no action needed" notice instead (template `booking.it_confirmed`;
  turn that template off in Administration → Emails to stop the notices entirely). A booking that genuinely
  needs review is unchanged.
- **IT emails carried no calendar invite.** The approval-request, amendment and new confirmed-notice emails
  to IT now all attach `booking.ics`. The approval-request event is marked `TENTATIVE` until the booking is
  approved.

### Added

- **Room / Pick-up / Other.** When a resource pool requires a room, the booking wizard and amend screen now
  offer three choices: pick a **Room**, **Pick-up from IT** (the requestor collects the kit; no room), or
  **Other** (room decided elsewhere / not known yet — put it in Notes). Pick-up and Other satisfy the room
  requirement without a location. Every screen, email and calendar file shows "Pick-up from IT" / "Location
  TBC" instead of a blank room.
- **Reassign a booking to another staff member.** IT/administrators get a "Reassign to another staff member"
  control on the booking detail page (and the existing amend-screen "Requestor" picker now notifies too).
  The new requestor is emailed "assigned to you", the previous requestor "no longer assigned to you", and
  the change is audited (`booking.reassigned`). New templates `booking.owner_reassigned_to` /
  `booking.owner_reassigned_away`.

## [1.3.2] - 2026-08-27

### Fixed

- Embedded mode was "sticky": once a session had loaded the app with `?embed=1`, the navigation chrome stayed
  hidden in that browser even when the site was later opened directly in a normal tab. `FrameEmbedding` now
  clears the `embedded` session flag on a genuine top-level document navigation (`Sec-Fetch-Dest: document`,
  no `?embed`), while leaving it intact for framed navigation, `wire:navigate` fetches, and the OIDC
  round-trip. Browsers that don't send `Sec-Fetch-Dest` keep the previous behaviour.

## [1.3.1] - 2026-08-27

### Added

- **Administration → Settings** now opens with an "About this instance" panel: the running Kitloan version,
  the version of the last completed `kitloan:upgrade` (with a nudge if the two differ), and a link to the
  changelog. The version also remains in the Administration tab bar.

### Fixed

- The README upgrade example checked out `v1.1.0`; bumped to `v1.3.0` so a copy-paste lands on the current
  release.

## [1.3.0] - 2026-08-26

No breaking changes. One additive table (`message_templates`); its migration has a working `down()`. Upgrade
with the normal `kitloan:upgrade` flow — it also runs the new `MessageTemplateSeeder` to backfill default
email copy.

### Added

- **Editable email templates** (Administration → Emails). Per notification — booking submitted / confirmed /
  declined / reminder / amended, and the IT approval + amendment + daily-summary emails — the subject line and
  opening paragraph are now editable, with `{{ reference }}`, `{{ date }}`, `{{ room }}`, `{{ requestor_name }}`
  and similar placeholders substituted per booking (never evaluated as code). A shared **policy notice** block
  is appended to every requestor email — the place for "all equipment must be returned to IT unless collection
  is arranged in advance". Each template has "Reset to default". Included in the configuration export/import.
- **Reporting** (Administration → Reports; visible to administrators and IT operators). Date range + pool
  filter, with: booking volume by month, utilisation per pool (resource-days booked vs. capacity-days),
  busiest days, top requestors, top rooms, and approval stats (auto vs. manual vs. rejected, rejection rate,
  average hours to approval). CSV export of the underlying bookings.
- **Book on behalf of another user.** IT operators and administrators get a "Requestor" picker on the booking
  wizard and the amend screen; the booking is recorded as that person's while `created_by` stays the real
  actor (audit: "… created BK-123 on behalf of {name}" / "… reassigned to {name}"). A normal user cannot set
  it — the server ignores the field for anyone without approval authority.
- **Audit log: clear + retention.** A "Clear log…" action purges entries older than 30/90/180/365 days or all
  (recorded as an `audit.cleared` entry), an event-type filter, and actor/IP shown per row. New
  `audit:prune` command + nightly schedule deletes entries older than the **Audit-log retention (months)**
  setting (Administration → Settings → Housekeeping; 0 = keep forever, the default).
- **Site logo actually appears.** An uploaded logo now renders in the top navigation and on the login page
  (previously it was stored but never shown), with the built-in mark as the fallback. Settings gains a
  "Remove logo" button and recommended-size guidance.
- **Delete individual resources** on a resource pool (soft; refused while allocated to an upcoming booking).

### Changed

- The "Import from Snipe-IT" button on a resource pool is now always shown — disabled, with a "Set up…" link,
  when the Snipe-IT integration env (`SNIPEIT_ENABLED` / `SNIPEIT_URL` / `SNIPEIT_API_TOKEN`) is not
  configured — instead of vanishing. The integration page spells out those variables when it's off.

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
