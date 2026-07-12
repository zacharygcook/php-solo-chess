# Scratchpad

## 2026-07-11 — Sprint prepared

- Sprint 1 completed orthodox rules state, legal generation, terminal results, SAN, coordinate
  notation, and FEN. Store those canonical outputs; do not duplicate rules inside repositories.
- The existing application has no database or accounts. Guest session play must remain functional.
- Use isolated temporary SQLite databases in tests and keep all runtime database artifacts ignored.
- Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`.
- Final gate: `./scripts/check.sh`.
- This folder was prepared without changing `CURRENT_SPRINT`; activation and execution remain an
  operator decision.
- Append decisions, dead ends, validation evidence, and the next-context handoff before every exit.

## 2026-07-11 — Chunk 1 complete: deterministic SQLite schema setup

- Decisions:
  - Added `SoloChess\Persistence\SqliteConnectionFactory` to centralize PDO SQLite construction,
    exception mode, foreign-key enforcement, and busy timeout.
  - Added `SoloChess\Persistence\DatabaseSchema` with ordered `CREATE TABLE IF NOT EXISTS` and
    `CREATE INDEX IF NOT EXISTS` statements for `users`, `games`, and `moves`.
  - Kept the schema explicit and future-ready for saved canonical state without adding repositories,
    authentication behavior, clocks, roles, or frontend changes in this chunk.
  - Added `scripts/setup-database.php [database-path]`; the default target is the ignored local
    runtime path `backend/storage/solo-chess.sqlite`.
  - Registered the `Persistence` architecture layer and ignored SQLite runtime database, journal,
    WAL, and SHM artifacts under `backend/storage`.
- Failed approaches / corrections:
  - Initial patch attempt missed the compact JSON formatting in `config/architecture-layers.json`;
    split file additions from the metadata edits and reapplied against the raw file.
  - `composer format:check` caught one lambda-spacing issue in `tests/DatabaseTest.php`; fixed it
    before the required gate.
- Validation evidence:
  - Baseline before edits: `./scripts/check.sh` passed.
  - Focused suite after edits: `./scripts/test.sh` passed with 45 tests, 0 failures.
  - Required fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
    passed.
  - Full post-change gate: `./scripts/check.sh` passed.
- Next handoff:
  - Continue with chunk 2 only. Build typed user/game/move repositories on top of the new
    `Persistence` boundary, keep all SQL parameterized, and use temporary SQLite databases in
    repository tests. Do not move auth or HTTP behavior into chunk 2.

## 2026-07-11 — Chunk 2 complete: typed user/game/move repositories

- Decisions:
  - Added `SoloChess\Repositories\UserRepository`, `GameRepository`, and `MoveRepository` with typed
    row/data objects so PDO access stays out of services, controllers, and future auth behavior.
  - Kept repository inputs as explicit scalar-backed data objects and stored canonical state, clock
    state, and move snapshots as JSON/FEN strings produced by upstream application/rules code.
  - Added `GameRepository::replaceCanonicalStateWithMoves()` as the transaction boundary for an
    owned game snapshot plus its ordered move list.
  - Added a `Repositories` architecture layer and allowed services to depend on it for the upcoming
    authentication/application-service chunks.
- Failed approaches / corrections:
  - Initial formatting used expanded empty constructors; `composer format` compacted those before
    validation.
- Validation evidence:
  - Baseline before edits: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
    passed with 45 tests, 0 failures.
  - Focused suite during implementation: `./scripts/test.sh` passed with 53 tests, 0 failures.
  - Typecheck during implementation: `composer typecheck` passed.
  - Required fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
    passed with 53 tests, 0 failures.
  - Full post-change gate: `./scripts/check.sh` passed.
- Next handoff:
  - Continue with chunk 3 only. Build registration/authentication services on top of
    `UserRepository`, normalize usernames consistently before repository calls, use
    `password_hash()`/`password_verify()`, and return safe identity data without exposing
    `passwordHash`. Do not add HTTP endpoints, sessions, email, roles, clocks, or frontend work in
    chunk 3.
