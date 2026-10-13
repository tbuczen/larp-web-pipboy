# LARP PipBoy

A Symfony 7.4 LTS application running on PHP 8.4 and PostgreSQL 17, with EasyAdmin 5 for organizers. This initial delivery covers accounts, event registration and approval, organizer authorization, and the first game-data entities. The remaining gameplay milestones are tracked in [docs/roadmap.md](docs/roadmap.md).

Project guidance: [agent workflow](AGENTS.md), [product decisions and roadmap](docs/roadmap.md), [architecture](docs/architecture.md), and [environment/deployment context](docs/operations.md). Operations also records the read-only review of `tbuczen/webspace` and hosting compatibility questions.

## Development

Docker Engine and Docker Compose are required. The application runs as a non-root user. Start from the existing checkout; cloud tasks are already isolated and do not need a Git worktree.

```bash
scripts/dev build
scripts/dev install
scripts/dev up
scripts/dev console doctrine:migrations:migrate --no-interaction
scripts/dev console assets:install public
scripts/dev console doctrine:schema:validate
```

The development server listens on port 8000. The PostgreSQL service is internal to Compose. Its database credentials and the default application secret are development values only. Persistent database data is stored in the `postgres_data` volume; `scripts/dev down` preserves it.

Create the first superadministrator from an interactive terminal:

```bash
scripts/dev console app:create-user organizer@example.org --admin
```

The command prompts for a password without echoing it. No default application administrator is installed.

In the admin panel:

1. Create organizer accounts and give them the Organizer role.
2. Create a LARP and assign its organizers.
3. Players create an account at `/register` using email, password, first name, and last name, or configured Discord OAuth.
4. Use **Invite player** on a LARP to generate a personal link bound to the player’s email, expiring in 1–30 days. Send that link to its recipient through your usual trusted channel.
5. The signed-in recipient accepts the invitation and enters a character name. Their player starts pending.
6. Assigned organizers approve players and grant individual game features.

Invitation links are shown once, contain random tokens, and store only a token hash in PostgreSQL. They are single-use and can be revoked; merely opening a link does not apply to an event. Invitation pages prevent caching and referrer forwarding. Development and test server logs redact invitation paths; hosting reverse-proxy/access logs must also redact them. Real names are visible only to organizers. Player-facing communication will use PipBoy numbers and, after mutual consent, character names.

## Tests

```bash
scripts/dev test
```

The helper migrates the separate `pipboy_test` database, builds the Codeception actor, starts a temporary HTTP test server, and runs PHPUnit and Codeception. It stops that server on completion or failure. Fixtures refuse to run against a database without the `_test` suffix. Development records are preserved.

The tests cover approval and feature permissions, event isolation, grant revocation, condition intervals, organizer authorization, login/CSRF, and HTTP registration/admin flows. PhpBrowser tests do not execute browser JavaScript; add Codeception WebDriver tests when camera, map, or PWA interactions are introduced.

## Discord

Discord OAuth is optional. Without configuration, password login and registration work and Discord links remain hidden.

Reuse the existing Discord OAuth application. Register the exact production callback URL ending in `/connect/discord/check` in the Discord developer portal. If testing OAuth locally, register a separate development callback and supply development credentials through environment settings or an ignored `.env.local` file. Never commit the client secret. Outbound HTTPS to `discord.com` is required.

Discord registration requires a verified Discord email and personal details. Applying to a LARP still requires a personal invitation, character name, and organizer approval. Existing Discord accounts can sign in and accept invitations to another event. Password accounts are deliberately not linked by matching email alone; a future account-linking flow must require authentication to both accounts. Live OAuth requires the external Discord application and has not been validated without those credentials.

## Production secrets

Production secrets will live in the GitHub repository environment named `prod`. A deployment job must declare `environment: prod` to access them. Keep `APP_SECRET`, `DATABASE_URL`, and `DISCORD_CLIENT_SECRET` in that environment's secrets. `DISCORD_CLIENT_ID` is public configuration and can be a `prod` environment variable, or a secret if preferred.

The deployment workflow must securely provide these values to the running Symfony application, with `APP_ENV=prod`. GitHub Actions secrets exist in the deployment job; declaring them does not automatically configure the hosting service or this cloud development environment. Do not print them, bake them into container images, or store them in repository files. Hosting-specific deployment wiring remains to be implemented after the hosting target is defined.

## Design

The application separates global accounts from event-specific players, scopes organizer access to assigned LARPs, and keeps personal names out of player communication. Domain rules live in small entities/policies/services, with Symfony validation and PostgreSQL constraints. See [architecture](docs/architecture.md) for model boundaries and extension conventions, and [the roadmap](docs/roadmap.md) for confirmed gameplay requirements.

The lockfile pins maintained Composer packages. Its GitHub archive URLs use the official `codeload.github.com` endpoint at the same immutable commits because the cloud proxy initially denied the GitHub REST API. TLS and integrity checks remain enabled. To refresh dependencies, use `scripts/dev composer update` with GitHub API network access enabled.

## Before a live event

This is a development foundation, not a production deployment. The built-in PHP server and Compose development credentials must be replaced for hosting. Account recovery/email verification, backups and a restore check, agreed data retention, and gameplay acceptance criteria are outstanding. Location tracking, chat, uploads, QR discovery, and offline behavior are subsequent milestones; see the roadmap for decisions needed before those features.
