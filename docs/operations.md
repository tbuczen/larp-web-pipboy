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

## Continuous integration

The [GitHub Actions workflow](../.github/workflows/ci.yml) runs the `PHP checks` job for pull requests into `main`, pushes to `main`, and manual dispatches. It provisions PHP 8.4 and an isolated PostgreSQL 17 service, validates Composer/platform/Symfony configuration, audits the lockfile, and runs [the shared test runner](../scripts/test). GitHub runs PHP directly; local development invokes the same runner inside Compose.

The runner creates/migrates the `_test` database, validates its schema, installs EasyAdmin assets, and executes PHPUnit and Codeception against a temporary HTTP server. CI settings and database credentials are disposable test values. The workflow has read-only repository permissions, uses actions pinned to immutable commit SHAs, and does not select `prod` or consume its secrets. Diagnostic output retains errors while redacting invitation paths. Production deployment is the separate [deploy workflow](#production-deployment), which runs only after this workflow passes on `main`.

CI sets PHP's `variables_order=EGPCS` so the HTTP test server exposes the supplied test configuration through `$_ENV`. Without it, Symfony can load development values from `.env` during HTTP requests even when CLI commands use the test environment.

## Production deployment

[`deploy.yml`](../.github/workflows/deploy.yml) follows the [LARPilot deploy workflow](https://github.com/tbuczen/LARPilot/blob/main/.github/workflows/deploy.yml): SSH into the server, update a git checkout in place, write production configuration and build there. It runs when the `CI` workflow succeeds for a push to `main`, and deploys exactly the commit CI tested. A manual run (`workflow_dispatch`, `main` only) deploys the head of `main` without waiting for CI. Deploys are serialized and never cancelled halfway.

On the server the workflow:

1. refuses to continue if `PROJECT_DIR` is not a git checkout or has local changes to tracked files;
2. fetches `main` over HTTPS from `REPOSITORY_URL` (default: this public repository), regardless of the checkout's own `origin`, and checks out the tested commit (detached). If the fetch fails, it prints the checkout's remote and any `url.*.insteadOf` rewrite rules;
3. runs [`scripts/write-prod-env.php`](../scripts/write-prod-env.php), which accepts a `DATABASE_URL` pasted with quotes, a leading `DATABASE_URL=` or an unencoded password (and reports what it normalized), refuses a non-PostgreSQL database URL, connects to the database once and reports its version, then writes `.env.local.php` (mode 640, git-ignored) from the injected secrets, then removes those values from the shell so Symfony reads the file;
4. runs [`scripts/deploy`](../scripts/deploy): PHP 8.4 check, `composer install --no-dev`, cache clear, `doctrine:migrations:migrate`, `assets:install public`, cache warmup.

A final step requests `HEALTHCHECK_URL` and fails the run unless it answers 2xx/3xx. CI's last step runs both scripts in production mode on PHP 8.4 and checks that `/login` returns HTTP 200, so a build that cannot boot without development dependencies fails before it reaches `main`.

### `prod` environment configuration

The deploy job checks names before connecting and prints only the missing names, never values. Settings marked "variable or secret" may be stored as either.

| Name | Kind | Purpose |
| --- | --- | --- |
| `SSH_HOST`, `SSH_USER` | variable or secret, required | Server address and account |
| `SSH_PORT` | variable or secret | Defaults to 22 |
| `SSH_PASSWORD` or `SSH_KEY` (+ `SSH_KEY_PASSPHRASE`) | secret, one required | SSH authentication; a key is recommended |
| `SSH_FINGERPRINT` | variable or secret, recommended | SHA256 host key fingerprint; without it the host key is not verified and the run warns |
| `PROJECT_DIR` | variable or secret, required | Absolute path of the repository checkout on the server |
| `APP_SECRET` | secret, required | Symfony secret for PipBoy only |
| `DATABASE_URL` | secret | Full `postgresql://` URL; used only when the four settings below are not all set |
| `DATABASE_HOST`, `DATABASE_NAME`, `DATABASE_USER`, `DATABASE_PASSWORD` | secrets, required without `DATABASE_URL` | Combined into a URL with URL-encoded credentials; take precedence over `DATABASE_URL` when all four are set |
| `DATABASE_PORT`, `DATABASE_SERVER_VERSION` | variable or secret | Default 5432 and 17 |
| `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` | ID variable or secret; secret | Existing OAuth application; set both or neither. LARPilot's `OAUTH_DISCORD_*` names are accepted as a fallback |
| `PHP_BIN`, `COMPOSER_BIN` | variable or secret | Server binaries when `php` is not PHP 8.4 or Composer is not on `PATH` |
| `HEALTHCHECK_URL` | variable | For example `https://<domain>/login`; without it the run warns and skips the check |
| `REPOSITORY_URL` | variable | Fetch URL used on the server; defaults to `https://github.com/tbuczen/larp-web-pipboy.git`. Needs credentials if the repository becomes private |

The names of the secrets and variables already added to `prod` were not readable from the cloud session that wrote this workflow; the first deploy run reports any that are missing.

### One-time server preparation

- Clone this repository to `PROJECT_DIR` as the deploy account. No GitHub SSH key or `known_hosts` entry is needed: the deploy fetches over HTTPS. Do not add a global `url.*.insteadOf` rule that rewrites `https://github.com/` to SSH.
- Provide PHP 8.4 (CLI and web), the `pdo_pgsql`, `intl`, `mbstring`, `xml`, `ctype` and `iconv` extensions, and Composer.
- Create the PostgreSQL 17 database and user.
- Serve `PROJECT_DIR/public` as the document root where the hosting allows it. On the current hosting the document root is fixed to `PROJECT_DIR` itself, so the committed [root `.htaccess`](../.htaccess) routes every request into `public/` (and selects the PHP 8.4 handler), and [`public/.htaccess`](../public/.htaccess) serves existing files and sends everything else to `index.php`. Requests for `.git`, `.env*`, `config/`, `var/` or `vendor/` therefore reach Symfony and get a 404, never the files. Without `mod_rewrite` the root file denies all requests. A hand-made `.htaccess` that is not yet in the repository is moved to `*.before-deploy` during the checkout.
- Redact `/invite/<token>` paths in the web server's access logs.
- Register the production callback ending in `/connect/discord/check` on the Discord OAuth application.

### Uploaded holodisk media

Holodisk images and recordings are stored in `PROJECT_DIR/var/storage/holodisks`, and LARP map images in `PROJECT_DIR/var/storage/maps`, under random file names, outside the web root. The admin map calibration page loads street tiles from `tile.openstreetmap.org` in the organizer's browser (light use within the OpenStreetMap tile policy, with attribution); players never load them. The deploy never touches this directory, so files survive deploys. Include it in backups together with the database: a database restore without the matching files leaves holodisks and maps pointing at missing images. Uploads are limited to 15 MB; `public/.user.ini` raises PHP's upload limits on hosts that honour it, and the hosting panel's PHP settings may need the same values.

### Limitations

The deploy updates the live checkout in place, like LARPilot: requests during the build may fail, and there is no automatic rollback. Roll back by reverting on `main`; migrations are not reversed automatically. Take a database backup before deploying destructive migrations. Persistent upload storage, release switching and a backup/restore plan remain to be designed. Live deployment, OAuth and the production PHP runtime have not been tested from this repository.

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
- How will uploads be preserved across deploys? Secrets are injected by the deploy workflow and readiness is checked through `HEALTHCHECK_URL`.
- What is the backup/restore and rollback plan, including database migrations?

Ask for non-secret decisions and permission-bound access when required. Users supply credentials in GitHub/environment settings; never ask for values in chat. No production deployment or modification of webspace is authorized by this reference review alone.
