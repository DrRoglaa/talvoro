<div align="center">

# Upgrading Talvoro

### Verify first. Back up second. Change production last.

**[Documentation](README.md)** · **[Backup & Restore](BACKUP-RESTORE.md)** · **[Release Verification](GITHUB-RELEASES.md)** · **[Troubleshooting](TROUBLESHOOTING.md)**

</div>

---

> [!CAUTION]
> Always read the release notes for the exact target version. Release-specific instructions override this generic guide.

## Upgrade principles

A safe Talvoro upgrade should be:

| Principle | Meaning |
| --- | --- |
| **Recoverable** | A verified pre-upgrade backup exists |
| **Authentic** | The target package comes from an official release |
| **Verified** | Checksums/signatures are validated before installation |
| **Migration-aware** | Schema/configuration changes are handled explicitly |
| **Observed** | Post-upgrade health and logs are reviewed |
| **Reversible** | The old recovery point is kept until the new version is proven stable |

Never assume that replacing application files is the only required step.

## 1. Confirm the supported upgrade path

Record and review:

- currently installed Talvoro version;
- target Talvoro version;
- release notes for versions you are skipping;
- minimum runtime requirements;
- database migration notes;
- removed/deprecated settings;
- backup compatibility notes.

Some pre-1.0 releases may require an intermediate upgrade.

## 2. Download the correct distribution

Stay with the deployment model your site already uses unless Talvoro explicitly documents a migration between models.

```text
Source / Standard    talvoro-vX.Y.Z.zip
Docker               talvoro-vX.Y.Z-docker.zip
Web Hosting          talvoro-vX.Y.Z-webhosting.zip
```

## 3. Verify the release

Download the matching ZIP and its Sigstore bundle, plus:

```text
SHA256SUMS.txt
SHA256SUMS.txt.sigstore.json
```

Use **[GitHub Releases & Verification](GITHUB-RELEASES.md#verify-an-official-release)** to verify SHA-256, Sigstore identity, and optional GitHub provenance.

> [!CAUTION]
> If verification fails, stop. Do not continue with the upgrade.

## 4. Create a complete backup

Protect at least:

- database;
- uploads;
- site-specific configuration;
- persistent application data.

See **[Backup & Restore](BACKUP-RESTORE.md)**.

> [!IMPORTANT]
> A backup that has never been tested is not a reliable recovery plan.

## 5. Record the current state

Before changing production, record:

| Item | Example |
| --- | --- |
| Talvoro version | `0.15.0` |
| Deployment type | Docker / Web Hosting / custom |
| Runtime | PHP/runtime version |
| Database | MySQL/MariaDB version |
| Important environment values | Non-secret configuration summary |
| Integrations | Enabled external services/extensions |
| Health state | Current health/status output where available |

This makes rollback and diagnosis far easier.

## 6. Put the site into a safe upgrade state

Depending on the release, this may involve:

- maintenance mode;
- stopping new write traffic;
- pausing scheduled jobs;
- stopping Docker services.

Use the target release instructions.

## 7. Install the new application version

### Docker

A typical flow may include:

```bash
docker compose down
```

Update the deployment/application files as documented by the release, then:

```bash
docker compose up -d --build
```

> [!CAUTION]
> Do not delete persistent volumes during a normal upgrade.

### Traditional web hosting

Upload the **verified** target package and replace files only according to the release instructions.

Preserve:

- user uploads;
- protected configuration;
- environment-specific secrets;
- other persistent data.

Do not overwrite production secrets with example files.

## 8. Run required database migrations

If the release includes migrations, run them exactly as documented.

A migration system should:

- fail clearly on error;
- preserve stable ordering/history;
- not silently ignore failed statements;
- be tested against supported upgrade paths.

Do not manually mark a failed migration successful unless Talvoro documentation explicitly instructs you to do so.

## 9. Validate the upgraded site

Confirm:

- public site loads;
- administrator sign-in works;
- existing content is present;
- existing media loads;
- new uploads work;
- settings persist;
- database writes succeed;
- scheduled tasks work if configured;
- health checks are successful;
- there are no unexplained migration errors.

## 10. Review logs

Docker example:

```bash
docker compose logs --tail=200
```

Review application, PHP/runtime, database, proxy, and hosting logs as relevant.

Never publish unsanitized production logs.

## 11. Keep the recovery point

Do not delete the pre-upgrade backup immediately.

Keep it until the new release has been stable long enough that rollback is no longer part of the expected recovery plan.

## Rollback

If the upgrade fails:

1. stop the upgraded application and new write traffic;
2. restore the Talvoro version compatible with the backup;
3. restore the database;
4. restore uploads and persistent data;
5. restore compatible configuration/secrets;
6. start the restored version;
7. run smoke checks;
8. investigate the failed upgrade separately.

> [!WARNING]
> Do not restore only the database while leaving an incompatible application version in place.

## Version skipping

Skipping versions may be supported, but never assume it is safe.

If intermediate releases introduced required migration steps, follow the documented supported path.

## Pre-1.0 rule

Talvoro may introduce breaking upgrade changes before `1.0.0`.

For every pre-1.0 upgrade:

```text
read release notes
→ verify package
→ verify backup
→ follow exact migration path
→ validate after upgrade
```

---

[← Documentation home](README.md) · [Troubleshooting →](TROUBLESHOOTING.md)
