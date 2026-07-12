# Relevant context

- All eight `SPEC.md` MVP acceptance criteria and three user journeys are in scope; the optional
  engine-review milestone remains out of scope.
- `README.md` defines one-command startup and the canonical local check.
- `docs/ARCHITECTURE.md`, `docs/API.md`, `docs/openapi.json`, and `docs/RUNBOOKS.md` must match the
  completed implementation and remain executable where generated.
- `scripts/check.sh` is the clean handoff gate; it must cover static checks, rules/unit/integration
  tests, and browser smoke deterministically.
- `scripts/package-release.sh` produces local artifacts only and must not tag, push, upload, or invoke CI.
- `RALPH_DOGFOOD_SCORECARD.md` records runtime findings, interventions, and escaped regressions.
