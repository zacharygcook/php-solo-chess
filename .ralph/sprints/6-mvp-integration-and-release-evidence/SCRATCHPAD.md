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

## 2026-07-12 — Chunk 1 complete

- Completed chunk 1 only: added `docs/MVP_ACCEPTANCE.md` as the executable acceptance-evidence
  matrix for all eight `SPEC.md` MVP criteria and the three required user journeys.
- Decision: mark criteria as `covered` only when backed by local repository evidence, and leave
  representative end-to-end gaps visible for chunks 2, 3, and 4 rather than claiming MVP completion
  from existing unit/browser fragments.
- Decision: wire `docs/MVP_ACCEPTANCE.md` into `scripts/check-agent-docs.sh` so the canonical
  documentation contract fails if the matrix is removed or empty.
- Out of scope: optional engine-powered review remains explicitly deferred; no tag, push, upload,
  CI, hosted service, or external account was introduced.
- Failed approaches: none requiring rollback. The sprint folder has no `PLAN.md`; the active plan is
  `IMPLEMENTATION_PLAN.md`.
- Validation evidence: baseline fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed before edits
  with seed `300059461`, 111 tests, PHPStan clean, and complexity within the method ceiling. After
  edits, `./scripts/check-agent-docs.sh && ./scripts/test.sh && composer typecheck &&
  ./scripts/check-complexity.sh` passed with seed `775553624`, 111 tests, PHPStan clean, and
  complexity within the method ceiling.
- Next handoff: start chunk 2 by adding a deterministic untimed saved-game journey that registers or
  logs in, plays to a terminal result, proves persistence/history/replay/export agreement, and
  verifies owner isolation through account switching.
