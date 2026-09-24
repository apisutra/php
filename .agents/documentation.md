# Documentation and agent instructions

Applies to README files, `docs/`, shipped examples, and agent instructions.
Content ownership and size limits are in [documentation maintenance](../docs/en/development/documentation.md).

- Keep each complete contract in one topical `docs/en/reference/` document; other pages
  provide context and links. Verify names, signatures, and behavior against `src/`
  and tests. Maintain `tests/Support/docs-api.json` for declarations.
- The [usage entry for agents](../docs/en/start/agent.md) and its capability map support
  task-specific choices through applicability, constraints, and links. Do not turn
  them into a mandatory sequence, a list of classes to create, or a second reference.
- Package development rules belong in `.agents/` and core explanations in
  `docs/en/development/`. Public documentation must stand on its own, without private
  working materials. Do not impose this repository's development procedures on consumers.
- When adding or moving a page, update relevant indexes and links. Remove the old
  page without redirect stubs, old-address sections, or working history in public
  documentation. Historical snapshots do not dictate its structure.
- Public documentation must work in distribution archives. Use full repository URLs
  when linking to excluded `.agents/`, AGENTS, CONTRIBUTING, `docs/en/development/`,
  or tests.
- Executable examples belong in `docs/example/`; smoke tests execute the published
  file, not a copy inside a test. Snippets state their required context and variables.
- Use named classes, imports, and Russian comments in executable PHP files. The main README
  may omit imports if it states the omission and links to the complete example.
- Distinguish hydration, DTO DX serialization, and request wire representation;
  query arrays and JSON strings inside fields; standalone capabilities and integration
  dependencies. Detailed rules belong in the reference, including validator and transport setup.
- Use [glossary](../docs/en/glossary/README.md) terminology and preserve public PHP names.
  Examples must reproduce the described scenario without real secrets.

See [verification](testing.md) for applicable commands.

## Language editions

English is the default edition; English and Russian are required. Keep page pairs
in `docs/translations.json`, including root README, CONTRIBUTING, and CHANGELOG.
Preserve stable document IDs, page kinds, and distribution scope when moving pages.
Use the same explicit English anchors and a registry-derived language switch on
both editions. Ordinary navigation stays within the current language.

Translate explanatory comments in Markdown snippets into the page language. Keep
PHP names, protocol values, exact output, and shared executable examples unchanged.
After reviewing meaning on both sides, explicitly record their SHA-256 values in
`reviewed` (normalize line endings only). Never update hashes merely to pass CI:
changed text requires a new semantic review. Required translations cannot fall back
to English. Optional languages may use the explicitly labelled English fallback.
