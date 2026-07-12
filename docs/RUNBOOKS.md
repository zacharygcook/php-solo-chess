# Local Runbooks

These procedures cover the operational surface of this local-only application. There is no hosted
environment, production database, CI, or paid monitoring service. Local accounts and saved games live
in an ignored SQLite file. Preserve failure output before changing code; do not delete user work to
make a check green.

## Local server does not start

Symptoms: `./scripts/dev.sh` exits, PHP reports an address conflict, or the browser cannot connect.

1. Confirm `php -v` reports PHP 8.1 or newer.
2. Retry on a different port: `./scripts/dev.sh 8090`.
3. Open the exact URL printed by the script, including `/frontend/`.
4. If the port still conflicts, inspect the owner with `lsof -nP -iTCP:8090 -sTCP:LISTEN` and stop
   it only when it is a process you own and recognize.
5. Run `./scripts/check.sh` to distinguish application failure from a browser-only problem.

## Frontend loads without styles or cannot reach the API

Symptoms: an unstyled board, asset 404s, or “Failed to reach backend session endpoint.”

1. Start through `./scripts/dev.sh`; do not serve `frontend/` as the document root and do not start
   the repository-root server without `scripts/router.php`.
2. Confirm the browser URL and API share one origin. The repository root must be PHP's document root.
3. Request `/frontend/assets/css/styles.css` and `/backend/public/api/session.php` on that origin.
4. Run `./scripts/check.sh`, which verifies both frontend URL forms, assets, cookies, and a move.
5. If only a UI module fails, request `/frontend/assets/js/app.js` and the imported module path named
   by the browser console on the same local origin; do not add an unreviewed package as a shortcut.

## Session or local persistence state is stale or corrupt

Symptoms: an unexpected turn, malformed board, login state that does not match the browser session,
or state that persists after browser refresh.

1. Save the JSON from `/backend/public/api/session.php` if it is useful for reproducing a rules bug.
2. Use the UI Reset button or POST to `/backend/public/api/reset.php` on the local origin.
3. If reset cannot run and only guest state is affected, stop the local server and remove only files
   under `backend/storage/sessions/`; they are ignored scratch state.
4. If local account or saved-game data is corrupt and can be discarded, stop the server and remove
   `backend/storage/solo-chess.sqlite` plus any SQLite `-journal`, `-wal`, or `-shm` sidecar files.
   To avoid touching the default database during diagnostics, start PHP with
   `SOLO_CHESS_DATABASE_PATH=/tmp/solo-chess-debug.sqlite`.
5. Restart and run `./scripts/check.sh` before investigating rules behavior.

## PGN download fails

Symptoms: **Download PGN** returns JSON instead of a file, a saved-game row will not export, or the
downloaded movetext/result does not match the saved game.

1. Confirm the request is same-origin under `/backend/public/api/games/export.php`.
2. For a saved-game export, confirm the browser is logged in as the owner of that saved game.
3. For an active guest export, omit the `id` query parameter so only the current PHP session state is
   used.
4. Run `php scripts/generate-api-docs.php --check` if the documented content type or endpoint list
   looks stale.
5. Run `./scripts/test.sh` before debugging PGN correctness; exporter and verifier tests replay
   canonical move records through the authoritative move path.

## Canonical check fails

1. Read the named step and its complete error output; each policy command can also run separately
   from `scripts/`.
2. Run `git status --short` and preserve unrelated user changes.
3. For a unit failure, replay the printed seed with `TEST_SEED=<seed> ./scripts/test.sh`.
4. For suspected coupling, run `./scripts/test-flakiness.sh`.
5. For server readiness failure, inspect the temporary server log path printed by the failing check
   before it is cleaned up, then reproduce with `./scripts/dev.sh 8090`.
6. Fix the capability or product defect; never weaken a threshold merely to unblock a commit.

## Local pre-commit hook is missing

1. Run `./scripts/install-hooks.sh`.
2. Confirm `git config --local --get core.hooksPath` prints `.githooks`.
3. Run `./scripts/check.sh` directly before retrying the commit.

## Security or privacy concern

This application should contain no repository secrets. Local account data, password hashes, cookies,
and session contents are ignored runtime data; do not paste them into issues or commits. If a real
credential is committed, stop sharing the repository, revoke the exposed credential at its source,
remove it from the working tree and history with explicit owner coordination, and document the
incident. Git history rewriting is never an automatic repair.
