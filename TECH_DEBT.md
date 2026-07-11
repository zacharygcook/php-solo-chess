# Technical Debt

This ledger keeps shortcuts and unfinished behavior visible to humans and agents. Source annotations
must use `TODO(DEBT-NNN):`, `FIXME(DEBT-NNN):`, or `HACK(DEBT-NNN):`; `./scripts/check.sh` rejects
untracked annotations and IDs missing from this file.

## DEBT-004 — Reduce rules-engine complexity hotspots

- Status: open
- Area: chess rules architecture
- Impact: `GameService` has overall cyclomatic complexity 248; `checkMoveLegality()` is 60 and the
  largest NPath values exceed 11 million. Small rule changes can affect distant branches.
- Completion: add focused coverage around each extracted rule, move directional and piece-specific
  decisions behind clear domain boundaries, lower `phpmd.xml` thresholds in the same commits, and
  retain behavior through the full local suite.

## DEBT-001 — Replace incomplete castling logic

- Status: open
- Area: chess rules
- Impact: castling behavior is split between legacy and placeholder paths and is not yet safe to
  extend without characterization tests.
- Completion: cover legal and illegal king- and queen-side castling, check-through-square rules,
  moved-piece state, and board mutation with focused unit tests; remove the placeholder branches.

## DEBT-002 — Finish post-move check analysis

- Status: open
- Area: chess rules
- Impact: a legal move does not yet produce a complete explanation of checks against the opponent.
- Completion: calculate checking pieces from the resulting board, cover discovered and double check,
  expose stable state, and add focused unit tests.

## DEBT-003 — Implement checkmate and stalemate outcomes

- Status: open
- Area: chess rules
- Impact: the game cannot reliably terminate or distinguish checkmate from stalemate.
- Completion: enumerate every legal response, distinguish checkmate and stalemate, prevent moves
  after termination, and add rules-engine plus API integration coverage.
