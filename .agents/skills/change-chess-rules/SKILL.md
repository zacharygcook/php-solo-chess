---
name: change-chess-rules
description: Implement, fix, refactor, or review chess legality and game-state behavior in PHP Solo Chess. Use for changes to GameService, move validation, check/checkmate/stalemate, castling, en passant, promotion, turn order, board mutation, move history, or rules-related API responses.
---

# Change Chess Rules

Protect chess correctness with characterization-first changes and evidence from the rules engine,
not visual browser behavior.

## Establish the contract

1. Read `AGENTS.md`, `SPEC.md`, `README.md`, `docs/ARCHITECTURE.md`, and relevant entries in
   `TECH_DEBT.md`.
2. Run `./scripts/check.sh` before editing. Preserve unrelated work and record any baseline failure.
3. Trace the requested rule through `GameService`, `SessionStore`, controller response shaping, and
   `config/api-endpoints.json` when the JSON contract may change.
4. Turn ambiguous chess behavior into explicit examples before implementation. Use standard chess
   rules; do not infer correctness from the current legacy code.

## Add focused proof first

Add or strengthen a behavior test in `tests/*Test.php` before expanding rules behavior. Prefer the
smallest board/state that proves the rule and its important rejection paths. Cover both the state
transition and invariants:

- the board remains an 8-by-8 array with stable piece codes;
- invalid moves do not mutate board, turn, or history;
- valid moves update source, destination, active color, and history exactly once;
- king safety is evaluated on the resulting position;
- session state remains serializable and contains no request- or environment-specific objects.

Use public behavior through `GameService`; avoid reflection tests for private helpers. Give every
test its own state so randomized execution remains isolated. Replay an order failure with the printed
`TEST_SEED`.

## Implement narrowly

Keep HTTP entry points and controllers thin. Put rule decisions and board mutation in `GameService`;
keep persistence mechanics in `SessionStore`. Extract a domain helper only when it creates a clear,
testable boundary—do not add a framework for convenience.

When behavior is intentionally incomplete, add a stable `DEBT-NNN` entry with impact and completion
conditions before adding its source annotation. If the API changes, update the endpoint manifest and
run `php scripts/generate-api-docs.php --write`.

Do not add paid services, external accounts, CI configuration, or GitHub Actions. All proof must run
locally and remain usable in the pinned devcontainer.

## Validate and hand off

Run focused proof first, then the full gates:

```bash
./scripts/test.sh
composer typecheck
./scripts/test-flakiness.sh
./scripts/check.sh
```

Append relevant decisions or friction to the active Ralph `SCRATCHPAD.md` and root scorecard. Commit
only the scoped rule change and its tests/docs after the local pre-commit hook passes. Report the
exact rule cases proved and any deliberately deferred chess behavior.
