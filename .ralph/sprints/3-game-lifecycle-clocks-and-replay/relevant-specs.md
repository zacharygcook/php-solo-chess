# Relevant context

- `SPEC.md` sections 2–3 and the saved-history/timed-game journeys define lifecycle, clock,
  persistence, termination, and replay requirements.
- Sprint 1 owns board legality and canonical terminal fields; Sprint 2 owns identities and storage.
- `AGENTS.md` requires `$change-chess-rules` for game-state changes and focused rules tests before
  behavior expansion.
- `docs/ARCHITECTURE.md` requires application services to coordinate rules, clocks, accounts, and
  repositories while controllers stay thin.
- Timeout is a draw when the non-flagging side cannot legally win; finished games reject later moves
  or clock changes.
- Replay is a read-only view over ordered canonical move/FEN records and must not mutate the saved game.
