# Scratchpad

## 2026-07-11 — Sprint initialized

- Sprint starts from a passing Phase 0 baseline at commit `b9282e0` with Ralph runtime `0.5.6`.
- The fast gate is `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; the
  comprehensive sprint gate is `./scripts/check.sh`.
- Existing code is session-backed and has no SQLite/account implementation. Those concerns are
  intentionally deferred to Sprints 2 and 3.
- The current test suite covers baseline movement, basic king safety, and partial castling only; do
  not infer complete orthodox-rule support from its current green result.
- Preserve the controller response envelope and browser smoke path while evolving domain state.
- Read this file first and append any decision, dead end, validation evidence, and next-context
  handoff before every exit.

## 2026-07-11 — Chunk 1 complete

- Completed chunk 1 only: characterized the public `GameService` state contract and added explicit
  rules metadata for castling rights, en-passant target, halfmove clock, fullmove number, and
  position history.
- Decision: keep the current array-backed state/API envelope for compatibility, but normalize older
  session fixtures through `GameStateFactory` so tests and existing sessions receive the explicit
  metadata.
- Decision: update eligibility metadata on accepted moves, including pawn double-step en-passant
  targets, fullmove advancement after Black moves, halfmove resets, and castling-right loss from
  king/rook moves or rook capture.
- Deferred: `CastlingResolver` still does not consume `castlingRights`; complete castling
  eligibility enforcement remains chunk 3/`DEBT-001`.
- Failed approaches: none requiring rollback. Formatting check caught one PHP-CS-Fixer spacing issue
  in an arrow function and it was fixed manually.
- Validation evidence: baseline `./scripts/check.sh` passed before edits. After edits,
  `composer format:check`, `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`,
  and full `./scripts/check.sh` all passed.
- Next handoff: start chunk 2 by generating deterministic legal ordinary moves with king-safety
  filtering from the explicit state; keep HTTP/session/rendering code out of rules decisions.

## 2026-07-11 — Chunk 2 complete

- Completed chunk 2 only: added `LegalMoveGenerator` under the chess-domain services and refreshed
  derived `legalMoves` from `GameService` on state load, reset, and accepted moves.
- Decision: expose generated legal moves as `array<string, list<string>>` keyed by source algebraic
  square, with row-major destination ordering. The generator uses existing `PieceMovement` geometry
  and `PositionAnalyzer` king-safety filtering instead of adding an HTTP/session-specific path.
- Decision: special moves remain out of the generator for chunk 2; castling, en-passant, and
  promotion eligibility are still chunk 3 work.
- Bug found and fixed: ordinary movement could treat the opponent king as capturable. Added a
  regression proving generated moves omit king captures and submitted king captures are rejected
  without mutating board or history.
- Failed approaches: initial test expectations assumed human-friendly destination ordering and
  missed valid queen retreat squares; corrected the tests to lock the implemented row-major contract
  after the generator exposed the deterministic list.
- Validation evidence: pre-edit baseline `./scripts/check.sh` passed. Focused red tests failed on
  missing `legalMoves` as expected. After implementation, `composer format:check`,
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`,
  `./scripts/test-flakiness.sh`, and full `./scripts/check.sh` all passed.
- Next handoff: start chunk 3 by consuming explicit eligibility state in special moves, especially
  complete castling rights/check-through-square enforcement, en-passant immediacy, and promotion
  choices with behavior-first tests.

## 2026-07-11 — Chunk 3 complete

- Completed chunk 3 only: special moves now consume explicit rules state through the domain services
  and accepted move path. Castling uses castling rights, rook presence, clear paths, and king
  traversal safety; en passant uses the immediate target, capture direction, and captured-pawn
  removal; promotion requires one of queen, rook, bishop, or knight and applies the selected piece.
- Decision: keep promotion input as full lowercase names (`queen`, `rook`, `bishop`, `knight`) and
  store that input in the existing move-history `promotion` field while preserving two-character
  board piece codes.
- Decision: legal-move generation now receives full normalized rules state so generated castling and
  en-passant destinations use the same eligibility data as submitted moves. HTTP, session, and
  rendering code remain outside the rule decisions.
- Bug found and fixed: en passant initially accepted a pawn moving diagonally in the wrong direction
  when the target square matched. Added a regression and direction check in both the submitted-move
  path and legal-move generator.
- Debt update: `DEBT-001` is resolved because focused tests now cover legal king- and queen-side
  castling, moved-piece rights, blocked paths, castling while in check, castling through check, and
  exact rook/king board mutation.
- Failed approaches: one early test over-specified the complete king legal-move list when only
  castling destinations mattered; it was narrowed to assert the castling destinations directly.
- Validation evidence: pre-edit baseline `./scripts/check.sh` passed. Focused special-move tests
  failed first on missing promotion application, en-passant capture, castling rights/check traversal,
  and generated castling destinations. After implementation, `composer format:check`,
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`,
  `./scripts/test-flakiness.sh`, and full `./scripts/check.sh` all passed. The exact fast gate used
  test seed `100426456` and ran 38 tests with 0 failures.
- Next handoff: start chunk 4 by computing immutable terminal outcomes from generated legal moves:
  distinguish checkmate from stalemate, represent draw conditions and claim-required draw actions,
  and reject all move/clock transitions once a game is finished.

## 2026-07-11 — Chunk 4 complete

- Completed chunk 4 only: accepted moves now resolve terminal board outcomes through
  `TerminalStateResolver`, persist `gameStatus`, `result`, `terminationReason`, `drawClaims`, and
  `availableActions`, clear legal moves for finished games, and reject later move submissions without
  mutating board, turn, move history, or position history.
- Decision: terminal evaluation runs after accepted moves, using the regenerated legal replies from
  the domain generator. This keeps existing reduced-board behavior tests isolated while ensuring
  positions reached through the authoritative move path are saved with immutable outcomes.
- Decision: threefold repetition and the fifty-move rule remain claim-required in this chunk: the
  game stays active and exposes `claimDraw` rather than ending automatically. Dead positions,
  checkmate, and stalemate finish immediately.
- Decision: resignation, agreed draw, and timeout are documented as future application-level
  transitions for the controls/clocks chunks; they must set the same terminal fields and then rely on
  the finished-game guard.
- Debt update: `DEBT-003` is resolved because focused tests now cover checkmate, stalemate,
  automatic dead-position draw, claim-required draw actions, and terminal immutability.
- Failed approaches: first implementation read the typed dead-position piece records with numeric
  offsets instead of the `piece` key; focused tests exposed the warning and it was fixed. The first
  repetition fixture repeated a placeholder key instead of the post-move position key; it was
  corrected to compute the exact repeated key through `GameStateFactory`.
- Validation evidence: pre-edit baseline `./scripts/check.sh` passed. Focused terminal tests failed
  first on missing terminal fields and missing classifications. After implementation,
  `composer format:check`, exact fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`,
  `./scripts/test-flakiness.sh`, and full `./scripts/check.sh` all passed. The exact fast gate used
  test seed `704628621` and ran 42 tests with 0 failures.
- Next handoff: start chunk 5 by generating canonical SAN plus FEN/coordinate interchange from the
  explicit domain state, preserving the existing controller response envelope and browser smoke path.
