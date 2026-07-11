# Agent Readiness Preferences

This optional repository policy guides how readiness improvements should be implemented. It is not
standing permission to create accounts, accept paid terms, add external-service secrets, install
vendor apps, or mutate production.

## Targets

- Primary score: owned applicability score
- Target level: 4 or better
- Target percentage: 80%

## Principles

- Build real capability, not score-only artifacts.
- Prefer improvements with measurable product, operational, security, or developer payoff.
- Treat an inapplicable application as outside that criterion's denominator.
- Require concrete source, configuration, or command evidence for every passing judgment.
- Keep AGENTS.md concise; link durable detail from domain documentation.

## Autonomous Remediation

- Agents may implement repository-owned code, tests, docs, scripts, configuration, and local checks.
- Keep every validation capability runnable locally. Do not add CI configuration, GitHub Actions
  workflows, hosted checks, or automation that runs on pushes or pull requests.
- Prefer one criterion-sized commit and preserve unrelated work.
- Run targeted validation plus the repository's normal required checks.

## Ask First

- Creating or connecting a third-party account, installing an external app, adding a service secret,
  accepting paid terms, or introducing recurring spend.
- Adding any paid service, paid tier, trial that may convert to paid use, or capability that requires
  billing information. This project has a zero-dollar operating budget.
- Mutating production or live repository, organization, deployment, or vendor settings.
- Large architectural refactors or broad new production call-site changes.
- Materially expanding metrics, tracing, profiling, analytics, or error-tracking instrumentation.

## Providers And Tools

| Capability | Preferred or existing tool | Repository-specific guidance |
|---|---|---|
| Validation | Local scripts in `scripts/` | Checks must run on a developer or agent workstation, never in CI. |
| External services | None | Do not add accounts, hosted integrations, telemetry vendors, or paid services. |

Do not install a low-quality substitute merely to satisfy a criterion when the preferred solution
requires a vendor decision or spend.

## Important Failure Notifications

- Email important, actionable scheduled or autonomous workflow failures only when this repository
  has an approved provider and explicitly opts in below.
- Use polished, accessible HTML plus a useful plain-text fallback.
- Do not create a new account, secret, or paid service to enable notifications without approval.
- Repository opt-in and provider: add here.

## Repository Priorities And Deferrals

- Prioritize chess-rules unit tests, deterministic local validation, architecture documentation,
  and safe repository hygiene.
- Defer criteria whose legitimate implementation requires CI, hosted automation, a deployment
  target, an external account, or paid infrastructure. Do not add a fake local substitute merely
  to gain rubric credit.
- Preserve the dependency-free PHP/JavaScript architecture unless a dependency has a concrete,
  documented product or safety payoff.

## Applicability Overrides

| Criterion | Application | Applicable? | Reason |
|---|---|---:|---|
| `fast_ci_feedback` | repository | No | The owner explicitly prohibits CI for this local-only side project. |
| `deployment_frequency` | repository | No | The app has no deployment target. |
| `progressive_rollout` | repository | No | The app is local-only and has no audience rollout surface. |
| `rollback_automation` | repository | No | The app is local-only and has no deployed release. |

## Criterion Overrides

| Criterion | Stricter local pass condition or implementation preference | Reason |
|---|---|---|
| Any CI-oriented criterion | Do not create CI or GitHub workflow files. Score as failing, deferred, or inapplicable according to the rubric. | Local validation is a deliberate project constraint. |
| Any vendor-backed criterion | Use only durable, no-cost, repository-owned capability; otherwise defer it. | The project has a zero-dollar budget and should not require external accounts. |
