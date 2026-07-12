# Implementation plan

1. Add deterministic, idempotent SQLite setup for the explicit `users`, `games`, and `moves` model.
2. Build repository boundaries with transaction-safe reads and writes for users and canonical games.
3. Implement registration and authentication services using PHP password hashing and normalized,
   unique usernames.
4. Expose session-backed register, login, logout, and current-user HTTP contracts without leaking
   password material.
5. Persist and reload owned canonical game/move records while preserving an ephemeral guest path and
   enforcing owner isolation.

Each chunk begins with focused tests, runs the configured fast gate, creates a scoped commit, and
records decisions, dead ends, and validation evidence in `SCRATCHPAD.md`.
