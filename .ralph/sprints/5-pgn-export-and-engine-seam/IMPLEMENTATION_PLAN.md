# Implementation plan

1. Generate standards-compliant PGN headers and movetext from canonical saved game/move records.
2. Prove exported movetext/result reproduces the saved final position and terminal result.
3. Add an authorized download endpoint and browser action for completed or saved games.
4. Define FEN/context input and coordinate-move output interfaces for a future engine, with a
   deterministic fake adapter whose proposals use the normal move path.
5. Document and integration-test PGN and engine boundaries while explicitly excluding real analysis.

Use `$change-chess-rules` if notation replay or adapter integration changes move application. Keep
export and engine code as consumers of canonical domain/persistence state.
