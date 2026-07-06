# Contributing

This project is developed as a documentation-driven engineering framework for
professional IP-Symcon development.

SAEF is not a prompt collection. Contributions should strengthen the framework:
principles, standards, knowledge articles, helpers, templates, reference
implementations and workflows should remain consistent with each other.

## Workflow

Before changing code or documentation, read the relevant context:

1. `README.md`
2. `project/AI_PROJECT.md`
3. `ARCHITECTURE.md`
4. `principles/ENGINEERING_PRINCIPLES.md`
5. `principles/AI_PRINCIPLES.md`
6. relevant ADRs in `adr/`
7. relevant standards in `standards/`
8. relevant knowledge articles in `knowledge/`
9. relevant reference implementations in `references/`

For reference implementations, also follow `prompts/IMPLEMENT_REFERENCE.md`.

## Engineering Rules

- Prefer reliability, maintainability and reviewability over convenience.
- Keep public framework content free of private installation data.
- Prefer complete files and complete examples over isolated snippets.
- Explain important engineering decisions when changing standards, knowledge,
  templates, helpers or references.
- Preserve existing structure unless the change is explicitly justified.

## Reuse Before Extend

Before introducing a new helper, public API, pattern or reusable abstraction:

1. Search the existing framework for suitable functionality.
2. Prefer composing existing helpers over adding new abstractions.
3. Add a new public API only when the recurring engineering pattern is clear.
4. Explain why the new API is necessary.
5. Keep implementation-specific convenience code local until reuse is proven.

## Helper-First Development

Reusable implementation logic should use SAEF helpers where they already exist.

Do not duplicate helper logic such as:

- idempotent category, variable, profile, instance, link, script or event
  creation,
- cyclic script event creation,
- variable waiting or bounded feedback checks,
- diagnostics state helpers such as configuration hashes, registries,
  statistics or error ring buffers.

If a helper is missing, propose a reusable helper or keep the logic local until
the pattern is proven.

## IP-Symcon Safety

- Avoid hardcoded IP-Symcon ObjectIDs in reusable artifacts.
- Prefer Idents, explicit configuration and clear ownership.
- Use `RequestAction()` for controllable variables.
- Use `SetValue()` only for script-owned internal state, calculated values,
  cache values or variables without action semantics.
- Keep configuration scripts idempotent.
- Bind automatically created script events explicitly for IP-Symcon 6.0+.
- Keep archive processing bounded and auditable.

## Private Data

Never commit:

- credentials,
- tokens,
- private IP addresses,
- hostnames,
- personal IP-Symcon ObjectIDs,
- private MQTT topics,
- local system descriptions,
- `.env*` files,
- files under `private/`.

Use `private/`, `*.local.*` files or local environment configuration for private
installation details.

## Verification

Run the full project check before submitting changes:

```sh
make check
```

This runs syntax linting, static analysis and coding-style checks configured for
SAEF.

If a change affects Markdown-only documentation, still run `make check` so the
repository remains in a known-good state.

## Commit Style

Use Conventional Commits where practical.

Examples:

- `docs(knowledge): clarify runtime diagnostics`
- `docs(references): add runtime diagnostics reference`
- `feat(helpers): add configuration hash helper`
- `chore: update contribution workflow`

Keep commits focused. Do not mix private local changes with public framework
updates.

## Guidance for AI Agents

AI coding agents must follow `AGENTS.md`.

In addition:

- read before changing,
- reuse before extending,
- do not introduce private data,
- do not create new public APIs without justification,
- do not implement one-off Ensure logic inside reference implementations,
- summarize verification results and changed files before finishing.
