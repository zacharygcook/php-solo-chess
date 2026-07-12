# Scratchpad

## 2026-07-11 — Sprint prepared

- Depends on completed Sprint 1 rules and planned Sprint 2 SQLite/account repositories.
- The rules domain owns board-derived outcomes. This sprint owns application actions and clocks while
  writing the same canonical terminal fields.
- Use injected time in tests; wall-clock sleeps are not acceptable validation.
- Replay reads saved move/FEN records and never mutates canonical saved state.
- Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final gate:
  `./scripts/check.sh`.
- This folder is dormant; `CURRENT_SPRINT` was intentionally left unchanged.
- Append decisions, dead ends, validation evidence, and the next-context handoff before every exit.

## 2026-07-11 23:03 CDT — Chunk 1 complete

- Implemented game creation modeling with `GameLifecycleService` and `TimeControl`; supported untimed
  games, presets `1+0`, `3+2`, `5+0`, `10+0`, `15+10`, and validated custom base/increment controls.
- Creation state now includes participant labels/types, time-control metadata, and initialized
  server-owned clock state using an injected deterministic millisecond source for tests.
- Wired `GameService::createGame()` and reset through the lifecycle path; authenticated creation now
  creates a new owner-scoped durable game and persists label/type, time-control, and clock JSON.
- Focused proof added in `tests/GameLifecycleTest.php` for default untimed games, preset/custom
  timed games, malformed controls, invalid participant types, and authenticated persistence metadata.
- Validation passed: baseline `./scripts/check.sh`; focused `./scripts/test.sh`; requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final
  `./scripts/check.sh`.
- Handoff: next chunk is chunk 2, server-authoritative clock debiting. Reuse the existing
  `clockState` fields and injected time pattern; do not add timeout termination until chunk 3.

## 2026-07-11 23:10 CDT — Chunk 2 complete

- Implemented `GameClock` as the server-owned clock accounting boundary with an injected
  deterministic millisecond source shared by `GameService` and `GameLifecycleService`.
- Accepted timed moves now debit elapsed time from the moving side, apply increment exactly once,
  advance the active clock timestamp to the next side, snapshot both remaining clocks onto the move
  history, and persist those nullable clock values into move rows.
- `GameService::getSessionState()` now returns a projected active-clock view while keeping canonical
  stored clock state unchanged, so refresh/reload cannot pause, reset, or double-debit the active
  clock; rejected moves return a projected view but do not save clock mutations.
- Focused proof added in `tests/GameClockTest.php` for both colors, increments, rejected moves,
  refresh projection, canonical store immutability, and overrun clamping while timeout termination
  remains deferred.
- Validation passed: baseline `./scripts/check.sh`; focused `./scripts/test.sh`; requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final
  `./scripts/check.sh`.
- Handoff: next chunk is chunk 3, complete game-ending actions. Reuse `GameClock` for timeout
  detection but keep timeout result classification and finished-game immutability in the terminal
  transition work; chunk 2 intentionally does not end games when clocks reach zero.
