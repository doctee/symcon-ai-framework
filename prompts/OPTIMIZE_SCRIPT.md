# OPTIMIZE_SCRIPT

Optimize an existing IP-Symcon script according to SAEF.

## Preparation

Before making changes:

1. Read `AGENTS.md`.
2. Read `standards/SYMCON_STANDARDS.md`.
3. Read relevant Knowledge articles.
4. Analyze the complete existing script.
5. Explain the current structure and relevant risks.

## Goals

- Preserve the full existing functionality.
- Improve readability, maintainability and robustness.
- Use existing SAEF helpers and engineering patterns where appropriate.
- Do not introduce new public APIs.
- Do not introduce new helpers unless recurring reuse is demonstrated.
- Preserve idempotent behavior where configuration is involved.
- Preserve existing user-facing behavior unless explicitly requested.

## Runtime Metadata

Use existing diagnostics responsibilities:

- `ConfigurationHash` for deterministic configuration fingerprints.
- `Registry` for small structured runtime metadata.
- `Statistics` for counters, timestamps and durations.
- `ErrorRingBuffer` for bounded error or event history.

Dedicated variables are allowed only when they represent real domain state or intentionally visible UI/trigger state.

Explain every new variable.

## Verification

Before finishing:

- run `make check` where possible;
- check PHP syntax for changed private scripts where possible;
- summarize changed files;
- explain architectural decisions;
- report unrelated pre-existing working tree changes;
- do not create commits.
