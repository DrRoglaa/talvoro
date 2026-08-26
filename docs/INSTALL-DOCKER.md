# Install Talvoro with Docker

This guide describes the recommended Docker-based installation path for Talvoro.

> Talvoro is still in active pre-1.0 development. Review the release notes before installing or upgrading.

## Package

Download the Docker distribution for the version you want to install:

```text
talvoro-vX.Y.Z-docker.zip
```

Also download:

```text
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

## 1. Verify the download

Verify the SHA-256 checksum before extracting the release.

On Linux:

```bash
sha256sum -c SHA256SUMS.txt
```

On macOS:

```bash
shasum -a 256 -c SHA256SUMS.txt
```

Signature verification instructions will be documented with the Talvoro release-signing setup.

Do not continue if the checksum or signature verification fails.

## 2. Requirements

Recommended host requirements:

- Docker Engine with Docker Compose support
- A supported 64-bit Linux host for production
- Sufficient disk space for the application, database, uploads, logs, and backups
- A domain name for public deployments
- HTTPS for production
- Outbound network access if the deployment needs to retrieve container images or updates

For local development, Docker Desktop on macOS or Windows can also be used.

## 3. Extract the package

Example:

```bash
unzip talvoro-vX.Y.Z-docker.zip
cd talvoro-vX.Y.Z-docker
```

Inspect the included files before starting the stack.

A release may include files such as:

```text
compose.yaml
Dockerfile
.env.example
README.md
VERSION
```

The exact contents may evolve between pre-1.0 releases.

## 4. Create the environment file

If the release contains `.env.example`, copy it:

```bash
cp .env.example .env
```

Open `.env` and configure all required values.

Never commit your production `.env` file to Git.

Use strong, unique secrets for:

- application keys
- database passwords
- administrator bootstrap secrets, if applicable
- mail credentials, if configured

## 5. Start Talvoro

Build and start the stack:

```bash
docker compose up -d --build
```

Check container status:

```bash
docker compose ps
```

If a health check is provided, wait until the required services report healthy before continuing.

## 6. Review logs

If Talvoro does not start correctly:

```bash
docker compose logs --tail=200
```

For a specific service:

```bash
docker compose logs --tail=200 <service-name>
```

Do not publish logs publicly without first checking them for secrets, tokens, email addresses, or other private data.

## 7. Complete the installer

Open the configured Talvoro URL in your browser.

The installer should guide you through the remaining setup, such as:

- database initialization
- site configuration
- administrator creation
- environment checks

The exact installer flow may change before Talvoro 1.0.

## 8. Configure HTTPS

Production Talvoro installations should use HTTPS.

HTTPS may be handled by:

- a reverse proxy included with the deployment
- Caddy
- Nginx
- Traefik
- another trusted reverse proxy

Do not expose administrative or authentication pages over plain HTTP on a public network.

## 9. Persistent data

Before using Talvoro in production, identify every persistent path or Docker volume used for:

- database data
- uploaded files
- application-generated persistent files
- configuration that is not reproducible from the release package

Back up persistent data separately from the application containers.

Containers themselves should be treated as replaceable.

## 10. Backups

Before every upgrade, create a verified backup.

See:

```text
docs/BACKUP-RESTORE.md
```

At minimum, back up:

- the database
- uploaded files
- site-specific configuration
- any additional persistent volumes

## 11. Updating Talvoro

Do not overwrite a running installation blindly.

Use the release-specific upgrade instructions and:

```text
docs/UPGRADE.md
```

A normal upgrade flow is expected to include:

1. Read the release notes.
2. Create and verify a backup.
3. Verify the new release.
4. Stop or place the site in maintenance mode if required.
5. Replace application code/images.
6. Run required migrations.
7. Start the updated stack.
8. Run smoke checks.
9. Confirm the site and administrator area work correctly.

## 12. Uninstalling

Before removing Talvoro, export or back up any data you want to keep.

Then stop the stack:

```bash
docker compose down
```

Be careful with destructive options that remove volumes. Removing a database volume may permanently delete site data.

## Troubleshooting

See:

```text
docs/TROUBLESHOOTING.md
```

Useful first checks:

```bash
docker compose ps
docker compose logs --tail=200
docker compose config
```

## Security notes

- Keep Docker and the host OS updated.
- Do not expose the database service publicly unless there is a specific and secured reason.
- Use strong secrets.
- Use HTTPS.
- Keep backups outside the live Talvoro host when possible.
- Verify official Talvoro release checksums and signatures.
