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
9. For authorized live work, read and follow
   `project/SYMCON_MCP_SCRIPT_READBACK.md`.

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
- Express downstream dependencies as explicit variable-role contracts. Keep
  authoritative control/status variables separate from optional activity or
  presentation variables.
- Do not infer on/off truth from brightness when the upstream integration may
  retain a non-zero stored brightness while switched off.
- Do not require implementation-specific upstream diagnostics unless they are
  an explicitly validated part of the integration contract.

## Live Change Gate

Before changing an authorized live caller:

- preserve a private recoverable source backup and expected source hash;
- snapshot affected object identity, values, metadata, tree, archive and links;
- define rollback before deployment;
- predict device actions and notifications from current read-only inputs;
- apply only one independently reviewable change;
- do not execute the caller manually unless that execution and its predicted
  effects are explicitly authorized;
- prefer observation of the next regular scheduled run when it proves the same
  contract more safely.

Use direct MCP source read-back and bounded structured probes. Evaluate
`transportError`, `executionError` and `truncated` separately. Do not create a
temporary marker when direct result channels are available.

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
- verify live source identity and the exact intended call replacement;
- verify object identity, value, relevant metadata, tree, archive, links and
  event progression after the regular or authorized test run;
- verify idempotency twice when caller execution is safe;
- verify every configured control target through its authoritative feedback
  variable and test activity-only variables independently from on/off truth;
- test retained non-zero activity values while the authoritative control state
  is inactive when the upstream device supports stored brightness;
- verify deletion of every explicitly authorized temporary live object;
- summarize changed files;
- explain architectural decisions;
- report unrelated pre-existing working tree changes;
- do not create commits.
