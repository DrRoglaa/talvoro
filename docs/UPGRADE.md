# Upgrading Talvoro

This guide defines the general Talvoro upgrade process.

> Always read the release notes for the exact version you are installing. Release-specific instructions override this generic guide.

## Upgrade principles

A Talvoro upgrade should be:

- reversible through a verified backup
- performed from an official release
- verified before installation
- migration-aware
- tested before old backups are removed

Never assume that replacing application files is the only required step.

## 1. Confirm the upgrade path

Check:

- currently installed Talvoro version
- target Talvoro version
- release notes for all versions you are skipping
- minimum runtime requirements
- database migration notes
- removed or deprecated settings
- backup compatibility notes

Some future releases may require an intermediate upgrade.

## 2. Download the correct distribution

Choose the package matching the installation type:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
```

Do not switch deployment types during a normal upgrade unless the release documentation explicitly supports that migration.

## 3. Verify the release

Download:

```text
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

Verify checksums:

Linux:

```bash
sha256sum -c SHA256SUMS.txt
```

macOS:

```bash
shasum -a 256 -c SHA256SUMS.txt
```

Verify the release signature using the instructions published for the Talvoro signing system.

Stop if verification fails.

## 4. Create a full backup

Back up all persistent data.

At minimum:

- database
- uploaded files
- site-specific configuration
- persistent application data

See:

```text
docs/BACKUP-RESTORE.md
```

A backup that has never been tested is not a reliable recovery plan.

## 5. Record the current state

Before changing anything, record:

- installed Talvoro version
- runtime version
- database version
- active deployment type
- important environment settings
- enabled extensions or integrations
- current health/status output if available

This makes rollback and troubleshooting easier.

## 6. Place the site in a safe upgrade state

Depending on the release, this may mean:

- enabling maintenance mode
- temporarily stopping write traffic
- stopping Docker services
- preventing scheduled jobs from running during migration

Follow release-specific instructions.

## 7. Install the new application version

### Docker

Use the Docker package for the target release.

Typical flow:

```bash
docker compose down
```

Replace or update the application files according to the release instructions, then:

```bash
docker compose up -d --build
```

Do not delete persistent volumes during a normal upgrade.

### Traditional web hosting

Upload the verified new package.

Follow the release instructions for replacing application files while preserving:

- user uploads
- environment-specific configuration
- persistent data

Do not overwrite secrets with example configuration files.

## 8. Run database migrations

If the release includes migrations, run them exactly as documented.

Migration execution should:

- fail clearly on error
- not silently ignore failed statements
- preserve a reliable migration history
- be tested against supported upgrade paths before release

Do not manually mark a failed migration as successful unless the Talvoro documentation explicitly instructs you to do so.

## 9. Run post-upgrade checks

Confirm:

- public site loads
- administrator login works
- existing content is present
- media/uploads load
- new uploads work
- settings persist
- database writes work
- scheduled tasks work if configured
- no unexpected migration errors appear
- application health checks are successful

## 10. Review logs

Check application and platform logs for new errors.

Docker example:

```bash
docker compose logs --tail=200
```

Never publish unsanitized production logs.

## 11. Keep the backup

Do not immediately delete the pre-upgrade backup.

Keep it until the upgraded installation has been stable long enough for you to be confident that rollback is no longer required.

## Rollback

If the upgrade fails:

1. Stop the upgraded application.
2. Do not continue writing new production data.
3. Restore the application version compatible with the backup.
4. Restore the database.
5. Restore uploads and other persistent data.
6. Confirm configuration compatibility.
7. Start the restored version.
8. Run smoke checks.
9. Investigate the failed upgrade separately.

Do not restore only the database while leaving incompatible application files in place.

## Version skipping

Skipping versions may be supported, but must never be assumed.

If the target release requires migration steps introduced by intermediate versions, follow the documented supported path.

## Pre-1.0 warning

Talvoro may introduce breaking upgrade changes before version 1.0.

Always read the release notes for every pre-1.0 upgrade.
