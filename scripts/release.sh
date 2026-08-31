#!/usr/bin/env bash
#
# Publish a GitHub Release for a tag, with notes assembled from:
#   1. the matching CHANGELOG.md section, and
#   2. a standard, copy-pasteable "how to upgrade to this version" block.
#
# Prerequisites: the tag already exists and is pushed (see CONTRIBUTING.md
# "Versioning and releasing"), and `gh` is authenticated with `repo` scope.
#
# Usage:
#   scripts/release.sh v1.4.0            # publish the release
#   scripts/release.sh v1.4.0 --draft   # create it as a draft to review first
#
set -euo pipefail

TAG="${1:-}"
shift || true
if [[ -z "$TAG" || ! "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "usage: $0 vX.Y.Z [--draft]" >&2
    exit 1
fi
VERSION="${TAG#v}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CHANGELOG="$REPO_ROOT/CHANGELOG.md"

# --- 1. pull this version's section out of the changelog ---------------------
SECTION="$(awk -v ver="## [$VERSION]" '
    index($0, ver) == 1 { grab = 1; next }
    grab && /^## \[/     { exit }
    grab                 { print }
' "$CHANGELOG")"

if [[ -z "${SECTION// /}" ]]; then
    echo "No '## [$VERSION]' section found in CHANGELOG.md — add one before releasing." >&2
    exit 1
fi

# --- 2. build the release notes -------------------------------------------------
NOTES="$(cat <<EOF
$SECTION

## Upgrading to $TAG

\`\`\`bash
git fetch origin --prune --tags --force
git checkout $TAG
docker compose build
docker compose run --rm migrate      # runs: php artisan kitloan:upgrade
docker compose up -d
curl -s https://<your-host>/health | jq .version   # -> $VERSION
\`\`\`

\`docker compose run --rm migrate\` runs \`kitloan:upgrade\`: migrations, role/settings/
email-template backfill, compiled-cache clear, queue restart, and it records the
installed version. It is idempotent and refuses to run on an instance too old to
upgrade directly.

Full procedure, per-release notes and rollback: [docs/UPGRADING.md](https://github.com/nicksweb/kitloan-resource-booking/blob/main/docs/UPGRADING.md).
EOF
)"

# --- 3. publish --------------------------------------------------------------
PREV_TAG="$(git -C "$REPO_ROOT" describe --tags --abbrev=0 "$TAG^" 2>/dev/null || true)"
echo "Publishing release $TAG (previous: ${PREV_TAG:-none})"

gh release create "$TAG" \
    --repo nicksweb/kitloan-resource-booking \
    --title "$TAG" \
    --notes "$NOTES" \
    "$@"
