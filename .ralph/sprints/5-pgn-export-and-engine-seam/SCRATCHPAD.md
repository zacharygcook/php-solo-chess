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
