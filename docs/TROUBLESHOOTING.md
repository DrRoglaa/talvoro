# Talvoro Troubleshooting

This guide covers general troubleshooting steps for Talvoro.

Before changing data or configuration, create a backup when practical.

## Start with these checks

Record:

- Talvoro version
- deployment type
- operating system or hosting provider
- PHP/runtime version if applicable
- database type and version
- exact error message
- time the problem occurred
- steps required to reproduce it

Avoid making several unrelated changes at once.

## Verify the release

If the problem started immediately after installation or upgrade, confirm that the package matches the official release checksum.

Linux:

```bash
sha256sum -c SHA256SUMS.txt
```

macOS:

```bash
shasum -a 256 -c SHA256SUMS.txt
```

Also verify the release signature where applicable.

## Docker: check service status

Run:

```bash
docker compose ps
```

Look for:

- stopped services
- restart loops
- unhealthy services
- missing dependencies

## Docker: inspect logs

```bash
docker compose logs --tail=200
```

Or:

```bash
docker compose logs --tail=200 <service-name>
```

If a service repeatedly restarts, inspect the first error in the sequence rather than only the final restart message.

## Docker: validate configuration

```bash
docker compose config
```

This can reveal invalid Compose syntax or missing environment variables.

## Database connection errors

Check:

- database host
- database port
- database name
- username
- password
- network connectivity
- database service status
- user permissions

In Docker, remember that `localhost` inside an application container refers to that container, not automatically to the database container.

## Migration errors

If a migration fails:

1. Stop.
2. Record the exact error.
3. Do not repeatedly force the migration.
4. Check the installed Talvoro version.
5. Confirm the supported upgrade path.
6. Preserve the current database state.
7. Restore from backup if the failed migration left the site unusable.

## File permission errors

Symptoms may include:

- upload failures
- cache/write failures
- installer errors
- inability to create generated files

Do not solve permission problems by making the entire site world-writable.

Identify the exact directory that requires write access and grant only the necessary permissions.

## Blank page or HTTP 500

Check:

- application logs
- web-server logs
- runtime/PHP logs
- required runtime extensions
- environment configuration
- database connectivity
- recent upgrade or deployment changes

Disable public debug output in production if it may expose secrets or internal paths.

## Login problems

Confirm:

- correct site URL
- HTTPS/cookie behavior
- system clock
- database availability
- administrator account state
- proxy headers if behind a reverse proxy

Do not reset or modify authentication data directly in the database unless Talvoro documentation explicitly supports that procedure.

## Upload problems

Check:

- writable upload directory
- upload size limits
- web-server limits
- PHP/runtime limits
- available disk space
- file-type restrictions
- reverse-proxy request limits

## Site loads but styling/scripts are broken

Check:

- configured base URL
- reverse-proxy path handling
- HTTPS redirects
- browser console errors
- Content Security Policy
- static asset paths
- cache state

## After an upgrade

If the problem started after upgrading:

1. Confirm the target version was installed completely.
2. Check migration status.
3. Review release notes.
4. Confirm persistent files were preserved.
5. Check logs.
6. If necessary, restore the pre-upgrade backup.

See:

```text
docs/UPGRADE.md
docs/BACKUP-RESTORE.md
```

## Disk-space problems

Check free space on:

- application filesystem
- database storage
- Docker volume storage
- backup destination

Low disk space can cause database and upload failures.

## Reverse proxy and HTTPS

If Talvoro is behind a reverse proxy, confirm:

- correct upstream target
- correct public hostname
- HTTPS forwarding
- forwarded protocol/host headers
- websocket support if Talvoro uses it in a future release
- no conflicting redirect loops

## Before opening a GitHub issue

Search existing issues first.

Include:

- Talvoro version
- deployment type
- environment information
- reproduction steps
- expected behavior
- actual behavior
- sanitized logs

Do not include:

- passwords
- API keys
- session cookies
- private keys
- `.env` contents
- raw production database dumps
- private customer/user information

## Security vulnerabilities

Do not report a suspected security vulnerability as a normal public issue.

Follow the instructions in:

```text
SECURITY.md
```
