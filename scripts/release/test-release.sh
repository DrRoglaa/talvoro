#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
. "$SCRIPT_DIR/common.sh"

require_command python3
version="$(read_version)"
tmp="$(mktemp -d "${TMPDIR:-/tmp}/talvoro-release-tests.XXXXXX")"
cleanup() { rm -rf -- "$tmp"; }
trap cleanup EXIT
trap 'exit 130' INT TERM

copy_repo() {
  local dest="$1"
  mkdir -p "$dest"
  python3 - "$REPO_ROOT" "$dest" <<'PY'
from pathlib import Path
import shutil, sys
src=Path(sys.argv[1]); dst=Path(sys.argv[2])
for item in src.iterdir():
    if item.name in {'.git', '.release-build', 'dist'} or item.name.startswith('.dist-previous-'):
        continue
    target=dst/item.name
    if item.is_dir(): shutil.copytree(item,target)
    else: shutil.copy2(item,target)
PY
}

hash_manifest() {
  python3 - "$1" <<'PY'
from pathlib import Path
import hashlib, sys
p=Path(sys.argv[1])
for f in sorted(p.glob('*')):
    if f.is_file(): print(hashlib.sha256(f.read_bytes()).hexdigest(), f.name)
PY
}

printf '%s\n' '[TEST] normal build + filenames + manifests + exclusions + checksums'
base="$tmp/base"
copy_repo "$base"
printf 'DB_PASSWORD=super-secret-local-value\n' > "$base/.env"
(cd "$base" && ./scripts/release/build-release.sh >/dev/null)
[ -s "$base/dist/talvoro-v$version.zip" ]
[ -s "$base/dist/talvoro-v$version-docker.zip" ]
[ -s "$base/dist/talvoro-v$version-webhosting.zip" ]
[ -s "$base/dist/SHA256SUMS.txt" ]
python3 - "$base/dist/talvoro-v$version.zip" "$version" <<'PY'
import json, sys, zipfile
p, version=sys.argv[1],sys.argv[2]
with zipfile.ZipFile(p) as z:
    assert 'talvoro/.env' not in z.namelist()
    assert not any('/dist/' in n or '/scripts/release/' in n or '/packaging/' in n for n in z.namelist())
    assert z.read('talvoro/VERSION').decode().strip()==version
    assert json.loads(z.read('talvoro/release.json'))['version']==version
PY

printf '%s\n' '[TEST] deterministic byte-for-byte ZIP writer'
deterministic_root="$tmp/deterministic"
mkdir -p "$deterministic_root"
python3 - "$base/dist/talvoro-v$version.zip" "$deterministic_root" <<'PY'
import sys, zipfile
with zipfile.ZipFile(sys.argv[1]) as z:
    z.extractall(sys.argv[2])
PY
python3 "$base/scripts/release/release_utils.py" zip "$deterministic_root/talvoro" "$tmp/deterministic-1.zip"
python3 "$base/scripts/release/release_utils.py" zip "$deterministic_root/talvoro" "$tmp/deterministic-2.zip"
cmp -s "$tmp/deterministic-1.zip" "$tmp/deterministic-2.zip" || release_die 'Deterministic ZIP writer changed bytes for identical input.'

printf '%s\n' '[TEST] missing VERSION fails without replacing existing dist'
before_failures="$tmp/before-failures.sha"
hash_manifest "$base/dist" > "$before_failures"
mv "$base/VERSION" "$base/VERSION.saved"
if (cd "$base" && ./scripts/release/build-release.sh >/dev/null 2>&1); then release_die 'Build unexpectedly succeeded without VERSION.'; fi
mv "$base/VERSION.saved" "$base/VERSION"

printf '%s\n' '[TEST] invalid VERSION fails'
printf '0.15\n' > "$base/VERSION"
if (cd "$base" && ./scripts/release/build-release.sh >/dev/null 2>&1); then release_die 'Build unexpectedly succeeded with invalid VERSION.'; fi
printf '%s\n' "$version" > "$base/VERSION"

printf '%s\n' '[TEST] mismatched release metadata fails'
printf '{"product":"talvoro","version":"9.9.9"}\n' > "$base/release.json"
if (cd "$base" && ./scripts/release/build-release.sh >/dev/null 2>&1); then release_die 'Build unexpectedly succeeded with mismatched release.json.'; fi
rm -f "$base/release.json"

printf '%s\n' '[TEST] likely private key file fails'
printf '%s\n' 'not-a-real-key' > "$base/accidental-release.key"
if (cd "$base" && ./scripts/release/build-release.sh >/dev/null 2>&1); then release_die 'Build unexpectedly succeeded with a private-key file.'; fi
rm -f "$base/accidental-release.key"
hash_manifest "$base/dist" > "$tmp/after-failures.sha"
cmp -s "$before_failures" "$tmp/after-failures.sha" || release_die 'A failed release build replaced the last successful dist/.'

printf '%s\n' '[TEST] no recursive dist packaging on repeated builds'
python3 - "$base/dist/talvoro-v$version.zip" <<'PY'
import sys, zipfile
with zipfile.ZipFile(sys.argv[1]) as z:
    assert not any(n.startswith('talvoro/dist/') for n in z.namelist())
PY

printf '\nAll Talvoro release-packaging tests passed.\n'
