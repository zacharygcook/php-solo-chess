# Scratchpad

## 2026-07-11 — Sprint prepared

- Depends on completed Sprints 1–5; this sprint closes only the required MVP.
- Start with an evidence map and do not treat documentation assertions as executable proof.
- Clean-clone startup, SQLite initialization, browser smoke, full integration, and canonical validation
  must remain local and deterministic.
- Do not lower existing ratchets or begin optional engine-powered review.
- Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final gate:
  `./scripts/check.sh`.
- This folder is dormant; `CURRENT_SPRINT` was intentionally left unchanged.
- Append decisions, dead ends, validation evidence, and the next-context handoff before every exit.
