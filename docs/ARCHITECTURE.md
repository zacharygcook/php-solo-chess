# Architecture

PHP Solo Chess is one local web application with a static jQuery frontend and a session-backed PHP
backend. There is no database, build step, dependency container, background worker, or deployment
service.

## Runtime flow

```text
Browser at /frontend/
  |
  | GET/POST JSON on the same origin
  v
/backend/public/api/{session,move,reset,setup}.php
  |
  | validates HTTP method and decodes request JSON
  v
GameController
  |
  | shapes API responses
  v
GameService  <---->  SessionStore  <---->  PHP session file
  |
  | owns board state and chess-rule decisions
  v
JsonResponse -> browser state render
```

The repository root must be the PHP server document root. The frontend and `/backend/public/api/`
then share an origin, so PHP's session cookie is sent with every API request. `scripts/dev.sh`
preserves this layout through `scripts/router.php`; the router exposes only frontend files and API
entry points while denying repository source/configuration. Serving only `frontend/` breaks the API
paths, while serving the root without the router exposes sensitive project files.

## Component responsibilities

- `frontend/index.html`, `frontend/assets/css/`, and `frontend/assets/js/app.js` render state, collect
  moves, and display backend messages. They do not decide whether a chess move is legal.
- `backend/public/api/` contains thin HTTP entry points. Each endpoint checks its method, decodes
  input where needed, and delegates immediately.
- `backend/src/Controllers/GameController.php` translates service results into the stable JSON
  response envelope: `success`, `message`, and `state`.
- `backend/src/Services/GameService.php` owns initial state, move validation, board mutation, turn
  changes, history, check state, and reset behavior. Rules changes require focused unit tests.
- `backend/src/Services/SessionStore.php` is the persistence boundary. It reads and writes one
  namespaced value in PHP's session.
- `backend/src/Http/JsonResponse.php` owns status codes, JSON content type, serialization, and
  response termination.

`config/architecture-layers.json` makes these boundaries executable. API entry points may depend on
controllers and HTTP response code; controllers may coordinate HTTP and services; services may only
depend on services; HTTP code may only depend on HTTP code. `scripts/check-architecture.php` rejects
new inward dependency violations.

## State lifecycle

The first session request creates the standard board and stores it under `solo_chess_state`. Later
requests load that state using the browser's session cookie. A valid move mutates the board, changes
the active color, appends history, and saves the whole state. Reset deletes the stored state and
creates a fresh game. Local session files live in `backend/storage/sessions/` and must never be
committed.

## Validation boundaries

- `./scripts/test.sh` calls `GameService` directly with isolated session arrays. Use it for chess
  rules and state-transition characterization.
- `./scripts/check.sh` adds repository policy, syntax checks, a real PHP server, cookie-backed API
  state, frontend delivery, and an `e2` to `e4` integration path.
- A visually successful browser move is not proof of chess correctness; rules belong in unit tests.

## External systems

The sole external runtime resource is pinned jQuery from `code.jquery.com`. The application has no
accounts, analytics, hosted persistence, paid services, or CI. Losing internet access prevents the
current frontend script from loading, but does not affect backend rules tests.

Every API request receives a safe request ID. `RequestLogger` returns it as `X-Request-ID` and emits
an allowlist-only JSON completion record to PHP's local error log so a failed response can be matched
to its duration and endpoint without recording request content.
