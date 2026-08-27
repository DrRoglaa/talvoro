# Talvoro release distributions

Every official Talvoro release is built from one exact source tree and the repository-root `VERSION`. The exact Git commit referenced by the official signed release tag is the canonical source.

Talvoro is published in three independently usable deployment distributions:

- `talvoro-vX.Y.Z.zip` — **Source / Standard** application distribution for developers, advanced users and custom deployments.
- `talvoro-vX.Y.Z-docker.zip` — **Docker** distribution with the supported Docker Compose, Caddy and Docker bootstrap configuration.
- `talvoro-vX.Y.Z-webhosting.zip` — **Web Hosting** distribution for conventional Apache-compatible PHP hosting, with Docker-only files removed.

All three distributions contain the same Talvoro application PHP, templates, migrations, public assets and `VERSION`. Each archive receives a generated, distribution-specific `release.json` whose SHA-256 file manifest covers exactly the files shipped in that archive.

`SHA256SUMS.txt` is generated from the completed archives. Official release signing/provenance is applied only after those archives and checksums have passed release verification.
