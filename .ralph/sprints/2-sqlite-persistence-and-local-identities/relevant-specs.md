# Relevant context

- `SPEC.md` sections 2, Architecture/Persistence, and acceptance criteria 2–3 define local accounts,
  SQLite ownership, automatic durable records, and guest behavior.
- `AGENTS.md` forbids convenience dependencies and requires the canonical checks and scoped commits.
- `docs/ARCHITECTURE.md` currently has only `SessionStore`; repositories must isolate PDO from domain
  behavior and controllers.
- `docs/RUNBOOKS.md` requires preserving user work during recovery; database setup must be idempotent.
- `backend/src/Services/GameService.php` exposes canonical rules state and notation completed in
  Sprint 1; persistence stores that state rather than re-deriving it.
- `backend/src/bootstrap.php`, `backend/src/Services/SessionStore.php`, and `backend/public/api/` are
  the current session/bootstrap/HTTP integration points.
- Local SQLite databases, journals, and test fixtures containing credentials are runtime data and
  must remain ignored and uncommitted.
