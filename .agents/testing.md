# Verifying changes

Run commands from the checkout root; use `composer install` if dependencies are missing.
Environment requirements and the full command set belong in the [testing guide](../docs/en/development/testing.md).

Complete the implementation before routine test runs, static analysis, lint, and other
completion checks below. Do not interrupt coding with checks after each file or small
batch of edits. During implementation, run only a narrow check whose result is needed
to resolve a concrete blocking uncertainty, reproduce a failure, or satisfy an explicitly
agreed technical gate. Batch final verification once the code is ready.

| Change | Verification |
| --- | --- |
| PHP behavior | Affected scenarios via `vendor/bin/pest <path>`; shared mechanisms also require `composer test` |
| PHP implementation or types | `composer analyse`, `composer lint` |
| Documentation or instructions | `composer check-docs`, `git diff --check`; check local links in `AGENTS.md` and `.agents/` separately |
| Shipped PHP examples | `composer check-docs`, `composer analyse-docs`, and the affected executable scenario |
| Distribution contents or structure | `composer check-package` against the changed tree, not just the previous HEAD |
| Integration or backend behavior | The relevant integration environment when the changed behavior depends on it |

At final verification, start with affected scenarios; expand based on shared dependencies and risk.
Run broader checks once the implementation is ready. Repeat passed checks only after
relevant changes, failures, or new evidence. Do not run the PHP suite, dependency matrix,
or archive installation checks for text-only edits. Full PHP/dependency matrices and
archive installation belong in CI and release verification; run them locally when
changing the corresponding compatibility or packaging behavior, or investigating a failure.
Redis checks apply when changing its backend or shared quota behavior that affects it.
Report commands, results, and limits briefly; do not save routine test logs or create
separate evidence reports unless requested or needed to reproduce a specific problem.

`check-package` checks HEAD by default and the index with `--staged`.
A temporary `GIT_INDEX_FILE` allows checking the working
tree without changing the user's staging. Do not report an old tree's checks as new.

- For defects, cover the reproducible scenario. Test observable behavior, significant
  errors, and effects rather than private methods or implementation text.
  Do not add tests that merely restate implementation or documentation.
- Ordinary tests use fakes or a test transport without real network calls. Run live
  scenarios separately. Control time, randomness, and global state; existing resets
  are in [tests/Pest.php](../tests/Pest.php). Fixtures must contain no real secrets.
- Use real DTOs and value objects; substitute external dependencies. Check attempts
  for retries and absence of additional effects for idempotency.
- Place tests in `tests/Unit/`, named doubles in `tests/Stubs/`, infrastructure in
  `tests/Support/`, and data in `tests/Fixtures/`. `src/Testing/` is the public
  consumer API, not internal test infrastructure.
- Move named helper types out of Pest files; a one-off anonymous double is allowed.
  Files using `describe()`/`it()` do not require namespaces.
  After autoload changes, run `composer dump-autoload --optimize --strict-psr`.

Measure performance with a separate reproducible scenario when it is a requirement.
There are no universal test duration or coverage thresholds.
