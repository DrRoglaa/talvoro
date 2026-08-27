#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

require_command cosign

: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required.}"
: "${GITHUB_REF_NAME:?GITHUB_REF_NAME is required.}"

version="$(read_version)"
tag="v$version"
[ "$GITHUB_REF_NAME" = "$tag" ] || release_die "Cannot sign release artifacts: tag '$GITHUB_REF_NAME' does not match VERSION '$version'."

identity="https://github.com/${GITHUB_REPOSITORY}/.github/workflows/release.yml@refs/tags/${tag}"
issuer="https://token.actions.githubusercontent.com"

artifacts=(
  "$RELEASE_OUTPUT_DIR/$(archive_name_for source "$version")"
  "$RELEASE_OUTPUT_DIR/$(archive_name_for docker "$version")"
  "$RELEASE_OUTPUT_DIR/$(archive_name_for webhosting "$version")"
  "$RELEASE_OUTPUT_DIR/SHA256SUMS.txt"
)

for artifact in "${artifacts[@]}"; do
  [ -s "$artifact" ] || release_die "Cannot sign: required release artifact is missing or empty: $artifact"

  bundle="${artifact}.sigstore.json"
  rm -f -- "$bundle"

  printf 'Signing %s with keyless Sigstore identity...\n' "$(basename -- "$artifact")"
  cosign sign-blob \
    --yes \
    --bundle "$bundle" \
    "$artifact"

  [ -s "$bundle" ] || release_die "Cosign did not create the expected Sigstore bundle: $bundle"

  cosign verify-blob \
    "$artifact" \
    --bundle "$bundle" \
    --certificate-identity "$identity" \
    --certificate-oidc-issuer "$issuer" >/dev/null

  printf 'Verified Sigstore signature for %s.\n' "$(basename -- "$artifact")"
done

printf 'Signed and verified %d Talvoro release artifacts with keyless Sigstore identity.\n' "${#artifacts[@]}"
