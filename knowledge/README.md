# Knowledge Base

This directory contains reusable engineering knowledge for SAEF.

Unlike standards, these documents explain concepts, trade-offs, patterns and
decision guidance. They support `standards/SYMCON_STANDARDS.md`; they do not
define additional mandatory rules.

## Current Articles

| Article | Topic |
| --- | --- |
| `EK-001-state-machines.md` | State-machine design for complex automations |
| `EK-002-retry-mechanisms.md` | Bounded retry design and recovery thinking |
| `EK-003-archive-processing.md` | Bounded and auditable archive processing |
| `EK-004-internal-state-management.md` | Script-owned internal state, diagnostics and ownership |
| `EK-005-idempotent-configuration.md` | Repeatable configuration scripts and helper-first setup |
| `EK-006-runtime-diagnostics.md` | Configuration hashes, registries, statistics and error ring buffers |

## Relationship to References

- `references/RI-001-idempotent-configuration-script.md` demonstrates the
  helper-first idempotent configuration pattern explained by EK-005.
- `references/RI-002-runtime-diagnostics-internal-state.md` demonstrates the
  runtime diagnostics composition explained by EK-004 and EK-006.

## Future Topics

- Error recovery
- Automation patterns
- Snapshot and diff workflows
- AI-assisted development workflow
