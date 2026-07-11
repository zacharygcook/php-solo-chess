# PHP Solo Chess

A local, two-sided chess board for practicing chess fundamentals. The current app is deliberately
small: plain PHP owns the game session and rules, while HTML, CSS, and JavaScript render the board.

## Run locally

Requirements:

- PHP 8.1 or newer
- Composer 2 for development checks
- A modern browser
- Internet access while the frontend still loads jQuery from its CDN

Install the pinned, free development formatter after cloning:

```bash
composer install
```

Runtime code still has no Composer or npm dependency.

For an isolated free local environment, open the repository in a Dev Container or build it directly:

```bash
docker build -f .devcontainer/Dockerfile -t php-solo-chess-devcontainer .
```

The image pins PHP 8.4.19 and Composer 2.9.5 by digest and includes Node, curl, jq, and Git. The
devcontainer installs locked development tools and local Git hooks after creation. It does not use a
hosted codespace, CI runner, paid service, or external account.

Install the repository's local Git hooks once per clone:

```bash
./scripts/install-hooks.sh
```

The pre-commit hook runs `./scripts/check.sh`. It is intentionally local; this project does not use
CI or GitHub Actions.

Apply deterministic PHP and frontend text formatting with `composer format`; verify it without
writing through `composer format:check`. PHP CS Fixer is an MIT-licensed development-only tool pinned
in `composer.lock` and does not enter the application runtime.

Run static type analysis with `composer typecheck`. PHPStan is pinned, free, local-only, covers the
backend and unit tests at enforced level 5, and uses no hosted service.
Source and file naming rules are documented in [`docs/NAMING.md`](docs/NAMING.md) and enforced by the
canonical check.
PHPMD enforces the legacy rules engine's current cyclomatic non-regression ceiling through
`composer mess:check`. The method limit is 60; tighten it after safe, test-backed extraction and
never raise it merely to clear validation.
`composer duplicate:check` measures normalized six-line windows across backend and frontend
JavaScript. The current 33% duplicated-line ceiling exposes heavy legacy repetition and blocks any
increase; lower it alongside test-backed extraction.
`./scripts/check-dependency-weight.sh` attributes transitive Composer package counts to each direct
tool and measures the downloaded jQuery bytes against explicit budgets.
`config/dependency-usage.json` maps every direct tool and runtime dependency to executable source
evidence; the canonical check rejects unregistered or disconnected dependencies.

Direct dependencies follow the 30-day adoption rule in [`DEPENDENCY_POLICY.md`](DEPENDENCY_POLICY.md)
and `config/dependency-policy.json`; the canonical check rejects unapproved or too-new pins.
Run `./scripts/dependency-updates.sh` monthly during active work for a local proposal report covering
Composer tools and the jQuery CDN pin; it never edits dependencies or opens automated PRs.
The application currently needs no secrets; [`SECURITY.md`](SECURITY.md) documents local handling,
reporting, and response, while `.env.example` is the safe inventory of required variable names.
Generate a readable local assessment with `./scripts/security-review.sh`; reports stay ignored under
`.agent-readiness/`, and the canonical check validates a temporary report on every run.
Generate a combined coverage, duplication, and complexity snapshot with
`./scripts/generate-quality-report.sh`; the canonical check validates a temporary snapshot too.

```bash
./scripts/dev.sh
```

Open [http://127.0.0.1:8080/frontend/](http://127.0.0.1:8080/frontend/). Keep the terminal running;
press `Ctrl-C` to stop the server. To use another port, pass it as the first argument:

```bash
./scripts/dev.sh 8090
```

The PHP development server must use the repository root as its document root so the frontend and API
share one origin. Both `/frontend` and `/frontend/` are supported.

## Quick QA

Run the focused rules-engine unit tests:

```bash
./scripts/test.sh
```

The suite reports elapsed time and enforces a two-second local budget. Override the budget for a
diagnostic run with `TEST_TIME_BUDGET_MS=500 ./scripts/test.sh`; do not raise the committed default
without documenting why the test workload legitimately changed.

Run `composer coverage:check` for Xdebug line coverage across all backend source. The current 21%
minimum is an honest legacy ratchet, not a quality claim; raise it with each test-backed rules change
and never lower it to clear a failure. The pinned devcontainer includes Xdebug with coverage disabled
except during this explicit command.

Test files live directly under `tests/`, must end in `Test.php`, and are discovered in sorted order.
Test names should describe observable behavior, such as `player cannot move twice in succession`.
The runner fails when the naming convention discovers no tests.

Every test must create its own game state rather than relying on a prior test. The harness randomizes
execution order and prints the seed; replay a failure with `TEST_SEED=12345 ./scripts/test.sh`.

Probe for flaky or order-dependent tests across 20 deterministic seeds:

```bash
./scripts/test-flakiness.sh
```

Use `FLAKY_TEST_RUNS` and `FLAKY_TEST_FIRST_SEED` to widen or resume a probe. The command stops at
the first failure and prints its exact replay seed.

Run the repeatable baseline check before and after changes:

```bash
./scripts/check.sh
```

It validates the agent-documentation contract, rejects repository files larger than one megabyte,
lints all PHP and frontend JavaScript (including committed debug calls), verifies that source debt annotations
appear in [`TECH_DEBT.md`](TECH_DEBT.md), runs the unit suite, starts an isolated local server, loads
a session, and plays `e2` to `e4` through the real API.
It also probes HTTP method abuse, malformed JSON, response content types, and sensitive repository
paths against the allowlist router.

For a short manual pass:

1. Confirm the board and all 32 pieces render.
2. Move the white pawn from `e2` to `e4`; the status should report success and Black should move next.
3. Try moving another white piece immediately; the move should be rejected.
4. Make a legal black move and confirm both moves appear in history.
5. Refresh the browser and confirm the position survives in the PHP session.
6. Select **Reset** and confirm the initial position returns.

Session files are local scratch data under `backend/storage/sessions/` and are ignored by Git.

## Current milestone

The target MVP is correct, test-backed, locally playable chess: complete legal-move enforcement,
check/checkmate/stalemate, special moves, clear frontend feedback, and an end-to-end smoke test.
Multiplayer and themed animation work are later milestones.

Ralph workflow quality is tracked visibly in [`RALPH_DOGFOOD_SCORECARD.md`](RALPH_DOGFOOD_SCORECARD.md).
The runtime request flow and component boundaries are documented in
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).
The JSON API is generated into [`docs/API.md`](docs/API.md) and
[`docs/openapi.json`](docs/openapi.json) from the checked endpoint manifest.
For startup, session, validation, and local security failures, use
[`docs/RUNBOOKS.md`](docs/RUNBOOKS.md).
Milestone release validation and generated Git-history notes are documented in
[`docs/RELEASING.md`](docs/RELEASING.md).
`./scripts/package-release.sh <version>` creates a validated local archive, notes, checksums, and
manifest under ignored `dist/`; it never tags, pushes, uploads, or invokes CI.
Before opening or reviewing a pull request, generate a deterministic risk review with
`php scripts/review-change.php --base=<base-ref> --output=.agent-readiness/pr-review.md`. It reports
file-specific missing test, contract, dependency, security, and frontend evidence for human review.
