# Talvoro Development Guide

This document describes the intended development workflow for Talvoro contributors.

Talvoro uses a simple branch model designed to keep development fast while keeping releases predictable.

## Repository

Official repository:

```text
https://github.com/DrRoglaa/talvoro
```

## Branch model

```text
main
└── stable and released code

dev
└── integration branch for the next release

feature/*
fix/*
docs/*
└── short-lived working branches
```

## `main`

`main` represents stable, releasable Talvoro code.

Normal feature development should not happen directly on `main`.

Official release tags are created from commits on `main`.

## `dev`

`dev` is the integration branch for the next Talvoro release.

Completed feature, fix, and documentation branches are merged into `dev` after review and automated checks.

## Working branches

Use clear branch names.

Examples:

```text
feature/media-library
feature/installer-validation
fix/session-timeout
fix/webhosting-permissions
docs/docker-installation
```

Keep branches focused on one logical change.

## Typical workflow

Start from the latest `dev`:

```bash
git switch dev
git pull
```

Create a branch:

```bash
git switch -c feature/example
```

Make and test the change.

Commit with a clear message:

```bash
git add .
git commit -m "Add example feature"
```

Push:

```bash
git push -u origin feature/example
```

Open a pull request into `dev`.

## Pull requests

A pull request should explain:

- what changed
- why the change is needed
- how it was tested
- whether database migrations are included
- whether deployment or upgrade behavior changes
- whether documentation needs to change

Avoid mixing unrelated refactors and features in the same pull request.

## Continuous integration

CI should run for relevant pushes and pull requests.

The intended CI checks include:

- application tests
- installer checks
- database migration validation
- packaging validation
- Docker build validation
- static or syntax checks
- regression checks
- security sanity checks

A failing required check should block merge into protected branches.

## Version source

Talvoro should maintain one authoritative application version source.

The intended repository-level file is:

```text
VERSION
```

Example:

```text
0.15.0
```

Release automation should verify that the Git tag and application version agree.

Example:

```text
Git tag:  v0.15.0
VERSION:  0.15.0
```

If they differ, the release must fail.

## Release flow

Normal release flow:

```text
feature/* / fix/* / docs/*
          ↓
         dev
          ↓
         main
          ↓
   signed vX.Y.Z tag
          ↓
 GitHub Actions release
```

## Release packages

Every official release is intended to generate:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

All distributions must be generated from the exact same tagged commit.

The signed Git tag is the canonical source reference.

## Release signing

Official releases are intended to use:

- signed version tags
- SHA-256 checksums
- a cryptographically signed checksum manifest

Private signing material must never be committed to the repository.

Release automation must fail rather than silently publish unsigned required artifacts.

## Semantic Versioning

Talvoro follows Semantic Versioning:

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
0.15.0
0.15.1
0.16.0
1.0.0
```

Pre-release versions may use:

```text
v0.16.0-alpha.1
v0.16.0-beta.1
v0.16.0-rc.1
```

## Database migrations

Database changes must be handled through Talvoro's migration system.

Migration rules:

- never edit production databases manually as part of a normal release
- migrations should be deterministic
- failures must be visible
- migration ordering must be stable
- migration tests should cover supported upgrade paths
- destructive changes require explicit review and documentation

## Packaging

Release ZIPs must exclude development-only or private files.

Examples of content that should normally not ship:

```text
.git/
local .env files
IDE metadata
local databases
temporary files
cache
logs
test output
private keys
developer secrets
user uploads
Docker volumes
```

The web-hosting package should include everything a supported shared-hosting installation needs at runtime, according to the release design.

## Documentation changes

If a change modifies installation, deployment, upgrade behavior, configuration, or user-visible workflows, update the relevant documentation in the same pull request whenever possible.

## Security

Never commit:

- passwords
- API tokens
- private keys
- production `.env` files
- database dumps containing real data
- customer or user data

If a secret is accidentally committed, assume it has been exposed and rotate it.

See:

```text
SECURITY.md
```

## Before merging

Confirm:

- tests pass
- migrations are correct
- no secrets are present
- documentation is updated
- version behavior is unchanged unless intentionally part of the change
- release packaging still works
- Docker and web-hosting paths remain supported where applicable

## Before releasing

Confirm:

1. `dev` is stable.
2. release notes are complete.
3. `VERSION` is correct.
4. CI passes.
5. migration checks pass.
6. `dev` is merged into `main`.
7. the release tag is signed.
8. release artifacts are generated from that exact tag.
9. checksums are generated.
10. required signatures verify.
11. smoke tests pass.
12. only then is the GitHub Release published.
