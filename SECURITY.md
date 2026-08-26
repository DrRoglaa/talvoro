# Security Policy

Security is an important part of Talvoro.

Talvoro is a self-hosted content management system, so vulnerabilities may affect authentication, content integrity, uploaded files, private configuration, databases, or the systems on which Talvoro is deployed.

Please report suspected security vulnerabilities responsibly.

---

## Supported Versions

Talvoro is currently in active pre-1.0 development.

The exact supported-version policy may evolve before Talvoro 1.0.

Until a formal support matrix is published, security fixes will generally target the latest supported Talvoro release and, where practical, other actively supported release lines.

Critical security fixes for supported Talvoro versions are intended to remain available to all supported users.

---

## Reporting a Security Vulnerability

**Do not report suspected security vulnerabilities through a public GitHub issue, discussion, pull request, or other public channel.**

Public disclosure before a fix is available may place Talvoro installations at unnecessary risk.

Instead, use GitHub's private vulnerability reporting feature for this repository when it is available and enabled.

Repository:

```text
https://github.com/DrRoglaa/talvoro
```

If private vulnerability reporting is not yet enabled, please avoid publishing technical exploit details publicly until a private reporting channel is provided.

---

## What to Include

A useful vulnerability report should include as much relevant information as possible, such as:

- affected Talvoro version
- installation type
  - Source / Standard
  - Docker
  - Web Hosting
- operating system or hosting environment
- database type and version
- runtime version
- vulnerability category
- affected component
- steps to reproduce
- expected behavior
- actual behavior
- potential impact
- whether authentication is required
- whether administrator privileges are required
- proof-of-concept details, where appropriate
- suggested remediation, if known

Please keep proof-of-concept material minimal and focused on demonstrating the vulnerability safely.

---

## Please Do Not Include

Do not send unnecessary sensitive or personal data.

Examples include:

- real user passwords
- production session tokens
- private API keys
- private signing keys
- complete production database dumps
- unrelated customer or user information
- secrets copied from a production `.env` file

Sanitize logs and screenshots before including them.

---

## Good-Faith Security Research

We ask security researchers to:

- act in good faith
- avoid accessing data that is not necessary to demonstrate the issue
- avoid modifying or deleting user data
- avoid disrupting production services
- avoid denial-of-service testing against systems you do not own
- avoid social engineering
- avoid publishing vulnerability details before coordinated disclosure
- stop testing if sensitive user data is unexpectedly exposed

Only test systems you own or systems for which you have explicit authorization.

---

## Coordinated Disclosure

For a confirmed vulnerability, the intended process is:

1. Confirm and reproduce the issue.
2. Assess severity and affected versions.
3. Develop and test a fix.
4. Prepare any required migration or mitigation instructions.
5. Prepare a security advisory.
6. Publish fixed Talvoro releases.
7. Publish verification data for the release.
8. Disclose the vulnerability publicly once users have a reasonable opportunity to update.

Exact timelines may vary depending on severity and complexity.

---

## Security Releases

Official Talvoro releases are intended to use:

- signed Git version tags
- SHA-256 checksums
- cryptographically signed release verification data
- reproducible release packaging from the exact tagged source

Typical release artifacts include:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

Users should verify release checksums and signatures before installing or upgrading Talvoro.

---

## Release Authenticity

The canonical source for an official Talvoro release is the exact Git commit referenced by its official signed version tag in:

```text
https://github.com/DrRoglaa/talvoro
```

Release packages should be generated from that same tagged commit.

If a release artifact, checksum, signature, version tag, or source reference does not match, do not install the release until the discrepancy has been resolved.

---

## Secrets

The Talvoro repository must never contain:

- production passwords
- private API tokens
- database credentials
- production `.env` files
- SSH private keys
- release-signing private keys
- TLS private keys
- real production database dumps
- private user data

If a secret is accidentally committed, deleting the file from a later commit is not sufficient.

Treat the secret as compromised and rotate or revoke it immediately.

Repository history may also need to be cleaned where appropriate.

---

## Dependencies

Talvoro should keep runtime dependencies current and review security advisories affecting supported releases.

Security-related dependency updates should be tested before release and should not be delayed solely because they do not introduce user-visible features.

---

## Database Security

Production Talvoro deployments should:

- use a dedicated database user
- grant only required database permissions
- avoid exposing the database directly to the public internet
- use strong credentials
- keep database software supported and updated
- protect database backups as sensitive data

---

## Web Hosting Security

Traditional web-hosting installations should ensure that sensitive application files are not publicly accessible.

This includes:

- environment files
- configuration containing credentials
- backups
- logs
- private keys
- internal application directories not intended for public access

Use HTTPS for production installations.

---

## Docker Security

Docker installations should:

- keep Docker Engine and the host operating system updated
- avoid exposing the database service publicly unless explicitly required and secured
- use persistent volumes only where required
- protect secrets outside the repository
- avoid running unnecessary services
- review published ports
- verify release artifacts before deployment

---

## Backups

Backups may contain the complete Talvoro database, uploaded files, configuration, and secrets.

Treat backups as sensitive production data.

Backups should be:

- access-controlled
- stored securely
- encrypted where appropriate
- retained according to a defined policy
- periodically tested through restore procedures

See:

```text
docs/BACKUP-RESTORE.md
```

---

## Security Updates

Users are strongly encouraged to install security updates promptly.

Security advisories and release notes should explain:

- affected versions
- fixed versions
- severity
- required upgrade actions
- temporary mitigations, where applicable

---

## Public Issues

GitHub Issues are appropriate for normal bugs, feature requests, documentation issues, and non-sensitive problems.

They are **not** appropriate for vulnerabilities that could put Talvoro installations or users at risk.

When in doubt, treat the report as security-sensitive until it has been assessed.

---

## Thank You

Responsible vulnerability reports help make Talvoro safer for everyone.

Thank you for taking the time to report security issues carefully and privately.
