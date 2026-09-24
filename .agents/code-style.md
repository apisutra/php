# PHP code style

Applies to PHP files in this repository, including tests and examples.

- PHP 8.4+, PSR-12, `declare(strict_types=1);`.
- Type parameters, return values, and properties. Specify array elements, keys,
  or shapes in PHPDoc. Use `mixed` for genuinely arbitrary values and unions
  for known sets of types.
- Use `use` imports instead of fully qualified names in expressions. Write code
  comments in Russian.
- Put each named class, interface, trait, or enum in its own file, with namespace
  and path matching [autoload configuration](../composer.json). Use PascalCase for
  types, camelCase for methods and properties, and UPPER_SNAKE_CASE for constants.
- Distinguish absence, permitted `null`, and errors. Defaults and null-safe access
  must not hide contract violations.
- An immutable object's `withX()` returns a copy and preserves the original.
  Prefer `readonly` for immutable data; execution state may be mutable.
  Keep types intended for inheritance extensible.
- Use `Override`, `Deprecated`, and `SensitiveParameter` as intended, with imports.
  Masking a parameter in a stack trace does not replace sanitizing logs and fixtures.

Choose constructor promotion, named arguments, `match`, and early returns for
readability. See [testing](testing.md) for test type placement and
[documentation rules](documentation.md) for exceptions in overview PHP snippets.
