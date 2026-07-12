# Sprint 2: SQLite persistence and local identities

Read `SCRATCHPAD.md` first, then `IMPLEMENTATION_PLAN.md`, `relevant-specs.md`, and `chunks.json`.
Work only on the next incomplete sequential chunk. Preserve all unrelated worktree changes.

Use PDO SQLite, parameterized queries, PHP password hashing, and PHP sessions. Keep storage behind
repositories and authentication/application behavior behind services. Do not add a framework, ORM,
email flow, roles, remote service, clocks, or frontend expansion.

Run the fast gate before declaring a chunk complete:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Create a scoped commit, set only the completed chunk's `passes` value to `true`, append decisions,
failed approaches, validation evidence, and the next handoff to `SCRATCHPAD.md`, then emit
`<promise>RALPH_CHUNK_COMPLETE</promise>`. When all chunks pass, emit
`<promise>RALPH_SPRINT_COMPLETE</promise>` for post-sprint review/docs/validation.
