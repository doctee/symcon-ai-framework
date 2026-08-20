# 329 Wet-Delay MQTT And Position Pilot Activation

**Case study:** Navimow native IP-Symcon module

**Status:** Bounded receive-only pilot active and stable

**Date:** 2026-08-20

## 1. Purpose

This step activates one bounded receive-only MQTT and local-position pilot
while the official app reports delayed mowing due to wet conditions. It seeks
natural event and later scheduled-task evidence without testing a forced
Start.

## 2. Authorization Boundary

The user explicitly authorized:

- one bounded receive-only MQTT and position activation;
- temporary Authorization and MQTT credentials in owned IP-Symcon Core
  instances; and
- mandatory cleanup after the pilot.

No restart, OAuth action, mower command, schedule change, module update or
activation retry was authorized or performed.

## 3. Fresh Preflight

The first preflight was otherwise valid but found only 562 seconds of token
horizon. Activation therefore remained blocked.

No manual token action was performed. After passive automatic token rotation,
the final immediate preflight established:

```text
installed commit:       405fd24b5450c909c35e038a12bd69378d33deb6
repository branch:      main
repository clean/valid: yes / yes
token horizon:          3260 seconds
required horizon:       1200 seconds
REST operational:       yes
MQTT and position:      disabled
Core credentials:       absent
contracts unchanged:    yes
```

All variable identities, five existing Archive logging assignments, command
evidence, topology and exact-topic subscription contracts matched.

## 4. Single Activation

The established activation probe recomputed all preconditions immediately
before mutation, enabled MQTT shadow and position diagnostics and performed
exactly one Account `ApplyChanges`.

```text
activation attempts: 1
ApplyChanges calls:  1
activation retries:  0
cleanup attempts:    0
```

The synchronous result was the accepted asynchronous state
`ReconnectScheduled`. No second activation was attempted.

## 5. Stable Read-Only Verification

The delayed MCP observation passed with separate channel checks:

```text
transportError: null
executionError: null
truncated:      false
```

Stable state:

```text
pilot session:          7
lifecycle:              ShadowActive
MQTT / WebSocket:       102 / 102
closure state:          Active
session incidents:      0
open incident:          none
accepted message delta: 1
position samples:       0
```

No position sample is expected while no location message has arrived. This is
not an activation failure.

## 6. Observation Window

```text
start:     2026-08-20 11:56:57 CEST
hard stop: 2026-08-23 11:56:57 CEST
duration:  72 hours
```

The window spans naturally scheduled jobs from multiple private areas without
publishing their labels or times. It can correlate app-observed wet-delay
timing with REST state, retained MQTT state hints, task percentage and later
position ingress.

## 7. Retention Limitation

The installed MQTT parser retains only an allowlisted diagnostic projection.
It currently includes state, battery, mowing percentage and position fields,
but not:

```text
currentMowBoundary
partitionIds
subtotalArea
mowingWeekArea
arbitrary rain, wetness or delay-reason fields
```

Consequently, this pilot can establish whether and when mowing or movement
begins after the app-visible delay. It cannot by itself prove the delay reason
or map activity to a configured area.

The active pilot must not receive an implementation or module update. An
additive, bounded task/area diagnostic extension belongs after closure and
requires its own publication and disabled-rollout gates.

## 8. Preserved Boundaries

```text
public mower-state authority: REST
MQTT direction:               receive-only
MQTT command path:            absent
forced Start:                 prohibited
public variables:             unchanged
Archive identities:           unchanged
```

Credentials may exist only inside the owned Core instances while the pilot is
active. Values are never emitted into evidence.

## 9. Closure Contract

Automatic closure remains required at or before the hard stop. Closure must:

- disable MQTT and position diagnostics;
- deactivate the WebSocket and MQTT Core chain;
- remove Authorization, MQTT username and MQTT password;
- retain only bounded aggregate evidence; and
- pass immediate and delayed credential-free readback.

A transport incident may close the pilot earlier under the accepted incident
policy. Manual cleanup remains the fallback if automatic closure cannot be
proven.

## 10. Architecture Decisions

### AD-NAV-1349: Wait for passive token rotation

**Decision:** Do not weaken the 1200-second activation horizon.

**Rationale:** The normal module rotation supplied a safe window without OAuth
or operator intervention.

### AD-NAV-1350: Observe wet delay without actuation

**Decision:** Use only naturally emitted MQTT and REST evidence.

**Rationale:** Generic Start has no supported weather-override contract.

### AD-NAV-1351: Reuse the accepted incident reducer and closure

**Decision:** Run the existing 72-hour pilot contract unchanged.

**Rationale:** Its recovery, deadline and credential-cleanup behavior already
passed a complete live cycle.

### AD-NAV-1352: Do not change retention during the active pilot

**Decision:** Accept the present state/percentage/position evidence boundary
and defer task/area fields.

**Rationale:** Updating an observed runtime would invalidate the session and
introduce a restart or migration boundary.

## 11. Decision And Next Step

**Activation: PASS.**

**Stable receive-only transport: PASS.**

**Wet-delay or task evidence: observation pending.**

The next work is read-only checkpointing of session 7. Final evaluation must
separate weather-delay evidence, per-area task fields, position ingress and
transport health, followed by automatic or manual cleanup proof.
