# MVP Acceptance Evidence

This matrix maps the required `SPEC.md` MVP acceptance criteria to repository-owned evidence. A row is
`covered` only when the evidence is executable locally. Documentation-only claims and future optional
engine review do not count as completion evidence.

## Required Acceptance Criteria

| # | Status | Requirement | Current executable evidence | Remaining sprint work |
| ---: | --- | --- | --- | --- |
| 1 | covered | Complete rules suite covers ordinary movement, king safety, special moves, check, checkmate, stalemate, draw conditions, promotion choices, and terminal-state immutability. | `tests/RulesEngineTest.php`, `tests/GameServiceTest.php`, `tests/GameClockTest.php`, `tests/PgnIntegrationTest.php`, and `./scripts/test.sh`; the canonical gate also runs coverage through `composer coverage:check`. | Keep rules evidence green while integration chunks add end-to-end journeys. |
| 2 | covered | A person can complete representative untimed and timed games entirely through the browser without state divergence between UI, API, persistence, clocks, history, or PGN. | `scripts/browser-smoke.sh` drives guest board play through drag/drop, keyboard, click/tap, capture, promotion, timed creation, terminal feedback, saved history, replay, login/logout, and mobile layout; API/unit coverage remains in `tests/GameApiTest.php`, `tests/GamePersistenceTest.php`, `tests/GameHistoryTest.php`, `tests/PgnControllerTest.php`, `tests/PgnIntegrationTest.php`, `tests/UntimedJourneyTest.php`, and `tests/TimedJourneyTest.php`. | Keep browser smoke and saved-game journeys green through final release packaging. |
| 3 | covered | Registration, login, logout, account switching, automatic game saving, personal history, and game replay work locally with SQLite. | `tests/AuthServiceTest.php`, `tests/AuthControllerTest.php`, `tests/RepositoryTest.php`, `tests/GamePersistenceTest.php`, `tests/GameHistoryTest.php`, `tests/GameApiTest.php`, `tests/UntimedJourneyTest.php`, `tests/DatabaseTest.php`, `scripts/dast.sh`, and `scripts/browser-smoke.sh`. | Keep owner-isolated journey evidence green while timed and browser chunks expand coverage. |
| 4 | covered | Drag/drop, click/tap movement, legal destination feedback, sound toggle, clocks, captured pieces, and distinct game-ending feedback work at laptop and mobile sizes. | Static frontend contract checks in `tests/FrontendContractTest.php`; `tests/RulesEngineTest.php` proves captured-piece state; `scripts/browser-smoke.sh` covers drag/drop, keyboard activation, click/tap moves, legal target highlighting, promotion choice, capture lists, optional audio failure, sound toggle persistence, timed clocks, resignation feedback, saved replay, and laptop/mobile layouts. | Keep browser smoke green through final release packaging. |
| 5 | covered | Saved games export valid PGN that reproduces their moves, result, and final position. | `tests/PgnExporterTest.php`, `tests/PgnControllerTest.php`, `tests/PgnIntegrationTest.php`, `tests/UntimedJourneyTest.php`, `tests/TimedJourneyTest.php`, `backend/src/Services/PgnVerifier.php`, and `./scripts/test.sh`. | Keep PGN agreement green while browser hardening expands coverage. |
| 6 | covered | Engine adapter boundary is documented and tested with a deterministic fake adapter; no real engine is bundled or required. | `tests/EngineAdapterTest.php`, `backend/src/Engine/`, `docs/ARCHITECTURE.md`, and dependency checks in `./scripts/check.sh`. | Optional engine-powered review remains out of scope for this sprint. |
| 7 | covered | `./scripts/check.sh` runs all repository-owned static checks, rules tests, integration tests, and browser smoke coverage deterministically from a clean clone. | `scripts/check.sh` runs documentation, file-size, debt, secrets, dependency, security, DAST, API-doc, release-notes, lint, naming, architecture, complexity, duplication, quality snapshot, formatting, typecheck, unit, coverage, API smoke, and browser smoke gates. | Chunk 5 must run the full final gate from the final clean state. |
| 8 | covered | Local startup still requires one documented command and no production service account, paid provider, or external database. | `README.md`, `scripts/dev.sh`, idempotent `scripts/setup-database.php` execution inside `./scripts/check.sh`, `.devcontainer/`, `SECURITY.md`, `DEPENDENCY_POLICY.md`, browser recovery probes in `scripts/browser-smoke.sh`, and local-only checks in `./scripts/check.sh`. | Keep local-only startup and recovery evidence green through final release packaging. |

## Required User Journeys

| Journey | Status | Current evidence | Remaining sprint work |
| --- | --- | --- | --- |
| Play immediately | covered | `scripts/check.sh` starts an isolated server, initializes SQLite idempotently, loads a session, serves both frontend URL forms and assets, and plays `e2` to `e4`; `scripts/browser-smoke.sh` plays a guest promotion line through drag/drop, keyboard, click/tap, legal-target feedback, and the promotion dialog. | Keep green through final release packaging. |
| Keep personal history | covered | `tests/UntimedJourneyTest.php` proves a complete untimed saved-game journey with registration, account switching, move persistence, owner-scoped history/replay/export, result, and final FEN agreement; supporting API and browser evidence remains in the existing auth, persistence, history, PGN, and smoke tests. | Keep green while browser recovery coverage expands in chunk 4. |
| Play with clocks | covered | `tests/TimedJourneyTest.php` proves a deterministic timed saved-game journey with debit, increment, refresh projection, timeout immutability, saved history, replay clock snapshots, PGN export, and verifier agreement without wall-clock sleeps; supporting clock/API/browser evidence remains in `tests/GameClockTest.php`, lifecycle/API tests, and `scripts/browser-smoke.sh`. | Keep green while chunk 4 expands browser interaction coverage. |

## Out Of Scope For MVP Closure

- Real engine play, engine-powered review, engine downloads, and analysis UI.
- Hosted services, CI, tags, pushes, uploads, production accounts, or external databases.
- Online multiplayer, matchmaking, ratings, email, password recovery, roles, or administration.

## Canonical Local Gates

- Fast chunk gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
- Full sprint gate: `./scripts/check.sh`
