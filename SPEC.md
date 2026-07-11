# PHP Solo Chess — Product Specification

## Product summary

PHP Solo Chess is a deliberately simple, local-first chess application for playing both sides on one
computer. Its first responsibility is absolute trustworthiness: it must allow every legal chess move,
reject every illegal move, preserve correct game state, and identify game outcomes correctly.

The finished MVP should feel like a small, polished personal chess table—not a chess platform. A
person can create a local account, start an untimed or timed pass-and-play game, interact naturally
with the board, return to saved games, and download the finished game as PGN.

The final required milestone leaves a clean boundary for a future computer opponent without actually
adding one. Engine-powered review is a desirable stretch capability only after the required MVP is
complete and reliable.

## Product principles

1. **Chess correctness before features.** No account, animation, or visual improvement matters while
   a legal move can be rejected, an illegal move accepted, or a game result misclassified.
2. **Crazy-simple architecture.** Prefer plain PHP, browser-native JavaScript, CSS, SQLite, and files
   that can be understood without framework knowledge.
3. **Local-first operation.** One command starts the complete application on a personal computer.
4. **Server-authoritative games.** The browser presents state; PHP decides legal moves, time, and
   game outcomes.
5. **Verification is a feature.** Rules behavior must be protected by deterministic tests and the
   same repository-owned validation command used locally and by autonomous agents.
6. **Polish should remain lightweight.** Sound and animation should make the board satisfying without
   introducing a frontend framework or media pipeline.

## Required MVP

### 1. Correct chess games

The application must support a complete orthodox chess game from the standard initial position.

- Every piece moves and captures correctly.
- A player cannot move an opponent's piece, move out of turn, capture a friendly piece, move through
  blocking pieces, expose their own king, or leave their king in check.
- Check is detected after every move and communicated clearly.
- Checkmate, stalemate, resignation, timeout, and agreed draws end the game with the correct result.
- Castling, en passant, and promotion work under their complete eligibility rules.
- Promotion offers queen, rook, bishop, and knight rather than silently forcing a queen.
- Repetition, move-count, and dead-position draw conditions are represented correctly, including a
  draw-claim action when the rules require a player claim.
- Once a game ends, no additional moves or clock changes are accepted.
- The same position and history always produce the same legal moves and game status.

The specification intentionally does not restate chess rules. Current orthodox chess rules are the
domain standard; executable examples and regression fixtures belong in the rules-engine test suite.

### 2. Local accounts and saved history

A person can use the app without an account for an ephemeral game or create a local account to retain
games on that installation.

- Registration asks for a unique username, display name, and password.
- Login and logout make it easy to switch between local users.
- Passwords are never stored as plaintext; PHP's built-in password hashing is sufficient.
- There are no email addresses, password recovery, roles, permissions, social login, or administrator
  features in this build.
- A logged-in user owns every game they start. The second local player may be given a display label
  but does not need another account.
- Owned games are saved automatically after every accepted move and game-state transition.
- Personal history lists date, result, colors/player labels, time control, and completion reason.
- A saved game can be opened and replayed move by move.

### 3. Game creation and local pass-and-play

Starting a game should take only a few seconds.

- Choose untimed play or a time control.
- Provide optional labels for White and Black; sensible defaults are used when omitted.
- Offer useful presets such as `1+0`, `3+2`, `5+0`, `10+0`, and `15+10`, plus a custom base time and
  increment.
- Clearly show whose turn it is, both clocks, current check state, and the final result.
- Clocks survive refreshes and are based on server-owned remaining time and turn timestamps rather
  than trusting a browser interval.
- Reaching zero ends the game by timeout unless the position cannot legally be won, in which case the
  correct draw result is recorded.
- Provide straightforward controls for new game, reset/abandon, resign, offer/accept draw, sound,
  and board orientation.

### 4. Board interaction

- Pieces can be moved by drag and drop.
- Click/tap source-and-destination movement remains available for accessibility and touch devices.
- Selecting or dragging a piece visually identifies it and its legal destination squares.
- Illegal attempts leave the position unchanged and explain the rejection without disruptive alerts.
- The board works at common laptop and mobile viewport sizes.
- The active side, last move, checked king, captures, and final position are visually distinct.
- Move history uses standard algebraic notation and supports stepping backward and forward during
  review without mutating the saved game.

### 5. Sound and game-ending feedback

- Lightweight sounds distinguish a normal move, capture, check, and game ending.
- One obvious control turns all sound on or off; the preference persists in that browser.
- Checkmate, stalemate, and other draws receive distinct, brief visual treatments.
- Animation never delays state persistence, hides the final position, or prevents immediate control
  of the application.
- Audio assets should be small, locally served, and safe to omit when the browser cannot play sound.

### 6. PGN export

- A completed or saved game can be downloaded as a valid `.pgn` file.
- Export uses standard algebraic notation and includes appropriate event, site, date, round, White,
  Black, result, and time-control headers when known.
- Check, checkmate, castling, captures, and promotion are notated correctly.
- The exported result and move list must reproduce the application's saved final position.
- PGN is generated from canonical saved game data, not reconstructed from text rendered in the UI.

### 7. Ready for a future computer opponent

The required stopping point includes an engine seam but no computer opponent.

- Game rules do not depend on whether a move came from a human or a future engine.
- A future engine adapter can receive the current position plus game context and return a proposed
  move, which must pass through the same legal-move application path as human input.
- Position interchange uses established chess representations such as FEN; move interchange uses a
  stable coordinate representation.
- The UI and persistence model can identify a participant as a local human or future engine without
  special-casing rules behavior.
- No engine binary, model download, search process, difficulty system, or AI move selection is part
  of the required MVP.

## Stretch milestone: engine-powered game review

After every required MVP acceptance criterion passes, a free local UCI-compatible chess engine may
be integrated for analysis—not as the opponent required by this build.

- Analyze a completed game without changing its official moves or result.
- Show an evaluation bar while stepping through positions.
- Show the top three candidate moves and their evaluations for the selected position.
- Make analysis asynchronous and visibly optional so ordinary play never waits for an engine.
- Cache completed analysis for saved games when practical.
- Keep the engine behind an adapter so a locally installed engine, WebAssembly engine, or future
  custom model can replace it without changing game rules or saved-game storage.
- Engine selection, packaging, licensing review, and resource limits are separate implementation
  decisions and must not complicate the required MVP prematurely.

## Architecture constraints

### Technology

- PHP 8.1 or newer for HTTP endpoints, rules, accounts, sessions, persistence, clocks, and export.
- Vanilla browser JavaScript and CSS for the interface. Remove the existing jQuery dependency rather
  than adding more client libraries.
- SQLite through PHP PDO for users, games, moves, and durable metadata.
- PHP sessions for the active login and in-progress browser context.
- No application framework, ORM, dependency-injection container, frontend framework, bundler,
  WebSocket service, queue, or background worker.
- No Composer or npm runtime dependency is expected. Development tooling is allowed only when it
  provides a clear verification payoff that cannot be achieved cleanly with the existing native test
  harness.

### Domain boundaries

- A rules engine owns positions, legal move generation, move application, notation, and terminal
  status. It accepts explicit state and returns explicit results; it does not read HTTP input, render
  HTML, or directly manage sessions.
- Application services coordinate rules, clocks, accounts, and persistence.
- Repositories isolate SQLite reads and writes from domain behavior.
- HTTP controllers validate requests and return stable JSON envelopes without embedding chess rules.
- The frontend renders server state and submits intent. Client-side legal-move hints are advisory;
  the backend remains authoritative.
- PGN export and future engine integration consume canonical game/domain state rather than scraping
  controller responses or browser markup.

### Persistence

The data model should remain small and explicit:

- `users`: username, display name, password hash, and timestamps.
- `games`: owner, player labels/types, status, result, termination reason, time control, current
  position/state, clock state, and timestamps.
- `moves`: game, ply number, move coordinates, standard notation, position-after-move, clock values,
  and timestamp.

Database setup must be deterministic and idempotent. The local database and session files are runtime
data and must never be committed.

## User journeys

### Play immediately

1. Start the local server and open the printed URL.
2. Choose an untimed game and accept default player labels.
3. Play both sides using drag/drop or click movement.
4. See check and final-result feedback, then download PGN.

### Keep personal history

1. Create a local account or log in.
2. Start and complete one or more games.
3. Open personal history and select a game.
4. Replay moves, inspect the final result, and download its PGN.
5. Log out or switch to another local account without seeing the first account's personal history.

### Play with clocks

1. Start a preset or custom timed game.
2. See the correct active clock run and increment apply after accepted moves.
3. Refresh without resetting or pausing elapsed time.
4. Finish by board result, resignation, draw, or timeout and see the recorded reason.

## Out of scope

- Internet deployment, production operations, scaling, or public hardening.
- Online multiplayer, matchmaking, spectators, chat, ratings, tournaments, or leaderboards.
- Email, account recovery, MFA, OAuth, roles, moderation, or administrative tools.
- A computer opponent, difficulty levels, engine downloads, or model training.
- Opening databases, puzzles, lessons, social features, cloud synchronization, or mobile applications.
- Elaborate security architecture. Basic safe defaults still apply: hashed passwords, parameterized
  SQLite queries, server-side authorization for saved history, and no committed credentials.
- A framework migration or abstraction layer whose only benefit is architectural fashion.

## MVP acceptance criteria

The required MVP is complete when all of the following are true:

1. The complete rules suite passes, covering ordinary movement, king safety, special moves, check,
   checkmate, stalemate, draw conditions, promotion choices, and terminal-state immutability.
2. A person can complete representative untimed and timed games entirely through the browser without
   state divergence between UI, API, persistence, clocks, history, or PGN.
3. Registration, login, logout, account switching, automatic game saving, personal history, and game
   replay work locally with SQLite.
4. Drag/drop, click/tap movement, legal destination feedback, sound toggle, clocks, captured pieces,
   and distinct game-ending feedback work at laptop and mobile sizes.
5. Saved games export valid PGN that reproduces their moves, result, and final position.
6. The engine adapter boundary is documented and tested with a deterministic fake adapter; no real
   engine is bundled or required.
7. `./scripts/check.sh` runs all repository-owned static checks, rules tests, integration tests, and
   browser smoke coverage deterministically from a clean clone.
8. Local startup still requires one documented command and no production service account, paid
   provider, or external database.

The optional engine-review milestone does not block MVP completion.
