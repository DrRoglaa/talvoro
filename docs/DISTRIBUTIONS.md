<div align="center">

# Talvoro Release Distributions

### One tagged source. Three deployment-ready packages.

**[Documentation](README.md)** · **[Docker](INSTALL-DOCKER.md)** · **[Web Hosting](INSTALL-WEB-HOSTING.md)** · **[Release Verification](GITHUB-RELEASES.md)**

</div>

---

Every official Talvoro release is generated from one exact tagged source tree and the repository-root `VERSION`. The exact Git commit referenced by the official signed release tag is the canonical source.

## At a glance

| Distribution | Package | Best for |
| --- | --- | --- |
| **Source / Standard** | `talvoro-vX.Y.Z.zip` | Developers, advanced users, custom deployments |
| **Docker** | `talvoro-vX.Y.Z-docker.zip` | VPSs, Docker servers, home servers, local development |
| **Web Hosting** | `talvoro-vX.Y.Z-webhosting.zip` | Conventional Apache-compatible PHP hosting without Docker |

> [!NOTE]
> These are not separate Talvoro editions. They are deployment distributions generated from the same application version.

## Source / Standard

```text
talvoro-vX.Y.Z.zip
```

Use this package when you need a clean Talvoro application distribution without Docker-specific deployment packaging.

Typical use cases include:

- custom server deployments;
- development and inspection;
- advanced automation;
- environments with their own runtime/deployment stack.

## Docker

```text
talvoro-vX.Y.Z-docker.zip
```

The Docker package combines the Talvoro application with the supported Docker deployment files for that release.

It is intended for:

- Linux VPSs;
- Docker-capable servers;
- home servers;
- Docker Desktop development environments;
- other supported container hosts.

See **[Install Talvoro with Docker](INSTALL-DOCKER.md)**.

## Web Hosting

```text
talvoro-vX.Y.Z-webhosting.zip
```

The Web Hosting package is prepared for conventional PHP/MySQL hosting and excludes Docker-only runtime files that a shared-hosting deployment does not need.

See **[Install Talvoro on Traditional Web Hosting](INSTALL-WEB-HOSTING.md)**.

## What stays consistent

All three distributions are generated from the same release source and contain the same Talvoro application version.

They carry the same core application code, including the relevant:

- PHP application code;
- templates;
- database migrations;
- public assets;
- `VERSION`.

Deployment-specific files may differ by package.

## `release.json`

Each archive receives a generated, distribution-specific `release.json`.

The manifest records release metadata and a SHA-256 file inventory for exactly the files shipped in that archive. This lets Talvoro's release verification detect missing, unexpected, or modified package content.

> [!IMPORTANT]
> `release.json` belongs to the generated distribution. It is release metadata, not a second version source. The repository-root `VERSION` remains authoritative.

## Official release asset set

A published release is expected to include the three ZIPs, SHA-256 manifest, and individual Sigstore bundles:

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

GitHub also records provenance attestations for the release assets.

## Integrity model

`SHA256SUMS.txt` is generated only after the release archives have been built and verified.

Official signing and provenance happen **after** that verified release set exists:

```text
tagged source
  ↓
three distributions
  ↓
archive verification
  ↓
SHA256SUMS.txt
  ↓
Sigstore signatures
  ↓
GitHub provenance
  ↓
publication
```

A release must fail rather than publish if required package, checksum, signature, provenance, or asset-set validation fails.

For complete verification commands, see **[GitHub Releases & Verification](GITHUB-RELEASES.md#verify-an-official-release)**.

---

[← Documentation home](README.md)
