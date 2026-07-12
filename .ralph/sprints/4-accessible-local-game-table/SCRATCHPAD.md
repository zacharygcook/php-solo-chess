# Scratchpad

## 2026-07-11 — Sprint prepared

- Depends on Sprint 3's stable lifecycle/history/replay APIs and server-owned clocks.
- Remove jQuery rather than replacing it with another client dependency.
- Server legal moves and timestamps remain authoritative; frontend state is presentation-only.
- Preserve click/tap access while adding drag/drop and keyboard-operable movement.
- Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final gate:
  `./scripts/check.sh`.
- This folder is dormant; `CURRENT_SPRINT` was intentionally left unchanged.
- Append decisions, dead ends, validation evidence, and the next-context handoff before every exit.

## 2026-07-12T14:01:25Z — Chunk 1 complete

- Replaced the jQuery CDN runtime with vanilla JavaScript modules:
  `api.js` owns `fetch` calls, `state.js` owns local presentation selection/current-state data,
  `board.js` renders board squares and keyboard/click activation, and `app.js` coordinates intent
  submission plus canonical server-state rendering.
- Removed jQuery from dependency policy, usage, update, weight, security-review, README, runbook, and
  architecture records. `./scripts/check-dependency-weight.sh` no longer downloads remote runtime
  assets, so the app and policy gate do not require internet for browser JavaScript.
- Preserved the existing click/tap and Enter/Space move path. The browser still submits only `{from,
  to}` intent and renders backend responses; legality remains server-owned.
- Validation evidence:
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed;
  `php scripts/check-dependencies.php`, `./scripts/check-dependency-weight.sh`,
  `php scripts/check-unused-dependencies.php`, `./scripts/lint.sh`,
  `./scripts/security-review.sh /tmp/php-solo-chess-security-review.md`, `composer format:check`,
  and `./scripts/check.sh` also passed.
- Handoff: continue with chunk 2, account game creation/history UI. Existing unrelated dirty files
  from Sprint 3 remain outside this chunk.

## 2026-07-12T14:10:38Z — Chunk 2 complete

- Added compact account, new-game, saved-game history, and replay controls to `frontend/index.html`
  with no framework, bundler, CDN, remote asset, or page-level alert dependency.
- Extended `api.js` with same-origin auth, game creation, history, open, and replay endpoints while
  preserving JSON validation messages from non-2xx responses for inline UI feedback.
- Extended `state.js` with user and read-only replay mode. `app.js` now keeps live server state
  separate from saved-game review state, disables board moves during review, and uses canonical
  server FEN from replay positions for board rendering through `boardFromFen()`.
- Added `tests/FrontendContractTest.php` to lock account/history/replay controls, same-origin API
  endpoint wiring, local-only runtime assets, and read-only replay behavior.
- Dead end avoided: did not make `open.php` mutate the live board; saved-game open/replay remain
  review-only so the browser does not invent active game state.
- Validation evidence: `./scripts/lint.sh` passed; `composer format:check` passed after one quote
  style correction; `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
  passed with 92 tests, PHPStan clean, and complexity budget clean.
- Handoff: continue with chunk 3, accessible board interaction and state cues. Existing unrelated
  Sprint 3 dirty files remain outside this chunk.

## 2026-07-12T14:20:26Z — Chunk 3 complete

- Added board render options for local orientation, server-supplied legal source/destination hints,
  last-move markers, checked-king highlighting, final-position styling, accessible square labels, and
  native drag/drop. Click/tap plus Enter/Space movement remains in the same intent path.
- Added a board orientation toggle, captured-piece summaries, and an inline promotion chooser with
  queen, rook, bishop, and knight. Promotion is submitted as move intent; PHP still accepts or rejects
  the canonical move.
- Illegal source selections and review-mode attempts now report through existing live status text
  without alerts. Illegal source/destination submissions still re-render the backend response state so
  the browser does not mutate the board optimistically.
- Added frontend contract coverage for drag/drop wiring, server `legalMoves` cues, promotion choices,
  orientation state, visual cue selectors, and non-disruptive feedback.
- Validation evidence: pre-change
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed with 92 tests;
  after changes `./scripts/lint.sh`, `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
  passed with 93 tests, `composer format:check` passed, and `./scripts/check.sh` passed.
- Handoff: continue with chunk 4, clock/control/terminal feedback and responsive polish. Existing
  unrelated Sprint 3 dirty files and runtime manifest state remain outside this chunk.
