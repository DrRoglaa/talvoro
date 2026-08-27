#!/usr/bin/env bash
set -euo pipefail

RELEASE_SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(CDPATH= cd -- "$RELEASE_SCRIPT_DIR/../.." && pwd)"
BUILD_ROOT="$REPO_ROOT/.release-build"
RELEASE_OUTPUT_DIR="${TALVORO_RELEASE_OUTPUT_DIR:-$REPO_ROOT/dist}"
UTIL="$RELEASE_SCRIPT_DIR/release_utils.py"

release_die() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || release_die "Required command not found: $1"
}

read_version() {
  local file="$REPO_ROOT/VERSION"
  [ -f "$file" ] || release_die "VERSION is missing at repository root."
  local line_count
  line_count="$(awk 'END { print NR }' "$file")"
  [ "$line_count" = "1" ] || release_die "VERSION must contain exactly one line."
  local version
  version="$(cat "$file")"
  case "$version" in
    *$'\r'*|*' '*|*$'\t'*) release_die "VERSION must contain only X.Y.Z with no whitespace." ;;
  esac
  printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' || release_die "VERSION must use X.Y.Z format; got '$version'."
  printf '%s\n' "$version"
}

read_minimum_version() {
  local file="$REPO_ROOT/packaging/MINIMUM_UPDATE_VERSION"
  [ -f "$file" ] || release_die "packaging/MINIMUM_UPDATE_VERSION is missing."
  local value
  value="$(cat "$file")"
  printf '%s' "$value" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' || release_die "MINIMUM_UPDATE_VERSION must use X.Y.Z format."
  printf '%s\n' "$value"
}

validate_release_source() {
  require_command python3
  local version
  version="$(read_version)"
  python3 "$UTIL" validate-source "$REPO_ROOT" "$version"
}

safe_remove_stage() {
  local path="$1"
  case "$path" in
    "$BUILD_ROOT"/*) rm -rf -- "$path" ;;
    *) release_die "Refusing to remove unsafe path: $path" ;;
  esac
}

archive_name_for() {
  local distribution="$1" version="$2"
  case "$distribution" in
    source) printf 'talvoro-v%s.zip\n' "$version" ;;
    docker) printf 'talvoro-v%s-docker.zip\n' "$version" ;;
    webhosting) printf 'talvoro-v%s-webhosting.zip\n' "$version" ;;
    *) release_die "Unknown distribution: $distribution" ;;
  esac
}

build_distribution() {
  local distribution="$1"
  require_command python3
  local version minimum archive stage_root package_root
  version="$(read_version)"
  minimum="$(read_minimum_version)"
  if [ "${TALVORO_RELEASE_SKIP_SOURCE_VALIDATION:-0}" != "1" ]; then
    validate_release_source
  fi
  archive="$(archive_name_for "$distribution" "$version")"
  stage_root="$BUILD_ROOT/stage-$distribution"
  package_root="$stage_root/talvoro"
  mkdir -p "$BUILD_ROOT" "$RELEASE_OUTPUT_DIR"
  safe_remove_stage "$stage_root"
  mkdir -p "$stage_root"
  python3 "$UTIL" stage "$REPO_ROOT" "$package_root" "$distribution"
  python3 "$UTIL" manifest "$package_root" "$version" "$distribution" "$minimum"
  python3 "$UTIL" zip "$package_root" "$RELEASE_OUTPUT_DIR/$archive"
  safe_remove_stage "$stage_root"
  printf 'Built %s\n' "$RELEASE_OUTPUT_DIR/$archive"
}
