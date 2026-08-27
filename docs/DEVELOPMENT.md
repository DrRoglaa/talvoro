<div align="center">

# Talvoro Development Guide

### A small branch model, predictable releases, and security-conscious changes.

**[Documentation](README.md)** · **[Contributing](../CONTRIBUTING.md)** · **[Release Engineering](RELEASING.md)** · **[Security](../SECURITY.md)**

</div>

---

Official repository:

```text
https://github.com/DrRoglaa/talvoro
```

## Branch model

```text
feature/*   fix/*   docs/*
       \      |      /
             dev
              ↓
             main
              ↓
      signed vX.Y.Z tag
              ↓
     automated release workflow
```

| Branch | Purpose |
| --- | --- |
| `main` | Stable/released source and release candidates promoted from `dev` |
| `dev` | Integration branch for the next Talvoro release |
| `feature/*` | Focused feature development |
| `fix/*` | Focused bug fixes |
| `docs/*` | Documentation-only work |

Normal development targets `dev`. Official version tags are created only from release-ready commits on `main`.

## Start a change

Update `dev`:

```bash
git switch dev
git pull --ff-only origin dev
```

Create a focused branch:

```bash
git switch -c feature/example
```

Other examples:

```text
feature/media-library
feature/installer-validation
fix/session-timeout
fix/webhosting-permissions
docs/docker-installation
```

Keep one branch focused on one logical change.

## Commit and push

After making and testing the change:

```bash
git add .
git commit -m "Add example feature"
git push -u origin feature/example
```

Open the pull request into:

```text
dev
```

## Pull-request expectations

A useful pull request explains:

- what changed;
- why it is needed;
- how it was tested;
- whether database migrations are included;
- whether installation/deployment behavior changed;
- whether upgrades are affected;
- which documentation was updated;
- any known limitations/follow-up work.

Avoid mixing unrelated refactors with functional changes.

## Continuous integration

Relevant pushes and pull requests should be validated automatically.

The Talvoro release-check pipeline covers the release-facing path, including:

- release-packaging regressions;
- all three distribution builds;
- archive/checksum verification;
- Docker/Web Hosting package smoke tests;
- release-script syntax checks;
- checks that release validation does not dirty tracked source.

Application-specific tests, migration checks, installer tests, and other validation should be added/retained as the codebase evolves.

A failing required check should block merge into a protected branch.

## Version source

Talvoro has one authoritative release version:

```text
VERSION
```

Example:

```text
0.15.0
```

For an official release:

```text
Git tag:  v0.15.0
VERSION:  0.15.0
```

These must agree exactly.

`packaging/MINIMUM_UPDATE_VERSION` is compatibility metadata for the updater. It is not a second application-version source.

## Database migrations

Database changes must use Talvoro's migration system.

Migration rules:

- deterministic behavior;
- stable ordering;
- clear failure reporting;
- supported upgrade-path coverage;
- explicit review/documentation for destructive changes;
- no normal release process that depends on manual production DB edits.

Do not silently mark a failed migration successful.

## Release packages

Every official release builds:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
```

Publication adds one Sigstore bundle per ZIP and one for `SHA256SUMS.txt`.

All distributions must come from the exact same tagged commit.

## Packaging safety

Release packages should exclude development-only/private state such as:

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

The release tooling validates package boundaries and scans for several high-confidence secret/private-key patterns.

> [!NOTE]
> Release packaging guardrails complement repository secret scanning; they do not replace it.

## Release signing model

Talvoro separates human source authorization from CI artifact signing:

| Layer | Purpose |
| --- | --- |
| Maintainer SSH/GPG signature | Proves the official release tag was authorized by a maintainer |
| Deterministic package build | Produces predictable release bytes from tagged source |
| SHA-256 manifest | Detects modified distribution archives |
| Sigstore/Cosign | Keylessly signs each release ZIP and `SHA256SUMS.txt` in GitHub Actions |
| GitHub attestations | Records build provenance for release assets |

Private signing keys never belong in the repository or normal CI secrets.

See **[GitHub Releases & Verification](GITHUB-RELEASES.md)**.

## Documentation changes

Update documentation in the same pull request when a change affects:

- installation;
- deployment;
- upgrades;
- migrations;
- configuration;
- user-visible behavior;
- release packaging;
- security expectations.

## Security rules

Never commit:

- passwords;
- API tokens;
- private keys;
- production `.env` files;
- real database dumps;
- private backups;
- customer/user data.

If a secret is accidentally committed, assume compromise and rotate/revoke it. Deleting it in a later commit is not enough.

See **[SECURITY.md](../SECURITY.md)**.

## Before merging

Confirm:

- relevant tests pass;
- migrations are correct;
- no secrets/private data are present;
- documentation is updated;
- release packaging still works;
- Docker and Web Hosting paths remain valid where affected;
- version behavior changed only when intentional.

## Before releasing

The maintainer flow is:

```text
dev stable
  ↓
local release tests/build/verification
  ↓
PR dev → main
  ↓
main release checks
  ↓
signed annotated vX.Y.Z tag
  ↓
tag verification + reproducible build
  ↓
package verification + smoke tests
  ↓
protected release approval
  ↓
Sigstore + provenance
  ↓
GitHub Release
```

For the exact procedure, see **[Release Engineering](RELEASING.md)** and **[GitHub Releases & Verification](GITHUB-RELEASES.md)**.

---

[← Documentation home](README.md)
