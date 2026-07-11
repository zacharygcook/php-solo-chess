# PHP Solo Chess — Agent Guide

Read `README.md` and `RALPH_DOGFOOD_SCORECARD.md` before changing the project. The MVP is a correct,
test-backed local chess game; multiplayer and elaborate animation are outside the current milestone.
Use `docs/ARCHITECTURE.md` for request flow, state ownership, and component boundaries.
Use `docs/RUNBOOKS.md` for local startup, session, validation, and recovery procedures.

Run `./scripts/check.sh` before and after relevant changes. Add focused rules-engine tests before
expanding chess behavior. Do not treat a visually successful move as proof of chess correctness.
Run `./scripts/install-hooks.sh` once per clone so the same check blocks invalid commits locally.
Use the repository skill `$change-chess-rules` for chess legality or game-state changes.

The runtime has no Composer or npm dependencies. Development tools are pinned in `composer.lock`;
run `composer install` after cloning. Do not add a framework or dependency merely for convenience;
explain its concrete product or safety payoff first.

Ralph sprint state lives under `.ralph/sprints/`. Agents must read and append `SCRATCHPAD.md`, make
scoped commits, and record workflow friction in the root scorecard rather than silently working around it.
