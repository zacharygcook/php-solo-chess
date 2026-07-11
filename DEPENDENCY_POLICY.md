# Dependency Policy

Runtime should remain dependency-light and development tools must provide a concrete safety payoff.
All dependencies must be free to use locally, require no external account, and remain pinned in a
committed lock or machine-readable policy record.

## Minimum release age

Do not adopt a direct runtime or development dependency until its selected release has been public
for at least 30 days. Record the upstream publication time and earliest eligible date in
`config/dependency-policy.json`; `./scripts/check.sh` verifies every direct Composer tool and pinned
CDN dependency against that record.

A critical security fix may bypass the waiting period only when the commit documents the advisory,
risk, affected surface, selected version, and why deferral is less safe. Update the policy record and
run `composer audit` before committing. Convenience and readiness score are not exceptions.

Transitive Composer packages are controlled by `composer.lock` and may change only during an
intentional direct-tool update. Review the complete lock diff and security audit during that update.

## Approved direct dependencies

- PHP CS Fixer 3.94.2: development-only deterministic PHP formatting; released 2026-02-20.
- PHPStan 2.2.2: development-only static type analysis; released 2026-06-05.
- jQuery 3.7.1: pinned browser runtime dependency inherited by the existing frontend; released
  2023-08-28. Removing this network dependency remains preferable to expanding it.
