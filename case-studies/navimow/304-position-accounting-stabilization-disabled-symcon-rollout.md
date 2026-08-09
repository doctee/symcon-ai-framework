# 304 Position Accounting Stabilization Disabled Symcon Rollout

**Case study:** Navimow native IP-Symcon module

**Status:** Exact published commit installed and verified with MQTT disabled

**Date:** 2026-08-09

## 1. Result

Two equal bounded read-only preflights passed before exactly one supported
module update. Immediate and delayed read-only postflights then passed.

```text
installed before: 4b4b4d7b577df2639ed4a82049aa127c56bdc989
installed after:  50b365200e0c5c55990214c31f4a46f28b1406c7
module updates:   1
module reloads:   0
explicit ApplyChanges calls: 0
```

Every MCP result separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Preflight

Both observations proved:

- exact baseline commit on branch `main`;
- clean and valid module repository;
- ready kernel and active Navimow module instances;
- MQTT and WebSocket inactive;
- Authorization header and MQTT username/password absent;
- REST operational and authentication usable;
- exact four-topic allowlist without wildcards;
- 14-variable identity contract unchanged;
- all five configured Archive logging contracts present and queryable;
- position diagnostics disabled and empty.

The new pilot-wide position-accounting projection was absent as expected on the
baseline version.

## 3. Single Mutation

The mutation probe recomputed all safety conditions immediately before calling
`MC_UpdateModule()` once. The call returned success and reported the exact
target commit. There was no retry, reload, explicit ApplyChanges, OAuth action,
restart, MQTT activation or mower command.

## 4. Postflight

Immediate and delayed observations proved:

- exact target commit, clean and valid;
- all module and Core statuses healthy;
- MQTT and position diagnostics still disabled;
- all transport credentials absent;
- REST still operational;
- instance, configuration, identity, Archive, command-evidence and
  subscription contracts unchanged;
- new `positionAccounting` projection present with all counters at zero.

The delay exceeded three minutes. No transient activation or later contract
drift was observed.

## 5. Architecture Decisions

### AD-NAV-1272: Update through Module Control exactly once

The supported module update is sufficient. Additional reload or explicit
ApplyChanges calls would add mutation without evidence value.

### AD-NAV-1273: Prove the additive API while disabled

The new coordinate-free accounting projection must exist and be empty before
any future receive-only pilot is considered.

### AD-NAV-1274: Preserve logging identity as a release invariant

The existing variables and their Archive logging contracts remain unchanged so
historical battery and state data continue without replacement variables.

### AD-NAV-1275: Keep activation separately gated

A healthy disabled installation does not itself justify a new MQTT pilot. Any
activation requires a new readiness decision and fresh token-horizon gate.

## 6. Mutation Counts

```text
read-only preflights:       2
module updates:             1
immediate postflights:      1
delayed postflights:        1
module reloads:             0
explicit ApplyChanges:      0
OAuth actions:              0
MQTT activations:           0
service restarts:           0
mower commands:             0
```

## 7. Gate State

| Gate | Status |
|---|---|
| SAEF merge | PASS |
| standalone publication | PASS |
| metadata conformance | PASS BY BYTE EQUIVALENCE |
| disabled Symcon rollout | PASS |
| MQTT activation | CLOSED |
| new private pilot | NOT STARTED |

## 8. Recommendation

Retain the module in its current disabled state. The next useful step is a
bounded readiness review for a new receive-only pilot that verifies the token
horizon and defines whether the corrected monotonic position counters are the
primary acceptance target. That activation remains a separate live gate.
