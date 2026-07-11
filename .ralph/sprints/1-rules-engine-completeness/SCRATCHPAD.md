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
