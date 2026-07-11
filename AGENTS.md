# PHP Solo Chess — Agent Guide

Read `README.md` and `RALPH_DOGFOOD_SCORECARD.md` before changing the project. The MVP is a correct,
test-backed local chess game; multiplayer and elaborate animation are outside the current milestone.

Run `./scripts/check.sh` before and after relevant changes. Add focused rules-engine tests before
expanding chess behavior. Do not treat a visually successful move as proof of chess correctness.

The app currently has no Composer or npm dependencies. Do not add a framework or build system merely
for convenience; explain the concrete payoff before introducing dependencies.

Ralph sprint state lives under `.ralph/sprints/`. Agents must read and append `SCRATCHPAD.md`, make
scoped commits, and record workflow friction in the root scorecard rather than silently working around it.
