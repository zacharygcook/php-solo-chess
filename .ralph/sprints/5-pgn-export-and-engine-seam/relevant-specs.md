# Relevant context

- `SPEC.md` sections 6–7 and acceptance criteria 5–6 define PGN headers/movetext, reproducibility,
  FEN/coordinate interchange, participant types, and the fake engine seam.
- Sprint 1 provides SAN, FEN, coordinate notation, and authoritative move application.
- Sprints 2–3 provide owned canonical game/move records and lifecycle metadata; Sprint 4 provides the
  browser download surface.
- PGN must use canonical saved data, never rendered text or DOM state.
- `docs/API.md` and `docs/openapi.json` are generated from repository endpoint metadata.
- The optional engine-review milestone remains excluded until the complete required MVP passes.
