# Relevant context

- `SPEC.md` Required MVP section 1 defines correctness, special-move, draw, and terminal-state
  requirements; section 7 requires FEN and stable coordinate interchange for a future engine seam.
- `AGENTS.md` requires focused rules-engine tests before expanding chess behavior and requires the
  `$change-chess-rules` workflow for legality or game-state changes.
- `docs/ARCHITECTURE.md` assigns domain decisions to `backend/src/Services/Chess/` and keeps PHP
  controllers/session code out of the rules engine.
- `docs/RUNBOOKS.md` defines validation/recovery expectations; preserve unrelated work and never
  weaken a gate to pass it.
- `tests/RulesEngineTest.php`, `tests/GameServiceTest.php`, and `tests/TestHarness.php` are the
  current behavior-test and deterministic-test-harness boundaries.
- `backend/src/Services/GameService.php` and `backend/src/Services/Chess/` are the current rules and
  state boundaries; `backend/src/Controllers/GameController.php` is a compatibility consumer.
- `TECH_DEBT.md` contains the existing rule-completeness debt references and must be updated only
  when their documented completion conditions are actually met.
