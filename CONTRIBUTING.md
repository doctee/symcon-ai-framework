# Contributing

This project is developed as a documentation-driven engineering framework.

## Principles

- Keep public and private data strictly separated.
- Explain decisions, not only rules.
- Prefer small, focused commits.
- Update ADRs when architecture decisions change.
- Update standards when reusable rules emerge.
- Add examples or tests when helpful.

## Before changing code or documentation

Read:

1. `project/AI_PROJECT.md`
2. relevant standards in `standards/`
3. relevant ADRs in `adr/`

## Private data

Never commit:

- credentials
- tokens
- IP addresses
- private object IDs
- site-specific configuration
- `.env` files
- files under `private/`
