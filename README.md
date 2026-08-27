<div align="center">

# Talvoro

### Self-hosted. Private. Yours.

A modern, privacy-focused content management system for people who want a capable publishing platform without unnecessary cloud dependencies, tracking, or vendor lock-in.

[![Release checks](https://github.com/DrRoglaa/talvoro/actions/workflows/release-checks.yml/badge.svg?branch=main)](https://github.com/DrRoglaa/talvoro/actions/workflows/release-checks.yml)
![Status](https://img.shields.io/badge/status-pre--1.0-5b5bd6)
![Self-hosted](https://img.shields.io/badge/self--hosted-yes-2ea44f)
![Privacy](https://img.shields.io/badge/privacy-first-0f766e)
![Docker](https://img.shields.io/badge/Docker-supported-2496ED?logo=docker&logoColor=white)
![Web hosting](https://img.shields.io/badge/web%20hosting-supported-6B7280)

**[Docker installation](docs/INSTALL-DOCKER.md)** ·
**[Web hosting installation](docs/INSTALL-WEB-HOSTING.md)** ·
**[Documentation](docs/README.md)** ·
**[Security](SECURITY.md)** ·
**[Contributing](CONTRIBUTING.md)** ·
**[Support Talvoro](SUPPORT.md)**

</div>

---

> [!IMPORTANT]
> **Talvoro is in active pre-1.0 development.** Breaking changes may still occur while the architecture, installer, upgrade system, APIs, administration experience, and release workflow mature. Review release notes and create a backup before upgrading.

## Why Talvoro

Talvoro is built around a straightforward idea: **your website and your data should remain under your control**.

| Principle | What it means |
| --- | --- |
| **Self-hosted by design** | Run Talvoro on your own VPS, server, development machine, home server, or compatible web host. |
| **Privacy-focused** | No mandatory analytics, tracking services, cloud account, or external platform is required to operate the CMS. |
| **Flexible deployment** | Use Docker where it fits, or deploy to traditional PHP/MySQL-compatible web hosting. |
| **Simple administration** | A clean, modern interface without unnecessary operational complexity. |
| **Predictable maintenance** | Backups, upgrades, release packaging, and verification are treated as first-class workflows. |
| **Verifiable releases** | Official packages are built from signed version tags and protected by checksums, Sigstore signatures, and provenance attestations. |

## Get started

Official Talvoro releases provide three distributions generated from the same tagged source version.

| Distribution | Package | Best for | Guide |
| --- | --- | --- | --- |
| **Source / Standard** | `talvoro-vX.Y.Z.zip` | Developers, advanced users, and custom deployments | [Distribution details](docs/DISTRIBUTIONS.md) |
| **Docker** | `talvoro-vX.Y.Z-docker.zip` | VPSs, Docker servers, home servers, and local development | [Install with Docker](docs/INSTALL-DOCKER.md) |
| **Web Hosting** | `talvoro-vX.Y.Z-webhosting.zip` | Conventional hosting where Docker is unavailable | [Install on web hosting](docs/INSTALL-WEB-HOSTING.md) |

> [!TIP]
> If you are unsure which package to choose, start with **Docker** when you control the server. Choose **Web Hosting** when your provider gives you a traditional PHP/MySQL hosting environment.

### Traditional web-hosting flow

1. Download the Web Hosting release ZIP.
2. Upload and extract it on your hosting account.
3. Create a MySQL/MariaDB database.
4. Open your domain in a browser.
5. Complete the Talvoro installer.
6. Create the administrator account.
7. Start building your site.

For deployment-specific requirements and details, use the installation guides above.

## Release integrity

Talvoro treats release authenticity as part of the product, not as an afterthought.

```text
main
  ↓
cryptographically verified annotated vX.Y.Z tag
  ↓
exact tagged commit + VERSION validation
  ↓
release regression tests
  ↓
deterministic Source / Docker / Web Hosting builds
  ↓
archive verification + SHA-256 checksums
  ↓
Docker / Web Hosting smoke tests
  ↓
Sigstore signature bundle for every ZIP + SHA256SUMS.txt
  ↓
GitHub build provenance attestations
  ↓
protected release approval
  ↓
GitHub Release
```

An official release is expected to contain:

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

A release must fail rather than publish incomplete, inconsistent, unverified, or unsigned artifacts.

### Verify a release

Verify package hashes:

**Linux**

```bash
sha256sum -c SHA256SUMS.txt
```

**macOS**

```bash
shasum -a 256 -c SHA256SUMS.txt
```

Each deployment ZIP and `SHA256SUMS.txt` also has its own Sigstore bundle, and GitHub records build provenance attestations for the release assets.

For the complete verification procedure, including Cosign and GitHub attestation commands, see **[GitHub Releases & Verification](docs/GITHUB-RELEASES.md#verify-an-official-release)**.

## Project status

Talvoro is currently being developed toward a stable `1.0.0` release.

The pre-1.0 phase is being used to harden:

- installation and upgrade behavior;
- database migrations and compatibility checks;
- content and administration workflows;
- Docker and traditional-hosting distributions;
- release reproducibility and verification;
- security and recovery procedures;
- the public extension and API surface.

Production users should treat pre-1.0 upgrades carefully and always maintain a current backup.

## Development model

Talvoro uses a deliberately small branch model:

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

- `main` contains stable/released code.
- `dev` contains development for the next Talvoro release.
- `feature/*`, `fix/*`, and `docs/*` are short-lived working branches.

Normal development is merged into `dev`. Release-ready work is promoted from `dev` to `main`, validated again, and only then tagged for publication.

See **[Development](docs/DEVELOPMENT.md)** and **[Releasing](docs/RELEASING.md)** for the detailed workflow.

## Documentation

| Topic | Document |
| --- | --- |
| Docker installation | [docs/INSTALL-DOCKER.md](docs/INSTALL-DOCKER.md) |
| Traditional web hosting | [docs/INSTALL-WEB-HOSTING.md](docs/INSTALL-WEB-HOSTING.md) |
| Distribution formats | [docs/DISTRIBUTIONS.md](docs/DISTRIBUTIONS.md) |
| Upgrading | [docs/UPGRADE.md](docs/UPGRADE.md) |
| Backup and restore | [docs/BACKUP-RESTORE.md](docs/BACKUP-RESTORE.md) |
| Troubleshooting | [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) |
| Development | [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) |
| Release process | [docs/RELEASING.md](docs/RELEASING.md) |
| GitHub release verification | [docs/GITHUB-RELEASES.md](docs/GITHUB-RELEASES.md) |
| Design system | [docs/DESIGN-SYSTEM.md](docs/DESIGN-SYSTEM.md) |

## Privacy

Privacy is a core Talvoro design principle.

Talvoro should not require unnecessary third-party analytics, mandatory cloud accounts, or external tracking services in order to operate. Site owners remain in control of their installation and data.

## Security

Please **do not report suspected security vulnerabilities through a public GitHub issue, discussion, pull request, or other public channel**.

Public disclosure before a fix is available may place Talvoro installations at unnecessary risk.

Follow the private reporting process described in **[SECURITY.md](SECURITY.md)**.

Critical security fixes for supported Talvoro versions are intended to remain available to all supported users.

## Contributing

Talvoro is developed publicly, and thoughtful contributions are welcome.

Before opening a pull request, read **[CONTRIBUTING.md](CONTRIBUTING.md)** and use the `dev` branch as the normal integration target unless the contribution guidelines specify otherwise.

## Support Talvoro

Talvoro is independently developed and maintained. Optional support helps fund continued development, testing, documentation, infrastructure, and security work.

Support is planned through:

- **PayPal** for convenient one-time support;
- **Bitcoin** for direct Bitcoin support.

See **[SUPPORT.md](SUPPORT.md)** for the official payment details and verification guidance.

> [!NOTE]
> Supporting Talvoro is completely optional. Sponsorship does not change access to the Community edition or critical security updates for supported versions, and it does not purchase roadmap priority or special product access.

## Versioning

Talvoro follows [Semantic Versioning](https://semver.org/):

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
0.15.0   feature milestone
0.15.1   patch release
0.16.0   next feature milestone
1.0.0    first stable major release
```

Pre-release tags may use forms such as:

```text
v0.16.0-alpha.1
v0.16.0-beta.1
v0.16.0-rc.1
```

## License

The Talvoro license will be published before the first stable release.

Until a license is explicitly added to this repository, no additional rights should be assumed beyond those provided by applicable copyright law.

---

<div align="center">

### Talvoro

**Self-hosted. Private. Yours.**

Built for people who want to own their publishing stack.

</div>
