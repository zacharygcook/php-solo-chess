# Sprint 5: PGN export and future-engine seam

Export canonical saved games as reproducible PGN and establish a narrow, tested future-engine
boundary. No engine is bundled or invoked; proposed moves still pass through the same authoritative
legal-move application path as human moves.

Fast chunk validation:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Comprehensive sprint validation:

```bash
./scripts/check.sh
```

Engine analysis, opponent selection, binaries, models, search, and difficulty levels are out of scope.
