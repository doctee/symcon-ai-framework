# 237 Native MQTT Pilot Checkpoint Failure and Safe Closure

**Case study:** Navimow native IP-Symcon module

**Status:** Pilot stopped by policy; mandatory cleanup passed

**Date:** 2026-07-30

**Scope:** Evaluate the first bounded native checkpoint and close the active
receive-only pilot safely

## 1. Checkpoint Result

The bounded read-only projection passed its transport, PHP execution,
truncation, repository, topology, variable and Archive Control contracts.
REST remained operational and authoritative. MQTT was receive-only and the
Account lifecycle had returned to `ShadowActive/healthy`.

The native diagnostics retained two exact five-hour checkpoints. They also
recorded two separate unexpected transport episodes:

| Episode | Duration | Recovery | Exhausted |
| --- | ---: | --- | --- |
| 1 | 121 seconds | automatic | no |
| 2 | 120 seconds | automatic | no |

Both episodes recovered within one reconnect attempt. No configuration,
authentication, ownership, subscription, archive or REST failure occurred.

## 2. Gate Decision

The private-pilot policy permits at most one recovered unexpected transport
episode. The second episode therefore stopped the pilot even though the
transport was healthy again when inspected.

```text
classification:       FAIL
completed cycles:     1
credential rotations: 15
transport episodes:   2
stop reason:          multiple-transport-episodes
```

The result is a pilot reliability finding, not a cleanup or public-state
failure. The 48-hour minimum and two-cycle acceptance criteria were not met.

## 3. Mandatory Cleanup

The already armed cleanup executed exactly:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
```

Immediate and 208-second delayed read-only verification proved:

- MQTT and WebSocket inactive;
- Authorization headers absent;
- MQTT username and password absent;
- lifecycle `Disabled`;
- reconnect state cleared;
- REST authentication and polling operational;
- variables, actions, topology and Archive Control contracts unchanged.

No MQTT publication, mower command, service restart, object creation or object
deletion occurred.

## 4. Harness Reconciliation

The original private harness still expected disabled pilot diagnostics to be
empty and relied on external snapshot spacing. Native diagnostics now provide
the authoritative five-hour evidence chain and intentionally retain bounded
history after a session closes.

The private harness and its read-only probe were aligned with that published
contract. Focused private regression tests now cover native checkpoint
continuity and retained closed diagnostics. No productive module code or
public API changed.

## 5. Evidence

Exact machine-readable evidence remains below:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-activation/
```

The public report contains no ObjectID, credential, MQTT topic, payload,
hostname, coordinate or private device identity.

## 6. Decision

The first diagnostic 72-hour pilot is closed as `FAIL` with complete cleanup.
Do not reactivate it unchanged. Analyze the two episode timelines and their
relation to credential rotation before proposing another pilot or changing
the one-episode policy.
