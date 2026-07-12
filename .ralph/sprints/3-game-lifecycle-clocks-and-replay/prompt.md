# Sprint 3: Game lifecycle, clocks, and replay services

Read `SCRATCHPAD.md` first, then the plan, relevant context, and `chunks.json`. Work only on the next
incomplete sequential chunk and preserve unrelated worktree changes.

Use `$change-chess-rules` for any legality, terminal-state, turn-order, or game-state change. Keep
clocks server-authoritative and test them through an injected deterministic time source. Keep replay
read-only and owner-scoped. Do not add PGN, frontend polish, or an engine.

Run the fast gate:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

After criteria pass, make a scoped commit, set only the current chunk to `passes: true`, append the
decision/validation/handoff record to `SCRATCHPAD.md`, and emit
`<promise>RALPH_CHUNK_COMPLETE</promise>`. Emit `<promise>RALPH_SPRINT_COMPLETE</promise>` only when
all chunks pass so the post-sprint hooks can finish review, docs, and full validation.
