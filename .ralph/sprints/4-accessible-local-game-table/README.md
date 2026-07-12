# Sprint 4: Accessible local game table

Turn the server-authoritative lifecycle into a lightweight, responsive local chess table. Vanilla
browser code presents account/game/history flows, accessible movement, clocks, review, sound, and
clear game feedback without becoming a second rules engine.

Fast chunk validation:

```bash
./scripts/test.sh && composer typecheck && ./scripts/check-complexity.sh
```

Comprehensive sprint validation:

```bash
./scripts/check.sh
```

No frontend framework, bundler, remote asset, or client-authoritative legality belongs here.
