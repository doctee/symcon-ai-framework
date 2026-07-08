# IMPLEMENT_REFERENCE

Implement a new SAEF reference implementation.

## Preparation

Before making any changes:

1. Read AGENTS.md completely.
2. Read the relevant Engineering Principles.
3. Read all relevant Standards and Knowledge articles.
4. Analyse existing Helpers.
5. Analyse existing Reference Implementations.
6. Explain the intended implementation before changing files.

## Architecture Rules

- Reuse Before Extend.
- Prefer composition over new abstractions.
- Do not introduce new public APIs unless explicitly requested.
- Do not duplicate existing helper functionality.
- Object creation must use existing Ensure helpers.
- Do not introduce private installation data.
- Configuration scripts must remain idempotent.

## Runtime Diagnostics Checklist

When a reference implementation needs runtime metadata, check whether it can be
modeled with existing diagnostics helpers:

- Registry for small structured metadata.
- Statistics for counters, timestamps and duration values.
- ErrorRingBuffer for bounded error or event history.
- ConfigurationHash for configuration fingerprints.

If dedicated variables are used instead, explain why they represent domain state
or must intentionally be visible for user interfaces, visualisation or trigger
logic.

## Implementation

The implementation should:

- follow the existing SAEF coding style;
- reuse existing helpers;
- remain focused on the requested feature;
- update documentation only when appropriate.

## Verification

Before finishing:

- run make check;
- summarize changed files;
- explain architectural decisions;
- if the working tree already contains unrelated modifications, explicitly identify them and state whether they were touched;
- do not create commits.
