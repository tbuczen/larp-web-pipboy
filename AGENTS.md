# Project instructions

- For subsequent changes, use a feature branch and open a pull request targeting `main`.
- Use Symfony, PHP 8.4, PostgreSQL, and maintained Composer packages. Follow SOLID, KISS, and DRY without adding abstractions that do not simplify the current workflow.
- Keep methods small and readable. Prefer descriptive names; add comments when business rules need explanation.
- Keep game data scoped to the player within a LARP. Organizers manage assigned events; superadministrators manage all events. Personal names remain organizer-only; chat reveals character names only after mutual consent.
- Ask when functionality is unclear, and record confirmed decisions in `docs/roadmap.md`.
- Use PHPUnit for business logic and Codeception for HTTP/end-to-end flows. Run `scripts/dev test` for relevant application changes; it uses an isolated test database.
- Read `README.md` for setup and production secret handling. Never commit credentials or generated dependency/test outputs.
