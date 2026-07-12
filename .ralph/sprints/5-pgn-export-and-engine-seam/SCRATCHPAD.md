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
