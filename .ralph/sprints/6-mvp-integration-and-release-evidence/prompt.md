# Sprint 6: MVP integration and release evidence

Read `SCRATCHPAD.md` first, then the plan, relevant context, and `chunks.json`. Work only on the next
incomplete sequential chunk and preserve unrelated changes.

Treat evidence gaps as real work. Never weaken a quality, coverage, security, dependency, complexity,
or validation threshold to claim completion. Use `$change-chess-rules` for any legality/game-state
fix. Keep all validation local; do not tag, push, upload, create CI, or begin optional engine review.

Run the fast gate:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

After acceptance and validation, create a scoped commit, mark only the current chunk passed, append
decisions/dead ends/evidence/handoff to `SCRATCHPAD.md`, and emit
`<promise>RALPH_CHUNK_COMPLETE</promise>`. Emit `<promise>RALPH_SPRINT_COMPLETE</promise>` only after
all chunks pass and allow post-sprint hooks to perform final review/docs/full validation.
