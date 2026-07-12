# Architecture

PHP Solo Chess is one local web application with a static vanilla JavaScript frontend and a PHP backend. Guest
games stay PHP-session backed. Authenticated local users also use SQLite persistence for the current
owned game and ordered move records. There is no build step, dependency container, background worker,
or deployment service.

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
  | for authenticated users only
  v
GamePersistenceService <----> GameRepository/MoveRepository <----> SQLite file
  |
  | coordinates explicit chess-domain services
  v
Services/Chess/{Coordinate, Move, PieceMovement, PositionAnalyzer,
                CastlingResolver, LegalMoveGenerator,
                TerminalStateResolver, NotationFormatter, GameStateFactory}
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

- `frontend/index.html`, `frontend/assets/css/`, and `frontend/assets/js/` render state, collect
  moves, and display backend messages through small API, UI-state, and board-rendering modules. They
  do not decide whether a chess move is legal.
- `backend/public/api/` contains thin HTTP entry points. Each endpoint checks its method, decodes
  input where needed, and delegates immediately.
- `backend/src/Controllers/GameController.php` translates service results into the stable JSON
  response envelope: `success`, `message`, and `state`.
- `backend/src/Services/GameService.php` is the application-level game orchestrator. It translates
  submitted coordinates into a move, coordinates validation and board mutation, updates turn/history,
  regenerates legal moves, terminal state, and notation, and persists accepted state through
  `SessionStore` plus the optional authenticated-user persistence service.
- `backend/src/Services/GamePersistenceService.php` owns the authenticated game persistence behavior.
  It loads only games owned by the current authenticated user, saves accepted canonical snapshots
  through repository transaction boundaries, and leaves guest sessions ephemeral.
- `backend/src/Services/Chess/Coordinate.php` is the algebraic-coordinate parsing boundary.
- `backend/src/Services/Chess/Move.php` carries one typed move and converts it to the existing history
  record contract.
- `backend/src/Services/Chess/PieceMovement.php` owns piece geometry, path clearance, captures, and
  attack geometry. Piece-specific decisions are intentionally separated into small methods.
- `backend/src/Services/Chess/PositionAnalyzer.php` finds kings and determines whether the opposing
  pieces attack their squares. `GameService` uses it both before accepting self-exposing moves and
  after moves to report check.
- `backend/src/Services/Chess/CastlingResolver.php` isolates castling eligibility and rook movement,
  including castling rights, clear paths, and attacked king traversal squares.
- `backend/src/Services/Chess/LegalMoveGenerator.php` produces deterministic legal destinations from
  the normalized rules state, including king-safety filtering and supported special moves.
- `backend/src/Services/Chess/TerminalStateResolver.php` classifies checkmate, stalemate,
  dead-position draws, claim-required draw actions, and finished-game state.
- `backend/src/Services/Chess/NotationFormatter.php` produces FEN for saved state and SAN plus
  coordinate notation for accepted moves.
- `backend/src/Services/Chess/GameStateFactory.php` owns the initial board and stable state shape.
- `backend/src/Services/SessionStore.php` is the session persistence boundary. It reads and writes
  namespaced session values for guest game state, authenticated user id, and the current durable game
  id.
- `backend/src/Repositories/` isolates PDO SQLite access for users, games, and moves. Game reads and
  updates are owner-scoped, and canonical game snapshots plus ordered moves are replaced atomically.
- `backend/src/Http/JsonResponse.php` owns status codes, JSON content type, serialization, and
  response termination.

`config/architecture-layers.json` makes these boundaries executable. API entry points may depend on
controllers and HTTP response code; controllers may coordinate HTTP and services; services may only
depend on services; HTTP code may only depend on HTTP code. `scripts/check-architecture.php` rejects
new inward dependency violations.

## State lifecycle

The first guest session request creates the standard board and stores it under `solo_chess_state`.
Later guest requests load that state using the browser's session cookie. A valid guest move mutates
the board, changes the active color, appends history, and saves the whole state back to the PHP
session only.

Authenticated requests keep the same session response shape, but `GamePersistenceService` first
reloads the current owned game id from SQLite when one exists. If the session points at another
user's game id, the service clears that pointer and falls back to the authenticated user's latest
owned game or the session state. Accepted moves and resets save the canonical state JSON and ordered
move rows through `GameRepository`; guests do not create durable game records. Local session files
live in `backend/storage/sessions/`, and the SQLite database lives under `backend/storage/`; neither
must be committed.

Rules-owned state is explicit in the saved game state: `castlingRights`, `enPassantTarget`,
`halfmoveClock`, `fullmoveNumber`, `positionHistory`, `legalMoves`, and `fen`. Accepted moves append
history records with `coordinate`, `san`, and post-move `fen` fields. Terminal state is explicit too:
`gameStatus`, `result`, `terminationReason`, `drawClaims`, and `availableActions`.
Board-derived endings such as checkmate, stalemate, and dead-position draws are resolved by the chess
domain immediately after an accepted move. Claim-required draw rules leave the game active and expose
`claimDraw` as an available action. Resignation, agreed draws, and clock timeouts are application-level
transitions for the future controls/clocks work; those paths must set the same terminal fields and
then rely on the existing finished-game guard to reject later moves.

## Validation boundaries

- `./scripts/test.sh` calls `GameService` directly with isolated session arrays. Use it for chess
  rules and state-transition characterization. Tests exercise the public service while its domain
  collaborators remain implementation details.
- `./scripts/check.sh` adds repository policy, syntax checks, a real PHP server, cookie-backed API
  state, frontend delivery, and an `e2` to `e4` integration path.
- A visually successful browser move is not proof of chess correctness; rules belong in unit tests.

## External systems

The application has no external runtime resource, remote accounts, analytics, hosted persistence,
paid services, or CI. Local accounts and saved games stay in the ignored SQLite database under
`backend/storage/` unless `SOLO_CHESS_DATABASE_PATH` points a process at another SQLite file.

Every API request receives a safe request ID. `RequestLogger` returns it as `X-Request-ID` and emits
an allowlist-only JSON completion record to PHP's local error log so a failed response can be matched
to its duration and endpoint without recording request content.
