Contributing to Talvoro

Thank you for your interest in contributing to Talvoro.

Talvoro is a modern, privacy-focused, self-hosted CMS intended to support both Docker deployments and traditional web hosting.

The project is still in active pre-1.0 development, so architecture, APIs, packaging, migrations, and contribution rules may continue to evolve.

────────

Before You Start

Please:

• search existing issues and pull requests before creating a new one
• keep changes focused on one clear purpose
• avoid mixing unrelated refactors with feature work
• never include secrets, private data, credentials, production backups, or real user data
• report security vulnerabilities privately rather than through a public issue

For security issues, see:

```text
SECURITY.md
```

────────

Branch Model

Talvoro uses a simple branch structure:

```text
main
└── stable and released code

dev
└── integration branch for the next Talvoro release

feature/*
fix/*
docs/*
└── short-lived working branches
```

Normal development work should target dev.

Do not open ordinary feature or bug-fix pull requests directly against main.

Official releases are created from main.

────────

Create a Working Branch

Start from the latest dev branch:

```bash
git switch dev
git pull
```

Create a focused working branch.

Examples:

```text
feature/media-library
feature/installer-validation
fix/session-timeout
fix/webhosting-permissions
docs/docker-installation
```

Create the branch:

```bash
git switch -c feature/example
```

────────

Make Your Changes

Keep the change as small and focused as reasonably possible.

Where applicable, update:

• application code
• automated tests
• database migrations
• installation documentation
• upgrade documentation
• user-facing documentation
• release packaging logic

Changes that alter behavior should normally include tests.

Changes that alter deployment, installation, configuration, migrations, or upgrade behavior should update the relevant documentation in the same pull request.

────────

Coding Expectations

Contributions should:

• follow the existing project structure and conventions
• prefer clear and maintainable code over clever code
• avoid unnecessary dependencies
• preserve privacy-focused behavior
• avoid introducing mandatory external services without prior project discussion
• maintain support for the intended Talvoro deployment models where applicable
• fail clearly when an operation cannot be completed safely

Avoid unrelated formatting-only changes in functional pull requests.

────────

Privacy Principles

Talvoro is privacy-focused by design.

Contributions should not introduce:

• mandatory telemetry
• hidden analytics
• unnecessary tracking
• mandatory cloud accounts
• unnecessary third-party requests
• collection of user data without a clear product requirement

Any feature that sends data to an external service should be explicit, documented, and reviewed carefully.

────────

Database Migrations

Database changes must use Talvoro’s migration system.

Migration contributions should:

• be deterministic
• use stable ordering
• fail clearly on error
• avoid silently ignoring migration failures
• preserve supported upgrade paths
• include regression coverage where practical
• document destructive or irreversible changes

Do not rely on manual production database edits as part of a normal Talvoro release.

────────

Tests

Before opening a pull request, run the relevant test suite locally where possible.

The Talvoro CI pipeline is intended to validate:

• application tests
• installer behavior
• database migrations
• regression checks
• release packaging
• Docker builds
• syntax/static validation
• security sanity checks

A pull request should not be merged while required checks are failing.

If a test cannot reasonably be added, explain why in the pull request.

────────

Commit Messages

Use clear commit messages that describe what changed.

Good examples:

```text
Add Docker installer validation
Fix session timeout handling
Document web-hosting upgrade process
Prevent invalid migration ordering
```

Avoid vague messages such as:

```text
fix
changes
update stuff
wip
```

Small, logical commits are preferred.

────────

Pull Requests

Open pull requests against:

```text
dev
```

A pull request should explain:

• what changed
• why the change is needed
• how it was tested
• whether database migrations are included
• whether installation or upgrade behavior changes
• whether documentation was updated
• any known limitations or follow-up work

Screenshots are useful for meaningful UI changes.

Keep pull requests focused enough to review and test safely.

────────

Pull Request Review

A contribution may require changes before it can be merged.

Review may consider:

• correctness
• maintainability
• security
• privacy
• UX impact
• migration safety
• deployment compatibility
• test coverage
• documentation
• release packaging impact

Approval does not guarantee immediate inclusion in a release.

────────

Versioning

Talvoro follows Semantic Versioning:

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
0.15.0
0.15.1
0.16.0
1.0.0
```

Pre-release tags may include:

```text
v0.16.0-alpha.1
v0.16.0-beta.1
v0.16.0-rc.1
```

Contributors generally should not change the release version unless the contribution is specifically part of release preparation.

The authoritative project version is intended to be stored in:

```text
VERSION
```

────────

Release Process

Official Talvoro releases follow this flow:

```text
feature/* / fix/* / docs/*
          ↓
         dev
          ↓
         main
          ↓
   signed vX.Y.Z tag
          ↓
 automated release workflow
```

Release artifacts are intended to include:

```text
talvoro-vX.Y.Z.zip
talvoro-vX.Y.Z-docker.zip
talvoro-vX.Y.Z-webhosting.zip
SHA256SUMS.txt
SHA256SUMS.txt.sig
```

All release packages must be generated from the exact same tagged commit.

The signed Git tag is the canonical source reference for an official release.

────────

Signed Releases

Official release signing is handled by the Talvoro release process.

Contributors must never commit:

• release-signing private keys
• signing passwords
• hardware-token secrets
• CI signing secrets

Release automation must fail rather than publish a release when required signing or verification fails.

────────

Security Vulnerabilities

Do not disclose suspected security vulnerabilities in:

• public GitHub Issues
• public Discussions
• pull requests
• public comments
• public proof-of-concept repositories

Follow the reporting process in:

```text
SECURITY.md
```

Security fixes may be developed privately until users have a reasonable opportunity to update.

────────

Never Commit Secrets

Do not commit:

• .env files containing real credentials
• database passwords
• API keys
• access tokens
• SSH private keys
• TLS private keys
• release-signing private keys
• production database dumps
• real user data
• private backups

If a secret is accidentally committed, assume it has been exposed and rotate or revoke it immediately.

Deleting it in a later commit is not sufficient.

────────

Release Packaging

Release packages must not contain development-only or private files unless explicitly required.

Examples of files that should normally be excluded:

```text
.git/
local .env files
IDE metadata
local databases
temporary files
cache
logs
test output
private keys
developer secrets
user uploads
Docker volumes
```

Packaging changes should be tested for all affected distribution types.

────────

Documentation

Documentation lives alongside the source code.

Important files include:

```text
README.md
SECURITY.md
CONTRIBUTING.md

docs/
├── INSTALL-DOCKER.md
├── INSTALL-WEBHOSTING.md
├── UPGRADE.md
├── BACKUP-RESTORE.md
├── TROUBLESHOOTING.md
└── DEVELOPMENT.md
```

Please update documentation when your change affects installation, deployment, upgrades, configuration, or user-visible behavior.

────────

Issues

GitHub Issues can be used for:

• reproducible bugs
• feature proposals
• documentation problems
• non-sensitive technical discussions

A useful bug report should include:

• Talvoro version
• deployment type
• environment
• reproduction steps
• expected behavior
• actual behavior
• relevant sanitized logs

Never include credentials or sensitive production data.

────────

Feature Proposals

For larger changes, open an issue before investing substantial implementation effort.

Describe:

• the problem being solved
• the proposed behavior
• expected user benefit
• impact on Docker and web-hosting deployments
• migration implications
• privacy/security implications
• alternatives considered

This helps avoid significant work on a direction that may not fit the project.

────────

License and Contribution Terms

Talvoro’s final project license is still being determined.

Until a repository license and contribution terms are explicitly published, contributors should review the current repository status before submitting substantial code.

Do not contribute code that you do not have the right to submit.

Do not copy code from incompatible or proprietary sources.

────────

Code of Conduct

A formal Code of Conduct may be added as the community grows.

In the meantime, keep discussions professional, constructive, and focused on improving Talvoro.

────────

Thank You

Every thoughtful bug report, documentation improvement, test, review, and code contribution can help make Talvoro more reliable and useful.

Thank you for contributing.
