#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

require_command gh
require_command python3

: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required.}"
: "${GITHUB_REF_NAME:?GITHUB_REF_NAME is required.}"
: "${GH_TOKEN:?GH_TOKEN is required to publish the GitHub Release.}"

version="$(read_version)"
tag="v$version"
[ "$GITHUB_REF_NAME" = "$tag" ] || release_die "Release tag '$GITHUB_REF_NAME' does not match VERSION '$version'."

source_archive="$RELEASE_OUTPUT_DIR/$(archive_name_for source "$version")"
docker_archive="$RELEASE_OUTPUT_DIR/$(archive_name_for docker "$version")"
webhosting_archive="$RELEASE_OUTPUT_DIR/$(archive_name_for webhosting "$version")"
checksum_file="$RELEASE_OUTPUT_DIR/SHA256SUMS.txt"

assets=(
  "$source_archive"
  "$source_archive.sigstore.json"
  "$docker_archive"
  "$docker_archive.sigstore.json"
  "$webhosting_archive"
  "$webhosting_archive.sigstore.json"
  "$checksum_file"
  "$checksum_file.sigstore.json"
)
for asset in "${assets[@]}"; do
  [ -s "$asset" ] || release_die "Required release asset is missing or empty: $asset"
done

release_json="$(mktemp)"
asset_names="$(mktemp)"
expected_names="$(mktemp)"
trap 'rm -f -- "$release_json" "$asset_names" "$expected_names"' EXIT

if gh release view "$tag" --repo "$GITHUB_REPOSITORY" --json isDraft,url > "$release_json" 2>/dev/null; then
  is_draft="$(python3 - "$release_json" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as handle:
    data=json.load(handle)
print("true" if data.get("isDraft") else "false")
PY
)"
  [ "$is_draft" = "true" ] || release_die "GitHub Release '$tag' is already published. Refusing to mutate an existing published release."
  printf 'Reusing existing draft GitHub Release %s.\n' "$tag"
else
  printf 'Creating draft GitHub Release %s.\n' "$tag"
  gh release create "$tag" \
    --repo "$GITHUB_REPOSITORY" \
    --verify-tag \
    --draft \
    --title "Talvoro $version" \
    --generate-notes >/dev/null
fi

# Draft releases are intentionally mutable until the complete, verified asset set is present.
gh release upload "$tag" "${assets[@]}" \
  --repo "$GITHUB_REPOSITORY" \
  --clobber

printf '%s\n' \
  "$(archive_name_for source "$version")" \
  "$(archive_name_for source "$version").sigstore.json" \
  "$(archive_name_for docker "$version")" \
  "$(archive_name_for docker "$version").sigstore.json" \
  "$(archive_name_for webhosting "$version")" \
  "$(archive_name_for webhosting "$version").sigstore.json" \
  'SHA256SUMS.txt' \
  'SHA256SUMS.txt.sigstore.json' | LC_ALL=C sort > "$expected_names"

gh release view "$tag" \
  --repo "$GITHUB_REPOSITORY" \
  --json assets \
  --jq '.assets[].name' | LC_ALL=C sort > "$asset_names"

if ! cmp -s "$expected_names" "$asset_names"; then
  printf 'Expected GitHub Release assets:\n' >&2
  cat "$expected_names" >&2
  printf 'Actual GitHub Release assets:\n' >&2
  cat "$asset_names" >&2
  release_die "Draft GitHub Release asset set is incomplete or contains unexpected assets."
fi

printf 'All expected assets are present; publishing %s.\n' "$tag"
gh release edit "$tag" \
  --repo "$GITHUB_REPOSITORY" \
  --draft=false >/dev/null

release_url="$(gh release view "$tag" --repo "$GITHUB_REPOSITORY" --json url --jq '.url')"
[ -n "$release_url" ] || release_die "Published release URL could not be resolved."

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  printf 'release_url=%s\n' "$release_url" >> "$GITHUB_OUTPUT"
fi
printf 'Published GitHub Release: %s\n' "$release_url"
