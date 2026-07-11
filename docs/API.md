# API Reference

This file is generated from `config/api-endpoints.json`. Do not edit it by hand; run
`php scripts/generate-api-docs.php --write` and commit the manifest plus generated artifacts.

All endpoints are same-origin JSON and use the PHP session cookie described in `docs/ARCHITECTURE.md`.

| Method | Path | Purpose | Success |
|---|---|---|---:|
| `GET` | `/backend/public/api/session.php` | Load or create the current game session | `200` |
| `POST` | `/backend/public/api/move.php` | Submit one move for the active player | `200` |
| `POST` | `/backend/public/api/reset.php` | Reset the current session to the initial position | `200` |
| `POST` | `/backend/public/api/setup.php` | Request loading a position from FEN (currently a placeholder) | `202` |

The machine-readable OpenAPI 3.1 contract is [`openapi.json`](openapi.json).
