<div align="center">

# Talvoro GitHub Releases & Verification

### Protected publication with signed tags, Sigstore, provenance, and deterministic packages.

**[Documentation](README.md)** · **[Release Engineering](RELEASING.md)** · **[Distributions](DISTRIBUTIONS.md)**

</div>

---

Talvoro keeps release logic in `scripts/release/`. GitHub Actions orchestrates those repository scripts rather than hiding core release behavior inside workflow YAML.

## Trust chain

```text
main
  ↓
cryptographically verified annotated vX.Y.Z tag
  ↓
exact tagged commit + VERSION validation
  ↓
release regression tests
  ↓
deterministic Source / Docker / Web Hosting build
  ↓
archive + checksum verification
  ↓
Docker/Web Hosting package smoke tests
  ↓
SHA256SUMS.txt
  ↓
Sigstore bundle for every ZIP + SHA256SUMS.txt
  ↓
GitHub artifact provenance attestations
  ↓
protected release approval
  ↓
complete GitHub Release
```

The workflow never creates the version tag. A maintainer creates and pushes the signed tag only after the release commit is on `main`.

## Workflows

### Release checks

```text
.github/workflows/release-checks.yml
```

Runs on relevant pushes/pull requests for `dev` and `main`, plus manual dispatch.

It validates the release-facing path by running:

- release-script syntax checks;
- release-packaging regression tests;
- all three distribution builds;
- release/checksum verification;
- Docker/Web Hosting smoke tests;
- a check that validation did not modify tracked source.

The job uses read-only repository permission.

On a successful push to `main`, Talvoro can send a brrr **release-ready** notification. Failed branch checks can also notify brrr. Notification delivery is never a release trust gate.

### Publish signed release

```text
.github/workflows/release.yml
```

Runs for version-tag pushes matching the workflow's release-tag trigger.

The repository scripts enforce the exact supported release-tag/version relationship.

The build job verifies that the tag:

- is annotated;
- points to the checked-out commit;
- matches root `VERSION`;
- is reachable from `origin/main`;
- is reported by GitHub as cryptographically verified.

The publish job uses the protected:

```text
release
```

environment.

It downloads the already-verified build artifacts, verifies them again, keylessly signs each release ZIP plus `SHA256SUMS.txt`, immediately verifies each Sigstore bundle, creates GitHub provenance attestations, validates the exact release asset set, and only then publishes.

## One-time repository setup

### Protected `release` environment

Create:

```text
Settings → Environments → release
```

Recommended policy:

| Setting | Recommendation |
| --- | --- |
| Required reviewer | Enable |
| Prevent self-review | Leave off if the sole maintainer must approve their own release |
| Wait timer | Optional / normally off |
| Admin bypass | Disable for a meaningful release gate |
| Deployment branches/tags | Restrict to intended release tags such as `v*.*.*` |

The build/test job completes before the publication approval gate.

### brrr notifications

brrr is optional observability and is deliberately **not** part of Talvoro's software trust root.

Supported repository secrets:

```text
BRRR_WEBHOOK_SECRET
BRRR_WEBHOOK_URL
```

When both are configured, the helper prefers the webhook secret path.

Never commit either value.

Typical lifecycle notifications include:

- release checks failed;
- `main` is release-ready;
- release started;
- release published;
- signing/provenance/publication failed.

A brrr outage must not make a valid release fail.

### Release immutability

When repository release immutability is enabled, Talvoro's draft-first publication model allows the complete asset set to be uploaded and checked before the release is finalized.

### Protect `main`

Recommended policy:

- require pull requests before merge;
- require the Release checks status;
- require branches to be current where appropriate;
- block force pushes;
- block branch deletion;
- require signed commits if that matches maintainer policy.

A similar check policy can be applied to `dev`.

### GitHub Actions policy

Talvoro pins third-party actions to full commit SHAs.

If the repository uses an Actions allow-list, include the GitHub-maintained actions required by the workflows and the reviewed Sigstore/Cosign installer dependency.

## Release procedure

### 1. Prepare `dev`

Run locally:

```bash
./scripts/release/test-release.sh
./scripts/release/build-release.sh
./scripts/release/verify-release.sh
./scripts/release/smoke-release-packages.sh
```

Push the completed release work to `dev` and wait for Release checks to pass.

### 2. Promote `dev` to `main`

Merge through the normal pull-request process.

The exact commit being released must be reachable from `main` before the release tag is pushed.

Wait for the `main` Release checks to pass.

### 3. Confirm release-ready state

For a successful `main` push, the brrr release-ready notification may report:

```text
Talvoro X.Y.Z is ready for release
```

This is a readiness signal, not a published release.

### 4. Create the signed annotated tag

Confirm:

```bash
cat VERSION
git status
git log -1 --oneline
```

For version `0.15.0`:

```bash
git switch main
git pull --ff-only origin main

git tag -s v0.15.0 -m "Talvoro v0.15.0"
git tag -v v0.15.0

git rev-parse v0.15.0^{}
git rev-parse main
```

The dereferenced tag commit and intended `main` commit must match.

Then push only the release tag:

```bash
git push origin v0.15.0
```

> [!IMPORTANT]
> `git tag -s` uses the signing backend configured in Git. Keep the human signing private key on the maintainer machine/hardware. It does not belong in repository or Actions secrets.

### 5. Build and verify

The tag workflow validates the source/tag relationship and then runs the release regression/build/verification/smoke sequence.

No publication occurs if this stage fails.

### 6. Approve `release`

If a reviewer is required, GitHub pauses the protected publish job.

Review:

- version;
- tag;
- commit;
- completed build job;
- expected workflow.

Approve only when they match the intended release.

### 7. Sign, attest, and publish

The protected job:

1. re-verifies the official tag;
2. downloads the previously verified release set;
3. verifies release packages/checksums again;
4. installs the pinned Cosign tooling;
5. keylessly signs all three ZIPs and `SHA256SUMS.txt`;
6. immediately verifies every Sigstore bundle against the expected OIDC identity;
7. creates GitHub provenance attestations;
8. creates/reuses a draft release;
9. uploads the exact expected asset set;
10. validates the asset set;
11. publishes only when all gates succeed.

## Official asset set

A successful Talvoro release contains:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z.zip.sigstore.json

talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-docker.zip.sigstore.json

talvoro-vX.Y.Z-webhosting.zip
talvoro-vX.Y.Z-webhosting.zip.sigstore.json

SHA256SUMS.txt
SHA256SUMS.txt.sigstore.json
```

GitHub-generated source archives may appear separately in the release UI. They are not Talvoro deployment distributions.

## Verify an official release

### SHA-256

If all three Talvoro ZIPs are present:

**Linux**

```bash
sha256sum -c SHA256SUMS.txt
```

**macOS**

```bash
shasum -a 256 -c SHA256SUMS.txt
```

If you downloaded only one distribution, filter the manifest to that filename before running the checksum tool.

### Sigstore signatures

Every Talvoro deployment ZIP and `SHA256SUMS.txt` has its own Sigstore bundle.

For `v0.15.0`:

```bash
IDENTITY='https://github.com/DrRoglaa/talvoro/.github/workflows/release.yml@refs/tags/v0.15.0'
ISSUER='https://token.actions.githubusercontent.com'

for artifact in \
  talvoro-v0.15.0.zip \
  talvoro-v0.15.0-docker.zip \
  talvoro-v0.15.0-webhosting.zip \
  SHA256SUMS.txt
do
  cosign verify-blob "$artifact" \
    --bundle "$artifact.sigstore.json" \
    --certificate-identity "$IDENTITY" \
    --certificate-oidc-issuer "$ISSUER"
done
```

The bundles intentionally are not listed inside `SHA256SUMS.txt`; signing bundles after generating the checksum manifest avoids a circular dependency.

### GitHub provenance

Verify downloaded release archives with GitHub CLI:

```bash
gh attestation verify talvoro-v0.15.0.zip -R DrRoglaa/talvoro
gh attestation verify talvoro-v0.15.0-docker.zip -R DrRoglaa/talvoro
gh attestation verify talvoro-v0.15.0-webhosting.zip -R DrRoglaa/talvoro
```

## Failure behavior

The release must not publish when any required gate fails, including:

- tag/version mismatch;
- lightweight or GitHub-unverified release tag;
- tagged commit not reachable from `main`;
- release regression failure;
- deterministic build or archive verification failure;
- Docker/Web Hosting smoke-test failure;
- checksum failure;
- Sigstore signing/verification failure for any required artifact;
- provenance attestation failure;
- incomplete or unexpected release asset set.

> [!NOTE]
> brrr is deliberately excluded from this list. Notifications improve observability; they do not establish software authenticity.

---

[← Documentation home](README.md) · [Release engineering →](RELEASING.md)
