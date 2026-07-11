# PHP Solo Chess

A local, two-sided chess board for practicing chess fundamentals. The current app is deliberately
small: plain PHP owns the game session and rules, while HTML, CSS, and JavaScript render the board.

## Run locally

Requirements:

- PHP 8.1 or newer
- A modern browser
- Internet access while the frontend still loads jQuery from its CDN

No Composer or npm install is currently required.

Install the repository's local Git hooks once per clone:

```bash
./scripts/install-hooks.sh
```

The pre-commit hook runs `./scripts/check.sh`. It is intentionally local; this project does not use
CI or GitHub Actions.

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
lints every PHP file, checks the frontend JavaScript syntax, verifies that source debt annotations
appear in [`TECH_DEBT.md`](TECH_DEBT.md), runs the unit suite, starts an isolated local server, loads
a session, and plays `e2` to `e4` through the real API.

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
