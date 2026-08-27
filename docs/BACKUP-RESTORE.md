<div align="center">

# Talvoro Backup & Restore

### A backup is only useful when it can rebuild a working site.

**[Documentation](README.md)** · **[Upgrade Guide](UPGRADE.md)** · **[Troubleshooting](TROUBLESHOOTING.md)**

</div>

---

> [!IMPORTANT]
> Copying Talvoro application source code is **not** a complete backup. Site data, uploads, configuration, and other persistent state must be protected separately.

## What a complete backup protects

| Area | Examples |
| --- | --- |
| Database | Content, accounts, settings, application state |
| Uploaded files | Media and user/site uploads |
| Site configuration | Environment-specific values that cannot be recreated from the release |
| Persistent application data | Other runtime data stored outside the release package |

Application release files can normally be downloaded again. Your site data cannot.

## The 3-2-1 baseline

Where practical, keep:

| Rule | Meaning |
| --- | --- |
| **3 copies** | The live data plus at least two backup copies |
| **2 storage types/systems** | Reduce dependence on one storage failure mode |
| **1 off-host copy** | Keep a copy away from the live Talvoro server |

> [!TIP]
> A backup strategy is stronger when it includes automated creation, independent storage, retention rules, and periodic restore tests.

## Before every upgrade

Create a recovery point **before** changing the running site:

1. back up the database;
2. back up uploads;
3. back up site-specific configuration;
4. record the installed Talvoro version;
5. verify that backup files are non-empty and readable;
6. preferably perform a test restore in a non-production environment.

## Database backup

Use the backup tooling appropriate for your database version and deployment.

A MySQL/MariaDB logical backup may look like:

```bash
mysqldump --single-transaction --routines --triggers \
  -h <host> -u <user> -p <database> > talvoro-database.sql
```

Exact flags depend on the environment.

> [!WARNING]
> Database dumps may contain authentication data, private content, email addresses, configuration, and other sensitive information. Protect them like production data.

## Uploaded files

Back up the complete persistent upload path or volume.

Preserve:

- filenames;
- directory structure;
- permissions where they matter.

A database-only backup cannot reconstruct media that exists only on disk.

## Configuration and secrets

Back up site-specific configuration that cannot be regenerated.

Never publish backups containing:

- passwords;
- database credentials;
- API keys;
- session secrets;
- private keys;
- production `.env` contents.

If backups contain secrets, encrypt and access-control them appropriately.

## Docker deployments

Identify persistent volumes first:

```bash
docker compose config
docker volume ls
```

The backup should cover the actual Talvoro and database data volumes.

> [!CAUTION]
> Containers are replaceable. Do not treat a container filesystem as persistent storage unless the deployment explicitly says it is.

## Traditional web hosting

A typical hosting backup requires both:

- a database export;
- a file backup containing uploads and required site-specific configuration.

Control-panel backups vary. Confirm whether the provider backs up **both database and files**, how long backups are retained, and how restores are performed.

## Backup naming

Make backups easy to identify.

Include:

```text
application version
environment/site
date or timestamp
data type
```

Example:

```text
talvoro-v0.15.0-production-2026-08-27-database.sql
talvoro-v0.15.0-production-2026-08-27-uploads.tar.gz
```

## Retention

Choose retention based on the importance and change rate of the site.

A reasonable starting model can include:

- recent daily backups;
- several weekly backups;
- monthly archival backups.

Adjust for business requirements, legal obligations, storage limits, and recovery objectives.

## Restore planning

Before restoring, determine:

| Question | Why it matters |
| --- | --- |
| Which Talvoro version created the backup? | Application/schema compatibility |
| Which database version was used? | Import/compatibility behavior |
| Docker or Web Hosting? | Different deployment restore steps |
| Which runtime version is required? | Application compatibility |
| Which secrets/keys are required? | Site may not start without them |
| Will current data be overwritten? | Restore can destroy newer production changes |

> [!CAUTION]
> A restore can overwrite current production data. Understand the target state before proceeding.

## General restore process

1. stop or isolate write traffic;
2. stop Talvoro services if required;
3. optionally preserve the current broken state for investigation;
4. restore the Talvoro application version compatible with the backup;
5. restore the database;
6. restore uploads and other persistent files;
7. restore required configuration/secrets;
8. verify permissions;
9. start Talvoro;
10. run post-restore checks.

## Database restore

A MySQL/MariaDB logical restore may look like:

```bash
mysql -h <host> -u <user> -p <database> < talvoro-database.sql
```

Exact commands vary by environment.

Do not import a production dump into an unrelated live database.

## Post-restore checklist

Confirm:

- public pages load;
- administrator sign-in works;
- expected content exists;
- uploaded media loads;
- settings are correct;
- database writes succeed;
- new uploads work;
- scheduled functions operate if configured;
- the Talvoro application version matches the restored schema/state.

## Test restores

> [!IMPORTANT]
> A successful backup command does not prove recoverability.

Periodically restore backups to a separate environment and confirm the result behaves as a working Talvoro site.

Record:

- restore duration;
- missing manual steps;
- required secrets;
- unexpected permission issues;
- any changes needed in the recovery runbook.

## Backup security

Backups can be more sensitive than the live application because they may contain the entire site dataset.

Protect them with:

- restricted access;
- encryption where appropriate;
- secure transfer;
- off-host storage;
- explicit retention and deletion rules.

---

[← Documentation home](README.md) · [Upgrade guide →](UPGRADE.md)
