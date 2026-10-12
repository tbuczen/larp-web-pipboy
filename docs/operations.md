# Environment and deployment context

Reviewed on 2026-10-12. The commands below are documented in [README.md](../README.md); this file records verified facts, reference conventions, and unresolved production requirements. Business rules are in [the roadmap](roadmap.md).

## Verified PipBoy development environment

- Docker Engine and Compose run PHP 8.4.26, Composer 2.10.3, and PostgreSQL 17.11. Host PHP/Composer/PostgreSQL were absent during setup; use `scripts/dev` rather than assuming host binaries.
- The app runs as a non-root user. PostgreSQL is internal to Compose with persistent storage and separate `pipboy`/`pipboy_test` databases.
- `/login`, `/register`, and the installed EasyAdmin CSS/JavaScript returned HTTP 200. The nine admin screens and account/invitation/organizer flows passed HTTP tests.
- The baseline passed 48 PHPUnit tests/100 assertions and 26 Codeception HTTP tests/122 assertions. These are executed foundation checks, not a capacity or production certification.
- The newest invitation migration rolled back and reapplied on populated test data; Doctrine confirmed the schema. Test fixtures guard the `_test` database and the runner cleans up only its own temporary server.
- `scripts/dev` uses a writable Docker configuration directory, supported cloud proxy DNS/CA handling, and TLS verification. The local credential defaults and PHP built-in server are development-only.
- Development/test request logs redact invitation paths. Hosting reverse-proxy logs must implement equivalent redaction.
- The dependency lock uses official GitHub codeload archives at unchanged commit references because initial API access was denied. API access later succeeded; network/authentication capability still needs checking in each execution context. Do not change checksums or disable TLS to resolve an install failure.
- On this review, `scripts/dev composer audit --locked` completed and found no security vulnerability advisories. The earlier blocked advisory check is resolved for this instance.

Live processes must restart after restoring a cloud task. Reuse the checkout, dependencies, and persistent data; avoid a new worktree unless requested. Saved cloud setup/start instructions are separate from executed commands and publication. A successful draft save does not publish an environment.

## Production configuration

The user selected this repository's GitHub environment named `prod` and an existing Discord OAuth application. API reads confirmed a `prod` environment exists for both PipBoy and webspace. Secret/variable metadata endpoints returned HTTP 403; the presence of individual bindings and their usability remain unverified. Names below describe the configuration contract, not confirmed credentials.

| Configuration | Storage / purpose |
| --- | --- |
| `APP_ENV=prod` | Non-secret runtime configuration |
| `APP_SECRET` | PipBoy-specific GitHub `prod` secret, provided securely to Symfony |
| `DATABASE_URL` | GitHub `prod` secret for the PipBoy PostgreSQL database |
| `DISCORD_CLIENT_ID` | GitHub `prod` variable or secret; existing OAuth application's public client ID |
| `DISCORD_CLIENT_SECRET` | GitHub `prod` secret; existing OAuth application's client secret |
| Deployment path, host, and SSH configuration | Hosting-dependent; select after the target is confirmed |

A deployment job declares `environment: prod` and supplies configuration to the running app. GitHub job secrets do not automatically configure a hosting service or cloud development task. GitHub environment names and repository secrets are scoped to the repository; webspace's bindings do not automatically transfer to PipBoy.

Register the exact production callback ending in `/connect/discord/check` on the existing Discord OAuth application. Development callbacks/configuration are separate. Live OAuth, SMTP, production database access, and production PHP runtimes have not been tested. Read secret names/presence, never values; use existing injected authentication and secure settings.

## Read-only reference: webspace

Access to `tbuczen/webspace` was verified. Inspection used immutable commit [`06c1908b782576b3dc1544acee467546210a442c`](https://github.com/tbuczen/webspace/tree/06c1908b782576b3dc1544acee467546210a442c). No source files, workflows, settings, or live services were changed there, and credential files were not read. A temporary reference clone is not part of PipBoy's selected cloud repositories.

| Source at the inspected commit | Observed convention |
| --- | --- |
| [README](https://github.com/tbuczen/webspace/blob/06c1908b782576b3dc1544acee467546210a442c/README.md) | CyberFolks VPS deployment, configuration names, Apache panel constraint, PHP compatibility rationale |
| [composer.json](https://github.com/tbuczen/webspace/blob/06c1908b782576b3dc1544acee467546210a442c/composer.json) | Symfony 7.2; PHP platform 8.2.31; EasyAdmin, Doctrine, AssetMapper without a Node build |
| [compose.yaml](https://github.com/tbuczen/webspace/blob/06c1908b782576b3dc1544acee467546210a442c/compose.yaml) | PostgreSQL 16, healthcheck and persistent volume; local override exposes the DB port |
| [deploy.yml](https://github.com/tbuczen/webspace/blob/06c1908b782576b3dc1544acee467546210a442c/.github/workflows/deploy.yml) | `main`/manual triggers, `prod` environment, PHP 8.2, Composer production install, asset compilation, archive/SCP/SSH, remote migrations and cache clearing |

The reference declares `PROJECT_DIR`, `SSH_*`, `DATABASE_*`, and `APP_SECRET` configuration. Prefer its naming/build conventions where useful, while retaining PipBoy's required PHP 8.4/Symfony 7.4/PostgreSQL 17 stack. Do not copy credential values, databases, application secrets, or deployment directories across projects.

The inspected deployment writes into the live directory and does not declare tests, backup/rollback, or an application healthcheck. For PipBoy, add a relevant test gate, verified SSH host identity, persistent protected uploads, release switching/rollback, and a database recovery plan when implementing deployment. SSH key authentication is recommended. Document-root rewrites in the reference address a specific panel constraint; first verify whether PipBoy can serve `public/` directly.

## Decisions needed before production deployment

- Hosting target: reuse the CyberFolks VPS with separate PipBoy resources, or use another target?
- Does that target support PHP 8.4 in both CLI/migration commands and the web process? Webspace's PHP 8.2 compatibility is not evidence of PHP 8.4 support.
- What are the PipBoy domain, deployment directory, database, and exact OAuth callback?
- How will the deployment securely inject secrets, preserve uploads, and verify application readiness?
- What is the backup/restore and rollback plan, including database migrations?

Ask for non-secret decisions and permission-bound access when required. Users supply credentials in GitHub/environment settings; never ask for values in chat. No production deployment or modification of webspace is authorized by this reference review alone.
