# Ralph Dogfood Scorecard

This is the human-readable record of using `ralph-workflows` to bring PHP Solo Chess to a reliable
MVP. Record workflow failures, confusing behavior, and successful recoveries here—even when the
product work itself ultimately succeeds.

## Current summary

| Measure | Current | Goal |
| --- | ---: | ---: |
| Product milestone | Phase 0 | Locally playable, test-backed MVP |
| Ralph runtime | v0.4.0 | Upgrade only between bounded runs |
| Sprints prepared | 1 | — |
| Autonomous loop runs | 0 | Increase deliberately after Phase 0 |
| Sprints completed without intervention | 0 | Increasing trend |
| Human interventions | 0 | As few as safely possible |
| False completion signals caught | 0 | Catch all |
| Runtime defects discovered | 0 | Record and repair every defect |
| Product regressions escaping validation | 1 | 0 |

## Sprint scorecard

| Sprint | Outcome | Agent | Iterations | Human interventions | Runtime findings | Product result |
| --- | --- | --- | ---: | ---: | --- | --- |
| `0-environment-and-baseline` | Completed | Human-guided setup | 0 | 0 | None | Reproducible startup, baseline QA, and disarmed runtime |

## Friction log

| Date | Sprint | Severity | Observation | Classification | Resolution |
| --- | --- | --- | --- | --- | --- |
| 2026-07-11 | Phase 0 | Info | The app had no canonical startup, validation command, or package manifest. | Project setup | Added `dev.sh`, stateful `check.sh`, and operator documentation. |
| 2026-07-11 | Phase 0 | Medium | Opening `/frontend` without a trailing slash loaded HTML but resolved CSS and JavaScript under missing `/assets/` paths. The baseline tested only `/frontend/`. | Project setup | Made browser asset/API paths root-absolute and added both URL forms plus assets to `check.sh`. |
| 2026-07-11 | Readiness | Medium | The large-file validator rejected a valid linked Git worktree because it required `.git` to be a directory. | Project setup | Validate repository identity with `git rev-parse --git-dir`, which supports normal clones and linked worktrees. |
| 2026-07-11 | Readiness | High | The root document-server layout exposed repository source and configuration paths over local HTTP. | Product setup | Added an allowlist router and blocking dynamic probes for sensitive paths and method/input abuse. |
| 2026-07-11 | Readiness | Info | Coverage loads the backend bootstrap outside HTTP, where `http_response_code()` returns `false`. | Project setup | Start request telemetry only when a request method exists and retain a defensive status fallback. |

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
