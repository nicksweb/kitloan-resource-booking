# Contributing to Kitloan

This project doesn't currently take outside pull requests, but the same workflow applies whether the next
change comes from a collaborator or an AI pairing session — the goal is to keep every deployed instance
upgradeable without surprises, per the guarantees in
[README.md § How releases stay upgrade-safe](README.md#how-releases-stay-upgrade-safe).

## Branching

Work straight on `main` for anything additive and reversible — most features and bug fixes fall here, and
that's how this project has been built so far. Cut a short-lived branch instead when a change is:

- **Schema-destructive** (renaming or dropping a column/table a released version depends on) — branch so you
  can develop and fully test the "expand" half in isolation, without a half-finished destructive migration
  sitting on `main` in the meantime.
- **Large enough that you'd want a clean way to bail out** — e.g. a rewrite of a core service — where the
  option to abandon the branch entirely beats reverting a string of commits on `main`.

Merge back to `main` only after the [pre-deploy checklist](#pre-deploy-checklist) below passes. Don't leave
long-lived branches around — `main` should always reflect what's actually deployed, or be one checklist away
from it.

## Pre-deploy checklist

This is the actual sequence used for every change in this project so far. Skipping steps is how the
`bookings.reference` incident happened (see [README.md](README.md#how-releases-stay-upgrade-safe)) — the
checklist exists to make that structurally hard to repeat.

1. **Write or extend tests** alongside the change, not as a separate later phase — every feature and bug fix
   in this codebase has feature-test coverage.
2. **Run the full suite** in a throwaway container against the actual application code, not just an editor's
   syntax check:
   ```bash
   docker run --rm -v "$(pwd)/app:/var/www/html" -w /var/www/html --entrypoint php \
     resource-booking-app:latest artisan test
   ```
   This needs a full `composer install` (not the image's `--no-dev` vendor) in `app/vendor` first — see
   [README.md § Running the test suite](README.md#running-the-test-suite).
3. **Rebuild the images**: `docker compose build app webserver`.
4. **Run migrations as their own step**, before touching the running containers, so a bad migration fails
   loudly instead of leaving `app`/`queue`/`scheduler` serving traffic against a schema they don't expect:
   ```bash
   docker compose up -d migrate
   docker logs resource-booking-migrate --tail 30
   ```
5. **Redeploy**: `docker compose up -d app webserver queue scheduler`.
6. **Verify**: `curl -s https://<host>/health` should report every check healthy, then exercise the actual
   changed feature in a real browser. Passing tests confirm the logic is correct, not that it works end-to-end
   through Livewire/Alpine in a browser — see the `$wire`-called-from-a-plain-`onchange` bug for exactly why
   this step earns its place.
7. **Commit, tag, changelog** — see below.

## Versioning and releasing

- Follow [Semantic Versioning](https://semver.org/): a release doing the "contract" half of an expand/contract
  schema change (see [README.md](README.md#how-releases-stay-upgrade-safe)) bumps the major version; anything
  else purely additive is a minor bump; pure bug fixes with no new capability are a patch bump.
- **Bump the version number** in both `VERSION` and `app/VERSION` (keep them identical — `config/version.php`
  reads `app/VERSION`, which is what ships in the image). If this release does a "contract" half, also bump
  `min_upgrade_from` in `app/config/version.php` to the release that shipped the matching "expand" half.
- Every release gets a [CHANGELOG.md](CHANGELOG.md) entry (Added/Changed/Fixed/Breaking) *before* tagging —
  the changelog is what an operator reads to decide whether an upgrade needs extra care, so it can't be an
  afterthought written after the fact. Keep the top heading's version in sync with `VERSION`.
- Tag every release (`git tag -a vX.Y.Z -m "..."`) and push the tag along with `main`. Deployed instances track
  tags, not `main` — see [README.md § Updating an existing instance](README.md#updating-an-existing-instance).
- **Publish a GitHub Release** for the tag:
  ```bash
  scripts/release.sh vX.Y.Z          # add --draft to review it on GitHub first
  ```
  It assembles the notes from that version's `CHANGELOG.md` section plus a standard, copy-pasteable
  "Upgrading to vX.Y.Z" command block, so anyone tracking the repo gets both what changed *and* exactly how to
  update. Needs `gh` authenticated with `repo` scope.
- Deployed instances finish an upgrade with `php artisan kitloan:upgrade` (the Compose `migrate` service runs
  it). It already clears compiled views on every run, so the "`view:clear` on deploy" habit is now enforced by
  the tooling rather than left to memory.

## Schema changes

Covered in full in [README.md § How releases stay upgrade-safe](README.md#how-releases-stay-upgrade-safe) —
read it before writing a migration. In short:

- Migrations are additive within a release; a destructive change splits across two releases (expand in one,
  contract in the next, once instances have had a chance to upgrade).
- Every migration needs a working `down()`.
- **Never edit a migration file that's already run against a real deployment.** Laravel only runs each
  migration once — editing an already-applied file's contents changes nothing on a live database, silently.
  Add a new migration to alter a column that's already shipped.
