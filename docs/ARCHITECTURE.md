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
/backend/public/api/{session,move,reset,setup,auth/*,games/*}.php
  |
  | validates HTTP method and decodes request JSON
  v
GameController / AuthController / GameHistoryController
  |
  | shapes API responses
  v
GameService <----> GameLifecycleService/TimeControl/GameClock
  |
  | stores the active browser context
  v
SessionStore <----> PHP session file
  |
  | for authenticated users only
  v
GamePersistenceService <----> GameRepository/MoveRepository <----> SQLite file
  ^
  | owner-scoped read-only history and replay
  |
GameHistoryService
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
  input where needed, and delegates immediately. The legacy session/move/reset endpoints and the
  additive `/backend/public/api/games/` lifecycle endpoints share the same application services.
- `backend/src/Controllers/GameController.php` translates service results into the stable JSON
  response envelope: `success`, `message`, and `state` for the active game, moves, creation,
  resignation, draw actions, and abandonment.
- `backend/src/Controllers/GameHistoryController.php` translates authenticated saved-history, open,
  and replay service results into the same stable response envelope without mutating the active game.
- `backend/src/Services/GameService.php` is the application-level game orchestrator. It translates
  submitted coordinates into a move, coordinates validation and board mutation, updates turn/history,
  applies server-owned clock accounting, resolves timeout and action-based terminal transitions,
  regenerates legal moves, terminal state, and notation, and persists accepted state through
  `SessionStore` plus the optional authenticated-user persistence service.
- `backend/src/Services/GameLifecycleService.php` creates canonical new games with participant
  labels/types, explicit time-control metadata, and initialized clock state.
- `backend/src/Services/TimeControl.php` parses untimed play, supported presets, and validated
  custom base/increment controls into the stable state shape.
- `backend/src/Services/GameClock.php` owns server-authoritative clock projection, accepted-move
  debit/increment accounting, timeout detection, and move-history clock snapshots.
- `backend/src/Services/GameHistoryService.php` lists authenticated owner history and opens saved
  games with read-only replay positions and moves built from persisted records.
- `backend/src/Services/PgnExporter.php`, `PgnVerifier.php`, and `PgnDownloadService.php` consume
  canonical saved or session-owned game state plus ordered move records to produce PGN, verify that
  persisted coordinates replay to the saved final FEN/result, and stream authorized downloads. They
  do not read rendered move history or browser markup.
- `backend/src/Engine/` defines the passive future-engine seam. `EngineRequest` exposes canonical
  FEN, active color, legal moves, participant metadata, terminal metadata, and stable context;
  `EngineAdapter` returns coordinate proposals; `FakeEngineAdapter` is deterministic test support.
  The seam does not bundle an engine or mutate game state.
- `backend/src/Services/GamePersistenceService.php` owns the authenticated game persistence behavior.
  It loads only games owned by the current authenticated user, saves accepted canonical snapshots
  through repository transaction boundaries, persists terminal/time-control/clock metadata, and
  leaves guest sessions ephemeral.
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
  dead-position draws, claim-required draw actions, timeout mating-material eligibility, and
  finished-game state.
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

The first guest session request creates an untimed standard board and stores it under
`solo_chess_state`. A new-game request can instead create untimed play, a supported preset, or a
validated custom time control with participant labels and types. Later guest requests load that state
using the browser's session cookie. A valid guest move mutates the board, debits the moving side's
server-owned clock when the game is timed, applies increment exactly once, changes the active color,
appends history with notation/FEN/clock snapshots, and saves the whole state back to the PHP session
only. Rejected moves and actions return the current projected state without saving a mutation.

Authenticated requests keep the same session response shape, but `GamePersistenceService` first
reloads the current owned game id from SQLite when one exists. If the session points at another
user's game id, the service clears that pointer and falls back to the authenticated user's latest
owned game or the session state. Starting a new authenticated game creates a new owned game row.
Accepted moves, resets, and lifecycle transitions save the canonical state JSON, terminal metadata,
time-control JSON, clock-state JSON, completion timestamp, and ordered move rows through
`GameRepository`; guests do not create durable game records. Local session files live in
`backend/storage/sessions/`, and the SQLite database lives under `backend/storage/`; neither must be
committed.

Rules-owned state is explicit in the saved game state: `castlingRights`, `enPassantTarget`,
`halfmoveClock`, `fullmoveNumber`, `positionHistory`, `legalMoves`, and `fen`. Accepted moves append
history records with `coordinate`, `san`, and post-move `fen` fields. Terminal state is explicit too:
`gameStatus`, `result`, `terminationReason`, `completedAt`, `drawClaims`, `availableActions`, and
`drawOffer`. Game setup and clocks are explicit in `participants`, `timeControl`, and `clockState`.
Board-derived endings such as checkmate, stalemate, and dead-position draws are resolved by the chess
domain immediately after an accepted move. Claim-required draw rules leave the game active and expose
`claimDraw` as an available action. Resignation, accepted draw offers, draw claims, abandonment, and
clock timeouts are application-level transitions that set the same terminal fields, clear active
actions/offers, and rely on the finished-game guard to reject later moves and clock changes. A timeout
is recorded as a draw only when the non-flagging side cannot legally win from the remaining material.

Saved history and replay are owner-scoped reads over persisted records. History summaries expose
dates, result/status, completion reason, labels, participant types, and time control. Opening a saved
game returns its canonical saved state plus ordered replay data; replay positions are rebuilt from
the initial position and persisted move FEN/clock rows, so stepping through replay data cannot mutate
the saved game or the active browser session.

## Validation boundaries

- `./scripts/test.sh` calls `GameService` directly with isolated session arrays. Use it for chess
  rules and state-transition characterization. Tests exercise the public service while its domain
  collaborators remain implementation details.
- `./scripts/check.sh` adds repository policy, syntax checks, a real PHP server, cookie-backed API
  state, frontend delivery, and an `e2` to `e4` integration path. It also runs
  `scripts/browser-smoke.sh`, which drives Chrome/Chromium through guest play, account navigation,
  timed status, saved-game replay, responsive layout, and sound toggling without adding npm
  dependencies.
- A visually successful browser move is not proof of chess correctness; rules belong in unit tests.

## External systems

The application has no external runtime resource, remote accounts, analytics, hosted persistence,
paid services, or CI. Local accounts and saved games stay in the ignored SQLite database under
`backend/storage/` unless `SOLO_CHESS_DATABASE_PATH` points a process at another SQLite file.

Every API request receives a safe request ID. `RequestLogger` returns it as `X-Request-ID` and emits
an allowlist-only JSON completion record to PHP's local error log so a failed response can be matched
to its duration and endpoint without recording request content.
