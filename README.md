# Kitloan — Resource Booking

A self-contained, Docker-deployable IT resource booking system for schools — built for ~20 Exam Laptops
initially, but modelled generically around **Resource Pools** so Loan Laptops, Chargers, Monitors, Cameras
and other equipment can be added later without code changes.

Stack: Laravel 13 / PHP 8.4, PostgreSQL (MySQL/MariaDB also supported), Livewire 4 + Alpine.js, Tailwind CSS 4,
Nginx + PHP-FPM, all behind your existing reverse proxy.

See [docs/BRIEF.md](docs/BRIEF.md) for the original design brief and the reasoning behind the data model, and
[CHANGELOG.md](CHANGELOG.md) for release history.

## Contents

- [Quick start](#quick-start)
- [Architecture](#architecture)
- [Initial administrator access](#initial-administrator-access)
- [Emergency local admin login](#emergency-local-admin-login)
- [Admin impersonation](#admin-impersonation)
- [OIDC configuration](#oidc-configuration)
- [Database configuration](#database-configuration)
- [SMTP / notifications configuration](#smtp--notifications-configuration)
- [Snipe-IT integration](#snipe-it-integration)
- [Reverse proxy](#reverse-proxy)
- [Backups](#backups)
- [Updating an existing instance](#updating-an-existing-instance)
- [Running the test suite](#running-the-test-suite)
- [What's implemented vs. simplified](#whats-implemented-vs-simplified)
- [Configuration checklist](#configuration-checklist)

## Quick start

Requirements: Docker with the Compose plugin, and two pre-existing external Docker networks (`frontend` and
`backend`) that this stack attaches to — see [Reverse proxy](#reverse-proxy). If you don't already have them
from another stack:

```bash
docker network create frontend
docker network create backend
```

Then:

```bash
git clone <this-repo-url> kitloan
cd kitloan
cp .env.example .env
```

Edit `.env` and fill in at least: `APP_URL`, `DB_PASSWORD`, mail settings, and the `OIDC_*` values (see
[OIDC configuration](#oidc-configuration)). Then generate the application key — Laravel uses this for
encryption and signed URLs, and the app will not run without it:

```bash
docker compose run --rm app php artisan key:generate
```

Bring the stack up and seed the initial administrator(s) named in `ADMIN_SEED_EMAILS`:

```bash
docker compose up -d --build
docker compose run --rm migrate php artisan db:seed --force
```

`docker compose up` brings up:

| Service     | Role                                                             |
|-------------|-------------------------------------------------------------------|
| `migrate`   | One-off: runs `php artisan migrate --force`, then exits           |
| `db`        | PostgreSQL 16                                                    |
| `app`       | PHP-FPM (the application)                                        |
| `webserver` | Nginx — the only container your reverse proxy should point at    |
| `queue`     | `php artisan queue:work` — emails, ICS, Snipe-IT sync             |
| `scheduler` | `php artisan schedule:work` — periodic Snipe-IT sync + reminders  |

No host port is published by default — `webserver` joins the `frontend` and `backend` external Docker
networks, so your reverse proxy reaches it there instead. See [Reverse proxy](#reverse-proxy).

`db`, `app`, `queue`, `scheduler` and `migrate` all join `backend` only. `db` is *not* published on any network
other than `backend` — nothing outside this stack can reach it directly.

## Architecture

The full domain model is in `app/database/migrations/`. In short:

- **Resource Pools** (`resource_pools`) are the top-level concept — "Exam Laptops", "Chargers", etc. Each pool
  is either `allocation_mode = individual` (discrete tracked items, e.g. Laptop 01..20) or `quantity` (a plain
  count, e.g. 30 chargers with no per-unit identity).
- **Resources** (`resources`) are individual items within an `individual`-mode pool, each with a `source` of
  `manual` or `snipeit`. Snipe-IT-sourced resources carry an `external_asset_links` row with a local snapshot
  of the Snipe-IT record (asset tag, serial, model, status) so the app never depends on Snipe-IT being up.
- **Bookings** (`bookings`) reference a primary resource pool plus one-or-more **Booking Items**
  (`booking_items`) — this is what lets a single booking span multiple resource types (e.g. 6 laptops + 6
  chargers). Individual-mode items get **Booking Resource Allocations** (`booking_resource_allocations`)
  tying them to specific `resources` rows; quantity-mode items are just a count.
- Conflict detection (`app/Services/Booking/AvailabilityService.php`) treats every booking's *buffered* range
  — `[start - preparation_buffer, end + return_buffer]` — as busy, and checks buffered-range overlap. Buffers
  are per-resource-pool. Allocation happens inside a single DB transaction with row locks
  (`lockForUpdate()`), re-checked immediately before commit, so two concurrent submissions can't both win the
  same resource — see `app/Services/Booking/BookingService.php`.
- Approval logic (`app/Services/Booking/ApprovalEvaluator.php`) separates **hard constraints** (lead time
  minimum, weekends/out-of-hours not permitted at all for this pool — these block submission outright) from
  **soft constraints** (lead time below the configured auto-approval threshold, weekend/out-of-hours needing
  sign-off, booking type always requiring approval, a configurable "more than N resources" rule) — soft
  constraints send the booking to `Pending Approval` rather than blocking it.
- All of this is unit/feature tested — see [Running the test suite](#running-the-test-suite).

## Initial administrator access

Administrator accounts are pre-seeded from `ADMIN_SEED_EMAILS` in `.env` (comma-separated) by
`database/seeders/AdminUserSeeder.php`, run via:

```bash
docker compose run --rm migrate php artisan db:seed --force
```

This is safe to re-run — it only fills in what's missing (`firstOrCreate`), it never overwrites a role you've
since changed via the UI. The seeded accounts have placeholder display names (the email's local-part,
uppercased) since real names weren't available at seed time — update them under **Administration → Users**
once real names are known.

There's no password login: the seeded account becomes active the first time that person signs in via OIDC —
see [Local user records / account matching](#oidc-configuration) below for exactly how that linking works.

To add further administrators later (once someone can already reach the admin panel), use
**Administration → Users → Add User**, set role to *Administrator*, and they'll be linked on their next OIDC
login.

If you need to seed an admin from the CLI without redeploying:

```bash
docker compose exec app php artisan tinker
>>> $u = App\Models\User::firstOrCreate(['email' => 'someone@example.edu.au'], ['name' => 'Someone', 'enabled' => true]);
>>> $u->assignRole('administrator');
```

## Emergency local admin login

A break-glass sign-in that bypasses OIDC entirely, for when the identity provider is down or misconfigured.
Off by default; controlled by `LOCAL_LOGIN_ENABLED` in `.env` — the feature flag alone doesn't create any
usable credential though.

To actually enable it for a specific administrator account:

```bash
docker compose exec app php artisan admin:set-local-password admin@example.edu.au
```

This only works for an existing, enabled account that already holds the `administrator` role — it refuses
otherwise rather than offering to create or promote one, so it can't be used to escalate privilege. There's no
web-based "set your password" flow; the only way to set or change this password is that CLI command, run by
someone with `docker exec` access to the host.

When the flag is on, a small "Administrator emergency sign-in" link appears at the bottom of the normal login
page, going to `/auth/local`. Login there is rate-limited (5 attempts/minute per email+IP, backed off for 60s
after that) and every attempt — success or failure — is written to the audit log
(`auth.local_login_succeeded` / `auth.local_login_failed`).

Consider turning `LOCAL_LOGIN_ENABLED` back off once OIDC is confirmed stable, to keep the attack surface to a
minimum — it's a fallback, not meant to be the everyday admin login path.

## Admin impersonation

Administrators can "become" another (non-administrator) user — useful for booking on behalf of a teacher who
called in, or checking exactly what a particular account can see. From **Administration → Users**, click
**Impersonate** on any enabled, non-administrator row.

While impersonating:

- The session is genuinely authenticated as that user — their permissions, their view, not the admin's. A
  persistent amber banner across every page names both the impersonated user and the impersonating admin, with
  a one-click **Return to my account**.
- Bookings created during impersonation are correctly attributed both ways: `booked_by_user_id` is the
  impersonated user (matches the brief's "Booking Owner" concept), while `created_by_user_id` records the real
  admin — so the audit trail and notifications don't lose track of who actually did the work.
- Administrators cannot be impersonated (there's no privilege to gain, and it would blur who actually
  performed subsequent actions in the audit log).

Both the start and stop of every impersonation session are audited (`auth.impersonation_started` /
`auth.impersonation_stopped`), independent of whatever the impersonated session goes on to do.

## OIDC configuration

Authentication is generic OpenID Connect (`app/Services/Oidc/`) — it fetches
`{OIDC_ISSUER}/.well-known/openid-configuration` at runtime rather than hard-coding provider-specific
endpoints, so any standards-compliant IdP works, Entra ID included.

Relevant `.env` keys:

```env
OIDC_ENABLED=true
OIDC_ISSUER=https://login.microsoftonline.com/{tenant-id}/v2.0
OIDC_CLIENT_ID=...
OIDC_CLIENT_SECRET=...
OIDC_REDIRECT_URI=${APP_URL}/auth/callback
OIDC_ALLOWED_DOMAINS=example.edu.au
```

`OIDC_ISSUER` above is an example for Entra ID — any standards-compliant OIDC issuer URL works.

**`OIDC_REDIRECT_URI` must exactly match the redirect URI registered against this client ID on the identity
provider.** If the OIDC client registration expects a different callback host than your `APP_URL`, login will
fail with an `invalid redirect_uri` error from the IdP, not from this app. Confirm the registered redirect URI
matches before going live.

**Account matching** (`app/Services/Auth/UserProvisioningService.php`), in order:

1. Match by the OIDC `sub` claim (immutable) if a local account already has it recorded.
2. Otherwise, match by email — but **only** if that local account has no `oidc_subject` yet (i.e. it was
   pre-created by an admin and never logged in). It gets linked on this login.
3. If an account's email matches but it *already* has a different `oidc_subject` recorded, the login is
   **rejected**, not silently taken over, and an `auth.identity_collision` audit event is recorded.
4. If nothing matches and the email's domain is in `OIDC_ALLOWED_DOMAINS`, a new account is auto-provisioned
   with the least-privilege `user` role.

Identity is established via the IdP's **userinfo endpoint** (a direct, TLS, confidential-client HTTPS call
using the just-issued access token) rather than by locally verifying the `id_token`'s JWT signature — see the
docblock on `OidcClient` for the reasoning; this is a deliberate simplification to avoid a JWKS-verification
dependency, given the authorization code itself was only exchangeable by this confidential client over TLS.

## Database configuration

```env
DB_CONNECTION=pgsql        # or mysql
DB_HOST=resource-booking-db
DB_PORT=5432                # 3306 for MySQL/MariaDB
DB_DATABASE=resource_booking
DB_USERNAME=resource_booking
DB_PASSWORD=...
```

**`DB_HOST` must be the database container's `container_name` (`resource-booking-db`), not the bare Compose
service name `db`.** This host runs many other stacks on the same shared `backend` network, several of which
also name their own database service `db` — on a shared external Docker network, Docker's embedded DNS can
resolve a generic service-name alias to *any* container using that name, not necessarily yours. This bit us
during setup (the app connected to a different stack's Postgres container entirely, which of course rejected
our credentials) — using the unique `container_name` avoids the collision. The same reasoning is why Nginx's
`fastcgi_pass` in `docker/nginx/default.conf` points at `resource-booking-app`, not `app`.

To use MySQL/MariaDB instead of Postgres: change `DB_CONNECTION=mysql`, `DB_PORT=3306`, swap the `db` service
in `docker-compose.yml` for a `mysql:8` or `mariadb` image (env vars `MYSQL_DATABASE` / `MYSQL_USER` /
`MYSQL_PASSWORD` / `MYSQL_ROOT_PASSWORD` instead of the `POSTGRES_*` ones), and re-run migrations. All
migrations use database-agnostic Laravel schema builder calls — no Postgres-specific types were used.

## SMTP / notifications configuration

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.edu.au
MAIL_PORT=25
MAIL_FROM_ADDRESS=admin@example.edu.au
IT_NOTIFICATION_ADDRESS=it@example.edu.au
HELPDESK_REPLY_TO_ADDRESS=helpdesk@example.edu.au
```

`IT_NOTIFICATION_ADDRESS` and `HELPDESK_REPLY_TO_ADDRESS` are editable without a redeploy under
**Administration → Settings → Notifications** once the app is running; the `.env` values above only seed the
initial settings. `MAIL_FROM_ADDRESS` itself (and SMTP host/port/auth) stays in `.env` since it's effectively
a credential, not a display setting.

Email is queued (`app/Mail/BookingNotificationMail.php`, `BookingApprovalRequestMail.php`) — a slow or down
mail server delays notifications, it never blocks or fails a booking. Failed sends are logged, not silently
dropped; check `docker compose logs queue` and Laravel's `failed_jobs` table.

Approve/reject links in the IT notification email use Laravel signed URLs (`URL::temporarySignedRoute`,
7-day expiry) — the signature proves the link wasn't tampered with, but the visitor must **also** be
authenticated as IT/Administrator for anything to happen (see `BookingApprovalController`), so a forwarded or
leaked link alone isn't enough.

## Snipe-IT integration

```env
SNIPEIT_ENABLED=true
SNIPEIT_URL=https://assets.example.edu.au
SNIPEIT_API_TOKEN=...
```

Go to **Administration → Resource Pools → (a pool) → Import from Snipe-IT** to search and select specific
assets to link — the app never bulk-imports the whole Snipe-IT inventory. Linked assets get an
`external_asset_links` row (asset tag, serial, model, status, location) that's refreshed every 30 minutes by
the scheduled `snipeit:sync` command (`app/Console/Commands/SyncSnipeItAssets.php`), or on demand from
**Administration → Integrations → Snipe-IT → Synchronise Now**.

Sync only ever touches the `external_*` columns — never `resource_pool_id`, resource status, notes, or
anything booking-related. If an asset disappears from Snipe-IT, the link is flagged (`missing_since`) rather
than deleted, and the resource/its booking history is left untouched; an admin has to explicitly deal with it
(retire the resource, or move the booking to another asset via **Substitute** on the booking detail page).
Duplicate imports of the same Snipe-IT asset are prevented by a DB-level unique constraint on
`(external_source, external_id)`.

If Snipe-IT is unreachable, the booking system keeps working entirely off the last-known local snapshot — see
`app/Services/SnipeIt/SnipeItSyncService.php` and its test coverage in
`tests/Feature/SnipeItIntegrationTest.php`. `/health` never fails because of Snipe-IT being down; integration
status is reported separately at **Administration → Integrations → Snipe-IT**.

## Reverse proxy

The `webserver` container intentionally publishes no host port — it joins the external `frontend` Docker
network so your existing reverse proxy (Nginx Proxy Manager, Traefik, Caddy, etc.) can reach it there. Point a
proxy host at `resource-booking-webserver:80` on the `frontend` network, with a TLS cert covering whatever
hostname you set `APP_URL` to.

The app trusts the proxy for `X-Forwarded-*` headers (`bootstrap/app.php` → `trustProxies(at: '*')`) — safe
here because the app container publishes no host port and is only reachable via the internal Docker networks,
so there's no path for a client to spoof those headers directly.

## Backups

Three things need backing up:

1. **Database** — `docker compose exec db pg_dump -U resource_booking resource_booking | gzip > backup-$(date +%F).sql.gz`
2. **Uploaded files** (logos, anything on the `public` disk) — the `public_uploads` named volume:
   `docker run --rm -v resource-booking_public_uploads:/data -v $PWD:/backup alpine tar czf /backup/uploads-$(date +%F).tar.gz -C /data .`
3. **`.env`** — contains secrets; back it up somewhere separate from the repo (it's git-ignored on purpose).

The `app_storage` volume (logs, framework cache) does *not* need backing up — it's fully disposable.

Restore: recreate the volumes, `gunzip | psql` the database dump back in, untar the uploads volume, then
`docker compose up -d`.

## Updating an existing instance

Releases are tagged (`v1.0.0`, `v1.1.0`, ...) and every release is listed in [CHANGELOG.md](CHANGELOG.md),
including any breaking changes. Track tags rather than `main` for a running deployment — `main` can contain
in-progress work.

1. **Read the changelog** between your current version and the target tag, specifically for anything under
   "Breaking" — that tells you if this upgrade needs extra care beyond the steps below.
2. **Back up first** — see [Backups](#backups). Migrations are one-directional; have a database dump you can
   restore from before running new ones.
3. **Pull the target release**:
   ```bash
   git fetch --tags
   git checkout v1.1.0   # replace with the version you're upgrading to
   ```
4. **Diff your `.env` against the latest `.env.example`** for any new or renamed variables:
   ```bash
   diff .env.example .env
   ```
   New releases may add settings (they'll have sensible defaults documented in `.env.example`, but review
   them rather than assuming). Never copy `.env.example` over your `.env` wholesale — that would wipe your
   real secrets.
5. **Rebuild and migrate**:
   ```bash
   docker compose build
   docker compose run --rm migrate   # runs any new migrations
   docker compose up -d
   ```
   If a migration fails, the `migrate` service exits non-zero and `app`/`queue`/`scheduler` never start
   (`depends_on: condition: service_completed_successfully` in `docker-compose.yml`) — you get a stopped
   stack to investigate, not a half-upgraded one serving traffic against a schema it doesn't expect.
6. **Check the logs** after restart:
   ```bash
   docker compose logs -f app queue scheduler
   ```

Containers are stateless and disposable — all persistent state lives in the `db_data`, `app_storage` and
`public_uploads` named volumes, so recreating any container (including `app`/`webserver`) never loses data.
If something goes wrong, `git checkout <previous-tag>`, rebuild, and restore the database backup from step 2
— do not attempt to roll back by editing already-applied migrations (see below).

### How releases stay upgrade-safe

- **Migrations are additive within a release.** A destructive schema change (renaming or dropping a column
  a released version depends on) is split across two releases using expand/contract: release *N* adds the
  new column and starts writing to it alongside the old one; release *N+1* (after everyone's had a chance to
  upgrade) drops the old column. Any release doing the "contract" half is called out under "Breaking" in the
  changelog.
- **Every migration ships a working `down()`.** This doesn't make upgrades reversible in the general case
  (a `down()` can't always recover already-transformed data), but it means a failed mid-release migration
  batch can be unwound cleanly rather than left half-applied.
- **`ADMIN_SEED_EMAILS` seeding is idempotent** (`firstOrCreate`) — re-running `db:seed` on an upgrade never
  overwrites a role you've changed via the UI.

**Never edit a migration file that's already run against a real deployment.** Laravel tracks applied
migrations in the `migrations` table and only runs each one once — editing an already-applied file's contents
changes nothing on a live database, silently. (This bit us once already: `bookings.reference` was edited to
`->nullable()` after the original migration had already run here, so the live column stayed `NOT NULL` while
the application code assumed otherwise, causing every booking submission to fail — fixed via a proper new
`ALTER TABLE` migration rather than by re-editing the original.) Always add a new migration to alter a column
that's already shipped.

## Running the test suite

The production image is built with `composer install --no-dev`, so dev dependencies (PHPUnit, Faker, Mockery)
aren't in it. To run tests, install full dependencies and run against a throwaway copy:

```bash
cd app
docker run --rm -v "$PWD":/app -w /app composer:2 install
docker run --rm -v "$PWD":/app -w /app node:22-alpine sh -c "npm install && npm run build"
cd ..
docker compose up -d db
docker run --rm --network backend -v "$PWD/app":/var/www/html -w /var/www/html \
  -e DB_CONNECTION=sqlite --entrypoint /entrypoint.sh resource-booking-app:latest \
  php vendor/bin/phpunit
```

(Tests use an in-memory SQLite database regardless of `DB_CONNECTION` in `.env` — see `phpunit.xml`. Prefer
`vendor/bin/phpunit` directly over `php artisan test`; the latter's pretty-printer truncates output in a
narrow terminal and shows spurious "warnings" that aren't real failures.)

33 tests currently cover: unauthenticated/role-based access control, IDOR protection on booking detail pages,
double-booking prevention, preparation/return buffer enforcement, cancellation releasing resources, quantity
availability math, auto-approval lead-time boundaries, booking-type-forced approval, manual approve/reject
(including "reason required"), signed-link expiry and the IT-staff-only requirement on approval links,
Snipe-IT search/sync (including a simulated Snipe-IT outage and the duplicate-import constraint), and OIDC
identity matching/collision handling.

## What's implemented vs. simplified

Everything in the brief is implemented at a working level, with these deliberate simplifications given the
brief's own "avoid unnecessary complexity" and "not mandatory for initial implementation" notes:

- **Calendar views**: List view (all bookings, day-grouped, filterable) and the IT logistics day view are
  fully built; a full drag-and-drop Week/Month calendar grid was not — the list view covers the same
  information.
- **PDF export**: not built; the logistics view has a browser-print stylesheet instead, which the brief
  explicitly allows as sufficient for the initial release.
- **Reminders**: a 24-hour-before reminder email to the booking owner is implemented and scheduled hourly.
  An IT "beginning of day" digest email was not built — the IT Dashboard serves that purpose as a live view.
- **Embeddable widget** and **Microsoft Graph/Teams notification channels**: explicitly out of scope per the
  brief ("do not implement these future channels unless required").
- **Asset checkout/check-in sync with Snipe-IT** at collection/return time: explicitly deferred by the brief
  itself — the integration point exists (the `resources`/`external_asset_links` model) but nothing calls it.

## Configuration checklist

Before going live on a new instance, double-check:

- `APP_URL` and the OIDC redirect URI host match what's registered against your OIDC client ID — see
  [OIDC configuration](#oidc-configuration).
- `MAIL_FROM_ADDRESS`, `IT_NOTIFICATION_ADDRESS`, and `HELPDESK_REPLY_TO_ADDRESS` are addresses you actually
  control — see [SMTP / notifications configuration](#smtp--notifications-configuration).
- `ADMIN_SEED_EMAILS` lists the people who should land as Administrator on first login.
- `DB_HOST` is the database container's `container_name`, not the bare Compose service name, if you're
  running other stacks on the same shared Docker network — see [Database configuration](#database-configuration).
