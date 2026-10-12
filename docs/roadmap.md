# Roadmap and product decisions

Source: the public [GitHub milestones](https://github.com/tbuczen/larp-web-pipboy/milestones), reviewed on 2026-10-12. There are nine open milestones and no linked issues. Milestone descriptions establish direction, but should be split into issues with acceptance criteria before each feature is implemented.

## Confirmed decisions

- Symfony and PHP 8.4, PostgreSQL, maintained Composer packages, EasyAdmin.
- Players create an account first, then apply using an automatically generated personal invitation link. Approval is required after accepting the invitation.
- Account registration collects first name, last name, and an email account identifier. Invitation acceptance collects the event-specific character name. Authentication supports a password or optional Discord OAuth.
- All game data belongs to the player within an event.
- Organizers manage only assigned LARPs; superadministrators manage everything.
- PipBoy numbers are unique within a LARP.
- Real names are organizer-only. Direct messages initially identify players by PipBoy number; character names are revealed only after mutual consent.
- Scanning itself unlocks holodisk content; organizers may revoke a grant in an exceptional case. Rotating a discovery code should not silently remove previously obtained content unless explicitly requested.

## Delivery status

| GitHub milestone | Due date | Initial delivery / remaining work |
| --- | --- | --- |
| Dataset created and DB ready | 2026-10-18 | Accounts, LARPs, players, organizer assignments, features, notes, conditions, holodisks and grants implemented. Chat, maps and temporal QR assignments still require their feature-specific schemas. |
| Admin Panel | 2026-10-25 | Login, scoped management and approval implemented for the initial entities. Map/file uploads and QR generation/import remain. |
| Frontend skeleton ready in PipBoy style | 2026-11-01 | Login and player membership/status page exist. The complete mobile PipBoy interface remains. |
| Chat | 2026-11-08 | Direct messages and mutual character-name disclosure confirmed; implementation remains. |
| MAP | No date | GPS-aligned uploaded image confirmed; calibration/upload/rendering remain. |
| Movement detector | No date | Capacity target: 100 concurrent PipBoys. Connectivity, consent and freshness rules remain. |
| Notes | No date | Storage/admin management exists. Player editing and dictation remain. |
| WebApp instalable on device | No date | Manifest, service worker, install UX and tested offline behavior remain. |
| QR code reader for scanning holodisks | No date | Storage/grants exist. QR assignments, secure scanning/unlock endpoint and camera interface remain. |

## Acceptance criteria for the initial foundation

- An account can accept only its personal, valid invitation. Opening a link does not consume it; acceptance is single-use, creates a pending player, and requires CSRF validation.
- Each account has at most one player in an event. The same account can join another event without sharing gameplay data.
- New players are pending. Organizers can approve only players in assigned events.
- Feature access requires both approval and a feature grant.
- An organizer cannot see another event in lists, select its records in forms, or edit its records through a direct URL.
- A superadministrator can assign organizers and manage accounts across events.
- Passwords and invitation tokens are hashed; admin, invitation, and registration forms validate CSRF tokens.
- Holodisk grants cannot cross LARP boundaries; condition end dates cannot precede start dates.
- PHPUnit checks domain rules; Codeception exercises HTTP flows against isolated PostgreSQL test data.

## Decisions and suggestions for later milestones

1. **Chat:** direct messages first, with mutual consent for character-name disclosure; real names never appear. Decide blocked users and moderation. Start with incremental polling; long polling occupies PHP workers and should follow a capacity measurement. PHP fibers do not determine browser chat transport.
2. **Maps:** an uploaded image aligned to real GPS coordinates. Define calibration points, image orientation and permissible location accuracy. Use PostGIS only if spatial queries are needed. State which map data may be cached and whether it can be downloaded before an event.
3. **Movement:** target 100 simultaneous PipBoy users in an event of up to 200 players; connectivity and location consent remain to be specified. Prefer explicitly opt-in foreground positions with a freshness timeout; distinguish an unknown/stale position from a player being outside the detection radius. A PWA cannot guarantee continuous tracking while the phone is locked.
4. **Holodisks:** decide whether one QR code unlocks one or multiple disks, how existing printed codes are imported, and how code assignments change over time. Require authentication, approval, same-event membership and the holodisk feature on the server. QR is the cross-platform baseline; Web NFC is unavailable in iOS browsers.
5. **Uploads:** determine text/audio/video/image types and size limits. Uploaded content must use protected storage and authenticated downloads, not predictable public file URLs.
6. **Notes and conditions:** decide player note privacy, whether organizers can read private notes, and who may change fictional game conditions. Voice input needs text entry as a fallback.
7. **Accounts:** define email verification, forgotten passwords, authenticated Discord account linking, suspended accounts and reapplication after rejection. A personal invitation allows application; organizer approval remains the authorization decision. Password registration currently does not verify email ownership; email verification must be added before relying on email alone as identity proof.
8. **Operations:** agree retention after events, database/file backups and restoration, and server-side support when a player loses access during play. Define offline read-only behavior before adding offline mutations or chat queues.
