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

## 2026-07-11 — First human QA finding

- Opening `/frontend` without the trailing slash exposed unstyled HTML because relative asset URLs resolved from `/`.
- The original baseline check missed this by requesting only `/frontend/` and not verifying CSS/JavaScript responses.
- Frontend asset and API URLs are now root-absolute; baseline QA covers both route forms and both local assets.
- This counts as one product regression escaping Phase 0 validation, not a Ralph runtime defect.

## 2026-07-11 — Agent-readiness baseline

- The evidence-backed 82-criterion baseline is Level 1 at 16.42% owned readiness: 11 passes, 56
  failures, and 15 inapplicable criteria.
- Readiness work must remain free and local. The owner explicitly prohibits CI and GitHub Actions,
  so improvements must be runnable through repository scripts on a developer or agent workstation.
- The numerical target is at least 80%. Under rubric 1.0 that is Level 5, even though the request
  described the desired milestone as “Level 4 / 80%.”
