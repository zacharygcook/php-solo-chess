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
  | coordinates explicit chess-domain services
  v
Services/Chess/{Coordinate, Move, PieceMovement, PositionAnalyzer,
                CastlingResolver, GameStateFactory}
  |
  | returns canonical board and game-state decisions
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
- `backend/src/Services/GameService.php` is the application-level game orchestrator. It translates
  submitted coordinates into a move, coordinates validation and board mutation, updates turn/history,
  and persists accepted state through `SessionStore`.
- `backend/src/Services/Chess/Coordinate.php` is the algebraic-coordinate parsing boundary.
- `backend/src/Services/Chess/Move.php` carries one typed move and converts it to the existing history
  record contract.
- `backend/src/Services/Chess/PieceMovement.php` owns piece geometry, path clearance, captures, and
  attack geometry. Piece-specific decisions are intentionally separated into small methods.
- `backend/src/Services/Chess/PositionAnalyzer.php` finds kings and determines whether the opposing
  pieces attack their squares. `GameService` uses it both before accepting self-exposing moves and
  after moves to report check.
- `backend/src/Services/Chess/CastlingResolver.php` isolates the currently supported castling board
  transformation. Complete castling eligibility remains tracked in `DEBT-001`.
- `backend/src/Services/Chess/GameStateFactory.php` owns the initial board and stable state shape.
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
  rules and state-transition characterization. Tests exercise the public service while its domain
  collaborators remain implementation details.
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
