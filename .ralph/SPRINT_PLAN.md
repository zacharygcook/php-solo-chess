# Ralph Sprint Plan — PHP Solo Chess MVP

This plan decomposes `SPEC.md` in dependency order. Sprints 1–6 are instantiated under
`.ralph/sprints/`; only the sprint named by `CURRENT_SPRINT` is active. Preparing a folder does not
authorize or start autonomous execution.

| Sprint | Goal | Depends on |
| --- | --- | --- |
| 1. Rules-engine completeness | Make orthodox rules explicit, deterministic, and test-backed: legal move generation, king safety, special moves, terminal states, draw rules, canonical notation, and FEN-ready position interchange. | Phase 0 baseline |
| 2. SQLite persistence and local identities | Add idempotent SQLite setup, repositories, registration/login/logout, password hashing, ownership, and durable canonical game/move storage. | Sprint 1 |
| 3. Game lifecycle, clocks, and replay services | Add game creation, player labels/types, server-owned clocks, resignation/draw/timeout handling, automatic saves, history, and replay APIs. | Sprints 1–2 |
| 4. Accessible local game table | Build creation/history/replay views; drag/drop and click/tap movement; legal hints, responsive board state, captures, clocks, orientation, and durable sound preference/game-end feedback. | Sprint 3 |
| 5. PGN export and future-engine seam | Export canonical saved games as reproducible PGN and provide tested FEN/coordinate-based adapter interfaces with a deterministic fake engine only. | Sprints 1–4 |
| 6. MVP integration and release evidence | Close cross-layer gaps with representative browser journeys, mobile/manual QA guidance, generated API/docs updates, and clean-clone validation evidence. | Sprints 1–5 |

Every sprint uses the configured fast chunk gate and full sprint gate. The optional engine-review
stretch milestone is deliberately excluded until all required-MVP sprints are complete.
