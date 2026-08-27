<div align="center">

# Talvoro Release Engineering

### Deterministic packages first. Signing and publication second.

**Maintainer documentation**

**[Documentation](README.md)** · **[GitHub Releases](GITHUB-RELEASES.md)** · **[Distributions](DISTRIBUTIONS.md)** · **[Development](DEVELOPMENT.md)**

</div>

---

> [!IMPORTANT]
> End users should follow the installation guide for their distribution. This document describes maintainer-facing release engineering.

## Source of truth

The repository-root:

```text
VERSION
```

is Talvoro's authoritative application release version and must contain one `X.Y.Z` value.

The exact Git commit referenced by the verified signed release tag is the canonical release source.

`packaging/MINIMUM_UPDATE_VERSION` is separate updater-compatibility metadata, not another version source.

## Trust chain

```text
verified signed vX.Y.Z tag
        ↓
exact tagged source + VERSION
        ↓
release regression suite
        ↓
deterministic Source / Docker / Web Hosting builds
        ↓
archive + release.json verification
        ↓
SHA256SUMS.txt
        ↓
Docker/Web Hosting smoke tests
        ↓
individual Sigstore signatures
        ↓
GitHub provenance attestations
        ↓
protected publication
```

## Local validation sequence

From the repository root:

```bash
./scripts/release/test-release.sh
./scripts/release/build-release.sh
./scripts/release/verify-release.sh
./scripts/release/smoke-release-packages.sh
```

A release candidate should not be promoted while any of these fail.

## Build output

A successful local build leaves the verified unsigned release set in:

```text
dist/
├── talvoro-vX.Y.Z.zip
├── talvoro-vX.Y.Z-docker.zip
├── talvoro-vX.Y.Z-webhosting.zip
└── SHA256SUMS.txt
```

Local release building intentionally stops here.

Official signing/provenance belongs to the protected GitHub release workflow after the exact tag has been verified.

## Build-host requirements

Maintainer packaging requires:

- Bash;
- Python 3;
- `sha256sum` on Linux or `shasum` on macOS.

Talvoro application/runtime requirements are separate.

## Atomic output

The builder stages release output under:

```text
.release-build/
```

and promotes a completed set to:

```text
dist/
```

only after archive/checksum verification succeeds.

A failed build must not replace the last successful release set.

Interrupted `.dist-previous-*` directories are excluded from release staging.

## Reproducibility

Talvoro's ZIP writer intentionally favors deterministic bytes over maximum compression.

Release ZIPs use:

- stable lexicographic file ordering;
- a fixed ZIP timestamp;
- normalized regular-file permissions;
- no UID/GID metadata;
- no directory entries;
- ZIP `STORE` mode.

For identical source contents and executable bits, supported Python build hosts should produce byte-for-byte identical release archives.

## Release verification

`./scripts/release/verify-release.sh` validates the generated release set, including:

- archive integrity;
- safe package root;
- required and forbidden files;
- generated `release.json`;
- application/minimum-version propagation;
- distribution independence;
- common private-key/secret patterns;
- `SHA256SUMS.txt`.

## Secret-safety model

Release source validation rejects several high-risk inputs, including likely:

- private/signing key files;
- common PEM/PGP/age/minisign private-key material;
- database dumps;
- impossible minimum-version metadata;
- old Talvoro release ZIPs left at repository root.

Local `.env` is development/runtime state and must not be copied into release packages.

> [!NOTE]
> These checks are a release guardrail. Repository secret scanning and normal security review are still required.

## Human signing

The maintainer creates a **signed annotated Git tag** only after the exact release commit is on `main`.

Example:

```bash
git switch main
git pull --ff-only origin main
git tag -s v0.15.0 -m "Talvoro v0.15.0"
git tag -v v0.15.0
git push origin v0.15.0
```

Git can use SSH or GPG signing according to maintainer configuration.

Private human signing keys stay in the maintainer's protected key store/hardware and are never committed or added to GitHub Actions merely to make release automation work.

## CI artifact signing

The GitHub publish job uses short-lived OIDC identity to keylessly sign:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
```

Each receives:

```text
<artifact>.sigstore.json
```

The workflow immediately verifies every generated bundle against the expected GitHub Actions identity before publication.

## Provenance

GitHub build provenance attestations are generated for release assets as part of the protected publish job.

This is independent from the maintainer's signed Git tag and complements Sigstore artifact signatures.

## Release permissions

The workflow intentionally separates:

| Job | Permission model |
| --- | --- |
| Build/test | Read-only repository access |
| Publish | Protected `release` environment with only the write/OIDC/attestation permissions it needs |

Keep publication permission out of routine build/test jobs.

## Official publication

For the complete GitHub process, including environment approval, brrr notifications, asset-set validation, Sigstore identity, and verification commands, use **[GitHub Releases & Verification](GITHUB-RELEASES.md)**.

## Release checklist

Before the official tag:

- `dev` is stable;
- `VERSION` is correct;
- release notes are ready;
- local release regression suite passes;
- all distributions build;
- release verification passes;
- smoke tests pass;
- `dev` is promoted to `main`;
- `main` release checks pass.

Before publication:

- tag is annotated and cryptographically verified;
- tag matches `VERSION`;
- tagged commit is reachable from `main`;
- GitHub build job passes;
- expected release version/commit is reviewed at the `release` environment gate;
- all Sigstore signatures verify;
- provenance generation passes;
- release assets are complete and contain no unexpected files.

> [!CAUTION]
> Signing, provenance, checksum, smoke-test, or asset-set failures are release blockers. The workflow must fail rather than publish an incomplete release.

---

[← Documentation home](README.md) · [GitHub release workflow →](GITHUB-RELEASES.md)
