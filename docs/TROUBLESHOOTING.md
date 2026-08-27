<div align="center">

# Talvoro Troubleshooting

### Diagnose deliberately. Change one thing at a time.

**[Documentation](README.md)** · **[Backup & Restore](BACKUP-RESTORE.md)** · **[Upgrade Guide](UPGRADE.md)** · **[Security](../SECURITY.md)**

</div>

---

> [!IMPORTANT]
> Before changing data, migrations, permissions, or production configuration, create a backup when practical.

## First 10 minutes

Record these details before making changes:

| Detail | Example |
| --- | --- |
| Talvoro version | `0.15.0` |
| Deployment | Docker / Web Hosting / custom |
| Host | OS, VPS, or hosting provider |
| Runtime | PHP/runtime version |
| Database | MySQL/MariaDB and version |
| Error | Exact message and timestamp |
| Reproduction | Smallest repeatable sequence |

Then follow this order:

1. verify the release if the problem began after install/upgrade;
2. check service/runtime health;
3. inspect the **first relevant error** in logs;
4. validate configuration;
5. check database connectivity;
6. change one variable at a time.

## Release integrity problems

If the issue started immediately after installation or upgrade, verify the package against the official release.

See **[GitHub Releases & Verification](GITHUB-RELEASES.md#verify-an-official-release)**.

Do not continue diagnosing a package that fails integrity or signature checks as if it were a trusted Talvoro build.

## Docker: service status

```bash
docker compose ps
```

Look for:

- stopped services;
- restart loops;
- unhealthy services;
- missing dependencies.

## Docker: logs

All services:

```bash
docker compose logs --tail=200
```

Specific service:

```bash
docker compose logs --tail=200 <service-name>
```

If a service repeatedly restarts, find the **first failure** in the sequence rather than reading only the final restart message.

## Docker: configuration

```bash
docker compose config
```

This can expose invalid Compose syntax or missing environment values.

## Database connection errors

Check:

- host;
- port;
- database name;
- username;
- password;
- database service state;
- network path;
- user permissions.

> [!TIP]
> In Docker, `localhost` inside the Talvoro application container means that container itself. It does not automatically mean the database container.

## Migration errors

If a migration fails:

1. stop;
2. record the exact error;
3. do not repeatedly force the same migration;
4. confirm the installed and target Talvoro versions;
5. confirm the supported upgrade path;
6. preserve the current database state;
7. restore the pre-upgrade backup if the site is left unusable.

Do not manually manipulate migration history unless Talvoro documentation explicitly instructs you to.

## File-permission errors

Common symptoms:

- upload failures;
- cache/write failures;
- installer errors;
- inability to create generated files.

> [!WARNING]
> Do not solve permission problems by making the entire site world-writable.

Identify the exact path that needs write access and grant only the necessary permissions.

## Blank page or HTTP 500

Check:

- application logs;
- web-server/proxy logs;
- PHP/runtime logs;
- required extensions;
- environment configuration;
- database connectivity;
- recent deployment or upgrade changes.

Disable public debug output in production if it can expose internal paths or secrets.

## Login problems

Confirm:

- correct public site URL;
- HTTPS and cookie behavior;
- system clock/time;
- database availability;
- administrator account state;
- reverse-proxy host/protocol headers.

Avoid directly modifying authentication records in the database unless Talvoro explicitly documents that recovery procedure.

## Upload problems

Check:

| Area | What to inspect |
| --- | --- |
| Filesystem | Writable upload path and available disk space |
| Runtime | PHP/request upload limits |
| Proxy/server | Request-size limits |
| Talvoro | File-type restrictions |
| Hosting | Account quotas and permission policies |

## Styling or scripts are broken

Check:

- configured base/public URL;
- HTTPS redirects;
- reverse-proxy path handling;
- static asset paths;
- browser developer-console errors;
- Content Security Policy;
- cache state.

## Problems after an upgrade

Use this sequence:

1. confirm the target version was installed completely;
2. check migration state;
3. read the target release notes;
4. confirm persistent files/configuration were preserved;
5. inspect logs;
6. if necessary, roll back using the pre-upgrade backup.

See **[Upgrade Guide](UPGRADE.md)** and **[Backup & Restore](BACKUP-RESTORE.md)**.

## Disk-space problems

Check free space for:

- application filesystem;
- database storage;
- Docker volume storage;
- backup destination.

Low disk space can cause apparently unrelated database, upload, logging, and migration failures.

## Reverse proxy and HTTPS

Confirm:

- correct upstream service;
- correct public hostname;
- HTTPS forwarding;
- forwarded protocol/host headers;
- no redirect loop;
- required protocol support for the Talvoro version.

## Before opening a GitHub issue

Search existing issues first.

Include:

- Talvoro version;
- deployment type;
- environment details;
- reproduction steps;
- expected result;
- actual result;
- sanitized logs.

Do **not** include:

```text
passwords
API keys
session cookies
private keys
.env contents
production database dumps
private customer/user data
```

## Security vulnerabilities

> [!CAUTION]
> Do not report a suspected security vulnerability as a normal public issue.

Follow **[SECURITY.md](../SECURITY.md)** and use the private reporting path when available.

---

[← Documentation home](README.md)
