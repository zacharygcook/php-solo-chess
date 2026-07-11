# Sprint 1: Rules-engine completeness

Establish a deterministic, server-authoritative orthodox chess rules engine before persistence or
frontend expansion. The sprint turns the current baseline move path into an explicit position and
move contract that can safely support later SQLite, clocks, PGN, and a future engine adapter.

Fast chunk validation:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Comprehensive sprint validation:

```bash
./scripts/check.sh
```

Do not add accounts, SQLite, frontend frameworks, a computer opponent, or UI polish in this sprint.
