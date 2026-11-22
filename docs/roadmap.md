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
- Superadministrators can also invite organizers (assigned to the invitation's LARP) and superadministrators with the same email-bound, single-use links. Organizers invite players only. New roles take effect at the next sign-in.
- The map is an uploaded image aligned to real GPS coordinates.
- Plan for 100 simultaneous PipBoy users in a LARP of up to 200 players. This is a capacity target requiring measurement.
- Reuse the existing Discord OAuth application. Production secrets belong to this repository's GitHub environment `prod`; the hosting target remains to be confirmed.

## Delivery status

| GitHub milestone | Due date | Initial delivery / remaining work |
| --- | --- | --- |
| [Dataset created and DB ready #1](https://github.com/tbuczen/larp-web-pipboy/milestone/1) | 2026-10-18 | Accounts, LARPs, players, organizer assignments, features, notes, conditions, holodisks and grants implemented. Chat, maps and temporal QR assignments still require their feature-specific schemas. |
| [Admin Panel #2](https://github.com/tbuczen/larp-web-pipboy/milestone/2) | 2026-10-25 | Implemented: login, per-LARP management (a LARP switcher scopes every event list and form; superadmins can pick any LARP, organizers their assigned ones), approval, feature access as checkboxes, map upload, holodisk media and QR codes. |
| [Frontend skeleton ready in PipBoy style #3](https://github.com/tbuczen/larp-web-pipboy/milestone/3) | 2026-11-01 | Login, the LARP list and a per-membership PipBoy interface exist: STAT (status, conditions) and DATA (holodisks, notes) screens gated by approval and feature grants, MAP/COMMS shown as offline modules, old-display screen transitions and mobile layout. Remaining: real map and chat modules, note editing, PWA/offline behaviour, browser (WebDriver) checks of the effects. |
| [Chat #4](https://github.com/tbuczen/larp-web-pipboy/milestone/4) | 2026-11-08 | Direct chat implemented in COMMS › Messages: requests by PipBoy number with an opening message, accept/decline, closing, mutual character-name disclosure, unread counts and polling. Remaining: capacity measurement of polling on the production host. |
| [MAP #5](https://github.com/tbuczen/larp-web-pipboy/milestone/5) | No date | Implemented: organizers upload one image per LARP and align it by dragging it over OpenStreetMap; players with the map feature see it in MAP › Local map with a green screen look and their own GPS position. Remaining: field test of GPS accuracy at the venue, offline caching of the map (PWA milestone). |
| [Movement detector #6](https://github.com/tbuczen/larp-web-pipboy/milestone/6) | No date | Proof of concept implemented: MAP › Motion radar showing nearby sharing PipBoys of the LARP as anonymous blips, opt-in sharing while the screen is open, new Motion detector feature. Remaining: field and capacity tests (100 PipBoys), deciding whether organizers get a live view, background behaviour. |
| [Notes #7](https://github.com/tbuczen/larp-web-pipboy/milestone/7) | No date | Implemented: players write, edit and delete their own private notes in DATA › Notes; organizer notes are read-only for them. Remaining: dictation (voice input). |
| [WebApp instalable on device #8](https://github.com/tbuczen/larp-web-pipboy/milestone/8) | No date | Implemented: manifest with maskable icons, service worker, Install PipBoy prompt (Android/desktop) and iOS instructions, opt-in 30-day "Keep me signed in", offline "No signal" screen and offline map. Remaining: testing on real iOS and Android phones. |
| [QR code reader for scanning holodisks #9](https://github.com/tbuczen/larp-web-pipboy/milestone/9) | No date | Implemented: every holodisk has a unique code (generated, or the text of an existing printed code), printable QR sheets, DATA › Scan with the camera or typed code, and phone-camera links that confirm before unlocking. Remaining: testing on real iOS/Android devices and in venue lighting. |

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

1. **Chat (user decisions, implemented):** players cannot browse each other. A player sends a chat request to a PipBoy number in their LARP with one opening message; the receiver sees only that number and message and accepts or declines. A declined request keeps looking pending to the requester and cannot be repeated. Either participant can close an active chat (no further messages, no new request). Chats are private to the two players; organizers cannot read them. Character names are shown only while both agree; real names never appear. Transport is incremental polling (every 4 s while visible, 30 s when hidden); long polling or other infrastructure should follow a capacity measurement.
2. **Maps (user decisions, implemented):** one uploaded image (JPEG/PNG/WebP, up to 15 MB) per LARP, aligned by dragging it over OpenStreetMap in the admin panel. Three corners (top-left, top-right, bottom-left) are stored, so position, scale and rotation are kept; replacing the image keeps the alignment. Players see only the uploaded image, never street tiles, styled like a wrist-terminal local map (green tint, inverted for light maps, grid and scanlines). The GPS position is shown only on the player's own phone and is never sent to the server. Browsers cache the image privately for an hour; downloading before an event and offline use belong to the PWA milestone. Still open: acceptable accuracy at the venue (phones typically show ±5–20 m outdoors).
3. **Movement (user decisions, proof of concept implemented):** players see a motion detector, not organizers. Sharing is opt-in (off by default), switched on in MAP › Motion and active only while that screen is open; a SHARING indicator shows it. A sharing player sees other sharing players of the same LARP who have the Motion detector feature as anonymous blips (offsets rounded to 5 m within 50/100/250 m); non-sharing players see nothing; names, numbers and coordinates are never sent to other players. Only the latest position per player is stored, deleted on stop, hidden after 60 s and purged after 2 h. Open: capacity at 100 PipBoys (each sharing phone sends a position every 5 s and polls every 3 s), an organizer view, and behaviour when the phone sleeps (a web app cannot share while locked).
4. **Holodisks (user decisions, implemented):** one QR code unlocks one holodisk. Codes are generated by the app (12 characters without look-alike letters) or entered by organizers as the text of an existing printed code; regenerating a code invalidates printed copies. Every approved player of the LARP with the holodisk feature who scans the code unlocks it once; a grant revoked by organizers stays revoked when scanned again. The QR holds the absolute link `/scan/{code}`: a phone camera opens it, and the page asks for confirmation (POST) before unlocking; the PipBoy's DATA › Scan screen reads codes with the camera (`BarcodeDetector` or jsQR) or accepts the typed code. Unknown codes, other events' codes and ineligible players get the same answer, and scans are rate-limited. Time windows and limited scan counts were not chosen and are not implemented. Web NFC is unavailable in iOS browsers.
5. **Uploads:** holodisks accept one JPEG/PNG/WebP/GIF image or MP3 recording up to 15 MB (user decision), stored outside the web root and served only to organizers of the LARP or approved players holding the holodisk feature and an active grant. Images get the green screen tint on display only. Video and other content types remain undecided.
6. **Notes and conditions (user decisions, implemented):** players write, edit and delete their own notes (up to 200, 10,000 characters each). Those notes are private: no organizer or superadministrator can list, read or edit them in admin. Notes written by organizers appear in the player's list marked "Org" and are read-only for the player. Conditions are set by organizers only. Voice dictation remains open and needs text entry as a fallback.
7. **Accounts:** forgotten passwords are handled by organizer-issued reset links (user decision, implemented): no email is sent; an organizer creates a one-time link (24 hours, a new link cancels the old one) for a player in their LARP and passes it on; superadministrators can do it for any account. Still open: email verification, authenticated Discord account linking, suspended accounts and reapplication after rejection. Password registration does not verify email ownership; add email verification before relying on email alone as identity proof.
9. **Installable app (user decisions, implemented):** the PipBoy installs to the home screen. Static files are stored on the phone; personal pages always come from the network and show a "No signal" screen offline. The LARP map image and the map screen are kept on the phone after the first visit, so MAP › Local map with GPS works without signal; signing out (or opening the sign-in page) clears them. Sign-in offers an opt-in "Keep me signed in" for 30 days, ended by signing out or changing the password. No offline writes (chat, notes) are queued.
8. **Operations:** agree retention after events, database/file backups and restoration, and server-side support when a player loses access during play. Define offline read-only behavior before adding offline mutations or chat queues.
