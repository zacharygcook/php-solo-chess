# Sprint 1: Rules-engine completeness

Read `SCRATCHPAD.md` first. Then inspect `IMPLEMENTATION_PLAN.md`, `relevant-specs.md`, and
`chunks.json`. Work only on the next incomplete sequential chunk.

Before any legality or game-state change, use the repository `$change-chess-rules` workflow and add
focused behavior-first regression tests. Keep domain behavior independent from HTTP, session, and
rendering code. Do not add persistence, accounts, clocks, UI polish, external dependencies, or an
engine implementation in this sprint.

Run the fast gate before declaring a chunk complete:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Create a scoped commit containing the chunk's changes. Set only that chunk's `passes` value to `true`
after its criteria and validation pass, then emit `<promise>RALPH_CHUNK_COMPLETE</promise>`. Append
decisions, failed approaches, validation evidence, and the next handoff to `SCRATCHPAD.md` before
exiting. If all chunks pass, emit `<promise>RALPH_SPRINT_COMPLETE</promise>`; the post-sprint hooks
will run the comprehensive `./scripts/check.sh` gate.
