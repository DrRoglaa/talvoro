<div align="center">

# Talvoro Security Policy

### Report privately. Verify releases. Protect production data.

**[Project README](README.md)** · **[Documentation](docs/README.md)** · **[Release Verification](docs/GITHUB-RELEASES.md)**

</div>

---

> [!CAUTION]
> **Do not report suspected security vulnerabilities through a public GitHub issue, discussion, pull request, comment, or public proof-of-concept repository.**
>
> Public disclosure before a fix is available may place Talvoro installations at unnecessary risk.

Talvoro is self-hosted software. Security issues can affect authentication, content integrity, uploaded files, protected configuration, databases, backups, or the host on which Talvoro runs.

## Supported versions

Talvoro is currently in active pre-1.0 development.

Until a formal support matrix is published, security fixes will generally target the latest supported release and, where practical, other actively supported release lines.

Critical security fixes for supported Talvoro versions are intended to remain available to all supported users.

## Report a vulnerability

Use GitHub's **private vulnerability reporting** feature for this repository when it is available and enabled.

Repository:

```text
https://github.com/DrRoglaa/talvoro
```

In GitHub, look under the repository **Security** area for the private vulnerability-reporting option.

If private reporting is temporarily unavailable, avoid publishing technical exploit details until a private contact path is provided.

## What to include

A useful report includes the minimum information needed to understand and reproduce the issue.

| Area | Useful detail |
| --- | --- |
| Version | Affected Talvoro version(s) |
| Distribution | Source / Standard, Docker, or Web Hosting |
| Environment | OS/hosting provider and runtime version |
| Database | Type and version |
| Component | Affected route, feature, parser, installer, API, etc. |
| Reproduction | Minimal safe steps |
| Impact | What an attacker could gain/change |
| Privileges | Whether authentication/admin rights are required |
| Evidence | Minimal proof of concept, sanitized logs/screenshots |
| Remediation | Suggested fix, if known |

Keep proof-of-concept material narrow and safe.

## Do not include unnecessary sensitive data

Do not send:

- real user passwords;
- production session tokens;
- private API keys;
- private signing keys;
- complete production database dumps;
- unrelated customer/user information;
- raw production `.env` contents.

Sanitize logs and screenshots before attaching them.

## Good-faith security research

We ask researchers to:

- test only systems they own or are explicitly authorized to test;
- minimize access to data;
- avoid modifying/deleting user data;
- avoid disruption of production services;
- avoid denial-of-service testing against third-party systems;
- avoid social engineering;
- stop testing if sensitive data is unexpectedly exposed;
- coordinate disclosure until users have a reasonable opportunity to update.

This section describes Talvoro's requested research behavior; it is not permission to test systems you do not control.

## Coordinated disclosure

For a confirmed vulnerability, the intended process is:

```text
report privately
  ↓
confirm and reproduce
  ↓
assess affected versions/severity
  ↓
develop + test remediation
  ↓
prepare migrations/mitigations if needed
  ↓
prepare advisory
  ↓
publish fixed signed releases
  ↓
allow users reasonable update time
  ↓
coordinate public disclosure
```

Exact timing depends on severity, exploitability, and fix complexity.

## Security releases

Talvoro's official release trust model includes:

- cryptographically verified signed Git version tags;
- deterministic release packaging from the exact tagged source;
- SHA-256 package checksums;
- individual Sigstore signatures for every release ZIP;
- a Sigstore signature for `SHA256SUMS.txt`;
- GitHub build provenance attestations;
- protected release publication.

Expected Talvoro release assets:

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

Users should verify official releases before installation or upgrade.

See **[GitHub Releases & Verification](docs/GITHUB-RELEASES.md)**.

## Release authenticity

The canonical source for an official Talvoro release is the exact Git commit referenced by its official signed version tag in:

```text
https://github.com/DrRoglaa/talvoro
```

All deployment distributions are generated from that same tagged source.

> [!CAUTION]
> If the tag, version, checksum, signature, provenance, or release asset set does not match, do not install the release until the discrepancy is resolved.

## Imported theme and Starter Site security

Talvoro imported themes are intentionally constrained packages. A theme may provide CSS, validated local image assets, and—starting with Talvoro 0.17.0—an optional declarative `starter/starter.json`.

A Starter Site is data, not executable code. Theme packages cannot use Starter Sites to run PHP, JavaScript, shell commands, SQL, arbitrary filesystem writes, unrestricted local file imports, or remote asset downloads. Talvoro core validates the complete starter definition before installation and performs all database/media operations through registered CMS resource adapters.

Starter packages reject path traversal, unsafe/remote media paths, unsupported files, duplicate resource keys, malformed or oversized JSON, unsupported resource types/fields, invalid or cyclic references, and other schema violations. Starter media is re-verified against its import-time SHA-256 before it is copied into the Media Library.

Starter Site install, repair, and **Delete Demo Data** require `starter_sites.manage`, authentication, authorization, CSRF protection, and POST requests. Theme import and activation never install content automatically. Delete Demo Data operates only on ownership records created by a Starter Site installation and preserves modified or unrelated user content when safe deletion cannot be proven.

See **[Theme Starter Sites](docs/THEME-STARTER-SITES.md)** for the full declarative package contract and limits.

## Repository secrets

The Talvoro repository must never contain:

```text
production passwords
private API/access tokens
database credentials
production .env files
SSH/TLS private keys
release-signing private keys
real production database dumps
private user/customer data
private backups
```

If a secret is accidentally committed:

1. treat it as compromised;
2. rotate or revoke it immediately;
3. assess any systems that trusted it;
4. clean repository history where appropriate.

Deleting the secret only in a later commit is not sufficient.

## Dependency security

Talvoro should keep supported runtime dependencies current and review security advisories that affect supported releases.

Security updates should be tested, but should not be delayed merely because they contain no user-visible feature.

## Database security

Production deployments should:

- use a dedicated database user;
- grant only required permissions;
- keep database software supported and updated;
- use strong unique credentials;
- avoid exposing database ports directly to the public internet;
- protect database backups as sensitive data.

## Web Hosting security

Traditional hosting should prevent public access to:

- environment/configuration files;
- backups;
- logs;
- private keys;
- internal application directories not intended to be public.

Use HTTPS in production.

## Docker security

Docker deployments should:

- keep Docker Engine and the host OS supported and updated;
- review published ports;
- avoid public database exposure;
- keep secrets outside the repository;
- avoid unnecessary services;
- use persistent storage only where intended;
- verify Talvoro release artifacts before deployment.

## Backup security

Backups may contain a complete copy of the site, including data and secrets.

Backups should be:

- access-controlled;
- stored securely;
- encrypted where appropriate;
- retained under a defined policy;
- periodically tested through restore procedures.

See **[Backup & Restore](docs/BACKUP-RESTORE.md)**.

## Security updates

Install security updates promptly.

A security advisory should explain, where applicable:

- affected versions;
- fixed versions;
- severity;
- required upgrade actions;
- temporary mitigations.

## Public issues

Public GitHub Issues are appropriate for normal bugs, feature requests, documentation issues, and non-sensitive technical problems.

They are **not** appropriate for a vulnerability that could put Talvoro installations or users at risk.

When unsure, treat a report as security-sensitive until it has been assessed.

---

<div align="center">

### Thank you

Responsible private reports help keep Talvoro installations safer.

[Documentation](docs/README.md) · [Release Verification](docs/GITHUB-RELEASES.md)

</div>
