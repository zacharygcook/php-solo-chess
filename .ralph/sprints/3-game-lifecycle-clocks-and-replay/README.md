# Sprint 3: Game lifecycle, clocks, and replay services

Coordinate the completed rules engine and SQLite repositories into complete untimed/timed local game
lifecycles. The server owns clocks and terminal transitions; saved history and replay consume
canonical records without mutating them.

Fast chunk validation:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Comprehensive sprint validation:

```bash
./scripts/check.sh
```

Do not add PGN export, a computer opponent, or frontend polish in this sprint.
