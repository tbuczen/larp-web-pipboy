# Application architecture

Business requirements and open questions live in [the roadmap](roadmap.md). Setup and deployment context live in [operations](operations.md); commands live in [the README](../README.md).

## Current approach

Use a Symfony modular monolith with Doctrine/PostgreSQL, EasyAdmin for organizers, and Twig for the player interface. PHP 8.4 and Symfony 7.4 LTS are required. The initial domain rules fit small entities, application services, and authorization policies; separate bounded contexts or repository interfaces are not needed for every CRUD operation.

| Location | Responsibility |
| --- | --- |
| `src/Entity`, `src/Enum` | Persistent model, membership state, permissions, condition intervals, and grant rules |
| `src/Service` | Account creation and transactional invitation workflows |
| `src/Security` | Event ownership, authorization, and mutation rules |
| `src/Form` | Validated registration input |
| `src/Controller/Admin` | EasyAdmin fields/actions, scoped queries, organizer workflows |
| `src/Controller` | Player-facing HTTP endpoints and authentication |
| `templates` | Twig rendering; escape user content |
| `migrations` | PostgreSQL schema history and constraints |
| `tests/Unit`, `tests/Functional` | PHPUnit business rules and Codeception HTTP workflows |

Controllers may use Doctrine directly for straightforward reads. Introduce a query/repository service when a query becomes shared or complex. Add domain services or value objects around new business rules rather than distributing those rules across controllers.

## Accounts, events, and authorization

`User` authenticates globally using normalized email/password or an associated Discord ID. First and last names are organizer-only personal data. Do not return them in player directory, chat, map, or motion responses.

`Player` joins a `User` to a `Larp`, with an event-specific character name, PipBoy number, and pending/approved/rejected status. PostgreSQL enforces unique account/event and number/event pairs. An existing player cannot change account or event.

`Larp` has assigned organizers. `ROLE_ORGANIZER` alone does not grant access to every event; assignment is also required. `ROLE_SUPER_ADMIN` grants global administration. Account administration and event creation/organizer assignment are superadministrator operations.

Extend `EventScope`, the access/mutation policies, and `ScopedCrudController` when adding an event-owned entity. Filter list and association queries, protect direct records with the voter, and validate submitted ownership before writes. Avoid applying query joins twice on autocomplete paths. A visible or hidden menu item is not an authorization boundary.

## Registration and invitations

Standalone registration creates an account. An organizer creates an `EventInvitation` for an email/event with an expiry. A cryptographically random token is shown once; only its SHA-256 hash is stored. The signed-in recipient accepts through a CSRF-protected POST, enters a character name, and receives a pending player.

The service claims the invitation with a conditional PostgreSQL update inside the player-creation transaction. This makes consumption single-use, checks expiry at the database clock, and rolls back the claim if membership creation fails. Opening the link must never consume it. Responses and logs protect token paths from caching/referrer/log disclosure.

An invitation is a membership application link. Password/Discord login remains authentication, and organizer approval remains gameplay authorization. Local password registration does not yet verify email ownership; email verification and recovery remain release requirements. Matching an email alone must never link a password account to Discord.

Transient plaintext passwords must not enter the serialized security session. Preserve the existing `User::__serialize()` protection and regression tests when changing account forms or session behavior.

## Initial game model

- `PlayerFeatureAccess` grants notes, chat, map, holodisks, or conditions. `Player::canUse()` also checks approval.
- `Holodisk` belongs to one LARP. `HolodiskAccess` belongs to a player/holodisk pair, supports revocation/restoration, and rejects cross-event associations. A grant alone is insufficient for a future content endpoint: membership, approval, and feature checks remain required.
- `Note` and `Condition` belong to a player. Conditions are fictional game state, with UTC timestamps and a database check preventing an end before the start. Player-note visibility still needs a product decision.
- Chat, map calibration/location, QR assignments, uploads, and offline storage have no complete implementation yet. Add their models with the feature's acceptance criteria rather than implying the initial dataset completes those milestones.

## Extension guidance

For chat, use event-scoped participants and authorize conversation membership on every read/write. Direct messages come first. Store character-name disclosure as explicit mutual consent; never substitute personal names. Incremental polling is a proposed initial transport; measure against the 100-user target before choosing long polling or additional infrastructure.

For maps, calibrate an uploaded image to GPS coordinates and enforce map feature permission. Decide calibration, location consent, stale-position behavior, and connectivity before movement logic. PostGIS is an option when actual spatial queries justify it.

For QR discovery, authorize the scan server-side and create an idempotent player/holodisk grant. Define multi-disk codes, imports, reassignment, and expiry before finalizing QR relations. Use maintained QR packages. Camera scanning is the baseline; Web NFC has limited browser support.

For uploads, use protected storage and authenticated downloads, explicit formats/size limits, and persistent files outside replaceable deployment releases. For PWA/offline behavior, specify cached data and conflict rules before adding offline mutations; browser installation, speech, NFC, and background geolocation need supported-device checks and fallbacks.
