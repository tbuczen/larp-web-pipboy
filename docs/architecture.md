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
- `Note` and `Condition` belong to a player. Conditions are fictional game state, with UTC timestamps, a database check preventing an end before the start. `Note::writtenBy()` creates a player-written note (`written_by_player`); `EventAccessPolicy` refuses those to every admin user and `NoteCrudController` filters them out of the list, so they stay private. `PlayerNoteController` handles player writes (POST + CSRF, ownership, approval and the notes feature).
- `Holodisk.code` is unique across all LARPs (the scan link carries no LARP), stored upper case without spaces or hyphens (database check `^[A-Z0-9]{6,64}$`). `HolodiskScanner` resolves the code, the scanning account's player in that LARP and the holodisk feature, and creates the grant once.
- `LarpMap` (one per LARP, unique, cascade on LARP delete) stores the uploaded image's random file name, type and size and three aligned corners as `[lat, lng]` JSON; a database check requires all three corners or none.
- Movement/location, QR assignments and offline storage have no complete implementation yet. Add their models with the feature's acceptance criteria rather than implying the initial dataset completes those milestones.

## Admin panel scope

`App\Admin\LarpContext` holds the LARP the panel works on (session; the first available LARP by default). `ScopedCrudController` limits every event list and association choice to it on top of the organizer scope, and new records get it as their LARP. `LarpContextController` (`/admin/larp/{id}/manage`) switches it after checking `MANAGE_EVENT`. The LARP list and accounts stay global. `FeatureAccessController` (`/admin/feature-access`, an EasyAdmin admin route) edits every player's features as checkboxes; `Player::setFeatures()` grants and revokes to match.

Password resets: `PasswordResetManager` issues organizer-created one-time links (`PasswordResetToken`, only the SHA-256 of the token is stored, 24 hours, single use, older unused links deleted). Organizers may reset accounts without a staff role that play in a LARP they organize; superadministrators any account. `PasswordResetController` (`/reset-password/{token}`) changes nothing on GET; the password change invalidates remember-me cookies and other sessions.

## Player interface

`/pipboy` lists the account's memberships; each opens `/pipboy/{player}/{tab}/{screen}/{item}` (`PipBoyController`). `App\PipBoy\Navigation` decides which tabs and screens exist: until approval only STAT › Status, afterwards one screen per granted feature. The controller returns 404 for another account's player, unknown or ungranted screens, and entries that are not the player's own. Add a new player module by adding its screen to `Navigation`, its records to `PipBoyController::entries()` and a partial under `templates/pipboy/screens/`.

Direct chat (`ChatService`, `ChatController`, entities `Conversation` and `ChatMessage`) stores one conversation per pair of players (`player_a_id < player_b_id`, unique), so a declined or closed chat can never be requested again. Recipient lookup answers identically for unknown numbers, other events, players without chat and the sender's own number, and requests are rate-limited, so numbers cannot be probed. All chat endpoints re-check ownership, approval, the chat feature and conversation visibility; writes are POST with CSRF. `public/js/pipboy-chat.js` adds sending without reload and polling; messages are inserted as text.

Holodisk scanning: `HolodiskScanController` serves `GET /scan/{code}` (confirmation only, no state change) and `POST /scan` (CSRF token `holodisk_scan`, rate limiter `holodisk_scan`, 30 per 10 minutes per account). `public/js/pipboy-scan.js` decodes camera frames on the device and submits the decoded text through the manual-entry form. `HolodiskCodeAdminController` renders printable QR sheets (`endroid/qr-code`, SVG) for a LARP or one holodisk; responses are `no-store` because codes unlock content.

Maps: `MapAdminController` (`/admin/larp/{id}/map`, organizers of the LARP) uploads through `MapImageStorage` (`var/storage/maps`, outside the web root) and saves the alignment. `public/js/admin-map-calibrate.js` lays the image over OpenStreetMap tiles with Leaflet and the vendored `Leaflet.ImageOverlay.Rotated` (see `public/vendor/README.md`); corner handles keep the image rectangular. `MapController` serves the image only to organizers or approved players with the map feature (`LarpMapVoter`). The player screen (`_map.html.twig`, `public/js/pipboy-map.js`) shows the image without base tiles, tinted by the inline SVG filter `#pip-map-tint`, and reads `navigator.geolocation` on the device only.

Visual effects are CSS (`public/css/pipboy.css`): cross-document View Transitions for screen switches with a power-on fallback, scanlines, noise and a boot log. `public/js/pipboy.js` is optional enhancement (clock, FX toggle, boot log, swipe/arrow-key tab switching). Effects stop with the FX toggle or `prefers-reduced-motion`.

## Extension guidance

For chat, use event-scoped participants and authorize conversation membership on every read/write. Direct messages come first. Store character-name disclosure as explicit mutual consent; never substitute personal names. Incremental polling is a proposed initial transport; measure against the 100-user target before choosing long polling or additional infrastructure.

Map alignment is affine in Web Mercator, which is accurate for event-sized areas. Positions are never sent to the server; movement detection will need its own consent, freshness and connectivity rules before any position leaves the device. PostGIS is an option when actual spatial queries justify it.

QR discovery is implemented as one code per holodisk. If time windows or scan limits are added later, keep them in `HolodiskScanner` so the link and the in-app scanner share the rules.

For uploads, use protected storage and authenticated downloads, explicit formats/size limits, and persistent files outside replaceable deployment releases. Installable app: `public/manifest.webmanifest`, `public/sw.js` (registered from `pipboy.js`) and `public/offline.html`. The service worker caches static paths cache-first by full versioned URL (dropping older `?v=` copies), keeps `/larp-map/{id}/image` and the map screen in `pipboy-map` for offline use, never caches other pages or `/admin`, and deletes `pipboy-map` on navigation to `/logout` or `/login`. Bump its `VERSION` when changing caching rules. Remember-me (`security.yaml`) is opt-in, 30 days, signed with the password hash. Before adding offline mutations, specify conflict rules; browser installation, speech, NFC, and background geolocation need supported-device checks and fallbacks.
