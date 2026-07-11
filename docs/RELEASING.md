# Local Release Process

This project has no hosted deployment, package registry, CI, or automatic publication. A “release”
is a reviewed Git milestone that another person can clone and run locally.

Create a validated local release bundle from a clean worktree with:

```bash
./scripts/package-release.sh 0.1.0
```

The command runs the canonical gate, archives the exact target commit under `dist/`, generates
release notes, writes SHA-256 checksums, and records the commit and artifact names in a JSON
manifest. It fails on a dirty worktree. It never tags, pushes, uploads, or invokes a hosted service.

To preview notes independently, generate them from the previous milestone (or chosen starting
commit) through the reviewed target:

```bash
php scripts/generate-release-notes.php \
  --from=<previous-tag-or-commit> \
  --to=HEAD \
  --output=.agent-readiness/release-notes.md
```

Review the generated groups against the actual diff, call out known chess-rule limitations, and copy
the approved content into the Git tag or GitHub release only through an explicitly authorized manual
publishing step. The generator never pushes, tags, publishes, creates an account, or incurs cost.

Before manually publishing a milestone tag, also run `./scripts/test-flakiness.sh` and review the
package manifest, checksum file, security snapshot, and known chess-rule limitations. Do not publish
when any blocking check fails.
