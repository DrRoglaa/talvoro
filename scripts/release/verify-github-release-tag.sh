#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

require_command git
require_command gh
require_command python3

: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required.}"
: "${GITHUB_REF_NAME:?GITHUB_REF_NAME is required.}"
: "${GITHUB_SHA:?GITHUB_SHA is required.}"
: "${GH_TOKEN:?GH_TOKEN is required for GitHub tag verification.}"

version="$(read_version)"
tag="v$version"

[ "$GITHUB_REF_NAME" = "$tag" ] || release_die "Git tag '$GITHUB_REF_NAME' does not match VERSION '$version' (expected '$tag')."

head_sha="$(git rev-parse HEAD)"
[ "$head_sha" = "$GITHUB_SHA" ] || release_die "Checked-out commit $head_sha does not match GitHub event SHA $GITHUB_SHA."

if [ "$(git cat-file -t "refs/tags/$tag" 2>/dev/null || true)" != "tag" ]; then
  release_die "Official release tag '$tag' must be an annotated signed tag, not a lightweight tag."
fi

tag_commit="$(git rev-list -n 1 "$tag")"
[ "$tag_commit" = "$head_sha" ] || release_die "Tag '$tag' resolves to $tag_commit, but the workflow checked out $head_sha."

# The official release commit must already be part of stable main.
git fetch --no-tags origin main >/dev/null 2>&1
if ! git merge-base --is-ancestor "$head_sha" origin/main; then
  release_die "Release commit $head_sha is not reachable from origin/main. Merge the release into main before tagging it."
fi

ref_json="$(mktemp)"
tag_json="$(mktemp)"
trap 'rm -f -- "$ref_json" "$tag_json"' EXIT

gh api \
  -H 'Accept: application/vnd.github+json' \
  -H 'X-GitHub-Api-Version: 2022-11-28' \
  "/repos/$GITHUB_REPOSITORY/git/ref/tags/$tag" > "$ref_json"

tag_object_sha="$(python3 - "$ref_json" <<'PY'
import json
import sys

with open(sys.argv[1], encoding="utf-8") as handle:
    data = json.load(handle)
obj = data.get("object") or {}
if obj.get("type") != "tag":
    raise SystemExit("GitHub reports a lightweight/non-annotated tag; expected object.type=tag.")
sha = obj.get("sha")
if not isinstance(sha, str) or not sha:
    raise SystemExit("GitHub tag reference is missing its tag-object SHA.")
print(sha)
PY
)" || release_die "GitHub did not report '$tag' as an annotated tag."

gh api \
  -H 'Accept: application/vnd.github+json' \
  -H 'X-GitHub-Api-Version: 2022-11-28' \
  "/repos/$GITHUB_REPOSITORY/git/tags/$tag_object_sha" > "$tag_json"

python3 - "$tag_json" "$head_sha" "$tag" <<'PY'
import json
import sys

path, expected_commit, tag = sys.argv[1:4]
with open(path, encoding="utf-8") as handle:
    data = json.load(handle)

obj = data.get("object") or {}
verification = data.get("verification") or {}

if obj.get("type") != "commit":
    raise SystemExit(f"ERROR: GitHub tag {tag} does not point to a commit object.")
if obj.get("sha") != expected_commit:
    raise SystemExit(
        f"ERROR: GitHub tag {tag} points to {obj.get('sha')}, expected {expected_commit}."
    )
if verification.get("verified") is not True:
    reason = verification.get("reason") or "unknown"
    raise SystemExit(
        f"ERROR: GitHub does not consider tag {tag} cryptographically verified (reason: {reason})."
    )
PY

printf 'Verified official signed release tag %s at commit %s.\n' "$tag" "$head_sha"
