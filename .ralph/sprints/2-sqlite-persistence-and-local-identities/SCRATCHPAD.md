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
