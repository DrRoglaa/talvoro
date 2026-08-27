<div align="center">

# Install Talvoro with Docker

### The recommended container-based deployment path.

**[Documentation](README.md)** · **[Distributions](DISTRIBUTIONS.md)** · **[Backup & Restore](BACKUP-RESTORE.md)** · **[Troubleshooting](TROUBLESHOOTING.md)**

</div>

---

> [!IMPORTANT]
> Talvoro is in active pre-1.0 development. Review the release notes for the exact version before installing or upgrading.

## Before you begin

| Requirement | Guidance |
| --- | --- |
| Docker | Docker Engine with Docker Compose support |
| Production host | Supported 64-bit Linux host |
| Storage | Enough space for application data, database, uploads, logs, and backups |
| Network | Domain name and HTTPS for public deployments |
| Local development | Docker Desktop on macOS or Windows is suitable |

## 1. Download the Docker release

Download the Docker distribution for the version you want to install:

```text
talvoro-vX.Y.Z-docker.zip
```

For release verification, also download:

```text
talvoro-vX.Y.Z-docker.zip.sigstore.json
SHA256SUMS.txt
SHA256SUMS.txt.sigstore.json
```

> [!CAUTION]
> Do not install a package that fails checksum or signature verification.

## 2. Verify the release

If you downloaded only the Docker ZIP, verify its SHA-256 entry rather than asking the checksum tool to validate distributions you did not download.

**Linux**

```bash
grep '  talvoro-vX.Y.Z-docker.zip$' SHA256SUMS.txt | sha256sum -c -
```

**macOS**

```bash
grep '  talvoro-vX.Y.Z-docker.zip$' SHA256SUMS.txt | shasum -a 256 -c -
```

Then verify the Sigstore bundle and, where desired, GitHub provenance using **[GitHub Releases & Verification](GITHUB-RELEASES.md#verify-an-official-release)**.

## 3. Extract the package

```bash
unzip talvoro-vX.Y.Z-docker.zip
cd talvoro
```

Inspect the package before starting it.

The exact package layout can evolve between pre-1.0 releases, but the Docker distribution includes the Talvoro application and supported Docker bootstrap/deployment files for that release.

## 4. Create the Docker environment file

Talvoro ships a Docker bootstrap template:

```text
.env.docker.example
```

Copy it to `.env`:

```bash
cp .env.docker.example .env
```

The template currently includes database bootstrap values such as:

```text
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_ROOT_PASSWORD
```

Replace placeholder passwords with long, unique values before first start.

> [!WARNING]
> Never commit a production `.env` file. Do not reuse the database root password as the application database-user password.

Application settings and `APP_KEY` are created by the browser installer and stored in Talvoro's protected configuration.

## 5. Start Talvoro

Build and start the stack:

```bash
docker compose up -d --build
```

Check service state:

```bash
docker compose ps
```

If health checks are configured, wait until required services report healthy.

## 6. Review logs if needed

All services:

```bash
docker compose logs --tail=200
```

One service:

```bash
docker compose logs --tail=200 <service-name>
```

> [!WARNING]
> Sanitize logs before sharing them. Logs can contain URLs, email addresses, tokens, paths, or other private information.

## 7. Complete the browser installer

Open the configured Talvoro URL.

The installer performs the remaining setup for the release, which may include:

- environment checks;
- database initialization;
- site configuration;
- administrator creation.

The exact installer flow may change before Talvoro 1.0.

## 8. Configure HTTPS

Every public production deployment should use HTTPS.

HTTPS may be terminated by the deployment's supported reverse proxy, Caddy, Nginx, Traefik, or another trusted proxy appropriate for your environment.

> [!CAUTION]
> Do not expose authentication or administrator pages over plain HTTP on a public network.

## 9. Identify persistent data

Treat containers as replaceable. Know exactly where persistent data lives.

Protect the storage used for:

- database data;
- uploaded media/files;
- application-generated persistent data;
- site-specific configuration that cannot be recreated from the release package.

Do not assume a container filesystem is a backup.

## 10. Establish backups before production use

At minimum, protect:

| Data | Why it matters |
| --- | --- |
| Database | Content, settings, accounts, application state |
| Uploads | User/site media that cannot be recreated from source |
| Configuration | Environment-specific values and protected site configuration |
| Other volumes | Any additional persistent runtime data |

Follow **[Backup & Restore](BACKUP-RESTORE.md)** and test a restore before relying on a backup strategy.

## Post-install checklist

Confirm:

- `docker compose ps` shows the expected services;
- the public site loads;
- administrator sign-in works;
- uploads work;
- database-backed settings persist;
- HTTPS is active in production;
- persistent volumes are known and backed up;
- no database port is unintentionally public.

## Updating Talvoro

Do not overwrite a running installation blindly.

Use **[Upgrading Talvoro](UPGRADE.md)** and the release notes for the exact target version.

A safe upgrade normally includes:

1. verify the new release;
2. create and test a backup;
3. place the site in a safe upgrade state;
4. update the application/deployment files;
5. run required migrations;
6. start the updated stack;
7. run smoke checks;
8. keep the pre-upgrade backup until the new version is proven stable.

## Uninstalling

Back up anything you want to keep, then stop the stack:

```bash
docker compose down
```

> [!CAUTION]
> Destructive volume-removal options can permanently delete database and uploaded data. Do not remove persistent volumes unless you intentionally want to destroy that data.

## First troubleshooting commands

```bash
docker compose ps
docker compose logs --tail=200
docker compose config
```

For deeper diagnosis, see **[Troubleshooting](TROUBLESHOOTING.md)**.

## Security baseline

- keep Docker Engine and the host OS supported and updated;
- use strong unique secrets;
- use HTTPS;
- do not expose the database publicly without a specific secured design;
- protect backups separately from the live host;
- verify official Talvoro release checksums and signatures before deployment.

---

[← Documentation home](README.md) · [Upgrade guide →](UPGRADE.md)
