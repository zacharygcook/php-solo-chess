# Sprint 4: Accessible local game table

Read `SCRATCHPAD.md` first, then the plan, relevant context, and `chunks.json`. Work only on the next
incomplete sequential chunk and preserve unrelated changes.

Use vanilla JavaScript and CSS with no framework, bundler, or remote runtime asset. The browser
renders canonical server state and submits intent; it never decides legality or clock outcomes. Keep
keyboard/touch access, mobile layout, and non-disruptive feedback within each chunk's acceptance.

Run the fast gate:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Make a scoped commit after validation, mark only the current chunk passed, append decisions/dead
ends/evidence/handoff to `SCRATCHPAD.md`, and emit `<promise>RALPH_CHUNK_COMPLETE</promise>`. Emit
`<promise>RALPH_SPRINT_COMPLETE</promise>` only after every chunk passes.
