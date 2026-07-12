# Scratchpad

## 2026-07-11 — Sprint prepared

- Depends on canonical Sprint 1 SAN/FEN/coordinates and durable lifecycle records from Sprints 2–3.
- PGN is generated from saved game/move data, never reconstructed from frontend markup.
- The engine seam is interface plus deterministic fake only; a real opponent and analysis remain out
  of scope.
- All proposed moves must traverse the same authoritative move application path.
- Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final gate:
  `./scripts/check.sh`.
- This folder is dormant; `CURRENT_SPRINT` was intentionally left unchanged.
- Append decisions, dead ends, validation evidence, and the next-context handoff before every exit.

## 2026-07-12 — Chunk 1 canonical PGN generation complete

- Implemented `SoloChess\Services\PgnExporter` as a canonical saved-data consumer: it accepts a
  `GameRecord` plus ordered `MoveRecord` rows, formats required PGN tag pairs, and writes movetext
  from persisted SAN without reading frontend text, controller envelopes, or DOM state.
- Decision: use `completedAt` for the PGN `Date` when present, otherwise `createdAt`, formatted as a
  UTC `YYYY.MM.DD` tag. Incomplete, abandoned, or missing results export the PGN result token `*`.
- Decision: represent untimed games as `TimeControl "-"`; timed games use seconds in PGN clock form
  like `180+2` from canonical `baseMilliseconds` and `incrementMilliseconds`.
- Focused tests cover incomplete untimed export, completed timed export, escaped header text, draw
  result export, and canonical SAN strings for captures, check, mate, castling, en passant-shaped
  captures, and promotion.
- Dead end: PHPStan flagged a redundant `array_values()` on a list (`arrayValues.list`); removed the
  no-op after checking the PHPStan identifier guidance.
- Validation evidence:
  - Baseline before edits: `./scripts/check.sh` passed.
  - Focused proof: `./scripts/test.sh` passed with 99 tests.
  - Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed.
  - Final validation: `./scripts/check.sh` passed, including browser smoke and coverage.
- Handoff: next incomplete chunk is chunk 2, `Verify PGN reproduces canonical games`; add replay
  verification against authoritative coordinate application without exposing downloads or adding any
  engine behavior yet.

## 2026-07-12 — Chunk 2 PGN replay verification complete

- Implemented `SoloChess\Services\PgnVerifier` and `PgnVerificationResult` as repository-owned
  verification for canonical `GameRecord` plus ordered `MoveRecord` data. The verifier replays
  persisted coordinate moves through `GameService::submitMove()` and compares replayed SAN,
  per-ply FEN, final FEN, move count, and canonical result consistency.
- Focused tests cover ordinary replay, castling, en passant, promotion, checkmate, drawn result
  metadata, corrupt SAN, corrupt final FEN, illegal coordinate records, and mismatched saved result
  data.
- Decision: non-board terminal results such as agreed draws are verified as canonical record/state
  consistency while board-derived terminal results from replay must match the saved result when the
  move path produces one.
- Dead end: the first special-move test generated canonical fixtures by applying every game before
  verification; under Xdebug coverage this duplicated replay work and exceeded the two-second test
  budget. Replaced those with compact canonical records while keeping verifier replay through the
  authoritative move path. The long special-move matrix remains in the normal fast unit suite and is
  skipped only during coverage measurement.
- Validation evidence:
  - Baseline before edits: `./scripts/check.sh` passed.
  - Focused proof: `./scripts/test.sh` passed with 102 tests.
  - Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed.
  - Formatting: `composer format:check` passed.
  - Final validation: `./scripts/check.sh` passed, including browser smoke and coverage.
- Handoff: next incomplete chunk is chunk 3, `Expose authorized PGN downloads`; add the API/browser
  download surface without adding engine behavior or changing move application.

## 2026-07-12 — Chunk 3 authorized PGN downloads complete

- Implemented `/backend/public/api/games/export.php` through thin `PgnController` streaming and
  `PgnDownloadService`, keeping repository reads and guest-session canonicalization out of the HTTP
  controller layer.
- Owned saved-game exports require the active authenticated owner, use ordered persisted move rows,
  return `application/x-chess-pgn`, and send deterministic attachment filenames like
  `solo-chess-game-1.pgn`. Unowned, missing, and unauthenticated saved-game requests return JSON
  errors without leaking durable records.
- Guest exports use only the active guest session state and synthesize canonical PGN records from
  server-owned move history, participant labels, time control, result, FEN, and SAN. Authenticated
  no-id exports use the current durable game id when present and otherwise refuse to export guest
  session state.
- Added browser actions for active-session PGN download and saved-game row PGN download. The frontend
  calls the same-origin endpoint directly and does not reconstruct PGN from rendered move history.
- Focused tests cover PGN response headers/content, deterministic filenames, owner authorization,
  missing games, unauthenticated saved exports, guest-session export, and authenticated no-id
  isolation.
- Dead end: the first full `./scripts/check.sh` after implementation failed the architecture gate
  because `PgnController` depended on repositories directly. Moved the lookup/export boundary into
  `PgnDownloadService`; the next full check passed the architecture layer gate.
- Dead end: a subsequent full-check run hit a transient test-budget failure during quality snapshot
  generation; rerunning `./scripts/generate-quality-report.sh /tmp/php-solo-q.md` passed, and the
  final full check passed.
- Validation evidence:
  - Baseline before edits: `./scripts/check.sh` passed.
  - Focused proof: `./scripts/test.sh` passed with 106 tests.
  - Frontend syntax: `node --check frontend/assets/js/api.js && node --check frontend/assets/js/app.js` passed.
  - Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed.
  - Formatting: `composer format:check` passed.
  - Final validation: `./scripts/check.sh` passed, including architecture, coverage, and browser smoke.
- Handoff: next incomplete chunk is chunk 4, `Define and prove the future-engine adapter seam`; keep
  the adapter deterministic, do not add an engine binary or opponent behavior, and route any proposed
  move through the existing authoritative move application path.

## 2026-07-12 — Chunk 4 future-engine adapter seam complete

- Implemented the passive `SoloChess\Engine` seam: `EngineAdapter` defines the proposal contract,
  `EngineRequest` carries canonical FEN, active color, participants, legal moves, terminal metadata,
  and stable context, `EngineMoveProposal` exposes coordinate move payloads, and
  `FakeEngineAdapter` returns deterministic proposals without importing HTTP, persistence, UI, or a
  real engine.
- Proved proposed moves enter the same authoritative path as human moves by applying fake adapter
  output through `GameService::submitMove()` and comparing the resulting board, turn, history, and
  FEN with an identical direct human move.
- Proved illegal fake-engine proposals are rejected without mutating board, active color, history, or
  FEN, and that participant metadata can identify a future `engine` seat.
- Decision: keep the engine namespace independent of service/repository concerns; orchestration code
  may convert a proposal to the existing move payload, but the adapter itself cannot mutate state.
- Dead end: the first full check rejected `EngineAdapter.php` because the naming checker only
  recognized classes. Updated `scripts/check-naming.php` to allow interface declarations while
  preserving PascalCase and filename checks.
- Validation evidence:
  - Baseline before edits: `./scripts/check.sh` passed.
  - Focused proof: `./scripts/test.sh` passed with 110 tests.
  - Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed.
  - Formatting: `composer format:check` passed.
  - Final validation: `./scripts/check.sh` passed, including architecture, coverage, and browser smoke.
- Handoff: next incomplete chunk is chunk 5, `Document and integrate export and engine boundaries`;
  update generated API/docs and integration evidence only, and continue to exclude real engine
  binaries, analysis, opponent behavior, model downloads, and runtime dependencies.
