# PHP Solo Chess

A local, two-sided chess board for practicing chess fundamentals. The current app is deliberately
small: plain PHP owns the game session and rules, while HTML, CSS, and JavaScript render the board.

## Run locally

Requirements:

- PHP 8.1 or newer
- A modern browser
- Internet access while the frontend still loads jQuery from its CDN

No Composer or npm install is currently required.

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

Run the repeatable baseline check before and after changes:

```bash
./scripts/check.sh
```

It rejects repository files larger than one megabyte, lints every PHP file, checks the frontend
JavaScript syntax, runs the unit suite, starts an isolated local server, loads a session, and plays
`e2` to `e4` through the real API.

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
