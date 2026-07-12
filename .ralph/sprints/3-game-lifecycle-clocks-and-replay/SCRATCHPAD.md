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

## 2026-07-11 23:16 CDT — Chunk 3 complete

- Implemented application-level terminal actions through `GameService`: resignation, draw offer,
  draw acceptance, draw claim, and abandonment. Accepted transitions now set canonical
  `gameStatus`, `result`, `terminationReason`, clear legal/actions/draw-offer state, and persist
  through the existing session/authenticated persistence path.
- Added timeout resolution before state views, moves, and actions. Timed-out clocks are persisted at
  zero, the non-flagging side wins when it has mating material, and timeout is recorded as a draw
  when the non-flagging side cannot legally win.
- Preserved finished-game immutability by rejecting later moves/actions without saving rejection
  markers and by keeping finished states at empty `legalMoves` during canonical reload.
- Focused proof added in `tests/GameLifecycleTest.php` and `tests/GameClockTest.php` for resignation,
  agreed draws, draw-claim authorization, abandonment, timeout loss, timeout draw, invalid action
  ordering, and no late clock/move mutation.
- Validation passed: focused `./scripts/test.sh`; requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final
  `./scripts/check.sh`.
- Handoff: next chunk is chunk 4, persist transitions and expose history/replay services. Reuse the
  new terminal action methods as the canonical transition surface; HTTP endpoints remain deferred to
  chunk 5.

## 2026-07-11 23:23 CDT — Chunk 4 complete

- Added `GameHistoryService` as the read-only owner-scoped service for personal history and saved-game
  replay. It lists only the authenticated owner, decodes time-control metadata, opens canonical saved
  final state, and returns ordered replay positions from saved move FEN/clock rows without touching
  session state.
- Finished canonical states now receive deterministic server-owned `completedAt` timestamps from the
  injected millisecond time source, and authenticated persistence writes `completed_at`, result,
  termination reason, time-control JSON, and clock-state JSON atomically with saved move rows.
- Focused proof added in `tests/GameHistoryTest.php` for owner isolation, required history fields,
  deterministic completion dates, persisted move clock metadata, ordered replay positions, and replay
  read immutability against both saved records and the active session game.
- Validation passed: baseline `./scripts/check.sh`; focused `./scripts/test.sh`; requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; rules flakiness
  `./scripts/test-flakiness.sh`; final `./scripts/check.sh`.
- Handoff: next chunk is chunk 5, publish lifecycle/history/replay HTTP contracts. Wire controllers
  and endpoint manifest to `GameService` terminal actions and `GameHistoryService`; keep replay
  read-only and owner-scoped, and do not add PGN or frontend polish in this sprint.
