# ControlLight Availability-Aware Feedback Classification

**Date:** 2026-07-22  
**Status:** Live activated; non-commanding seven-wrapper regression passed  
**Scope:** Post-v0.2 ControlLight runtime and SAEF contract

## Trigger

A migrated Z2M light produced repeated authoritative STATE confirmation
timeouts while its target module retained the last reported on-state. Read-only
inspection showed that the individual device was unavailable even though the
MQTT and Z2M Symcon instances remained active. A plausible normal cause is a
user physically removing power from the lamp.

The initial idea of rejecting commands while availability is false was not
safe. After power is restored, the availability value can remain stale briefly
while an interactive command already arrives.

## Decision

ControlLight now treats availability as optional post-failure evidence:

1. A permitted command is dispatched without an availability precondition.
2. Existing bounded authoritative confirmation remains unchanged.
3. Successful feedback completes the action even if availability still carries
   an older offline value.
4. After a confirmation timeout, the latest optional availability value is
   inspected.
5. A still-unavailable target is classified as `device_offline`; an available
   or unconfigured target remains `feedback_timeout`.

The classification uses `ControlLightCommandException`, local to the
ControlLight reference implementation. No public SAEF helper was added.

## Configuration

The normalized optional contract contains:

- stable target Ident;
- target variable type;
- value representing availability.

The Z2M preset supplies boolean `device_status=true`. Matter, Home Assistant
and Homematic presets do not assume a shared availability variable and can be
configured explicitly when their concrete target module exposes one.

Missing optional availability is not a configuration failure. An existing
Ident with an incompatible object or variable type remains ownership or
configuration drift and fails explicitly.

## Diagnostics

The existing bounded ErrorRingBuffer records the typed failure class and
affected capability. Existing command, timeout and error statistics retain
their responsibilities. No duplicate device-state cache or new metadata
helper was introduced.

## Regression Evidence

Offline tests cover:

- all 29 sanitized installed ControlLight contracts;
- Z2M availability normalization and strict type validation;
- a stale offline indication followed immediately by a successful interactive
  command and authoritative feedback;
- offline, available and unconfigured timeout classification;
- exactly one command dispatch in the reconnection case;
- no optimistic local state after missing feedback;
- semaphore release, action rejection and existing brightness semantics;
- idempotent topology and optional availability discovery.

The deterministic fileset build contains 14 ordered PHP sources plus the
bootstrap and two provenance files, for 17 deployment files in total. The
managed runtime mirror tests and complete repository check pass, including
PHPStan and PHPCS.

## Read-Only Live Compatibility

All seven active v2 wrappers use the Z2M preset and resolve an existing boolean
`device_status` variable below their configured target root. There are no
missing variables or type conflicts. Five targets reported available and two
reported unavailable during the bounded read-only inspection, which also
demonstrates why availability cannot be an activation or command precondition.

No wrapper was executed, no device action was attempted and no live object or
source was changed. Installation-specific IDs and exact evidence remain in the
private overlay.

## Live Gate

The approved live gate completed successfully:

- the restricted deployment channel staged the 17-file package immutably and
  passed its non-activating preflight;
- the channel's global `activate` operation was deliberately not used because
  ControlLight is a secondary file-backed runtime, not the System.Locals helper
  owner;
- seven v2 wrapper sources moved atomically to the staged runtime after a
  byte-exact private rollback snapshot;
- the non-executable managed mirror was generated with the exact authoritative
  runtime payload;
- direct readback found no source or object-tree mismatch across all 29
  wrappers;
- one non-commanding `RunScript` reconciliation per active wrapper produced no
  command, error, timeout or local-value delta; and
- System.Locals, the kernel process and all 22 retained legacy wrappers remained
  unchanged.

Two targets remained physically unavailable during activation. Availability
did not block staging, source activation or non-commanding reconciliation, as
required by the new contract. A later supervised hard-power reconnect test is
an operational observation gate, not a prerequisite for retaining the
activated source.

## Action-Boundary Correction

The first live offline observation confirmed the `device_offline`
classification, but also exposed a Symcon-specific transport effect: rethrowing
the classified exception from the action script produced an additional
uncaught ScriptEngine fatal even though the initiating AutoOff script caught
its `RequestAction()` call. The bounded AutoOff sequence still terminated
correctly, but the duplicate fatal was misleading operational noise.

ControlLight therefore retains the typed exception inside its command path,
records the same statistics and bounded error context, and converts it at the
outer runtime boundary to a structured `command_failed` result. Unexpected
configuration and programming failures are still logged and rethrown. The
command dispatch, post-timeout availability read, authoritative-feedback
semantics and caller-owned retry policy are unchanged.
