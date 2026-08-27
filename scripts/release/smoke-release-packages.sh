#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

require_command python3
require_command docker

version="$(read_version)"
dist_dir="${1:-$RELEASE_OUTPUT_DIR}"
docker_archive="$dist_dir/$(archive_name_for docker "$version")"
web_archive="$dist_dir/$(archive_name_for webhosting "$version")"

[ -s "$docker_archive" ] || release_die "Docker release archive is missing: $docker_archive"
[ -s "$web_archive" ] || release_die "Web Hosting release archive is missing: $web_archive"

tmp="$(mktemp -d "${TMPDIR:-/tmp}/talvoro-release-smoke.XXXXXX")"
image_tag="talvoro-release-smoke:${version//./-}-$$"
cleanup() {
  docker image rm -f "$image_tag" >/dev/null 2>&1 || true
  rm -rf -- "$tmp"
}
trap cleanup EXIT

python3 - "$docker_archive" "$tmp/docker" "$web_archive" "$tmp/webhosting" <<'PY'
from pathlib import Path
import sys
import zipfile

pairs = [(Path(sys.argv[1]), Path(sys.argv[2])), (Path(sys.argv[3]), Path(sys.argv[4]))]
for archive, destination in pairs:
    destination.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(archive) as zf:
        zf.extractall(destination)
PY

docker_root="$tmp/docker/talvoro"
web_root="$tmp/webhosting/talvoro"
[ -d "$docker_root" ] || release_die "Docker archive did not extract to talvoro/."
[ -d "$web_root" ] || release_die "Web Hosting archive did not extract to talvoro/."

[ -f "$docker_root/.env.docker.example" ] || release_die "Docker package is missing .env.docker.example."
cp "$docker_root/.env.docker.example" "$docker_root/.env"

printf '[SMOKE] Validate Docker Compose configuration\n'
(
  cd "$docker_root"
  docker compose --env-file .env config -q
)

printf '[SMOKE] Build Docker distribution independently\n'
docker build --tag "$image_tag" "$docker_root"

printf '[SMOKE] Docker distribution - Talvoro %s\n' "$version"
printf '[SMOKE] Docker compatibility regression suite (introduced in v0.14.7)\n'
docker run --rm --entrypoint php "$image_tag" bin/check-v0147.php
printf '[SMOKE] Docker current release suite (v%s)\n' "$version"
docker run --rm --entrypoint php "$image_tag" bin/check-v0150.php

printf '[SMOKE] Lint Web Hosting PHP with the Docker package runtime\n'
docker run --rm \
  --volume "$web_root:/var/www/html" \
  --workdir /var/www/html \
  --entrypoint sh \
  "$image_tag" \
  -c 'set -eu; find . -type f -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'

printf '[SMOKE] Web Hosting distribution - Talvoro %s\n' "$version"
printf '[SMOKE] Web Hosting compatibility regression suite (introduced in v0.14.7)\n'
docker run --rm \
  --volume "$web_root:/var/www/html" \
  --workdir /var/www/html \
  --entrypoint php \
  "$image_tag" \
  bin/check-v0147.php

printf '[SMOKE] Web Hosting current release suite (v%s)\n' "$version"
docker run --rm \
  --volume "$web_root:/var/www/html" \
  --workdir /var/www/html \
  --entrypoint php \
  "$image_tag" \
  bin/check-v0150.php

printf 'Release package smoke checks passed for Talvoro %s.\n' "$version"
