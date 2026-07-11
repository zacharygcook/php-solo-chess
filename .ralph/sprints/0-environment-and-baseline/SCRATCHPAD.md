# Scratchpad

## 2026-07-11 — Phase 0 baseline

- Clean clone began at `4559b4c` on `master`.
- Local tools: PHP 8.5.4, Composer 2.9.5, Node 24.14.1, Python 3.14.3, jq 1.7.1, Playwright, and macOS Bash 3.2.
- The application currently has no Composer or npm dependency manifest and needs no package installation.
- Canonical URL is `http://127.0.0.1:8080/frontend/` when the repository root is the PHP document root.
- PHP syntax, frontend JavaScript syntax, initial session creation, frontend delivery, cookie persistence, and `e2` to `e4` all passed.
- jQuery is fetched from a public CDN, so fully offline use is not yet supported.
- Runtime v0.4.0 was installed in monorepo mode and remains deliberately disarmed.
- Phase 1 should establish characterization tests around chess rules before refactoring `GameService.php`.
