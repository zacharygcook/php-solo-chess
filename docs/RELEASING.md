# Local Release Process

This project has no hosted deployment, package registry, CI, or automatic publication. A “release”
is a reviewed Git milestone that another person can clone and run locally.

After every completed Ralph sprint and before creating a milestone tag, generate release notes from
the previous milestone (or chosen starting commit) through the reviewed target:

```bash
php scripts/generate-release-notes.php \
  --from=<previous-tag-or-commit> \
  --to=HEAD \
  --output=.agent-readiness/release-notes.md
```

Review the generated groups against the actual diff, call out known chess-rule limitations, and copy
the approved content into the Git tag or GitHub release only through an explicitly authorized manual
publishing step. The generator never pushes, tags, publishes, creates an account, or incurs cost.

Before tagging, run `composer install`, `./scripts/test-flakiness.sh`, `./scripts/security-review.sh`,
and `./scripts/check.sh` from a clean tree. Do not release when any blocking check fails.
