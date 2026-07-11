# Scratchpad

## 2026-07-11 — Phase 0 baseline

- Clean clone began at `4559b4c` on `master`.
- Local tools: PHP 8.5.4, Composer 2.9.5, Node 24.14.1, Python 3.14.3, jq 1.7.1, Playwright, and macOS Bash 3.2.
- The application currently has no Composer or npm dependency manifest and needs no package installation.
- Canonical URL is `http://127.0.0.1:8080/frontend/` when the repository root is the PHP document root.
- PHP syntax, frontend JavaScript syntax, initial session creation, frontend delivery, cookie persistence, and `e2` to `e4` all passed.
- jQuery is fetched from a public CDN, so fully offline use is not yet supported.
- Runtime v0.4.0 was installed in monorepo mode and remains deliberately disarmed.
- Phase 1 should establish characterization tests around chess rules before refactoring `GameService.php`.

## 2026-07-11 — First human QA finding

- Opening `/frontend` without the trailing slash exposed unstyled HTML because relative asset URLs resolved from `/`.
- The original baseline check missed this by requesting only `/frontend/` and not verifying CSS/JavaScript responses.
- Frontend asset and API URLs are now root-absolute; baseline QA covers both route forms and both local assets.
- This counts as one product regression escaping Phase 0 validation, not a Ralph runtime defect.

## 2026-07-11 — Agent-readiness baseline

- The evidence-backed 82-criterion baseline is Level 1 at 16.42% owned readiness: 11 passes, 56
  failures, and 15 inapplicable criteria.
- Readiness work must remain free and local. The owner explicitly prohibits CI and GitHub Actions,
  so improvements must be runnable through repository scripts on a developer or agent workstation.
- The numerical target is at least 80%. Under rubric 1.0 that is Level 5, even though the request
  described the desired milestone as “Level 4 / 80%."
- Added a dependency-free unit harness and initial `GameService` characterization tests before any
  further chess-rules expansion. The separate `./scripts/test.sh` command keeps fast logic tests
  distinct from the HTTP smoke check.
- Expanded `.gitignore` around the actual PHP/JavaScript workflow: local secrets, runtime state,
  dependencies, generated reports, coverage/build output, caches, editors, and OS metadata.
- The canonical local check now rejects tracked or unignored files over one megabyte before running
  other validation. It also includes the fast unit suite so the single handoff command covers both
  characterization tests and the existing end-to-end smoke path.
- Added an architecture guide for the same-origin request flow, component responsibilities, PHP
  session lifecycle, validation boundaries, and sole external runtime dependency.
- Replaced anonymous chess-rules TODOs with stable debt IDs and a ledger containing impact and
  completion conditions. The local check now rejects untracked TODO/FIXME/HACK annotations.
- The unit harness now reports monotonic elapsed time and blocks when the suite exceeds its
  two-second default budget; `TEST_TIME_BUDGET_MS` supports explicit diagnostic overrides.
- Test discovery now operationally enforces the `tests/*Test.php` file convention, deterministic
  file ordering, behavior-oriented test names, and a non-empty suite.
- The test harness now shuffles cases and prints a replayable `TEST_SEED`; every GameService case
  constructs fresh session state, so hidden order dependencies become observable.
- Added a bounded flakiness probe that runs consecutive deterministic seeds, fails fast, and prints
  the exact replay command. It defaults to 20 runs and remains separate from the fast handoff check.
- Added a committed pre-commit hook and idempotent local installer. This clone uses
  `core.hooksPath=.githooks`, so scoped commits now prove the canonical check passes.
- The canonical check now validates the agent-guide contract: referenced docs must be non-empty,
  instructed commands must be executable and shell-valid, and key AGENTS.md references cannot drift.
- Added local runbooks for server/port failures, same-origin asset/API problems, session recovery,
  validation and flakiness failures, missing hooks, and security/privacy response.
- Added CODEOWNERS with a root fallback and explicit product, test, validation, documentation, and
  Ralph path ownership; the agent-documentation contract verifies the fallback remains valid.
- Added structured bug and feature issue templates for reproduction, chess-rule expectations,
  acceptance criteria, regression coverage, MVP alignment, and the no-cost/no-CI constraint.
