# Talvoro

**A modern, privacy\-focused, self\-hosted CMS for Docker and traditional web hosting\.**

Talvoro is an independent content management system designed for people who want a clean, capable publishing platform without unnecessary cloud dependencies, tracking, or vendor lock\-in\.

> **Project status:** Talvoro is under active development and has not yet reached version 1.0.

---

## About Talvoro

Talvoro is being built around a few simple principles:

- Self\-hosted by design
- Privacy\-focused
- No mandatory external services
- Simple administration
- Modern and responsive interface
- Docker\-friendly
- Traditional web\-hosting support
- Straightforward backup and upgrade workflows
- Open development

The goal is to make Talvoro easy to run whether you have your own Docker server, VPS, home server, development machine, or conventional web hosting\.

---

## Project Status

Talvoro is currently in active pre\-1\.0 development\.

Breaking changes may still occur while the architecture, installer, upgrade system, administration experience, APIs, and release workflow are being finalized\.

For production use, always review the release notes and create a backup before upgrading\.

---

## Installation and Downloads

Official Talvoro releases provide three distributions built from the same tagged source version\.

### Source / Standard

```text
talvoro-vX.Y.Z.zip
```

For developers, custom deployments, advanced users, and anyone who wants a clean Talvoro application package without Docker\-specific packaging\.

### Docker

```text
talvoro-vX.Y.Z-docker.zip
```

For:

- VPS environments
- Docker servers
- home servers
- development machines
- other Docker\-capable systems

Full instructions will be maintained in:

```text
docs/INSTALL-DOCKER.md
```

### Traditional Web Hosting

```text
talvoro-vX.Y.Z-webhosting.zip
```

For conventional web\-hosting environments where Docker is not available\.

The goal is to make installation as straightforward as possible:

1. Download the release ZIP\.
2. Upload and extract it on your hosting\.
3. Create a MySQL/MariaDB database\.
4. Open your domain\.
5. Complete the Talvoro installer\.
6. Create the administrator account\.
7. Start building your site\.

Full instructions will be maintained in:

```text
docs/INSTALL-WEB-HOSTING.md
```

---

## Canonical Source

The canonical source of every official Talvoro release is the exact Git commit referenced by its signed version tag in the official GitHub repository\.

Example:

```text
v0.15.0
```

All release packages are generated from that same tagged source so the Source / Standard, Docker, and Web Hosting distributions cannot drift apart\.

Official repository:

```text
https://github.com/DrRoglaa/talvoro
```

---

## Releases

Stable Talvoro releases are published from the `main` branch and tagged using Semantic Versioning\.

Examples:

```text
v0.15.0
v0.15.1
v0.16.0
v1.0.0
```

Each official release is planned to include:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

Official releases use signed version tags and signed release verification data so users can confirm both authenticity and integrity\.

---

## Verify a Release

Every official release includes SHA\-256 checksums for all Talvoro distribution packages\.

Example `SHA256SUMS.txt`:

```text
<sha256>  talvoro-vX.Y.Z.zip
<sha256>  talvoro-vX.Y.Z-docker.zip
<sha256>  talvoro-vX.Y.Z-webhosting.zip
```

Verify the downloaded files with:

```bash
sha256sum -c SHA256SUMS.txt
```

The checksum manifest is also cryptographically signed:

```text
SHA256SUMS.txt.sig
```

Signature verification instructions will be documented alongside the Talvoro release\-signing system\.

A release must not be published if required signing or verification steps fail\.

---

## Development Branches

Talvoro uses a simple development model:

```text
main
└── stable and released code

dev
└── development of the next Talvoro release

feature/*
fix/*
docs/*
└── short-lived development branches
```

Normal development work is merged into `dev`\.

When a release is ready:

```text
feature/* / fix/* / docs/*
          ↓
         dev
          ↓
         main
          ↓
   signed vX.Y.Z tag
          ↓
 automated release workflow
```

---

## Automated Releases

Talvoro’s release workflow is intended to:

1. Validate the version\.
2. Confirm the Git tag matches the Talvoro version\.
3. Run automated tests\.
4. Validate database migrations\.
5. Build the Source / Standard release package\.
6. Build the Docker release package\.
7. Build the traditional web\-hosting release package\.
8. Smoke\-test the generated packages\.
9. Generate SHA\-256 checksums\.
10. Sign the release verification data\.
11. Create the GitHub Release\.
12. Attach the verified release artifacts\.

A release must fail rather than publish incomplete, inconsistent, or unsigned artifacts\.

---

## Updating Talvoro

Upgrade instructions will be maintained in:

```text
docs/UPGRADE.md
```

Always create a full backup before upgrading\.

---

## Backup and Restore

Talvoro documentation will include procedures for safely backing up and restoring:

- database data
- uploaded files
- site configuration
- persistent application data

Documentation:

```text
docs/BACKUP-RESTORE.md
```

---

## Privacy

Privacy is a core design principle of Talvoro\.

Talvoro should not require unnecessary third\-party analytics, mandatory cloud accounts, or external tracking services in order to operate\.

Site owners remain in control of their installation and data\.

---

## Security

Please do not publicly disclose security vulnerabilities through a normal GitHub issue\.

Security reporting instructions will be maintained in:

```text
SECURITY.md
```

Critical security fixes for supported Talvoro versions are intended to remain available to all supported users\.

---

## Documentation

Documentation will be maintained alongside the source code\.

Planned documentation includes:

```text
docs/
├── INSTALL-DOCKER.md
├── INSTALL-WEB-HOSTING.md
├── UPGRADE.md
├── BACKUP-RESTORE.md
├── TROUBLESHOOTING.md
└── DEVELOPMENT.md
```

---

## Contributing

Talvoro is developed publicly\.

Contribution guidelines will be maintained in:

```text
CONTRIBUTING.md
```

---

## Versioning

Talvoro follows Semantic Versioning:

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
0.15.0   New functionality
0.15.1   Bug fixes
0.16.0   Next feature milestone
1.0.0    First stable major release
```

Pre\-release versions may use tags such as:

```text
v0.16.0-alpha.1
v0.16.0-beta.1
v0.16.0-rc.1
```

---

## License

The Talvoro license will be published before the first stable release\.

Until a license is explicitly added to this repository, no additional rights should be assumed beyond those provided by applicable copyright law\.

---

## Talvoro

**Self\-hosted\. Private\. Yours\.**
