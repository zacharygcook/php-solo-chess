# MVP Acceptance Evidence

This matrix maps the required `SPEC.md` MVP acceptance criteria to repository-owned evidence. A row is
`covered` only when the evidence is executable locally. Documentation-only claims and future optional
engine review do not count as completion evidence.

## Required Acceptance Criteria

| # | Status | Requirement | Current executable evidence | Remaining sprint work |
| ---: | --- | --- | --- | --- |
| 1 | covered | Complete rules suite covers ordinary movement, king safety, special moves, check, checkmate, stalemate, draw conditions, promotion choices, and terminal-state immutability. | `tests/RulesEngineTest.php`, `tests/GameServiceTest.php`, `tests/GameClockTest.php`, `tests/PgnIntegrationTest.php`, and `./scripts/test.sh`; the canonical gate also runs coverage through `composer coverage:check`. | Keep rules evidence green while integration chunks add end-to-end journeys. |
| 2 | gap | A person can complete representative untimed and timed games entirely through the browser without state divergence between UI, API, persistence, clocks, history, or PGN. | Partial browser path in `scripts/browser-smoke.sh`; API/unit coverage in `tests/GameApiTest.php`, `tests/GamePersistenceTest.php`, `tests/GameHistoryTest.php`, `tests/PgnControllerTest.php`, and `tests/PgnIntegrationTest.php`. | Chunk 2 must prove the complete untimed saved-game journey. Chunk 3 must prove the complete timed-game journey. |
| 3 | covered | Registration, login, logout, account switching, automatic game saving, personal history, and game replay work locally with SQLite. | `tests/AuthServiceTest.php`, `tests/AuthControllerTest.php`, `tests/RepositoryTest.php`, `tests/GamePersistenceTest.php`, `tests/GameHistoryTest.php`, `tests/GameApiTest.php`, `tests/DatabaseTest.php`, `scripts/dast.sh`, and `scripts/browser-smoke.sh`. | Chunk 2 should add owner-isolated journey evidence tying account switching to history, replay, and PGN. |
| 4 | gap | Drag/drop, click/tap movement, legal destination feedback, sound toggle, clocks, captured pieces, and distinct game-ending feedback work at laptop and mobile sizes. | Static frontend contract checks in `tests/FrontendContractTest.php`; click movement, sound toggle, timed status, terminal feedback, saved replay, and mobile overflow smoke in `scripts/browser-smoke.sh`. | Chunk 4 must harden browser smoke for drag/drop, keyboard movement, promotion, and representative recovery/failure paths. |
| 5 | covered | Saved games export valid PGN that reproduces their moves, result, and final position. | `tests/PgnExporterTest.php`, `tests/PgnControllerTest.php`, `tests/PgnIntegrationTest.php`, `backend/src/Services/PgnVerifier.php`, and `./scripts/test.sh`. | Chunk 2 and chunk 3 must include PGN agreement in the representative journey tests. |
| 6 | covered | Engine adapter boundary is documented and tested with a deterministic fake adapter; no real engine is bundled or required. | `tests/EngineAdapterTest.php`, `backend/src/Engine/`, `docs/ARCHITECTURE.md`, and dependency checks in `./scripts/check.sh`. | Optional engine-powered review remains out of scope for this sprint. |
| 7 | covered | `./scripts/check.sh` runs all repository-owned static checks, rules tests, integration tests, and browser smoke coverage deterministically from a clean clone. | `scripts/check.sh` runs documentation, file-size, debt, secrets, dependency, security, DAST, API-doc, release-notes, lint, naming, architecture, complexity, duplication, quality snapshot, formatting, typecheck, unit, coverage, API smoke, and browser smoke gates. | Chunk 5 must run the full final gate from the final clean state. |
| 8 | covered | Local startup still requires one documented command and no production service account, paid provider, or external database. | `README.md`, `scripts/dev.sh`, `scripts/setup-database.php`, `.devcontainer/`, `SECURITY.md`, `DEPENDENCY_POLICY.md`, and local-only checks in `./scripts/check.sh`. | Chunk 4 must prove clean-clone startup and recovery evidence explicitly. |

## Required User Journeys

| Journey | Status | Current evidence | Remaining sprint work |
| --- | --- | --- | --- |
| Play immediately | covered | `scripts/check.sh` starts an isolated server, loads a session, serves both frontend URL forms and assets, and plays `e2` to `e4`; `scripts/browser-smoke.sh` plays a guest move through the board UI. | Keep green while later chunks expand browser interaction coverage. |
| Keep personal history | gap | Auth, persistence, owner-scoped history/replay, and PGN export are covered by unit/API tests and partial browser smoke. | Chunk 2 must prove a complete untimed saved-game journey with account switching, replay, history, export, result, and final FEN agreement. |
| Play with clocks | gap | Clock debit/increment/refresh/timeout behavior is covered by `tests/GameClockTest.php`, lifecycle/API tests, and partial browser timed status smoke. | Chunk 3 must prove a deterministic timed journey without wall-clock sleeps and with timeout/result/history/replay/PGN agreement. |

## Out Of Scope For MVP Closure

- Real engine play, engine-powered review, engine downloads, and analysis UI.
- Hosted services, CI, tags, pushes, uploads, production accounts, or external databases.
- Online multiplayer, matchmaking, ratings, email, password recovery, roles, or administration.

## Canonical Local Gates

- Fast chunk gate: `./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh`
- Full sprint gate: `./scripts/check.sh`
