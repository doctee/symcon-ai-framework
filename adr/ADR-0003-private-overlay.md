# ADR-0003: Separate public framework content from private installation overlay

Status: Accepted  
Date: 2026-07-05

## Context

The framework is intended to become publishable, reusable, and suitable for GitHub. At the same time, real Symcon development often requires private installation details such as IP addresses, object IDs, MQTT topics, site names, credentials, VPN information, and local device names.

Mixing public engineering knowledge with private installation data would make publication risky and reduce reusability.

## Decision

The repository is split into public and private layers.

Public content may live in:

- `handbook/`
- `standards/`
- `adr/`
- `principles/`
- `knowledge/`
- `templates/`
- `helpers/`
- `examples/`
- `tests/`
- `docs/`
- `project/` if it contains non-secret project context.

Private content must live in:

- `private/`
- `*.local.*`
- `.env`
- secret files.

Private files must be excluded from Git.

## Rationale

This separation allows the framework to be useful in the real installation while remaining safe to publish later.

It also gives AI assistants a clear rule: private data may be used locally for context but must not be copied into public files.

## Consequences

Positive:

- safer future publication,
- clearer review process,
- reduced risk of leaking credentials or internal topology,
- better distinction between reusable knowledge and local configuration.

Negative:

- requires discipline when creating examples,
- some examples need anonymization,
- private overlays must be maintained separately.

## Alternatives considered

### Keep everything private forever

Rejected because the framework is intended to be reusable and potentially publishable.

### Store private data inline and clean up later

Rejected because cleanup is error-prone and secrets can remain in Git history.

## Related

- `.gitignore`
- `project/AI_PROJECT.md`
- `CONTRIBUTING.md`
