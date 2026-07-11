# Relevant context

- `README.md` is the operator guide and defines the current MVP.
- `AGENTS.md` defines repository-level agent constraints.
- `scripts/dev.sh` is the canonical local server entry point.
- `scripts/check.sh` is the current repo-native validation command.
- `RALPH_DOGFOOD_SCORECARD.md` records product-run workflow evidence.
- `frontend/index.html` expects the repository root to serve both `frontend/` and `backend/`.
- `backend/src/bootstrap.php` creates and uses `backend/storage/sessions/` for local PHP sessions.
