# Install Talvoro on Traditional Web Hosting

This guide describes the Talvoro installation path for conventional web hosting without Docker.

> Talvoro is still in active pre-1.0 development. Hosting requirements and installer behavior may change before version 1.0.

## Package

Download:

```text
talvoro-vX.Y.Z-webhosting.zip
```

Also download:

```text
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

## 1. Verify the release

Verify the downloaded ZIP before uploading it to your hosting account.

Linux:

```bash
sha256sum -c SHA256SUMS.txt
```

macOS:

```bash
shasum -a 256 -c SHA256SUMS.txt
```

Do not install a package that fails checksum or signature verification.

## 2. Hosting requirements

The exact requirements for each Talvoro release will be listed in that release's notes.

A typical installation is expected to require:

- a supported PHP version
- MySQL or MariaDB
- HTTPS
- writable directories required by Talvoro
- common PHP extensions required by the application
- permission to configure the site's document root or web directory as required by the release

Before uploading Talvoro, confirm that your hosting plan meets the requirements for the selected release.

## 3. Create the database

In your hosting control panel:

1. Create a new MySQL or MariaDB database.
2. Create a dedicated database user.
3. Assign the user only the permissions required for the Talvoro database.
4. Record the database host, port, database name, username, and password.

Do not reuse a database user with unnecessary access to unrelated databases.

## 4. Upload the package

Upload:

```text
talvoro-vX.Y.Z-webhosting.zip
```

to the directory selected for the site.

Extract the archive using your hosting control panel or SSH access.

Avoid extracting over an existing production installation unless the release-specific upgrade instructions explicitly tell you to do so.

## 5. Configure the document root

Talvoro may use a public web directory depending on the release structure.

If your hosting provider supports changing the document root, point the domain to the directory documented by the release.

Do not expose internal application files directly to the web unless Talvoro's package structure explicitly intends them to be public.

## 6. File permissions

Use the most restrictive permissions that still allow Talvoro to operate correctly.

Do not make the entire application world-writable.

Only directories that Talvoro needs to write to should receive write permissions for the web-server user.

## 7. Open the installer

Visit your Talvoro domain in a browser.

The installer should perform environment checks and request the information needed to complete setup.

Typical setup data may include:

- database host
- database name
- database username
- database password
- site name
- administrator account details

## 8. Complete installation

Allow Talvoro to initialize the required database schema.

When setup finishes:

- sign in to the administrator area
- confirm the site loads correctly
- confirm uploads work
- confirm database-backed settings persist
- review security-related settings
- configure mail only if required

## 9. Protect sensitive files

Production hosting must not publicly expose sensitive files such as:

- environment files
- credentials
- backup archives
- logs containing private data
- application secrets
- private keys

Where supported, use the web server or hosting control panel to block access to sensitive paths.

## 10. HTTPS

Use HTTPS for every production Talvoro installation.

Enable a trusted TLS certificate through your hosting provider or another supported certificate mechanism.

## 11. Backups

Before adding content or customizing the site, confirm that you know how to back up:

- the Talvoro database
- uploaded files
- site-specific configuration

See:

```text
docs/BACKUP-RESTORE.md
```

## 12. Updating

Use:

```text
docs/UPGRADE.md
```

Do not treat an upgrade as a fresh installation.

Before every upgrade:

1. Read release notes.
2. Verify the new package.
3. Create a full backup.
4. Confirm the backup can be restored.
5. Follow the version-specific migration instructions.

## Shared-hosting limitations

Some providers restrict:

- PHP versions or extensions
- cron jobs
- file permissions
- command-line access
- background processes
- database features
- maximum upload size
- execution time

If Talvoro reports an environment requirement that the host cannot provide, contact the hosting provider or use a compatible host.

## Troubleshooting

See:

```text
docs/TROUBLESHOOTING.md
```

Before reporting a problem, collect:

- Talvoro version
- PHP version
- database type and version
- hosting environment
- exact error message
- relevant sanitized logs
- steps to reproduce the issue

Never publish passwords, secrets, private keys, or database dumps in a public issue.
