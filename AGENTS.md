# Project instructions

## Read the relevant context

Start with [the roadmap](docs/roadmap.md) and inspect the affected code and tests. Read [architecture](docs/architecture.md) for application changes and [operations](docs/operations.md) for setup or deployment work. [README.md](README.md) contains runnable development commands.

The user's latest explicit decisions take precedence over older milestone descriptions. The roadmap records those decisions and unresolved questions; GitHub owns milestone names and dates. Code and executed checks establish delivery status. Update guidance when a decision or implemented behavior changes; do not mark a milestone complete while its required scope remains unfinished.

Ask focused questions when business behavior is unclear. Continue useful work that does not depend on the answer. Keep confirmed requirements, observed facts, and proposed defaults distinct. Challenge assumptions with concrete alternatives that serve the event's needs.

## Collaboration and Git workflow

- Use the existing checkout. Cloud tasks are isolated; create a worktree only when the user requests one.
- Work on a feature, fix, or documentation branch and open a pull request targeting `main`. Subsequent work must follow this route rather than direct pushes to `main`.
- Preserve existing changes. Review the diff and run relevant checks before committing and pushing. Merge or deploy when the user authorizes that action.
- The user permits orchestration with up to three workers when separable tasks benefit from parallel work. Give each worker a bounded scope and explicit file ownership; avoid overlapping edits.
- Assign one owner at a time to Composer changes, migration generation, and HTTP tests using the shared test database. Parallel unit checks and read-only reviews are appropriate.
- PR descriptions explain the resulting behavior, relevant validation, and outstanding limitations. Use a body file or structured argument to preserve multiline text.

## Engineering conventions

- Use PHP 8.4, Symfony 7.4 LTS, PostgreSQL 17, Doctrine ORM, and EasyAdmin 5. Check `composer.json` and `composer.lock` before selecting packages; prefer maintained Composer packages and preserve signature, checksum, and TLS verification.
- Follow SOLID, KISS, and DRY. Use lightweight domain design where it clarifies rules; add interfaces, repositories, value objects, or additional layers when a concrete workflow benefits from them.
- Keep methods small, typed, and readable. Prefer descriptive names and self-describing code. Add comments when business logic needs explanation.
- Keep business rules in entities/policies/services, and controllers focused on HTTP/forms. Use Symfony validation for input and PostgreSQL constraints for durable invariants.
- Keep database changes in migrations. Preserve established event ownership when extending the model; use the existing scoped admin controller and authorization policies for new game entities.
- Use Twig and Symfony's asset tools as the default frontend approach. Introduce another frontend stack only for a justified need.

## Product rules that must remain true

- `User` is a global account; `Player` is its identity within one LARP. One account has at most one player in a LARP. PipBoy numbers are unique within that LARP.
- Accounts are created before event application. Personal invitations are email-bound, expiring, revocable, and single-use; accepting one creates a pending player. Invitation links do not replace password/Discord authentication. GET requests never consume them.
- Organizers manage assigned LARPs; superadministrators manage all. Enforce this on queries, forms, direct requests, and writes.
- Personal game state and feature grants belong to the event player; shared content and maps belong to the LARP. Neither may cross event boundaries. Gameplay requires approval and the corresponding feature permission.
- Personal first/last names remain organizer-only. Chat starts with direct messages by PipBoy number; mutual consent may reveal character names only.
- Maps are uploaded images aligned to GPS coordinates. The capacity target is 100 simultaneous PipBoys in a LARP with up to 200 players; this is not a measured capacity claim.
- Scanning unlocks holodisks once; organizers may revoke access. Server-side checks must enforce the player, event, approval, and feature rules.
- Reuse the existing Discord OAuth application. Production secrets belong to this repository's GitHub environment `prod`; credentials and deployment wiring are documented in operations.

## Validation and sensitive data

- Use simple PHPUnit tests for business rules and Codeception for larger HTTP/end-to-end flows. Test meaningful failure cases and authorization boundaries.
- Run `scripts/dev test` for relevant application changes. It uses an isolated `_test` database and manages its own temporary HTTP server. Do not reset development data or run competing suites against that database.
- Run migrations and `scripts/dev console doctrine:schema:validate` for schema changes. Use real PostgreSQL for database behavior. Add WebDriver checks when browser JavaScript needs validation; PhpBrowser does not exercise it.
- For documentation-only changes, check accuracy, references, and the diff; application test reruns are unnecessary unless a documented behavior is uncertain.
- Keep passed, failed, skipped, and unrun checks distinct. Do not claim a live integration or deployment works from configuration alone.
- Never commit or print credentials, invitation tokens, private keys, local environment overrides, or generated outputs. Inspect secret names/presence when needed and use existing authentication. Request credential values through secure settings, never chat.
