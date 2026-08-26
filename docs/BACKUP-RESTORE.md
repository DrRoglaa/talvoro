# Talvoro Backup and Restore

A Talvoro backup must protect the data required to reconstruct a working site.

Copying only the application source code is not a complete backup.

## What to back up

At minimum, identify and protect:

- database
- uploaded media and files
- site-specific configuration
- environment-specific settings
- other persistent Talvoro data

Application release files can normally be downloaded again from the official repository, but site data cannot.

## Backup rule

Use the 3-2-1 principle where practical:

- 3 copies of important data
- 2 different storage media or systems
- 1 copy stored separately from the live server

## Database backup

Use the backup tool appropriate for the deployed database.

For MySQL/MariaDB, a logical dump may be suitable.

Example pattern:

```bash
mysqldump --single-transaction --routines --triggers   -h <host> -u <user> -p <database> > talvoro-database.sql
```

Exact options depend on your environment and database version.

Protect database dumps because they may contain private or sensitive site data.

## Uploaded files

Back up the complete persistent upload directory or volume.

Preserve:

- filenames
- directory structure
- permissions where relevant

## Configuration

Back up site-specific configuration that cannot be reconstructed from the release package.

Never publish backups containing:

- passwords
- API keys
- private keys
- session secrets
- database credentials

## Docker backups

For Docker deployments, identify persistent volumes before backing up.

Useful commands may include:

```bash
docker compose config
docker volume ls
```

Do not assume container filesystems are persistent.

The backup should cover the data volumes used by Talvoro and its database.

## Web-hosting backups

Traditional hosting usually requires:

- database export
- file backup of uploads and configuration

Many hosting control panels provide both database and file backup features.

Confirm whether your hosting provider's backup includes databases or only files.

## Backup before upgrade

Before every Talvoro upgrade:

1. Create a database backup.
2. Back up uploads.
3. Back up site-specific configuration.
4. Record the installed Talvoro version.
5. Verify that backup files are non-empty and readable.
6. Preferably test the restore in a non-production environment.

## Backup naming

Use clear names containing:

- Talvoro version
- site/environment
- date and time

Example:

```text
talvoro-v0.15.0-production-2026-08-27-database.sql
talvoro-v0.15.0-production-2026-08-27-uploads.tar.gz
```

## Retention

Choose a retention policy suitable for the importance and rate of change of the site.

A reasonable starting point may include:

- several recent daily backups
- several weekly backups
- monthly archival backups

Adapt retention to available storage and business requirements.

## Restore prerequisites

Before restoring, determine:

- Talvoro version associated with the backup
- database version
- deployment type
- required runtime version
- whether encryption keys or secrets are needed
- whether the target installation contains newer data that will be lost

A restore may overwrite current production data.

## Restore process

General restore flow:

1. Put the site into maintenance mode or stop write traffic.
2. Stop Talvoro services if required.
3. Back up the current broken state if it may help investigation.
4. Restore the application version compatible with the backup.
5. Restore the database.
6. Restore uploads and persistent files.
7. Restore required configuration and secrets.
8. Verify permissions.
9. Start Talvoro.
10. Run smoke checks.

## Database restore

For MySQL/MariaDB, a logical restore may look like:

```bash
mysql -h <host> -u <user> -p <database> < talvoro-database.sql
```

The exact command depends on your environment.

Do not import a production dump into an unrelated live database.

## Post-restore checks

Confirm:

- public site loads
- administrator login works
- content exists
- uploaded media loads
- site settings are correct
- database writes succeed
- scheduled functions operate if configured
- Talvoro version matches the restored schema

## Test restores

Periodically test backups by restoring them to a separate environment.

A successful backup command does not guarantee that the resulting backup can actually restore a working site.

## Security

Backups can be more sensitive than the live application because they often contain the entire site dataset.

Protect them with:

- restricted access
- encryption where appropriate
- secure transfer
- off-host storage
- defined retention and deletion policies
