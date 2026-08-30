<div align="center">

# Talvoro Documentation

### Install confidently. Operate safely. Verify everything.

Clear, task-focused guides for installing, maintaining, developing, securing, and releasing Talvoro.

**[Project README](../README.md)** · **[Security](../SECURITY.md)** · **[Contributing](../CONTRIBUTING.md)** · **[Support](../SUPPORT.md)**

</div>

---

> [!IMPORTANT]
> **Talvoro is in active pre-1.0 development.** Requirements, upgrade paths, installer behavior, and internal APIs may still change. Always read the release notes for the exact version you are using.

## Choose your path

| I want to… | Start here |
| --- | --- |
| Install Talvoro with Docker | **[Docker Installation](INSTALL-DOCKER.md)** |
| Install Talvoro on traditional PHP hosting | **[Web Hosting Installation](INSTALL-WEB-HOSTING.md)** |
| Choose the right release package | **[Release Distributions](DISTRIBUTIONS.md)** |
| Upgrade an existing site | **[Upgrade Guide](UPGRADE.md)** |
| Back up or restore a site | **[Backup & Restore](BACKUP-RESTORE.md)** |
| Diagnose a problem | **[Troubleshooting](TROUBLESHOOTING.md)** |
| Develop Talvoro | **[Development Guide](DEVELOPMENT.md)** |
| Understand the design system | **[Design System](DESIGN-SYSTEM.md)** |
| Build themes with optional demo content | **[Theme Starter Sites](THEME-STARTER-SITES.md)** |
| Build a release locally | **[Release Engineering](RELEASING.md)** |
| Publish or verify an official GitHub release | **[GitHub Releases & Verification](GITHUB-RELEASES.md)** |
| Report a vulnerability | **[Security Policy](../SECURITY.md)** |
| Contribute code or documentation | **[Contributing](../CONTRIBUTING.md)** |
| Support Talvoro | **[Support](../SUPPORT.md)** |

## Getting started

### Installation

| Guide | Best for |
| --- | --- |
| [Docker Installation](INSTALL-DOCKER.md) | VPSs, Docker servers, home servers, and development machines |
| [Web Hosting Installation](INSTALL-WEB-HOSTING.md) | Conventional Apache-compatible PHP/MySQL hosting |
| [Release Distributions](DISTRIBUTIONS.md) | Understanding Source, Docker, and Web Hosting packages |

> [!TIP]
> If you control the server and already use containers, start with **Docker**. If your provider gives you a classic PHP/MySQL hosting account, use the **Web Hosting** distribution.

## Operating Talvoro

| Guide | Purpose |
| --- | --- |
| [Upgrade Guide](UPGRADE.md) | Safely move an existing installation to a newer Talvoro release |
| [Backup & Restore](BACKUP-RESTORE.md) | Protect the database, uploads, configuration, and persistent data |
| [Troubleshooting](TROUBLESHOOTING.md) | Diagnose installation, runtime, database, upload, proxy, and upgrade issues |

## Development

| Guide | Purpose |
| --- | --- |
| [Development Guide](DEVELOPMENT.md) | Branches, pull requests, testing, migrations, packaging, and versioning |
| [Design System](DESIGN-SYSTEM.md) | Semantic tokens, section styles, themes, Visual Builder behavior, and safety |
| [Theme Starter Sites](THEME-STARTER-SITES.md) | Declarative starter manifests, ownership, repair, safe Delete Demo Data, and theme-author security rules |
| [Contributing](../CONTRIBUTING.md) | Contribution expectations, privacy principles, reviews, and issue guidance |

## Releases and trust

Talvoro treats release integrity as part of the product.

```text
main
  ↓
verified signed version tag
  ↓
exact tagged source
  ↓
deterministic release packages
  ↓
SHA-256 verification
  ↓
Sigstore signatures
  ↓
GitHub provenance attestations
  ↓
protected publication
```

| Guide | Purpose |
| --- | --- |
| [Release Engineering](RELEASING.md) | Local build, verification, reproducibility, and maintainer release rules |
| [GitHub Releases & Verification](GITHUB-RELEASES.md) | Protected GitHub workflow, Sigstore signing, provenance, and user verification |

## Security

Security-sensitive reports must not be filed as ordinary public issues.

> [!CAUTION]
> If you believe you found a vulnerability, follow **[SECURITY.md](../SECURITY.md)** and use GitHub private vulnerability reporting when it is available. Do not publish exploit details before a fix is available.

## Documentation conventions

Talvoro documentation uses a few consistent rules:

- commands are shown from the directory where they should be run;
- placeholders such as `X.Y.Z`, `<host>`, and `<service-name>` must be replaced with real values;
- release-specific instructions take precedence over generic guides;
- production logs, backups, `.env` files, credentials, and private keys must be sanitized before sharing;
- a failed integrity or signature check is a **stop condition**, not a warning to ignore.

---

<div align="center">

**Talvoro** · Self-hosted. Private. Yours.

[Project README](../README.md) · [Security](../SECURITY.md) · [Contributing](../CONTRIBUTING.md)

</div>
