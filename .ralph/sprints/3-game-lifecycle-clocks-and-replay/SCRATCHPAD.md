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
