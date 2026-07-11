# Phase 0: Environment and baseline

Read `SCRATCHPAD.md` first, then inspect the plan, relevant context, and `chunks.json`. Phase 0 is a
human-guided baseline and its chunks are already verified. Do not change chess behavior. Append any
new environment or workflow discovery to `SCRATCHPAD.md` before exiting.

Validation command: `./scripts/check.sh`

For future incomplete chunks, set `passes: true` only after its acceptance criteria are verified and
emit `<promise>RALPH_CHUNK_COMPLETE</promise>`. If every chunk passes, emit
`<promise>RALPH_SPRINT_COMPLETE</promise>`.
