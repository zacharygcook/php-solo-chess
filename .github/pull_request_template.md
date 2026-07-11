## Summary

What problem does this change solve, and what is intentionally out of scope?

## Behavior and rules impact

Describe user-visible behavior, chess-rule changes, state transitions, or API contract changes. Use
“None” when the change is workflow-only.

## Validation

List exact commands and results. Rules changes require focused unit tests in addition to the
canonical check.

- [ ] `./scripts/test.sh`
- [ ] `./scripts/check.sh`
- [ ] Relevant manual browser QA, or not applicable with reason
- [ ] Local change review generated with `php scripts/review-change.php --base=<base-ref> --output=.agent-readiness/pr-review.md` and findings addressed

## Operational and security impact

Describe changes to startup, session data, logs, external requests, secrets, privacy, rollback, or
local recovery. Link a runbook update when behavior changes.

## Constraints

- [ ] No paid service, trial, billing requirement, or new external account
- [ ] No CI configuration or GitHub Actions workflow
- [ ] No unrelated user changes included
- [ ] Documentation and technical-debt ledger updated where needed

## Deferred work

Link follow-up issues or `TECH_DEBT.md` entries. Do not hide known gaps in prose-only notes.
