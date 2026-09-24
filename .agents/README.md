# For agents developing ApiSutra

Scope: code, tests, documentation, and tooling of `apisutra/php` itself.
Building SDKs and extensions through public APIs belongs to
[using the package](../docs/en/start/agent.md).

## Rules by task

| Change | Applicable rules |
| --- | --- |
| PHP code, including tests and examples | [Code style](code-style.md) |
| Behavior, APIs, dependencies, or structure | [Architecture](architecture.md) and [testing](testing.md) |
| Tests, fixtures, or test infrastructure | [Testing](testing.md) |
| Documentation, examples, or agent instructions | [Documentation](documentation.md) and its checks in [testing](testing.md) |

Use the [development guide](../docs/en/development/README.md) to locate the affected
mechanism. Verify public guarantees against the [reference](../docs/en/reference/README.md),
source, and tests. A local change does not require reading the entire documentation set.

## Working on a change

- Default to inspecting the affected code, implementing the requested change, verifying
  it, and reporting the result. Create a plan only when the user explicitly asks for one,
  regardless of task size. Do not add audits, ADRs, or report files as routine stages.
- Identify the observed scenario and expected behavior; reproduce defects.
- Preserve component boundaries. When public behavior changes, explain compatibility
  and any actions required from users.
- Resolve routine technical choices independently. Ask only about unresolved product
  behavior, scope, or compatibility choices that materially affect the result; group
  related questions and include a recommendation. Do not reopen accepted decisions
  without new evidence, or ask again to perform already authorized work.
- Update the document that owns the affected contract and dependent examples.
  Run checks appropriate to the risks. Report results, commands, and verification limits.
- Keep useful decisions and results in existing task materials when available; a concise
  chat summary is sufficient otherwise. A requested plan does not require a separate
  audit, discussion, or completion report. New evidence may justify revising the approach.

A proposal in a discussion or audit does not replace an accepted decision.
Design recommendations alone do not require user approval.

Keep concise, topic-specific instructions in `.agents/`, using `kebab-case.md`.
Explain internals in `docs/en/development/` and specify contracts in `docs/en/reference/`.
