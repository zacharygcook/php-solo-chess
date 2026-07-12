# API Reference

This file is generated from `config/api-endpoints.json`. Do not edit it by hand; run
`php scripts/generate-api-docs.php --write` and commit the manifest plus generated artifacts.

All endpoints are same-origin JSON and use the PHP session cookie described in `docs/ARCHITECTURE.md`.

Successful responses keep the stable envelope `success`, `message`, and `state`. Game state
contains the board, participants, time control, server-owned clock state, move history, active
color, legal moves, FEN, castling/en-passant fields, terminal fields, draw offers, and
draw-claim actions. Accepted move-history records include coordinate notation, SAN, post-move
FEN, and clock snapshots. Promotion requests use `queen`, `rook`, `bishop`, or `knight`.
Auth state contains only the current safe user identity or `null`, never password material.
History, saved-game open, and replay endpoints require an authenticated owner session and never
mutate the active game while returning saved replay positions.

| Method | Path | Purpose | Success |
|---|---|---|---:|
| `GET` | `/backend/public/api/session.php` | Load or create the current game session | `200` |
| `POST` | `/backend/public/api/auth/register.php` | Register a local user and authenticate the session | `201` |
| `POST` | `/backend/public/api/auth/login.php` | Authenticate the session as an existing local user | `200` |
| `POST` | `/backend/public/api/auth/logout.php` | Clear the authenticated user from the current session | `200` |
| `GET` | `/backend/public/api/auth/user.php` | Load the authenticated user for the current session | `200` |
| `POST` | `/backend/public/api/move.php` | Submit one move for the active player | `200` |
| `POST` | `/backend/public/api/games/new.php` | Create a new untimed, preset, or custom timed game | `201` |
| `POST` | `/backend/public/api/games/move.php` | Submit one move for the active player through the games API | `200` |
| `POST` | `/backend/public/api/games/resign.php` | Resign the active game for one side | `200` |
| `POST` | `/backend/public/api/games/draw-offer.php` | Offer a draw for the side to move | `200` |
| `POST` | `/backend/public/api/games/draw-accept.php` | Accept an available draw offer as the opponent | `200` |
| `POST` | `/backend/public/api/games/draw-claim.php` | Claim an available rule-owned draw | `200` |
| `POST` | `/backend/public/api/games/abandon.php` | Abandon the active game without a winner | `200` |
| `GET` | `/backend/public/api/games/history.php` | List saved games for the authenticated owner | `200` |
| `GET` | `/backend/public/api/games/open.php` | Open one saved game owned by the authenticated user | `200` |
| `GET` | `/backend/public/api/games/replay.php` | Load read-only replay data for one saved game owned by the authenticated user | `200` |
| `POST` | `/backend/public/api/reset.php` | Reset the current session to the initial position | `200` |
| `POST` | `/backend/public/api/setup.php` | Request loading a position from FEN (currently a placeholder) | `202` |

The machine-readable OpenAPI 3.1 contract is [`openapi.json`](openapi.json).
