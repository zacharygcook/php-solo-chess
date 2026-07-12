# Sprint 2: SQLite persistence and local identities

Add the smallest durable local data layer needed for accounts and saved games. SQLite repositories
own storage, PHP sessions own login context, and the completed chess domain remains authoritative for
canonical game state.

Fast chunk validation:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Comprehensive sprint validation:

```bash
./scripts/check.sh
```

Do not add clocks, replay UI, frontend frameworks, email recovery, roles, or remote services.
