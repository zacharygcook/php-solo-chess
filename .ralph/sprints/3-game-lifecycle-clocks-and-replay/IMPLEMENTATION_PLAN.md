# Implementation plan

1. Model explicit game creation for untimed and validated preset/custom time controls, labels, and
   participant types.
2. Implement server-owned clock accounting using persisted remaining times and turn timestamps.
3. Add immutable application-level termination transitions for resignation, draw actions, timeout,
   abandonment/reset, and rules-owned draw claims.
4. Automatically persist accepted owned-game transitions and expose owner-scoped history/open/replay
   services while guests remain session-only.
5. Publish and integration-test lifecycle, action, history, and replay HTTP contracts.

Use `$change-chess-rules` whenever a chunk changes game-state or terminal behavior. Add focused tests
first, preserve the rules/application/repository boundaries, and record every handoff in the scratchpad.
