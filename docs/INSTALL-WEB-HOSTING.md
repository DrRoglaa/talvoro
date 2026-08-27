<div align="center">

# Install Talvoro on Web Hosting

### A clean deployment path for conventional PHP/MySQL hosting.

**[Documentation](README.md)** · **[Distributions](DISTRIBUTIONS.md)** · **[Backup & Restore](BACKUP-RESTORE.md)** · **[Troubleshooting](TROUBLESHOOTING.md)**

</div>

---

> [!IMPORTANT]
> Talvoro is in active pre-1.0 development. Hosting requirements and installer behavior may change. Always follow the release notes for the exact version you are installing.

## Before you begin

A typical compatible hosting environment is expected to provide:

| Requirement | Guidance |
| --- | --- |
| PHP | A version supported by the selected Talvoro release |
| Database | MySQL or MariaDB |
| HTTPS | Required for production |
| Filesystem | Write access only to directories Talvoro actually needs |
| Web root | Ability to serve the release's intended public directory/layout |
| PHP extensions | The common extensions required by the selected release |

Confirm the release requirements with your hosting provider before uploading Talvoro.

## 1. Download the Web Hosting release

Download:

```text
talvoro-vX.Y.Z-webhosting.zip
```

For verification, also download:

```text
talvoro-vX.Y.Z-webhosting.zip.sigstore.json
SHA256SUMS.txt
SHA256SUMS.txt.sigstore.json
```

## 2. Verify the release

If you downloaded only the Web Hosting ZIP:

**Linux**

```bash
grep '  talvoro-vX.Y.Z-webhosting.zip$' SHA256SUMS.txt | sha256sum -c -
```

**macOS**

```bash
grep '  talvoro-vX.Y.Z-webhosting.zip$' SHA256SUMS.txt | shasum -a 256 -c -
```

Verify the Sigstore bundle and optional GitHub provenance using **[GitHub Releases & Verification](GITHUB-RELEASES.md#verify-an-official-release)**.

> [!CAUTION]
> Stop if release verification fails.

## 3. Create a dedicated database

In the hosting control panel:

1. create a MySQL or MariaDB database;
2. create a dedicated database user;
3. grant that user only the permissions required for the Talvoro database;
4. record the database host, port, name, username, and password securely.

Do not reuse an unnecessarily privileged database account shared with unrelated applications.

## 4. Upload and extract the package

Upload:

```text
talvoro-vX.Y.Z-webhosting.zip
```

to the directory chosen for the site, then extract it using the hosting control panel or SSH access.

> [!WARNING]
> Do not extract a fresh-install package over an existing production site unless the target release's upgrade instructions explicitly tell you to do so.

## 5. Configure the document root

Use the public/document-root layout documented by the selected release.

If your host lets you choose the domain's document root, point it to the directory intended to be web-accessible.

Internal application files, credentials, backups, logs, and protected configuration must not be exposed directly to the web.

## 6. Set safe permissions

Use the most restrictive permissions that still let Talvoro operate.

Avoid:

```text
world-writable application trees
```

Grant write access only where Talvoro needs it, such as supported upload or generated-data paths.

## 7. Open the installer

Visit your Talvoro domain in a browser.

The installer should validate the environment and request the information needed for setup, typically including:

- database host/name/user/password;
- site name;
- administrator account details.

Allow Talvoro to initialize the required database schema.

## 8. Validate the installation

After setup, confirm:

- the public site loads;
- administrator sign-in works;
- content/settings persist;
- uploads work;
- HTTPS is active;
- sensitive files are not directly accessible;
- mail works if you configured it.

## 9. Protect sensitive files

Production hosting must not publicly expose:

- environment files;
- credentials;
- backup archives;
- private keys;
- logs containing private information;
- internal application files not intended for public access.

Use the host's web-server configuration or control panel to block protected paths where necessary.

## 10. Establish backups

Before adding important content, confirm you can back up and restore:

- the Talvoro database;
- uploaded files;
- site-specific configuration;
- other persistent Talvoro data.

See **[Backup & Restore](BACKUP-RESTORE.md)**.

## Shared-hosting limitations

Some hosting providers restrict:

| Area | Possible limitation |
| --- | --- |
| PHP | Available versions or extensions |
| Database | Features, versions, connection policies |
| Jobs | Cron/background-process availability |
| Files | Permissions, quotas, upload size |
| CLI | SSH or command-line access |
| Runtime | Execution time and memory limits |

If a Talvoro environment check requires something the hosting plan cannot provide, use a compatible plan/host rather than bypassing the requirement.

## Updating Talvoro

Treat an upgrade differently from a fresh installation.

Before every upgrade:

1. read the target release notes;
2. verify the release package;
3. create a complete backup;
4. confirm the backup is usable;
5. preserve uploads and protected configuration;
6. follow the release-specific migration instructions.

See **[Upgrading Talvoro](UPGRADE.md)**.

## Before requesting support

Collect:

- Talvoro version;
- PHP version;
- database type and version;
- hosting environment;
- exact error message;
- reproduction steps;
- relevant **sanitized** logs.

Never publish passwords, tokens, private keys, `.env` contents, or production database dumps in an issue.

See **[Troubleshooting](TROUBLESHOOTING.md)**.

---

[← Documentation home](README.md) · [Upgrade guide →](UPGRADE.md)
