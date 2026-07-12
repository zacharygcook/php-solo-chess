# Ralph Dogfood Scorecard

This is the human-readable record of using `ralph-workflows` to bring PHP Solo Chess to a reliable
MVP. Record workflow failures, confusing behavior, and successful recoveries here—even when the
product work itself ultimately succeeds.

## Current summary

| Measure | Current | Goal |
| --- | ---: | ---: |
| Product milestone | MVP release evidence | Locally playable, test-backed MVP |
| Ralph runtime | v0.4.0 | Upgrade only between bounded runs |
| Sprints prepared | 7 | — |
| Autonomous loop runs | 4 | Increase deliberately after Phase 0 |
| Sprints completed without intervention | 3 | Increasing trend |
| Human interventions | 0 | As few as safely possible |
| False completion signals caught | 0 | Catch all |
| Runtime defects discovered | 1 | Record and repair every defect |
| Product regressions escaping validation | 3 | 0 |

## Sprint scorecard

| Sprint | Outcome | Agent | Iterations | Human interventions | Runtime findings | Product result |
| --- | --- | --- | ---: | ---: | --- | --- |
| `0-environment-and-baseline` | Completed | Human-guided setup | 0 | 0 | None | Reproducible startup, baseline QA, and disarmed runtime |
| `4-accessible-local-game-table` | Chunks done; post-sprint review fix applied | Ralph/Codex | 5 | 0 | Review and documentation hooks recorded; final validation pending | Vanilla accessible local game table, saved-game replay UI, optional sound, and browser smoke coverage |
| `6-mvp-integration-and-release-evidence` | Chunks done; post-sprint hooks pending | Ralph/Codex | 5 | 0 | None during chunks | Executable MVP evidence matrix, untimed and timed saved-game journeys, hardened browser smoke, and local release packaging proof |

## Friction log

| Date | Sprint | Severity | Observation | Classification | Resolution |
| --- | --- | --- | --- | --- | --- |
| 2026-07-11 | Phase 0 | Info | The app had no canonical startup, validation command, or package manifest. | Project setup | Added `dev.sh`, stateful `check.sh`, and operator documentation. |
| 2026-07-11 | Phase 0 | Medium | Opening `/frontend` without a trailing slash loaded HTML but resolved CSS and JavaScript under missing `/assets/` paths. The baseline tested only `/frontend/`. | Project setup | Made browser asset/API paths root-absolute and added both URL forms plus assets to `check.sh`. |
| 2026-07-11 | Readiness | Medium | The large-file validator rejected a valid linked Git worktree because it required `.git` to be a directory. | Project setup | Validate repository identity with `git rev-parse --git-dir`, which supports normal clones and linked worktrees. |
| 2026-07-11 | Readiness | High | The root document-server layout exposed repository source and configuration paths over local HTTP. | Product setup | Added an allowlist router and blocking dynamic probes for sensitive paths and method/input abuse. |
| 2026-07-11 | Readiness | Info | Coverage loads the backend bootstrap outside HTTP, where `http_response_code()` returns `false`. | Project setup | Start request telemetry only when a request method exists and retain a defensive status fallback. |
| 2026-07-11 | Pre-Ralph readiness | High | `GameService` was 887 lines with class complexity 248, one method at 60, NPath above 11 million, and 33% measured duplication. The initial five rules tests could not safely support autonomous edits. | Project setup | Added behavior-first coverage, extracted six chess-domain boundaries, reduced `GameService` to 165 lines, capped methods at complexity 9, and reduced duplication below 7%. |
| 2026-07-11 | `2-sqlite-persistence-and-local-identities` | Medium | Ralph repeatedly reset chunk 2 after `./scripts/test.sh` passed because `composer typecheck` inherited a 128 MB PHPStan child-process memory limit while parsing internal stubs. | Runtime defect | Made `composer typecheck` invoke PHPStan through PHP with an explicit 512 MB memory limit, preserving the configured fast gate. |
| 2026-07-11 | `3-game-lifecycle-clocks-and-replay` | High | Sprint validation missed timeout result misclassification when the non-flagging side had only a minor piece but the flagging side still had material that can make legal mate possible. | Product setup | Added a focused timeout-material regression test and made `canColorLegallyWin()` consider flagging-side non-king material before declaring a timeout draw. |
| 2026-07-12 | `4-accessible-local-game-table` | Medium | Sprint validation and browser smoke coverage missed a failed-auth edge case: after an existing account was visible, failed login or registration applied `state.user: null` in the browser even though the backend kept the authenticated session. | Product setup | Preserved visible account state on failed auth, added frontend contract regression coverage, and recorded richer failed-auth browser assertions as future coverage value. |
| 2026-07-12 | `5-pgn-export-and-engine-seam` | Medium | Full validation exposed a timing-sensitive engine seam test that compared wall-clock move-history timestamps, and PGN replay integration pushed Xdebug coverage-mode tests over the two-second budget. | Product setup | Injected a deterministic clock for the engine path comparison and kept heavyweight PGN replay matrices in the normal fast suite while skipping them only during coverage measurement. |
| 2026-07-12 | `6-mvp-integration-and-release-evidence` | Info | The working checkout contained unrelated Ralph runtime and prior product edits, while release packaging intentionally requires a clean worktree. | Agent behavior | Kept chunk edits scoped and planned the package proof from a separate clean Git worktree after the chunk commit. |

Classifications: `runtime defect`, `skill guidance`, `project setup`, `chunk design`, `agent behavior`,
or `expected product difficulty`.

## What to measure after every sprint

- Time to the first useful commit and total iterations.
- Human interventions, why they were necessary, and whether Ralph should prevent them next time.
- Whether the scratchpad preserved decisions, dead ends, and next steps across fresh contexts.
- Whether completion markers matched real acceptance-criteria progress.
- Whether review, documentation, and test hooks were accurate, idempotent, and resumable.
- Any product defect that passed the sprint's stated validation.
- Any project-specific workaround that belongs in the reusable skill or runtime.

## Runtime change protocol

Use one frozen Ralph runtime version during a bounded sprint. If it breaks, capture logs and record the
failure here. Repair and validate `ralph-workflows` separately, then update `.ralph/` between runs.
Do not let a product sprint silently rewrite the orchestrator executing it.
