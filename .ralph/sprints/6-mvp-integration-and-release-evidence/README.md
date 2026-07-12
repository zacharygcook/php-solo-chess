# Sprint 6: MVP integration and release evidence

Close the required MVP with deterministic cross-layer evidence. Representative untimed and timed
journeys must agree across UI, API, rules, SQLite, clocks, history, replay, and PGN while clean-clone
startup and validation remain local and dependency-light.

Fast chunk validation:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Comprehensive sprint validation:

```bash
./scripts/check.sh
```

This is a required-MVP closure sprint, not authorization for the optional engine-review milestone.
