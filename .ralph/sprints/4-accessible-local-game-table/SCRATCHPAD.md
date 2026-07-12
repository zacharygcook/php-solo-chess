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
