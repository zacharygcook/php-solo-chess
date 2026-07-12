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
