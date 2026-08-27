<div align="center">

# Contributing to Talvoro

### Focused changes. Clear tests. Privacy-first decisions.

Thank you for helping make Talvoro more reliable, secure, and useful.

**[Project README](README.md)** · **[Documentation](docs/README.md)** · **[Development Guide](docs/DEVELOPMENT.md)** · **[Security](SECURITY.md)**

</div>

---

> [!IMPORTANT]
> Talvoro is in active pre-1.0 development. Architecture, APIs, migrations, packaging, and contribution rules may continue to evolve.

> [!CAUTION]
> **Security vulnerabilities must not be reported in public issues, discussions, or pull requests.** Follow [SECURITY.md](SECURITY.md).

## Before you start

Please:

- search existing issues and pull requests;
- keep the change focused on one clear purpose;
- avoid mixing unrelated refactors with functional work;
- never include credentials, private data, production backups, or real user data;
- discuss large architectural changes before investing substantial implementation effort.

## Branch model

```text
feature/*   fix/*   docs/*
       \      |      /
             dev
              ↓
             main
              ↓
      signed vX.Y.Z tag
```

| Branch | Purpose |
| --- | --- |
| `main` | Stable/released code |
| `dev` | Integration branch for the next release |
| `feature/*` | Focused feature work |
| `fix/*` | Focused bug fixes |
| `docs/*` | Documentation-only changes |

Normal contribution pull requests target:

```text
dev
```

Do not open ordinary feature/fix PRs directly against `main`.

## Create a working branch

Start from current `dev`:

```bash
git switch dev
git pull --ff-only origin dev
```

Create a focused branch:

```bash
git switch -c feature/example
```

Examples:

```text
feature/media-library
feature/installer-validation
fix/session-timeout
fix/webhosting-permissions
docs/docker-installation
```

## Make the change complete

When relevant, update:

| Area | Expectation |
| --- | --- |
| Application code | Keep the change maintainable and consistent with project structure |
| Tests | Cover changed behavior where practical |
| Migrations | Use Talvoro's migration system |
| Installation docs | Update when deployment/setup changes |
| Upgrade docs | Update when compatibility or migration behavior changes |
| User docs | Update when visible behavior changes |
| Release packaging | Update/test when distribution contents change |

Changes that alter behavior should normally include tests.

## Coding expectations

Contributions should:

- prefer clear, maintainable code over cleverness;
- follow existing project structure/conventions;
- avoid unnecessary dependencies;
- preserve privacy-focused behavior;
- avoid mandatory external services without prior project discussion;
- keep supported deployment models working where applicable;
- fail clearly when an operation cannot be completed safely.

Avoid unrelated formatting-only changes inside functional PRs.

## Privacy principles

Talvoro is privacy-focused by design.

A contribution should not silently introduce:

```text
mandatory telemetry
hidden analytics
unnecessary tracking
mandatory cloud accounts
unnecessary third-party requests
unjustified collection of user data
```

Any feature that sends data to an external service should be explicit, documented, and carefully reviewed.

## Database migrations

Migration contributions should:

- be deterministic;
- use stable ordering;
- fail clearly;
- preserve supported upgrade paths;
- include regression coverage where practical;
- document destructive or irreversible behavior.

Normal Talvoro releases must not depend on manual production database edits.

## Tests

Before opening a PR, run the relevant local checks where practical.

For release-facing changes, the repository release tooling includes:

```bash
./scripts/release/test-release.sh
./scripts/release/build-release.sh
./scripts/release/verify-release.sh
./scripts/release/smoke-release-packages.sh
```

CI should also validate application behavior, installer behavior, migrations, syntax/static rules, and security checks as applicable.

Do not merge while required checks are failing.

If a meaningful test cannot be added, explain why in the PR.

## Commit messages

Use concise messages that describe the change.

Good:

```text
Add Docker installer validation
Fix session timeout handling
Document web-hosting upgrade process
Prevent invalid migration ordering
```

Avoid:

```text
fix
changes
update stuff
wip
```

Small logical commits are preferred.

## Pull requests

Open ordinary contribution PRs against `dev`.

A good PR description answers:

- What changed?
- Why is it needed?
- How was it tested?
- Are migrations included?
- Does installation/deployment change?
- Does upgrade behavior change?
- Was documentation updated?
- Are there limitations or follow-up tasks?

Screenshots are useful for meaningful UI changes.

## Review criteria

Review may consider:

| Area | Questions |
| --- | --- |
| Correctness | Does the change do what it claims? |
| Maintainability | Is the implementation clear and sustainable? |
| Security | Does it expand attack surface or weaken safeguards? |
| Privacy | Does it introduce new data collection or third-party traffic? |
| UX | Is user-facing behavior coherent and accessible? |
| Migration safety | Are existing installations protected? |
| Deployment | Do Docker/Web Hosting paths remain valid? |
| Tests | Is changed behavior adequately validated? |
| Documentation | Can users/operators understand the change? |
| Packaging | Does the release boundary remain clean? |

Approval does not guarantee immediate inclusion in a release.

## Versioning

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

Pre-release tags may use forms such as:

```text
v0.16.0-alpha.1
v0.16.0-beta.1
v0.16.0-rc.1
```

Contributors generally should not change `VERSION` unless the contribution is explicitly release preparation.

## Release trust

Official releases are generated from the exact signed version tag and publish:

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

GitHub provenance attestations are generated as part of the protected release workflow.

Contributors do **not** need access to release signing private keys. Human signing keys stay with maintainers; artifact signing is handled by the protected release process.

See [GitHub Releases & Verification](docs/GITHUB-RELEASES.md).

## Never commit secrets

Do not commit:

- `.env` files containing real credentials;
- database passwords;
- API keys;
- access tokens;
- SSH/TLS private keys;
- release-signing private keys;
- production database dumps;
- real user data;
- private backups.

> [!CAUTION]
> If a secret is committed, assume exposure and rotate/revoke it immediately. Deleting it in a later commit is not enough.

## Release packaging

Release packages should exclude development-only/private files, including:

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

Packaging changes should be tested across every affected distribution.

## Documentation

Documentation lives with the source.

The documentation hub is:

**[docs/README.md](docs/README.md)**

Update documentation when a change affects installation, deployment, upgrades, configuration, migrations, release verification, or user-visible behavior.

## Issues

Public GitHub Issues are appropriate for:

- reproducible bugs;
- feature proposals;
- documentation problems;
- non-sensitive technical discussions.

A useful bug report includes:

- Talvoro version;
- deployment type;
- environment;
- reproduction steps;
- expected behavior;
- actual behavior;
- sanitized logs.

Never include credentials or sensitive production data.

## Feature proposals

For larger changes, describe:

- the problem;
- proposed behavior;
- user benefit;
- Docker/Web Hosting impact;
- migration implications;
- privacy/security implications;
- alternatives considered.

Early discussion can prevent substantial work on a direction that does not fit Talvoro.

## License and contribution terms

Talvoro's final project license is still being determined.

Until repository license and contribution terms are explicitly published, review the current repository status before submitting substantial code.

Do not contribute code you do not have the right to submit or copy code from incompatible/proprietary sources.

## Community conduct

A formal Code of Conduct may be added as the community grows.

Until then, keep discussions professional, constructive, and focused on improving Talvoro.

---

<div align="center">

### Thank you

Thoughtful bug reports, documentation, tests, reviews, and code contributions all help Talvoro improve.

[Documentation](docs/README.md) · [Security](SECURITY.md)

</div>
