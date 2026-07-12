# Sprint 5: PGN export and future-engine seam

Read `SCRATCHPAD.md` first, then the plan, relevant context, and `chunks.json`. Work only on the next
incomplete sequential chunk and preserve unrelated work.

PGN and engine boundaries consume canonical saved/domain state. Use `$change-chess-rules` if work
touches move application, notation, terminal results, or participant game state. Do not bundle or
invoke an engine, add analysis UI, or implement an opponent.

Run the fast gate:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

After acceptance and validation, create a scoped commit, mark only the current chunk passed, append
decisions/dead ends/evidence/handoff to `SCRATCHPAD.md`, and emit
`<promise>RALPH_CHUNK_COMPLETE</promise>`. Emit `<promise>RALPH_SPRINT_COMPLETE</promise>` only after
all chunks pass.
