# Upgrading a Kitloan instance

This is the exact, copy-pasteable procedure for upgrading a running deployment to a newer tagged release.
The worked example below is **1.1.0 → 1.2.0**, but the shape is the same for every release — substitute the
target tag, and read the [changelog](../CHANGELOG.md) entries between your version and the target for anything
that needs extra care (a "Breaking" heading, or the per-release notes just below).

Everything below runs on the host where the instance's `docker compose` stack lives, from the repository
checkout that stack was built from.

## Per-release notes

### → 1.3.0

No breaking changes; standard procedure. New this release: one additive table (`message_templates`), a new
`BOOKING_AUDIT_RETENTION_MONTHS` env var (optional, defaults to `0` = keep audit entries forever), and a new
nightly `audit:prune` job. `kitloan:upgrade` runs `MessageTemplateSeeder` for you, so the default email copy
is populated on upgrade. After upgrading, glance at **Administration → Emails** (edit the shared "policy
notice" if you want the "return to IT" wording) and **Administration → Reports**.

### → 1.2.0

See the full walk-through below. Adds `two_factor_*` / `locked_until` / `deleted_at` columns to `users` (all
additive) and the `kitloan:upgrade` command that the `migrate` service now runs. **Local-password admins are
forced through TOTP 2FA enrolment on their next sign-in** — brief them, and keep a second admin reachable.

---

## 0. Before you start

- **Know your current version.** `curl -s https://<host>/health | jq -r .version` on 1.2.0+, or check the
  `CHANGELOG.md` you deployed from. `kitloan:upgrade` refuses to run if the instance is older than the
  release's `min_upgrade_from` (for 1.2.0 that's `1.0.0`), so if you're on something very old, step through
  the intermediate tags.
- **Have a maintenance window.** The stack is briefly down between `docker compose up -d` completing the new
  `migrate` run and the `app`/`webserver` containers coming back. Typically under a minute.

---

## 1. Back up

Containers are disposable; the data is not. Take a database dump you can restore from — migrations are
one-directional.

```bash
# Postgres (default)
docker compose exec -T db pg_dump -U "$DB_USERNAME" "$DB_DATABASE" > backup-$(date +%F-%H%M).sql

# also snapshot the uploads/logo volume
docker run --rm -v resource-booking_public_uploads:/data -v "$PWD":/backup alpine \
  tar czf /backup/uploads-$(date +%F-%H%M).tgz -C /data .
```

Keep both files off the box.

---

## 2. Pull the target release

```bash
git fetch --tags
git checkout v1.2.0          # the tag you're upgrading to
```

Deployed instances track **tags**, not `main`.

---

## 3. Review environment changes

```bash
diff <(git show v1.1.0:.env.example) .env.example
```

For 1.2.0 the only additions are optional, with safe defaults:

| Variable | Default | Notes |
| --- | --- | --- |
| `BOOKING_EMBEDDING_ENABLED` | `false` | Seeds the initial value only; the live switch is Administration → Settings → Embedding. |
| `BOOKING_EMBEDDING_ALLOWED_ORIGINS` | empty | Parent origins allowed to iframe the app. |
| `SESSION_SAME_SITE` note | `lax` | No change needed. With embedding on, the app promotes the cookie to `SameSite=None; Secure` itself. |

You do **not** need to edit `.env` for this upgrade. Never copy `.env.example` over `.env`.

---

## 4. Rebuild and run the upgrade

```bash
docker compose build
docker compose run --rm migrate      # runs: php artisan kitloan:upgrade
docker compose up -d
```

`kitloan:upgrade` (the `migrate` service's command as of 1.2.0):

1. checks the instance is new enough to upgrade directly;
2. runs migrations (1.2.0 adds `two_factor_*`, `locked_until`, `deleted_at` columns to `users` — all
   additive, each with a working `down()`);
3. backfills new roles/settings rows (idempotent — never overwrites a value you changed in the UI);
4. clears **all** compiled caches — **views** (on the shared `app_storage` volume, so the app/queue/
   scheduler containers get it too), plus config / routes / events. This is what guarantees a stale compiled
   Blade template can't survive the upgrade (the 1.1.0 quick-fill-from-period bug);
5. restarts queue workers;
6. records `1.2.0` as the installed version.

If it fails it exits non-zero and prints the failing step. `app`, `queue` and `scheduler` will **not** start
(`depends_on: condition: service_completed_successfully`), so you get a stopped stack to investigate, not a
half-upgraded one serving traffic. Fix the cause and re-run — the command is safe to run again.

---

## 5. Verify

```bash
curl -s https://<host>/health | jq '{version, database}'
# => { "version": "1.2.0", "database": "healthy" }

docker compose logs --tail=50 app queue scheduler
```

Then in a browser:

- **Quick-fill from period** on the booking wizard actually fills the start/finish times (hard-reload the
  page first). This is the regression the compiled-view clear fixes.
- **Administration → Settings** shows an "Embedding" card and a "Configuration export / import" card, and the
  tab bar shows `Kitloan v1.2.0`.
- **Administration → Resource Pools / Locations / Booking Types / Users** each have a **Delete** action.
- If you use local (break-glass) admin login: sign out and back in via `/auth/local`. A password-holding
  admin is now sent to a **two-factor setup** screen — enrol with an authenticator app and save the recovery
  codes. (Pure-SSO admins see no change.)

---

## 6. Post-upgrade: 2FA for local admins

If any administrator has a local break-glass password set (Administration → Users → "Local login" =
Configured), that person **must** complete TOTP enrolment on their next sign-in — they can't use the app
until they do. Brief them, and make sure at least one other administrator can reach the panel in case
someone is mid-enrolment. An admin who is locked out can be reset by another admin from Administration →
Users → 2FA column → **Reset**.

---

## Rollback

If something is wrong and you need to revert:

```bash
git checkout v1.1.0
docker compose build
docker compose up -d
# restore the database from step 1
cat backup-YYYY-MM-DD-HHMM.sql | docker compose exec -T db psql -U "$DB_USERNAME" "$DB_DATABASE"
```

Do **not** roll back by editing already-applied migration files (see
[README § How releases stay upgrade-safe](../README.md#how-releases-stay-upgrade-safe)). The 1.2.0
migration's `down()` also works if you'd rather `docker compose run --rm migrate php artisan migrate:rollback
--step=1` before checking out the old tag — but restoring the dump is the safer default.
