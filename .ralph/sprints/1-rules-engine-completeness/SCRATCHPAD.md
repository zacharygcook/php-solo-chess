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
