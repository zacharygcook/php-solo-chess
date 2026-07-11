# Naming Conventions

These rules favor names that expose domain intent without abbreviations. `./scripts/check-naming.php`
enforces the mechanically verifiable subset on every canonical check.

- PHP classes use `PascalCase`, live in a same-named `.php` file, and follow the `SoloChess\`
  namespace path under `backend/src/`.
- PHP functions and methods use `camelCase`; constants use `UPPER_SNAKE_CASE`.
- PHP API entry points use lowercase kebab-case filenames. Test files use `PascalCaseTest.php` and
  behavior-focused test descriptions.
- Shell commands under `scripts/` use lowercase kebab-case filenames.
- JavaScript local variables and methods use `camelCase`, module constants use `UPPER_SNAKE_CASE`,
  and named application objects use `PascalCase`.
- CSS classes use lowercase kebab-case. JSON response fields use `camelCase` because they are read
  directly by the frontend.

Prefer chess vocabulary such as `activeColor`, `moveHistory`, and `kingInCheck`. Avoid new ambiguous
short forms, numbered variants, or names that describe implementation position instead of behavior.
