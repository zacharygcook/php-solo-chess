#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

required_files=(
  AGENTS.md
  README.md
  RALPH_DOGFOOD_SCORECARD.md
  docs/ARCHITECTURE.md
  docs/RUNBOOKS.md
  docs/API.md
  docs/openapi.json
  docs/RELEASING.md
  docs/NAMING.md
  config/api-endpoints.json
  .ralph/sprints/0-environment-and-baseline/SCRATCHPAD.md
  .github/CODEOWNERS
  .github/ISSUE_TEMPLATE/bug_report.md
  .github/ISSUE_TEMPLATE/feature_request.md
  .github/ISSUE_TEMPLATE/config.yml
  .github/pull_request_template.md
  .editorconfig
  .php-cs-fixer.dist.php
  composer.json
  composer.lock
  phpstan.neon
  phpmd.xml
  DEPENDENCY_POLICY.md
  config/dependency-policy.json
  config/quality-budgets.json
  config/architecture-layers.json
  config/dependency-weight.json
  config/dependency-usage.json
  scripts/check-dependency-weight.php
  scripts/check-unused-dependencies.php
  scripts/router.php
  tests/coverage.php
  scripts/check-architecture.php
  scripts/check-duplication.php
  SECURITY.md
  .env.example
  .devcontainer/Dockerfile
  .devcontainer/devcontainer.json
  .dockerignore
  .agents/skills/change-chess-rules/SKILL.md
  .agents/skills/change-chess-rules/agents/openai.yaml
)

required_commands=(
  scripts/check.sh
  scripts/test.sh
  scripts/install-hooks.sh
  scripts/format.sh
  scripts/check-secrets.sh
  scripts/security-review.sh
  scripts/dast.sh
  scripts/dependency-updates.sh
  scripts/check-complexity.sh
  scripts/check-dependency-weight.sh
)

for relative_path in "${required_files[@]}"; do
  if [[ ! -s "$ROOT/$relative_path" ]]; then
    echo "Agent documentation references a missing or empty file: $relative_path" >&2
    exit 1
  fi
done

if ! grep -Eq '^\* +@zacharygcook$' "$ROOT/.github/CODEOWNERS"; then
  echo "CODEOWNERS must retain a root fallback owner." >&2
  exit 1
fi

for relative_path in "${required_commands[@]}"; do
  if [[ ! -x "$ROOT/$relative_path" ]]; then
    echo "Agent documentation references a non-executable command: $relative_path" >&2
    exit 1
  fi
  bash -n "$ROOT/$relative_path"
done

required_agent_references=(
  'README.md'
  'RALPH_DOGFOOD_SCORECARD.md'
  'docs/ARCHITECTURE.md'
  'docs/RUNBOOKS.md'
  './scripts/check.sh'
  './scripts/install-hooks.sh'
  'SCRATCHPAD.md'
  '$change-chess-rules'
)

for reference in "${required_agent_references[@]}"; do
  if ! grep -Fq "$reference" "$ROOT/AGENTS.md"; then
    echo "AGENTS.md is missing required reference: $reference" >&2
    exit 1
  fi
done

echo "Agent documentation contract passed."
