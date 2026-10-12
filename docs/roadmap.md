# Roadmap and product decisions

Source: the public [GitHub milestones](https://github.com/tbuczen/larp-web-pipboy/milestones), reviewed on 2026-10-12. There are nine open milestones and no linked issues. Milestone descriptions establish direction, but should be split into issues with acceptance criteria before each feature is implemented.

This file is the business source of truth for future work. Later explicit user decisions below supersede older milestone phrasing: gameplay data is player/event-scoped, chat starts with direct messages, and consent reveals character names only. [Architecture](architecture.md) records implementation conventions; [operations](operations.md) records verified environment and deployment context. Treat recommendations and unanswered questions as proposals, not settled functionality.

## Confirmed decisions

- Symfony and PHP 8.4, PostgreSQL, maintained Composer packages, EasyAdmin.
- Players create an account first, then apply using an automatically generated personal invitation link. Approval is required after accepting the invitation.
- Account registration collects first name, last name, and an email account identifier. Invitation acceptance collects the event-specific character name. Authentication supports a password or optional Discord OAuth.
- Personal game state and feature grants belong to the player within an event. Shared content and maps belong to the LARP; neither may cross event boundaries.
- Organizers manage only assigned LARPs; superadministrators manage everything.
- PipBoy numbers are unique within a LARP.
- Real names are organizer-only. Direct messages initially identify players by PipBoy number; character names are revealed only after mutual consent.
- Scanning itself unlocks holodisk content; organizers may revoke a grant in an exceptional case. Rotating a discovery code should not silently remove previously obtained content unless explicitly requested.
- Invitation magic links allow membership application after account authentication. They do not provide passwordless login.
- The map is an uploaded image aligned to real GPS coordinates.
- Plan for 100 simultaneous PipBoy users in a LARP of up to 200 players. This is a capacity target requiring measurement.
- Reuse the existing Discord OAuth application. Production secrets belong to this repository's GitHub environment `prod`; the hosting target remains to be confirmed.

## Delivery status

| GitHub milestone | Due date | Initial delivery / remaining work |
| --- | --- | --- |
| [Dataset created and DB ready #1](https://github.com/tbuczen/larp-web-pipboy/milestone/1) | 2026-10-18 | Accounts, LARPs, players, organizer assignments, features, notes, conditions, holodisks and grants implemented. Chat, maps and temporal QR assignments still require their feature-specific schemas. |
| [Admin Panel #2](https://github.com/tbuczen/larp-web-pipboy/milestone/2) | 2026-10-25 | Login, scoped management and approval implemented for the initial entities. Map/file uploads and QR generation/import remain. |
| [Frontend skeleton ready in PipBoy style #3](https://github.com/tbuczen/larp-web-pipboy/milestone/3) | 2026-11-01 | Login and player membership/status page exist. The complete mobile PipBoy interface remains. |
| [Chat #4](https://github.com/tbuczen/larp-web-pipboy/milestone/4) | 2026-11-08 | Direct messages and mutual character-name disclosure confirmed; implementation remains. |
| [MAP #5](https://github.com/tbuczen/larp-web-pipboy/milestone/5) | No date | GPS-aligned uploaded image confirmed; calibration/upload/rendering remain. |
| [Movement detector #6](https://github.com/tbuczen/larp-web-pipboy/milestone/6) | No date | Capacity target: 100 concurrent PipBoys. Connectivity, consent and freshness rules remain. |
| [Notes #7](https://github.com/tbuczen/larp-web-pipboy/milestone/7) | No date | Storage/admin management exists. Player editing and dictation remain. |
| [WebApp instalable on device #8](https://github.com/tbuczen/larp-web-pipboy/milestone/8) | No date | Manifest, service worker, install UX and tested offline behavior remain. |
| [QR code reader for scanning holodisks #9](https://github.com/tbuczen/larp-web-pipboy/milestone/9) | No date | Storage/grants exist. QR assignments, secure scanning/unlock endpoint and camera interface remain. |

None of these milestones is complete. The initial foundation passed its own checks, but partial dataset/admin coverage must not be reported as full milestone delivery.

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

## Acceptance gates for subsequent work

These are minimum gates derived from confirmed requirements. Refine them into linked issues and resolve the questions below before implementing the affected behavior.

| Milestone | Minimum acceptance gate |
| --- | --- |
| Dataset/admin | New event-owned models have migrations, uniqueness/relationship constraints, assigned-organizer list/form/direct-request/write protection, and required management screens. |
| PipBoy frontend | An approved player selects an event and sees only permitted features. Pending/rejected state is clear. UI/API responses exclude personal first/last names and other events' data. |
| Chat | Direct messages use event PipBoy numbers; only participants read/write history. Character disclosure requires consent from both sides; personal names never appear. Test revoked feature access and cross-event/foreign-conversation requests. |
| Map | An organizer uploads/calibrates an image; authorized players see its GPS alignment. Validate calibration against agreed reference points and deny other events' maps. |
| Movement | Implement agreed opt-in/visibility and freshness rules; stale/unknown positions are distinguishable. Measure polling/query behavior against the 100-user target on representative hosting. |
| Notes | Authors create/edit their own event notes. Enforce the agreed organizer visibility policy. Writing remains available when browser speech input is unsupported. |
| PWA | Installation/full-screen behavior is verified on supported devices. Agreed offline data remains available; cached/private event content follows logout/access-revocation rules. |
| QR discovery | A valid authorized scan creates an idempotent same-event grant. Expired/disabled/imported codes follow agreed rules; protected content cannot be fetched without authorization. Test camera/browser interaction when introduced. |

## Suggested work order

Complete remaining dataset/admin tasks alongside each defined feature, then build the player event selector and PipBoy shell, direct chat, calibrated maps, notes/conditions interfaces, QR discovery, and PWA/device behavior. Implement movement after map/location/consent decisions. Prioritize the dated milestones and agree changes to their scope explicitly. This is an implementation proposal; the user may reprioritize it.

## Decisions and suggestions for later milestones

1. **Chat:** direct messages first, with mutual consent for character-name disclosure; real names never appear. Decide blocked users and moderation. Start with incremental polling; long polling occupies PHP workers and should follow a capacity measurement. PHP fibers do not determine browser chat transport.
2. **Maps:** an uploaded image aligned to real GPS coordinates. Define calibration points, image orientation and permissible location accuracy. Use PostGIS only if spatial queries are needed. State which map data may be cached and whether it can be downloaded before an event.
3. **Movement:** target 100 simultaneous PipBoy users in an event of up to 200 players; connectivity and location consent remain to be specified. Prefer explicitly opt-in foreground positions with a freshness timeout; distinguish an unknown/stale position from a player being outside the detection radius. A PWA cannot guarantee continuous tracking while the phone is locked.
4. **Holodisks:** decide whether one QR code unlocks one or multiple disks, how existing printed codes are imported, and how code assignments change over time. Require authentication, approval, same-event membership and the holodisk feature on the server. QR is the cross-platform baseline; Web NFC is unavailable in iOS browsers.
5. **Uploads:** determine text/audio/video/image types and size limits. Uploaded content must use protected storage and authenticated downloads, not predictable public file URLs.
6. **Notes and conditions:** decide player note privacy, whether organizers can read private notes, and who may change fictional game conditions. Voice input needs text entry as a fallback.
7. **Accounts:** define email verification, forgotten passwords, authenticated Discord account linking, suspended accounts and reapplication after rejection. A personal invitation allows application; organizer approval remains the authorization decision. Password registration currently does not verify email ownership; email verification must be added before relying on email alone as identity proof.
8. **Operations:** agree retention after events, database/file backups and restoration, and server-side support when a player loses access during play. Define offline read-only behavior before adding offline mutations or chat queues.
