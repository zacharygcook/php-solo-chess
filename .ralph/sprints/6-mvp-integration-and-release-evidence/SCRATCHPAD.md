# Scratchpad

## 2026-07-11 — Sprint prepared

- Depends on completed Sprints 1–5; this sprint closes only the required MVP.
- Start with an evidence map and do not treat documentation assertions as executable proof.
- Clean-clone startup, SQLite initialization, browser smoke, full integration, and canonical validation
  must remain local and deterministic.
- Do not lower existing ratchets or begin optional engine-powered review.
- Fast gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`; final gate:
  `./scripts/check.sh`.
- This folder is dormant; `CURRENT_SPRINT` was intentionally left unchanged.
- Append decisions, dead ends, validation evidence, and the next-context handoff before every exit.

## 2026-07-12 — Chunk 1 complete

- Completed chunk 1 only: added `docs/MVP_ACCEPTANCE.md` as the executable acceptance-evidence
  matrix for all eight `SPEC.md` MVP criteria and the three required user journeys.
- Decision: mark criteria as `covered` only when backed by local repository evidence, and leave
  representative end-to-end gaps visible for chunks 2, 3, and 4 rather than claiming MVP completion
  from existing unit/browser fragments.
- Decision: wire `docs/MVP_ACCEPTANCE.md` into `scripts/check-agent-docs.sh` so the canonical
  documentation contract fails if the matrix is removed or empty.
- Out of scope: optional engine-powered review remains explicitly deferred; no tag, push, upload,
  CI, hosted service, or external account was introduced.
- Failed approaches: none requiring rollback. The sprint folder has no `PLAN.md`; the active plan is
  `IMPLEMENTATION_PLAN.md`.
- Validation evidence: baseline fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed before edits
  with seed `300059461`, 111 tests, PHPStan clean, and complexity within the method ceiling. After
  edits, `./scripts/check-agent-docs.sh && ./scripts/test.sh && composer typecheck &&
  ./scripts/check-complexity.sh` passed with seed `775553624`, 111 tests, PHPStan clean, and
  complexity within the method ceiling.
- Next handoff: start chunk 2 by adding a deterministic untimed saved-game journey that registers or
  logs in, plays to a terminal result, proves persistence/history/replay/export agreement, and
  verifies owner isolation through account switching.

## 2026-07-12 — Chunk 2 complete

- Completed chunk 2 only: added `tests/UntimedJourneyTest.php`, a deterministic controller/service
  journey that registers an owner, creates an untimed Ada-vs-Byron game, plays Fool's mate to
  checkmate, verifies durable move persistence, history summaries, open/replay FEN agreement, PGN
  verifier/export output, and owner isolation after switching to another local account.
- Updated `docs/MVP_ACCEPTANCE.md` so the personal-history journey is now covered by executable
  evidence and the remaining MVP gaps stay assigned to timed-game and browser-hardening chunks.
- Decision: keep this heavyweight end-to-end journey in normal `./scripts/test.sh` and skip it only
  during `SOLO_CHESS_COVERAGE`, matching the existing PGN integration pattern. The initial full
  check failed during the Xdebug-backed code-quality snapshot by exceeding the two-second test
  budget; no quality or coverage threshold was lowered.
- Baseline evidence before edits: `./scripts/check.sh` passed; requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed with seed
  `58682272`, 111 tests, PHPStan clean, and complexity within the method ceiling.
- Post-edit validation evidence: `XDEBUG_MODE=coverage php tests/coverage.php --measure` passed with
  seed `1901901835`, 107 coverage-mode tests, 82.81% backend line coverage; fast gate passed with
  seed `36664480`, 112 tests, PHPStan clean, and complexity within the method ceiling; full
  `./scripts/check.sh` passed with seed `1495192447`, coverage seed `367708398`, and browser smoke
  passed.
- Next handoff: start chunk 3 by adding a deterministic timed journey proving debit, increment,
  refresh persistence, timeout result/immutability, and saved history/replay/PGN agreement without
  wall-clock sleeps.

## 2026-07-12 — Chunk 3 complete

- Completed chunk 3 only: added `tests/TimedJourneyTest.php`, a deterministic controller/service
  journey that registers an owner, creates a custom 1+3 timed Ada-vs-Byron game, proves accepted-move
  debit and increment, proves refresh clock projection does not persist a debit, records a timeout
  without sleeps, rejects a late move without mutating FEN/history/clocks, and verifies saved
  history, replay clock snapshots, PGN export, and PGN verifier agreement from canonical records.
- Updated `docs/MVP_ACCEPTANCE.md` so the clocks journey is now covered by executable evidence while
  the browser-only MVP gap remains assigned to chunk 4.
- Decision: keep timeout as the representative timed terminal result. PGN export is asserted from the
  saved canonical game record, and `PgnVerifier` is used for coordinate/final-FEN/result-token
  agreement; elapsed server time itself is intentionally not reconstructed by coordinate replay.
- Out of scope: no optional engine review, tag, push, upload, CI, hosted service, dependency, or
  production account was introduced.
- Validation evidence: baseline requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed before edits
  with seed `303564568`, 112 tests, PHPStan clean, and complexity within the method ceiling.
  Focused post-edit replay `TEST_SEED=1 ./scripts/test.sh` passed with 113 tests. Final fast gate
  passed with seed `1124221032`, 113 tests, PHPStan clean, and complexity within the method ceiling.
- Next handoff: start chunk 4 by hardening repository-owned browser smoke and clean-clone/recovery
  evidence for drag/drop, keyboard movement, promotion, clocks, sound, terminal feedback, replay,
  representative mobile/laptop layouts, malformed input, unauthorized access, stale session/database,
  and optional audio failure behavior.

## 2026-07-12 — Chunk 4 complete

- Completed chunk 4 only: hardened `scripts/browser-smoke.sh` to prove browser recovery probes,
  optional audio failure, drag/drop movement, keyboard movement, click/tap movement, legal target
  highlighting, promotion choice, capture-list rendering, timed status, terminal feedback, saved
  replay, login/logout, and mobile layout from a local isolated Chrome run.
- Added `./scripts/check.sh` step 22 to run `scripts/setup-database.php` twice against a temporary
  SQLite path, proving idempotent local setup inside the canonical gate without production accounts,
  hosted services, tags, pushes, or uploads.
- Added focused rules proof for captured-piece lists by moving color and fixed `GameService` to append
  accepted captures to the capturing side's displayed list. This was required because the new browser
  capture assertion exposed that captures were legal and notated but not reflected in UI state.
- Updated `docs/MVP_ACCEPTANCE.md`, `docs/RUNBOOKS.md`, and `tests/FrontendContractTest.php` so the
  new browser/recovery evidence is visible and guarded.
- Failed approaches: the first browser promotion assertion timed out because captured-piece arrays
  stayed empty after legal captures; a first black-capture test fixture used an illegal rook path
  across its own king and was replaced with a legal knight capture.
- Validation evidence: baseline requested fast gate
  `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh` passed before edits with
  seed `1562234612`, 113 tests, PHPStan clean, and complexity within the method ceiling. Focused
  pre-fix proof `TEST_SEED=1 ./scripts/test.sh` failed on the new captured-list test, then passed
  after the fix with 114 tests. Direct `./scripts/browser-smoke.sh 18182` passed. Final requested
  fast gate passed with seed `1254761798`, 114 tests, PHPStan clean, and complexity within the method
  ceiling. Full `./scripts/check.sh` passed with unit seed `2070998779`, coverage seed `23277317`,
  82.89% backend line coverage, idempotent SQLite setup, and browser smoke passed.
- Next handoff: start chunk 5 by reconciling final architecture/API/runbooks/README/debt/scorecard
  documentation, packaging a local release candidate, and proving the full final gate from the final
  clean state. Optional engine-powered review remains out of scope.
