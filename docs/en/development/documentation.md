<!-- languages --> <a href="documentation.md">English</a> · <a href="../../ru/development/documentation.md">Русский</a> <!-- /languages -->
# Documentation maintenance <a id="section-1"></a>

Public documentation serves SDK authors and users of existing clients. Core changes
are covered separately in `docs/en/development/`. AI agent instructions are in
[.agents/documentation.md](../../../.agents/documentation.md).

## Content ownership <a id="section-2"></a>

| Layer | Contents |
| --- | --- |
| `docs/en/start/` | Task guides; a separate agent entry point and capability map |
| `docs/en/guides/` | Practical guidance linking to contracts and executable examples |
| `docs/en/reference/` | One complete owner of parameters, precedence, and limitations |
| `docs/en/glossary/` | Short definitions and links to contract owners |
| `docs/example/` | Shipped source files, fixtures, and runnable scripts |
| `docs/en/development/` | ApiSutra developer guide, core internals, and checkout checks |

AI agents have two entry points: [using the package](../start/agent.md) and
[changing ApiSutra](../../../.agents/README.md). The first provides context, selection
criteria, and a [capability map](../start/agent-capabilities.md); the second provides
repository procedures and rules. The user map does not prescribe a mandatory sequence.
Both entries link to the same contract owners in the reference.

## Change a contract <a id="section-3"></a>

Check declarations against `src/` and behavior against a targeted test. Update the
topic's owner, then examples and short links from other layers. Do not duplicate
precedence tables in the glossary, guides, and attributes. The attribute reference
defines declaration signatures and links to behavior in the relevant topic.

Use the explicit [docs-api.json](../../../tests/Support/docs-api.json) registry for
public PHP names. Reflection checks classes, visibility, parameters, and types without
constructing user-defined object defaults. For attributes, constructor text and the
published signature are compared separately: defaults are not evaluated, but changing
their expression requires a documentation update. This registry does not prove semantics:
side effects of defaults containing `new`, precedence, and limitations are verified
against source code and observable scenarios.

## Executable examples <a id="section-4"></a>

Example classes live in separate namespaced PSR-4 files. Guides link to those files.
Smoke tests execute the published file, including in archives without require-dev;
do not copy the example into a test. Reference snippets may show individual calls,
but must identify external variables and required context.

Run the Laravel example separately with real dependencies. Use fakes for ordinary
checks: reading the quickstart must not require an external API or credentials.

## Move a page <a id="section-5"></a>

Preserve useful content and update links in the current documentation, including the
declaration registry. Remove the previous page and unused anchors. When moving the
development section, update Git/Composer archive exclusions and checker classification.
Every published page contains a current guide, reference, or index. Update all
public links when a page moves; retain only the current documentation structure.

Line limits: root overview README with three examples and grouped capability tables 240;
index 150; task guide 200;
guide 300; reference 320; glossary 180; development 220. Files also have a 24 KiB limit.
Changelog and version release notes are exempt. If a topic does not fit, split it by
independent tasks rather than creating "part 1/2".

## Checks <a id="section-6"></a>

```bash
composer check-docs
git diff --check
```

These checks suffice for text-only changes. Changed PHP examples also need
`composer analyse-docs` and execution of the affected scenario. Run `composer check-package`
when changing distribution contents, paths, or installation, and for release verification.
`composer check-docs` already runs the checker's tests; no separate rerun is needed.

`check-docs` checks local paths, anchors, reference-style links, size, reachability,
forbidden dependencies of public documentation, redirect pages, old-section blocks,
empty glossary definitions, the API registry, and published examples. `--report`
saves detailed JSON; `--navigation-only --distribution` checks documentation in an
extracted archive.

[Docs CI](../../../.github/workflows/docs.yml) runs `composer check-docs` and
`composer analyse-docs` on PHP 8.4. The workflow runs on push, pull request, and manual
invocation; the technical `badges` branch is excluded from push. Changes to src also
trigger API/documentation consistency checks. The README Docs CI badge shows the
master branch's push result; the docs badge links to user documentation.

Checker failure cases have dedicated tests. External web pages, semantic API
completeness, SQL/JSON/command meaning, and correctness of all contextual PHP snippets
are not proved automatically: they require editorial review and targeted checks.
Matching file counts does not replace that work.

When an archive check is needed, `check-package` uses HEAD by default and the Git index
with `--staged`. Select the intended changes, optionally with a temporary `GIT_INDEX_FILE`
to preserve user staging, and identify the checked tree in the result. A separate report
file is optional. Git and Composer archives must match and install runtime dependencies.

## Language editions <a id="section-7"></a>

English is the default; English and Russian are required editions.
`docs/translations.json` connects stable page IDs, kinds, distribution scopes, and
edition paths. Root README, CONTRIBUTING, and CHANGELOG have explicit mappings.
Add optional languages under `languages`; a missing translation is explicitly labelled
as an English fallback in the switch. Required languages cannot use fallback.

When a contract changes, update both editions and separately compare conditions,
negations, precedence, limitations, and examples. After review, explicitly record
SHA-256 values for the default page and translation under `reviewed`; only line
endings are normalized. Changing either side makes the entry stale. CI checks hashes
but neither updates them nor proves translation quality. Updating hashes without
reviewing meaning is not acceptable.

Language switches use document IDs; ordinary links stay within the current locale.
Explicit English anchors match across translations and are independent of heading
text. `docs/example/` and fixtures are shared; executable PHP comments are not
translated. Explanatory comments within Markdown use the page language. PHP names,
protocol literals, commands, and exact output remain unchanged.

User editions and the registry ship in Git/Composer dist. `docs/*/development/`,
CONTRIBUTING, AGENTS, and `.agents/` are excluded for every language. Check archive
navigation with `--navigation-only --distribution`; external links to development
documentation are checked in the checkout.
