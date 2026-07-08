# OPTIMIZE_CONTROL_SCRIPT

Optimize an existing IP-Symcon automation or control script according to SAEF.

Use this prompt for scripts that control physical devices, actuators or safety-relevant automation.

Examples:

- lights
- irrigation
- heating
- pumps
- switches
- HomeConnect devices
- alarm-related automation

## Preparation

Before making changes:

1. Read `AGENTS.md`.
2. Read `standards/SYMCON_STANDARDS.md`.
3. Read `knowledge/EK-001-state-machines.md`.
4. Read `knowledge/EK-002-retry-mechanisms.md`.
5. Read `knowledge/EK-004-internal-state-management.md`.
6. Read `knowledge/EK-006-runtime-diagnostics.md`.
7. Analyze the complete existing script.
8. Explain the current structure, device interactions and relevant risks.

## Goals

- Preserve the full existing functionality.
- Preserve safe behavior.
- Make state transitions explicit where useful.
- Make retries explicit.
- Make timeout handling explicit.
- Make error handling explicit.
- Use existing SAEF helpers and engineering patterns where appropriate.
- Do not introduce new public APIs.
- Do not introduce new helpers unless recurring reuse is demonstrated.

## Safety Requirements

- Unknown or failed states must not leave actuators permanently active.
- Timeouts must lead to a defined safe state.
- Communication failures must be handled explicitly.
- Sensor failures must not lead to unsafe operation.
- Switching sequences must be deterministic.
- Hardware or configuration changes that invalidate runtime assumptions should be detected and handled.

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
