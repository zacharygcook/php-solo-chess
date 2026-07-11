# Local Runbooks

These procedures cover the operational surface of this local-only application. There is no hosted
environment, production database, CI, or paid monitoring service. Preserve failure output before
changing code; do not delete user work to make a check green.

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
5. If only the UI script fails while offline, check whether `code.jquery.com` is reachable. Backend
   unit tests remain usable without it; do not replace the dependency with an unreviewed package.

## Session state is stale or corrupt

Symptoms: an unexpected turn, malformed board, or state that persists after browser refresh.

1. Save the JSON from `/backend/public/api/session.php` if it is useful for reproducing a rules bug.
2. Use the UI Reset button or POST to `/backend/public/api/reset.php` on the local origin.
3. If reset cannot run, stop the local server and remove only files under
   `backend/storage/sessions/`; they are ignored scratch state.
4. Restart and run `./scripts/check.sh` before investigating rules behavior.

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

This application should contain no secrets or personal data. Do not paste tokens, cookies, or local
session contents into issues or commits. If one is committed, stop sharing the repository, revoke
the exposed credential at its source, remove it from the working tree and history with explicit
owner coordination, and document the incident. Git history rewriting is never an automatic repair.
