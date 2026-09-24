# Architectural constraints

Apply these when changing ApiSutra behavior, contracts, dependencies, or structure.
See the [development guide](../docs/en/development/README.md) for component internals.

- Keep generic mechanisms in the core. External API specifics belong in provider SDKs;
  Laravel dependencies belong in the integration. The core must work without a Laravel
  application. Do not impose a provider SDK's directory layout on core modules.
- Pass dependencies explicitly; the container remains optional. Use auto-resolve at
  existing integration points. Do not introduce global service lookups within computations
  or circular module dependencies.
- Preserve public behavior during refactoring. Intentional contract changes require
  scenario coverage and updated contract documentation. Describe migration actions only
  when existing consumers need them; do not add legacy layers or migration pages for
  hypothetical users. General recommendations do not justify unrelated API redesigns.
- Preserve [result and error semantics](../docs/en/reference/results/README.md):
  result-first behavior, exception modes, and pipeline control flow.
  Do not turn errors into success or indefinite waiting, or expose secrets.
- Distinguish immutable metadata, reusable dependencies, and per-call state.
  Caching must not introduce unintended shared mutable state; transformation contexts
  are valid only during the handler invocation.
- Keep synchronous `send()` available. Async uses the canonical pipeline in Fibers;
  the default HTTP adapter performs concurrent I/O. Promise-based signatures alone
  do not establish concurrency: custom adapters must declare the required capability.

Extend an existing mechanism when it expresses the required contract. Extract a
component for a separate rule, variation in behavior, or isolation of effects;
line count alone does not justify another layer. Orchestration, computation, and I/O
have different reasons to change. Prefer composition; inheritance is appropriate
when it preserves the base contract. DTOs and value objects do not perform I/O.
