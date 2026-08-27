#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import stat
import sys
import zipfile
from pathlib import Path, PurePosixPath

SEMVER_RE = re.compile(r"^[0-9]+\.[0-9]+\.[0-9]+$")
PRIVATE_PATTERNS = [
    re.compile(rb"-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----"),
    re.compile(rb"-----BEGIN PGP PRIVATE KEY BLOCK-----"),
    re.compile(rb"AGE-SECRET-KEY-1[0-9A-Z]+"),
    re.compile(rb"minisign encrypted secret key", re.IGNORECASE),
    re.compile(rb"AKIA[0-9A-Z]{16}"),
    re.compile(rb"github_pat_[A-Za-z0-9_]{30,}"),
    re.compile(rb"gh[pousr]_[A-Za-z0-9]{30,}"),
    re.compile(rb"sk_live_[0-9A-Za-z]{20,}"),
    re.compile(rb"xox[baprs]-[0-9A-Za-z-]{20,}"),
    re.compile(rb"AIza[0-9A-Za-z_-]{35}"),
]

COMMON_SKIP_TOP = {
    ".git", ".github", ".release-build", ".idea", ".vscode",
    "dist", "packaging", "node_modules", "vendor", "coverage",
    ".pytest_cache", ".phpunit.cache",
}
COMMON_SKIP_FILES = {
    ".env", ".DS_Store", "Thumbs.db", "release.json", "SHA256SUMS.txt",
    ".phpunit.result.cache",
}
DANGEROUS_NAMES = {"id_rsa", "id_ed25519", "id_dsa", "id_ecdsa", "secring.gpg"}
DANGEROUS_SUFFIXES = {".pem", ".key", ".p12", ".pfx", ".kdbx", ".sqlite", ".sqlite3", ".db", ".dump"}
TEXT_SCAN_LIMIT = 2 * 1024 * 1024
FIXED_ZIP_TIME = (1980, 1, 1, 0, 0, 0)
RELEASE_ARCHIVE_RE = re.compile(r"^talvoro-v[0-9]+\.[0-9]+\.[0-9]+(?:-(?:docker|webhosting))?\.zip$")


def fail(message: str) -> None:
    raise SystemExit(f"ERROR: {message}")


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def is_runtime_dump(rel: PurePosixPath) -> bool:
    name = rel.name.lower()
    suffixes = [s.lower() for s in rel.suffixes]
    if name.endswith((".sql.gz", ".sql.bz2", ".sql.xz")):
        return True
    if rel.suffix.lower() == ".sql":
        return not (len(rel.parts) >= 3 and rel.parts[0] == "database" and rel.parts[1] == "migrations")
    return rel.suffix.lower() in DANGEROUS_SUFFIXES or ".bak" in suffixes


def should_skip_common(rel: PurePosixPath) -> bool:
    if not rel.parts:
        return False
    if rel.parts[0] in COMMON_SKIP_TOP:
        return True
    if rel.parts[0].startswith(".dist-previous-"):
        return True
    if len(rel.parts) >= 2 and rel.parts[0] == "scripts" and rel.parts[1] == "release":
        return True
    if rel.name in COMMON_SKIP_FILES:
        return True
    if len(rel.parts) == 1 and RELEASE_ARCHIVE_RE.fullmatch(rel.name):
        return True
    if rel.name.endswith((".log", ".tmp", ".swp", ".swo", ".orig", ".rej", "~")):
        return True
    if rel.parts[:2] in [("storage", "logs"), ("storage", "cache"), ("storage", "sessions"), ("storage", "backups"), ("storage", "update")]:
        return True
    if rel.as_posix() in {"storage/config.php", "storage/config.pending.php", "storage/installed.lock"}:
        return True
    if rel.parts[:2] == ("public", "uploads"):
        return True
    if rel.as_posix() == "docs/RELEASING.md":
        return True
    return False


def should_skip_distribution(rel: PurePosixPath, distribution: str) -> bool:
    p = rel.as_posix()
    if distribution == "source":
        return False
    if distribution == "docker":
        return p in {".gitignore", "docs/INSTALL-WEB-HOSTING.md"}
    if distribution == "webhosting":
        if rel.parts and rel.parts[0] == "docker":
            return True
        return p in {
            ".gitignore", ".dockerignore", ".env.example", ".env.docker.example",
            "Caddyfile", "Dockerfile", "compose.yaml", "docs/INSTALL-DOCKER.md",
        }
    fail(f"Unknown distribution: {distribution}")
    return True


def scan_bytes_for_secrets(data: bytes, label: str) -> None:
    if len(data) > TEXT_SCAN_LIMIT or b"\x00" in data:
        return
    for pattern in PRIVATE_PATTERNS:
        if pattern.search(data):
            fail(f"Likely secret/private key material found in {label}.")


def validate_source(root: Path, version: str) -> None:
    if not root.is_dir():
        fail(f"Repository root does not exist: {root}")
    if not SEMVER_RE.fullmatch(version):
        fail(f"VERSION is not X.Y.Z: {version}")

    minimum_path = root / "packaging" / "MINIMUM_UPDATE_VERSION"
    if not minimum_path.is_file():
        fail("packaging/MINIMUM_UPDATE_VERSION is missing.")
    minimum_version = minimum_path.read_text(encoding="utf-8").strip()
    if not SEMVER_RE.fullmatch(minimum_version):
        fail(f"MINIMUM_UPDATE_VERSION is not X.Y.Z: {minimum_version!r}")
    if tuple(map(int, minimum_version.split("."))) > tuple(map(int, version.split("."))):
        fail(f"MINIMUM_UPDATE_VERSION {minimum_version} cannot be newer than release VERSION {version}.")

    manifest_path = root / "release.json"
    if manifest_path.is_file():
        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        except Exception as exc:
            fail(f"Existing release.json cannot be parsed: {exc}")
        if str(manifest.get("version", "")) != version:
            fail(f"Existing release.json version {manifest.get('version')!r} does not match VERSION {version}.")

    composer_path = root / "composer.json"
    if composer_path.is_file():
        try:
            composer = json.loads(composer_path.read_text(encoding="utf-8"))
        except Exception as exc:
            fail(f"composer.json cannot be parsed: {exc}")
        composer_version = composer.get("version")
        if composer_version is not None and str(composer_version) != version:
            fail(f"composer.json version {composer_version!r} does not match VERSION {version}.")
        runtime_packages = [
            name for name in (composer.get("require") or {}).keys()
            if name != "php" and not name.startswith("ext-") and not name.startswith("lib-")
        ]
        if runtime_packages:
            fail(
                "Runtime Composer packages are declared but Talvoro's current bootstrap does not load vendor/autoload.php: "
                + ", ".join(sorted(runtime_packages))
            )

    for path in sorted(root.rglob("*")):
        try:
            rel = PurePosixPath(path.relative_to(root).as_posix())
        except ValueError:
            continue
        if rel.parts and (rel.parts[0] in {".git", "dist", ".release-build"} or rel.parts[0].startswith(".dist-previous-")):
            continue
        if path.is_symlink():
            fail(f"Symlink found in release source: {rel}. Symlinks are rejected for deterministic/safe packaging.")
        if not path.is_file():
            continue
        if len(rel.parts) == 1 and RELEASE_ARCHIVE_RE.fullmatch(rel.name):
            fail(f"Generated Talvoro release archive found outside dist/: {rel}. Remove/move old manual artifacts before building.")
        lower_name = rel.name.lower()
        if lower_name in DANGEROUS_NAMES or rel.suffix.lower() in {".pem", ".key", ".p12", ".pfx", ".kdbx"}:
            fail(f"Dangerous private/signing file found in source tree: {rel}")
        if is_runtime_dump(rel):
            fail(f"Possible database dump/local database found in source tree: {rel}")
        if len(rel.parts) >= 2 and rel.parts[0] == "scripts" and rel.parts[1] == "release":
            continue
        if rel.parts and rel.parts[0] == "packaging":
            continue
        if rel.name in {".env.example", ".env.docker.example"}:
            continue
        try:
            data = path.read_bytes()
        except OSError as exc:
            fail(f"Cannot read {rel}: {exc}")
        scan_bytes_for_secrets(data, rel.as_posix())


def stage_tree(root: Path, destination: Path, distribution: str) -> None:
    if destination.exists():
        shutil.rmtree(destination)
    destination.mkdir(parents=True, exist_ok=True)
    for path in sorted(root.rglob("*"), key=lambda p: p.relative_to(root).as_posix()):
        rel = PurePosixPath(path.relative_to(root).as_posix())
        if should_skip_common(rel) or should_skip_distribution(rel, distribution):
            continue
        if path.is_symlink():
            fail(f"Symlink found while staging: {rel}")
        if not path.is_file():
            continue
        target = destination / Path(*rel.parts)
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(path, target)
        source_mode = path.stat().st_mode
        os.chmod(target, 0o755 if source_mode & 0o111 else 0o644)


def create_manifest(root: Path, version: str, distribution: str, minimum_version: str) -> None:
    if not SEMVER_RE.fullmatch(version):
        fail(f"Invalid release version: {version}")
    if not SEMVER_RE.fullmatch(minimum_version):
        fail(f"Invalid minimum update version: {minimum_version}")
    files: dict[str, str] = {}
    for path in sorted(root.rglob("*"), key=lambda p: p.relative_to(root).as_posix()):
        if not path.is_file():
            continue
        rel = path.relative_to(root).as_posix()
        if rel == "release.json":
            continue
        data = path.read_bytes()
        scan_bytes_for_secrets(data, rel)
        files[rel] = sha256_bytes(data)
    if not files:
        fail(f"No files staged for {distribution} distribution.")
    manifest = {
        "product": "talvoro",
        "brand": "Talvoro",
        "version": version,
        "minimum_version": minimum_version,
        "package_format": 1,
        "distribution": distribution,
        "files": files,
    }
    payload = json.dumps(manifest, indent=2, sort_keys=False, ensure_ascii=False) + "\n"
    manifest_path = root / "release.json"
    with manifest_path.open("w", encoding="utf-8", newline="\n") as handle:
        handle.write(payload)
    os.chmod(manifest_path, 0o644)


def create_deterministic_zip(root: Path, archive: Path) -> None:
    if root.name != "talvoro":
        fail(f"Staging root must be named 'talvoro', got {root.name!r}")
    archive.parent.mkdir(parents=True, exist_ok=True)
    tmp = archive.with_name(archive.name + ".tmp")
    if tmp.exists():
        tmp.unlink()
    files = [p for p in root.rglob("*") if p.is_file()]
    files.sort(key=lambda p: p.relative_to(root).as_posix())
    if not files:
        fail("Cannot create an empty release archive.")
    with zipfile.ZipFile(tmp, "w", compression=zipfile.ZIP_STORED, strict_timestamps=False) as zf:
        for path in files:
            rel = path.relative_to(root).as_posix()
            arcname = f"talvoro/{rel}"
            info = zipfile.ZipInfo(arcname, date_time=FIXED_ZIP_TIME)
            info.create_system = 3
            info.compress_type = zipfile.ZIP_STORED
            mode = 0o755 if path.stat().st_mode & 0o111 else 0o644
            info.external_attr = (stat.S_IFREG | mode) << 16
            info.flag_bits = 0x800
            zf.writestr(info, path.read_bytes())
    os.replace(tmp, archive)


def read_required(repo_root: Path, distribution: str) -> list[str]:
    path = repo_root / "packaging" / distribution / "required-files.txt"
    if not path.is_file():
        fail(f"Required-file profile missing: {path}")
    return [line.strip() for line in path.read_text(encoding="utf-8").splitlines() if line.strip() and not line.lstrip().startswith("#")]


def forbidden_archive_path(rel: PurePosixPath) -> str | None:
    if not rel.parts:
        return "empty path"
    if any(part in {".git", ".github", "dist", ".release-build", ".idea", ".vscode", "node_modules", "coverage"} for part in rel.parts):
        return "development/private directory"
    if len(rel.parts) >= 2 and rel.parts[0] == "scripts" and rel.parts[1] == "release":
        return "maintainer release tooling"
    if rel.parts and rel.parts[0] == "packaging":
        return "maintainer packaging metadata"
    if rel.name == ".env":
        return "real .env file"
    if rel.name in {".DS_Store", "Thumbs.db"}:
        return "OS metadata"
    if rel.name.lower() in DANGEROUS_NAMES or rel.suffix.lower() in {".pem", ".key", ".p12", ".pfx", ".kdbx"}:
        return "private/signing material"
    if is_runtime_dump(rel):
        return "database dump/local database"
    if rel.as_posix() in {"storage/config.php", "storage/config.pending.php", "storage/installed.lock"}:
        return "installed-site secret/state"
    if len(rel.parts) >= 2 and rel.parts[0] == "storage" and rel.parts[1] in {"logs", "cache", "sessions", "backups", "update"}:
        return "runtime data"
    if len(rel.parts) >= 2 and rel.parts[0] == "public" and rel.parts[1] == "uploads":
        return "user uploads"
    return None


def verify_one_archive(repo_root: Path, archive: Path, version: str, distribution: str, minimum_version: str) -> None:
    if not archive.is_file() or archive.stat().st_size <= 0:
        fail(f"Missing or empty archive: {archive.name}")
    try:
        with zipfile.ZipFile(archive, "r") as zf:
            bad = zf.testzip()
            if bad is not None:
                fail(f"Archive CRC test failed for {archive.name}: {bad}")
            infos = zf.infolist()
            if not infos:
                fail(f"Archive is empty: {archive.name}")
            names = [info.filename for info in infos]
            if len(names) != len(set(names)):
                fail(f"Archive contains duplicate entries: {archive.name}")
            files: dict[str, bytes] = {}
            for info in infos:
                name = info.filename
                if "\\" in name or name.startswith("/"):
                    fail(f"Unsafe archive entry in {archive.name}: {name}")
                pp = PurePosixPath(name)
                if ".." in pp.parts:
                    fail(f"Path traversal entry in {archive.name}: {name}")
                if not pp.parts or pp.parts[0] != "talvoro":
                    fail(f"Archive entry is outside talvoro/ root in {archive.name}: {name}")
                if info.is_dir():
                    continue
                mode = (info.external_attr >> 16) & 0o170000
                if mode == stat.S_IFLNK:
                    fail(f"Symlink entry is not allowed in {archive.name}: {name}")
                rel = PurePosixPath(*pp.parts[1:])
                reason = forbidden_archive_path(rel)
                if reason:
                    fail(f"Forbidden file in {archive.name}: {rel} ({reason})")
                data = zf.read(info)
                scan_bytes_for_secrets(data, f"{archive.name}:{rel.as_posix()}")
                files[rel.as_posix()] = data
    except zipfile.BadZipFile as exc:
        fail(f"Invalid ZIP archive {archive.name}: {exc}")

    required = read_required(repo_root, distribution)
    missing = [name for name in required if name not in files]
    if missing:
        fail(f"{archive.name} is missing required files: {', '.join(missing)}")

    if files.get("VERSION", b"").decode("utf-8", errors="replace").strip() != version:
        fail(f"VERSION inside {archive.name} does not match {version}.")

    if distribution == "webhosting":
        docker_leaks = [name for name in ("Dockerfile", "compose.yaml", "Caddyfile", ".env.docker.example") if name in files]
        if docker_leaks or any(name.startswith("docker/") for name in files):
            fail(f"Web Hosting package contains Docker-only files: {', '.join(docker_leaks) or 'docker/'}")
    else:
        for name in ("Dockerfile", "compose.yaml", "Caddyfile", ".env.docker.example"):
            if name not in files:
                fail(f"{distribution} package is missing Docker support file: {name}")

    try:
        manifest = json.loads(files["release.json"].decode("utf-8"))
    except Exception as exc:
        fail(f"release.json is invalid in {archive.name}: {exc}")
    if manifest.get("product") != "talvoro" or manifest.get("brand") != "Talvoro":
        fail(f"release.json product metadata is invalid in {archive.name}.")
    if manifest.get("version") != version:
        fail(f"release.json version mismatch in {archive.name}.")
    if manifest.get("distribution") != distribution:
        fail(f"release.json distribution mismatch in {archive.name}.")
    if manifest.get("package_format") != 1:
        fail(f"Unsupported package_format in {archive.name}.")
    minimum = str(manifest.get("minimum_version", ""))
    if not SEMVER_RE.fullmatch(minimum):
        fail(f"Invalid minimum_version in {archive.name}.")
    if minimum != minimum_version:
        fail(f"minimum_version mismatch in {archive.name}: expected {minimum_version}, got {minimum}.")
    manifest_files = manifest.get("files")
    if not isinstance(manifest_files, dict) or not manifest_files:
        fail(f"release.json has no file manifest in {archive.name}.")
    expected_names = sorted(name for name in files if name != "release.json")
    if sorted(manifest_files) != expected_names:
        missing_manifest = sorted(set(expected_names) - set(manifest_files))
        extra_manifest = sorted(set(manifest_files) - set(expected_names))
        fail(
            f"release.json file list mismatch in {archive.name}. "
            f"Unmanifested={missing_manifest[:5]} missing-from-archive={extra_manifest[:5]}"
        )
    for name in expected_names:
        expected_hash = manifest_files.get(name)
        if not isinstance(expected_hash, str) or not re.fullmatch(r"[0-9a-f]{64}", expected_hash):
            fail(f"Invalid SHA-256 entry for {name} in {archive.name}.")
        actual = sha256_bytes(files[name])
        if actual != expected_hash:
            fail(f"release.json checksum mismatch for {name} in {archive.name}.")

    composer = json.loads(files["composer.json"].decode("utf-8"))
    runtime_packages = [
        name for name in (composer.get("require") or {}).keys()
        if name != "php" and not name.startswith("ext-") and not name.startswith("lib-")
    ]
    if runtime_packages and "vendor/autoload.php" not in files:
        fail(f"{archive.name} has runtime Composer dependencies but no production vendor/autoload.php.")


def verify_archives(repo_root: Path, output: Path, version: str) -> None:
    minimum_path = repo_root / "packaging" / "MINIMUM_UPDATE_VERSION"
    if not minimum_path.is_file():
        fail("packaging/MINIMUM_UPDATE_VERSION is missing during verification.")
    minimum_version = minimum_path.read_text(encoding="utf-8").strip()
    if not SEMVER_RE.fullmatch(minimum_version):
        fail(f"MINIMUM_UPDATE_VERSION is invalid during verification: {minimum_version!r}")
    expected = {
        "source": output / f"talvoro-v{version}.zip",
        "docker": output / f"talvoro-v{version}-docker.zip",
        "webhosting": output / f"talvoro-v{version}-webhosting.zip",
    }
    for distribution, archive in expected.items():
        verify_one_archive(repo_root, archive, version, distribution, minimum_version)


def verify_checksum_manifest(output: Path, version: str) -> None:
    expected_names = [
        f"talvoro-v{version}.zip",
        f"talvoro-v{version}-docker.zip",
        f"talvoro-v{version}-webhosting.zip",
    ]
    sums = output / "SHA256SUMS.txt"
    if not sums.is_file() or sums.stat().st_size <= 0:
        fail("SHA256SUMS.txt is missing or empty.")
    lines = sums.read_text(encoding="ascii").splitlines()
    if len(lines) != 3:
        fail(f"SHA256SUMS.txt must contain exactly 3 lines, found {len(lines)}.")
    parsed: list[tuple[str, str]] = []
    for line in lines:
        match = re.fullmatch(r"([0-9a-f]{64})  ([^/\\]+)", line)
        if not match:
            fail(f"Malformed SHA256SUMS.txt line: {line!r}")
        parsed.append((match.group(1), match.group(2)))
    names = [name for _, name in parsed]
    if names != expected_names:
        fail(f"SHA256SUMS.txt filenames/order are wrong: {names}")
    for expected_hash, name in parsed:
        path = output / name
        if not path.is_file():
            fail(f"Checksum manifest references missing file: {name}")
        if sha256_file(path) != expected_hash:
            fail(f"Checksum verification failed for {name}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Talvoro release helper")
    sub = parser.add_subparsers(dest="command", required=True)

    p = sub.add_parser("validate-source")
    p.add_argument("root", type=Path)
    p.add_argument("version")

    p = sub.add_parser("stage")
    p.add_argument("root", type=Path)
    p.add_argument("destination", type=Path)
    p.add_argument("distribution", choices=["source", "docker", "webhosting"])

    p = sub.add_parser("manifest")
    p.add_argument("root", type=Path)
    p.add_argument("version")
    p.add_argument("distribution", choices=["source", "docker", "webhosting"])
    p.add_argument("minimum_version")

    p = sub.add_parser("zip")
    p.add_argument("root", type=Path)
    p.add_argument("archive", type=Path)

    p = sub.add_parser("verify-archives")
    p.add_argument("repo_root", type=Path)
    p.add_argument("output", type=Path)
    p.add_argument("version")

    p = sub.add_parser("verify-checksums")
    p.add_argument("output", type=Path)
    p.add_argument("version")

    args = parser.parse_args()
    if args.command == "validate-source":
        validate_source(args.root.resolve(), args.version)
    elif args.command == "stage":
        stage_tree(args.root.resolve(), args.destination.resolve(), args.distribution)
    elif args.command == "manifest":
        create_manifest(args.root.resolve(), args.version, args.distribution, args.minimum_version)
    elif args.command == "zip":
        create_deterministic_zip(args.root.resolve(), args.archive.resolve())
    elif args.command == "verify-archives":
        verify_archives(args.repo_root.resolve(), args.output.resolve(), args.version)
    elif args.command == "verify-checksums":
        verify_checksum_manifest(args.output.resolve(), args.version)


if __name__ == "__main__":
    main()
