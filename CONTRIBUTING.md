<!-- languages --> <a href="CONTRIBUTING.md">English</a> · <a href="docs/ru/development/contributing.md">Русский</a> <!-- /languages -->
# Contributing to ApiSutra <a id="section-1"></a>

Environment setup, the mechanism map, and verification of core, test, documentation,
and tooling changes are in the [development guide](docs/en/development/README.md).
AI agents have a [separate entry point with repository rules](.agents/README.md).
To build an external API SDK, start with the [user documentation](docs/en/start/create-sdk.md).

## Describe a proposal or problem <a id="section-2"></a>

State the user problem the change solves. For a defect, include a minimal scenario,
input data, expected and actual results, and PHP and package versions. Use anonymized
data without access keys.

Explain why the mechanism belongs in the shared core and which existing public
capabilities have already been checked. API-specific protocol behavior belongs in
the provider's SDK.

## Prepare a change for review <a id="section-3"></a>

The change description should include:

- The problem and resulting behavior from the user's perspective.
- Affected contracts, compatibility, and required upgrade actions.
- Checks performed, their results, and significant verification limits.
- Links to updated guides and examples.

Verification commands belong in the [testing guide](docs/en/development/testing.md);
documentation rules belong in [documentation maintenance](docs/en/development/documentation.md).
The description must be understandable without reading the discussion history.
