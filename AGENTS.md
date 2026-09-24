# ApiSutra: entry points for AI agents

Choose an entry point based on the scope of your task:

| Scope | Entry point |
| --- | --- |
| An external API SDK or application using ApiSutra, including custom casts, hooks, and results | [Using the package](docs/en/start/agent.md) |
| ApiSutra itself: core, public contracts, tests, documentation, tooling, and CI | [Developing the package](.agents/README.md) |

The usage entry helps select capabilities for a task. The development entry defines
rules for changing this repository. Read only relevant sections; both entries use
the same [contract reference](docs/en/reference/README.md).

Both entries apply to shipped SDK examples: the usage entry covers public APIs,
and the development entry covers changes and checks within this package.
External SDKs follow their own repository rules; ApiSutra development procedures
do not automatically apply to them.

## Instruction language

Maintain `AGENTS.md` and all documents under `.agents/` strictly in English.
This requirement covers headings, prose, tables, and link labels. Preserve file paths
and API identifiers. It does not change the language rules for public documentation
or PHP code comments.
