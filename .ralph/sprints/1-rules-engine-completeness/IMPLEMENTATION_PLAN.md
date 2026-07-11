# Implementation plan

1. Characterize the existing public game-state/API contract and introduce explicit position and move
   state without changing externally established baseline behavior.
2. Implement exhaustive legal-move generation and king-safety filtering, with behavior-first tests
   for ordinary movement and invalid moves.
3. Preserve position history needed for orthodox special moves and implement complete castling,
   en-passant, and all promotion choices.
4. Compute immutable terminal outcomes, including checkmate, stalemate, repetition, move-count,
   dead-position draws, and explicit draw claims where applicable.
5. Produce canonical SAN and FEN/coordinate interchange from the same domain state and verify that
   controller/API state remains compatible with the existing local browser path.

Each chunk must add or update focused rules tests before altering behavior, run the configured fast
gate, create a scoped commit, and append decisions, failed approaches, and validation evidence to
`SCRATCHPAD.md`.
