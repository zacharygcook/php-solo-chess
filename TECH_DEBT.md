# Technical Debt

This ledger keeps shortcuts and unfinished behavior visible to humans and agents. Source annotations
must use `TODO(DEBT-NNN):`, `FIXME(DEBT-NNN):`, or `HACK(DEBT-NNN):`; `./scripts/check.sh` rejects
untracked annotations and IDs missing from this file.

## DEBT-004 — Reduce rules-engine complexity hotspots

- Status: resolved 2026-07-11
- Area: chess rules architecture
- Original impact: `GameService` had overall cyclomatic complexity 248; `checkMoveLegality()` was 60
  and the largest NPath values exceeded 11 million. Normalized duplicate analysis marked 33% of
  significant backend/JavaScript lines as repeated. Small rule changes affected distant branches.
- Resolution: extracted typed coordinates and moves, piece movement, position attacks, castling
  resolution, and initial-state creation behind focused service boundaries. `GameService` fell from
  887 to 165 lines, the enforced per-method cyclomatic ceiling fell from 60 to 9, and measured
  duplication fell below 7% under a new 10% ceiling. Public rules coverage grew from 5 to 18 isolated
  tests and now protects every extracted movement family, blocking, captures, check reporting,
  self-check, check escape, and the supported castling paths.

## DEBT-001 — Replace incomplete castling logic

- Status: resolved 2026-07-11
- Area: chess rules
- Original impact: castling behavior was split between legacy and placeholder paths and was not safe
  to extend without characterization tests.
- Resolution: `CastlingResolver` now consumes explicit castling rights, verifies the required rook,
  clear path, and attacked king traversal squares, and moves the rook through the same domain move
  application path. Focused rules tests cover accepted king- and queen-side castling, moved-piece
  rights loss, blocked paths, castling while in check, castling through check, and exact board
  mutation.

## DEBT-002 — Finish post-move check analysis

- Status: open
- Area: chess rules
- Impact: a legal move does not yet produce a complete explanation of checks against the opponent.
- Completion: calculate checking pieces from the resulting board, cover discovered and double check,
  expose stable state, and add focused unit tests.

## DEBT-003 — Implement checkmate and stalemate outcomes

- Status: resolved 2026-07-11
- Area: chess rules
- Original impact: the game could not reliably terminate or distinguish checkmate from stalemate.
- Resolution: accepted moves now regenerate all legal replies, classify no-reply positions as
  checkmate or stalemate, persist `gameStatus`, `result`, and `terminationReason`, clear legal moves
  for finished games, and reject later move submissions without mutating board, turn, move history,
  or position history. Focused tests cover Fool's mate, stalemate, automatic dead-position draw,
  claim-required draw actions, and terminal immutability.
