# Security Policy

PHP Solo Chess is a local-only side project with local account records stored in an ignored SQLite
database. It has no production environment, paid service, CI, external account provider, or
repository secrets. The application should run without external credentials.

## Local secret handling

- Never commit credentials, password hashes, session cookies, PHP session files, SQLite runtime
  files, tokens, private keys, or copied environment files.
- If a future local integration genuinely needs a credential, read it from a named environment
  variable. Add only the variable name and a non-secret explanation to `.env.example`; keep the
  actual value in a shell session or ignored `.env` file. `SOLO_CHESS_DATABASE_PATH` is a non-secret
  local path override for SQLite diagnostics.
- Do not add CI secrets or GitHub Actions. Do not create an external account or paid service for this
  project.
- `./scripts/check-secrets.sh` scans tracked and unignored files for high-signal credential formats.
  GitHub secret scanning remains defense in depth, not a substitute for the local check.

## Reporting and response

Report a suspected vulnerability privately to the repository owner rather than placing exploit or
credential details in a public issue. Include the affected commit and a minimal, sanitized
reproduction.

If a real credential enters the repository:

1. Stop sharing or pushing the affected branch.
2. Revoke or rotate the credential at its source before editing history.
3. Remove it from the working tree and add a regression rule when the format is safe to detect.
4. Coordinate any history rewrite explicitly with the owner; agents must not rewrite history on
   their own.
5. Re-run the local secret scan, canonical check, and repository-host secret scan.

## Local request logs

API requests emit one JSON completion record to PHP's local error log and return the same safe
`X-Request-ID` for correlation. The schema is allowlist-only: timestamp, severity, event, request ID,
method, URL path without its query string, status, duration, and fatal-error location when present.
Never add bodies, query strings, cookies, session IDs, authorization headers, or arbitrary request
headers. The local DAST gate sends a sentinel in both an authorization header and JSON body and fails
if that value appears in the server log.
