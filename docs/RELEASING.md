# Talvoro release engineering

This document is for Talvoro maintainers. End users should follow the installation guide for their chosen distribution.

## Release source of truth

`VERSION` at the repository root is the authoritative application release version. It must contain one `X.Y.Z` value and nothing else.

Official releases follow this trust chain:

```text
signed Git tag
      -> exact tagged source
      -> ./scripts/release/build-release.sh
      -> three verified distributions
      -> SHA256SUMS.txt
      -> cryptographic signature / provenance
```

The exact Git commit referenced by the signed tag is the canonical source. The Source / Standard ZIP is a deployment distribution and is not called the canonical source.

`packaging/MINIMUM_UPDATE_VERSION` is separate compatibility metadata for Talvoro's updater; it is not a second application-version source.

## Build all distributions

From the repository root:

```bash
./scripts/release/build-release.sh
```

Requirements for the maintainer build host are Bash, Python 3 and either `sha256sum` (Linux) or `shasum` (macOS). The application itself keeps its existing PHP/Docker runtime requirements.

A successful build leaves only the verified release set in `dist/`:

```text
dist/
├── talvoro-vX.Y.Z.zip
├── talvoro-vX.Y.Z-docker.zip
├── talvoro-vX.Y.Z-webhosting.zip
└── SHA256SUMS.txt
```

The builder stages into `.release-build/` and promotes the output to `dist/` only after every archive and checksum passes verification. A failed build does not replace the last successful `dist/` directory.

## Verification and tests

Run verification against an existing `dist/`:

```bash
./scripts/release/verify-release.sh
```

Run the release-packaging regression suite:

```bash
./scripts/release/test-release.sh
```

The verification checks archive integrity, package root safety, required/forbidden files, generated `release.json` manifests, application/minimum-version propagation, distribution independence, obvious secret/private-key material, and `SHA256SUMS.txt`.

## Reproducibility

Talvoro's ZIP writer uses a stable lexicographic file order, a fixed ZIP timestamp, normalized regular-file permissions, no UID/GID metadata, no directory entries and the ZIP `STORE` method. Using `STORE` intentionally avoids zlib/compression-version differences. Identical file contents and executable bits therefore produce byte-for-byte identical release archives across supported Python 3 build hosts.

The tradeoff is modestly larger ZIP files. Talvoro's current application is small enough that deterministic bytes are preferred over a small compression saving.

## Secret safety

Local `.env` is an expected development file and is never copied into a package. The release source validation fails on likely private/signing key files (including common PEM, PGP, age and minisign forms), database dumps, impossible minimum-version metadata and old Talvoro release ZIPs left at the repository root. Interrupted `.dist-previous-*` promotion directories are always excluded. Package verification additionally scans shipped text for common high-confidence private-key/token formats. This is a release guardrail, not a replacement for repository secret scanning.

Private signing keys never belong in the repository, release scripts, examples or GitHub Actions YAML.

## Signing integration point

Local release building intentionally stops after verified `SHA256SUMS.txt`. Signing/provenance is a separate post-verification step over that immutable release set. CI must treat signing as required for an official release and must fail before publishing if the signing/provenance step fails.

For GitHub-hosted releases, prefer short-lived/keyless OIDC-backed provenance/signing where it meets the distribution requirements. A hardware-backed or offline maintainer key is the preferred alternative for long-lived human signing keys. Do not store a reusable private signing key in Git, a release ZIP or a plain repository secret when a keyless or hardware-backed design is available.

## Recommended GitHub release implementation

Keep the release logic in this repository and make GitHub Actions call the same scripts used locally. The workflow should be triggered only for a release tag such as `v0.15.0`, then fail immediately unless the tag is exactly `v$(cat VERSION)`.

Recommended trust and publishing sequence:

1. Create the `vX.Y.Z` release tag with a verified SSH or GPG signature. Prefer a hardware-backed maintainer key when practical.
2. Check out the exact tagged commit in GitHub Actions and run `./scripts/release/build-release.sh` followed by `./scripts/release/verify-release.sh`.
3. Give only the release job the GitHub OIDC/attestation permissions it needs (`contents: read`, `id-token: write`, `attestations: write`) and create GitHub artifact attestations for the three ZIPs and `SHA256SUMS.txt`.
4. For a portable explicit signature, use Sigstore/Cosign keyless blob signing on `SHA256SUMS.txt` and publish the generated Sigstore bundle alongside the release assets. This uses short-lived OIDC identity instead of a long-lived signing key stored in the repository.
5. Enable GitHub immutable releases when the repository is ready to publish official releases that way.
6. Publish the GitHub Release only after build, verification, attestation and required signing steps all succeed. Any signing/provenance failure must prevent publication.

A future workflow may therefore use GitHub's maintained attestation action for provenance and a command conceptually equivalent to:

```bash
cosign sign-blob SHA256SUMS.txt --bundle SHA256SUMS.txt.sigstore.json --yes
```

The exact action versions and Cosign invocation should be pinned/reviewed when the workflow is introduced because CI dependencies evolve independently from Talvoro's packaging scripts.
