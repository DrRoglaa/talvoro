#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

require_command python3
version="$(read_version)"
validate_release_source
export TALVORO_RELEASE_SKIP_SOURCE_VALIDATION=1

case "$BUILD_ROOT" in
  "$REPO_ROOT"/.release-build) rm -rf -- "$BUILD_ROOT" ;;
  *) release_die "Unsafe release build path: $BUILD_ROOT" ;;
esac
mkdir -p "$BUILD_ROOT/output"
export TALVORO_RELEASE_OUTPUT_DIR="$BUILD_ROOT/output"

"$SCRIPT_DIR/build-source.sh"
"$SCRIPT_DIR/build-docker.sh"
"$SCRIPT_DIR/build-webhosting.sh"
"$SCRIPT_DIR/verify-release.sh" --archives-only
"$SCRIPT_DIR/create-checksums.sh"
"$SCRIPT_DIR/verify-release.sh"

final_output="$REPO_ROOT/dist"
previous_output="$REPO_ROOT/.dist-previous-$$"
rm -rf -- "$previous_output"
if [ -e "$final_output" ]; then
  mv -- "$final_output" "$previous_output"
fi
if mv -- "$BUILD_ROOT/output" "$final_output"; then
  rm -rf -- "$previous_output" "$BUILD_ROOT"
else
  rm -rf -- "$final_output"
  if [ -e "$previous_output" ]; then mv -- "$previous_output" "$final_output"; fi
  release_die "Could not promote verified release artifacts into dist/."
fi

printf '\nTalvoro %s release build completed successfully.\n' "$version"
printf 'Artifacts:\n'
printf '  %s\n' "$final_output/$(archive_name_for source "$version")"
printf '  %s\n' "$final_output/$(archive_name_for docker "$version")"
printf '  %s\n' "$final_output/$(archive_name_for webhosting "$version")"
printf '  %s\n' "$final_output/SHA256SUMS.txt"
