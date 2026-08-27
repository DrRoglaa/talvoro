# Talvoro GitHub release workflow

Talvoro keeps release logic in `scripts/release/`. GitHub Actions calls the same repository scripts used locally; the workflow YAML is orchestration only.

## Trust chain

Official releases follow this chain:

```text
main
  -> cryptographically verified annotated vX.Y.Z tag
  -> exact tagged commit
  -> VERSION validation
  -> release regression tests
  -> deterministic Source / Docker / Web Hosting build
  -> archive + checksum verification
  -> Docker/Web Hosting package smoke tests
  -> SHA256SUMS.txt
  -> keyless Sigstore signature bundle
  -> GitHub artifact provenance attestations
  -> complete draft GitHub Release
  -> published/immutable GitHub Release
```

The workflow never creates the release tag. A maintainer creates and pushes the signed tag only after the exact release commit is on `main`.

## Workflows

### `.github/workflows/release-checks.yml`

Runs on pushes and pull requests targeting `dev` or `main`, plus manual dispatch. It runs the release regression suite, builds all three distributions, verifies them, and smoke-tests the Docker and Web Hosting packages.

The job has read-only repository permission. A failed branch push can optionally send a brrr notification.

### `.github/workflows/release.yml`

Runs only for `v*.*.*` tags. The repository scripts enforce the stricter `vX.Y.Z` form and require the tag to match the root `VERSION` exactly.

The build job is read-only. It verifies that the tag is annotated, points to the checked-out commit, is reachable from `origin/main`, and is reported by GitHub as cryptographically verified.

The publish job uses the protected `release` environment and receives only the permissions needed to publish, request an OIDC token, and write artifact attestations. It downloads the already-verified artifacts, verifies them again, signs `SHA256SUMS.txt` keylessly with Cosign, generates GitHub provenance attestations, creates/reuses a draft release, uploads the exact expected asset set, validates that asset set, and only then publishes the release.

## One-time GitHub setup

### 1. Create a `release` environment

In the repository:

1. Open **Settings -> Environments**.
2. Create an environment named exactly `release`.
3. Recommended: add a required reviewer so final signing/publishing requires explicit approval.
4. Restrict deployment branches/tags as appropriate for your repository policy.

The build/test job runs before this approval gate. Only signing and publication wait on the environment.

### 2. Configure brrr notifications

brrr is optional and is never a release gate.

Preferred setup:

1. In the brrr app, copy only the webhook secret, for example `br_usr_...`.
2. In GitHub open **Settings -> Secrets and variables -> Actions**.
3. Create repository secret `BRRR_WEBHOOK_SECRET` with that secret value.

Talvoro sends this value in the `Authorization: Bearer ...` header to `https://api.brrr.now/v1/send`, which avoids placing the secret in a URL.

Compatibility setup matching brrr's GitHub Actions guide:

- Create repository secret `BRRR_WEBHOOK_URL` containing the complete brrr webhook URL.

If both are configured, `BRRR_WEBHOOK_SECRET` takes precedence. Never commit either value to the repository.

Notifications are sent for release start, release success, release failure, publication failure, and failed branch release checks. Notification delivery failure prints a warning but cannot make a valid Talvoro release fail.

### 3. Enable immutable GitHub Releases

In repository **Settings**, find **Releases** and enable **release immutability**.

Talvoro intentionally creates a draft first, uploads and verifies the complete asset set, then publishes it. This allows all assets to be present before an immutable release is finalized.

### 4. Protect `main`

Recommended branch policy:

- require pull requests before merging;
- require the `Release checks / Build and verify distributions` status check;
- require branches to be up to date before merging;
- restrict force pushes and branch deletion;
- require signed commits if that matches the maintainer signing policy.

Apply a similar check requirement to `dev` if desired.

### 5. GitHub Actions policy

Talvoro pins every external action to a full commit SHA. If the repository uses an Actions allow-list, allow GitHub-authored actions and `sigstore/cosign-installer`.

## Release procedure

### 1. Prepare `dev`

Run locally:

```bash
./scripts/release/test-release.sh
./scripts/release/build-release.sh
./scripts/release/verify-release.sh
```

Push the completed release to `dev` and let **Release checks** pass.

### 2. Promote `dev` to `main`

Merge `dev` into `main` through the normal pull request process. The exact commit being released must be reachable from `main` before the release tag is pushed.

### 3. Create a signed annotated tag

Confirm the checked-out release version:

```bash
cat VERSION
```

For version `0.15.0`, the tag must be exactly `v0.15.0`.

With Git signing already configured on the maintainer machine:

```bash
git switch main
git pull --ff-only origin main
git tag -s v0.15.0 -m "Talvoro v0.15.0"
git push origin v0.15.0
```

`git tag -s` uses the signing backend configured in Git. That may be GPG or SSH signing. Keep the signing private key in the maintainer's protected key store/hardware; never place it in Talvoro or GitHub Actions secrets merely to make this workflow work.

The workflow fails if GitHub does not report the annotated tag as cryptographically verified.

### 4. Approve the `release` environment

After build and smoke tests pass, GitHub pauses before the publish job if required reviewers were configured. Review the workflow run and approve only the expected version/tag/commit.

### 5. GitHub publishes the release

A successful release contains exactly these uploaded assets:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
SHA256SUMS.txt.sigstore.json
```

GitHub also records provenance attestations for the release assets. GitHub's automatically generated source archives may still appear separately in the release UI; they are not Talvoro deployment distributions.

## Verify an official release

### SHA-256

Linux:

```bash
sha256sum -c SHA256SUMS.txt
```

macOS:

```bash
shasum -a 256 -c SHA256SUMS.txt
```

### Sigstore signature on the checksum manifest

For `v0.15.0` in the official repository:

```bash
cosign verify-blob SHA256SUMS.txt \
  --bundle SHA256SUMS.txt.sigstore.json \
  --certificate-identity 'https://github.com/DrRoglaa/talvoro/.github/workflows/release.yml@refs/tags/v0.15.0' \
  --certificate-oidc-issuer 'https://token.actions.githubusercontent.com'
```

The signature bundle signs the checksum manifest itself, so it is intentionally not listed inside `SHA256SUMS.txt` (doing that would create a circular dependency).

### GitHub build provenance

For each downloaded release archive:

```bash
gh attestation verify talvoro-v0.15.0.zip -R DrRoglaa/talvoro
gh attestation verify talvoro-v0.15.0-docker.zip -R DrRoglaa/talvoro
gh attestation verify talvoro-v0.15.0-webhosting.zip -R DrRoglaa/talvoro
```

## Failure behavior

A release is not published if any required gate fails, including:

- tag/version mismatch;
- lightweight or GitHub-unverified release tag;
- release commit not reachable from `main`;
- release regression test failure;
- deterministic package build/verification failure;
- Docker or Web Hosting smoke-test failure;
- checksum failure;
- Sigstore signing or immediate signature verification failure;
- GitHub provenance attestation failure;
- incomplete or unexpected GitHub Release asset set.

brrr is deliberately excluded from this list. Notifications are useful observability, not part of Talvoro's software trust root.
