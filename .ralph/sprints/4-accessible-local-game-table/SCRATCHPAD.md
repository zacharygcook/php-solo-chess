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
