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
[ "$GITHUB_REF_NAME" = "$tag" ] || release_die "Cannot sign checksums: tag '$GITHUB_REF_NAME' does not match VERSION '$version'."

checksum_file="$RELEASE_OUTPUT_DIR/SHA256SUMS.txt"
bundle_file="$RELEASE_OUTPUT_DIR/SHA256SUMS.txt.sigstore.json"
[ -s "$checksum_file" ] || release_die "Cannot sign: SHA256SUMS.txt is missing or empty."
rm -f -- "$bundle_file"

identity="https://github.com/${GITHUB_REPOSITORY}/.github/workflows/release.yml@refs/tags/${tag}"
issuer="https://token.actions.githubusercontent.com"

cosign sign-blob \
  --yes \
  --bundle "$bundle_file" \
  "$checksum_file"

[ -s "$bundle_file" ] || release_die "Cosign did not create the expected Sigstore bundle."

cosign verify-blob \
  "$checksum_file" \
  --bundle "$bundle_file" \
  --certificate-identity "$identity" \
  --certificate-oidc-issuer "$issuer" >/dev/null

printf 'Signed and verified %s with keyless Sigstore identity.\n' "$checksum_file"
printf 'Bundle: %s\n' "$bundle_file"
