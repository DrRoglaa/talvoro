#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

archives_only=0
if [ "${1:-}" = "--archives-only" ]; then
  archives_only=1
elif [ "$#" -gt 0 ]; then
  release_die "Usage: $0 [--archives-only]"
fi

require_command python3
version="$(read_version)"
python3 "$UTIL" verify-archives "$REPO_ROOT" "$RELEASE_OUTPUT_DIR" "$version"
if [ "$archives_only" -eq 0 ]; then
  python3 "$UTIL" verify-checksums "$RELEASE_OUTPUT_DIR" "$version"
fi
printf 'Release verification passed for Talvoro %s%s.\n' "$version" "$([ "$archives_only" -eq 1 ] && printf ' (archives only)' || true)"
