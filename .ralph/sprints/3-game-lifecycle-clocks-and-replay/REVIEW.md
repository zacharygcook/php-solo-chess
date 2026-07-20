# Post-sprint review

## Findings

1. High - Fixed: timeout result classification treated `K+minor` as unable to win without checking
   whether the flagging side still had material that could make a legal mate possible. The sprint
   required timeout to draw only when the opponent cannot legally win, but
   `TerminalStateResolver::canColorLegallyWin()` looked only at the non-flagging side's material.
   See `backend/src/Services/Chess/TerminalStateResolver.php:33` and
   `tests/GameClockTest.php:135`.

2. Medium - Open orchestration gap: the sprint manifest shows all chunks complete, but post-sprint
   hook state is still pending while this review hook is running. No `.hook-review.done`,
   `.hook-documentation.done`, or `.hook-tests.done` marker exists in the sprint directory yet. The
   latest orchestrator log reaches `hooks_started`; completion/reconciliation has not happened at the
   time of this review.

## Fixes applied

- Added `timeout is a loss when flagged material leaves a legal mate possible` to cover a timed-out
  side with remaining blocker material.
- Updated timeout mating-material evaluation so lone bishops/knights, or same-color bishops, still
  draw against a bare king but can win on time when the flagging side has non-king material that can
  make legal mate possible.

## Checks run

- Baseline before fix: `./scripts/check.sh` passed.
- Focused proof: `./scripts/test.sh` passed, 89 tests.
- Static/risk gates: `composer typecheck`, `./scripts/check-complexity.sh`, and
  `./scripts/test-flakiness.sh` passed.
- Final gate: `./scripts/check.sh` passed.

## Residual risk

- I did not change draw-offer policy or action authorization beyond the clear timeout defect.
- Replay/history endpoints remain service-tested and DAST-covered for owner scoping, but there is no
  browser UI for these new contracts in this sprint.
- Post-sprint hook markers should be rechecked after the hook runner completes.

Readiness score: 4/5. The sprint is implementation-complete and well tested after the timeout fix,
but orchestration is not fully reconciled until hook marker files and manifest hook statuses complete.
