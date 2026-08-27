#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

version="$(read_version)"
mkdir -p "$RELEASE_OUTPUT_DIR"
expected="$(archive_name_for source "$version") $(archive_name_for docker "$version") $(archive_name_for webhosting "$version")"

sha256_file() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1" | awk '{print $1}'
  elif command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$1" | awk '{print $1}'
  else
    release_die "Neither sha256sum nor shasum is available."
  fi
}

manifest_tmp="$RELEASE_OUTPUT_DIR/SHA256SUMS.txt.tmp"
: > "$manifest_tmp"
for name in $expected; do
  file="$RELEASE_OUTPUT_DIR/$name"
  [ -s "$file" ] || release_die "Cannot checksum missing/empty artifact: $name"
  hash="$(sha256_file "$file")"
  printf '%s  %s\n' "$hash" "$name" >> "$manifest_tmp"
done
mv -f -- "$manifest_tmp" "$RELEASE_OUTPUT_DIR/SHA256SUMS.txt"
printf 'Created %s\n' "$RELEASE_OUTPUT_DIR/SHA256SUMS.txt"
