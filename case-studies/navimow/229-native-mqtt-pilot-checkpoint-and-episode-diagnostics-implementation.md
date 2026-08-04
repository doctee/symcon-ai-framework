# 229 Native MQTT Pilot Checkpoint and Episode Diagnostics Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation complete; publication, Symcon update and
live MQTT use remain closed
**Date:** 2026-07-30
**Scope:** Implement the bounded native checkpoint and episode contract from
step 228

## 1. Purpose

Step 228 replaced the unproven external checkpoint dependency with
restart-safe Account-owned diagnostics. This step implements that design
locally and validates it without accessing Symcon or the mower.

## 2. Productive Delta

Changed productive distribution files:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/distribution/NavimowAccount/form.json
case-studies/navimow/distribution/NavimowAccount/locale.json
```

The Account now registers:

```text
MqttPilotObservationRegistry
MqttPilotCheckpoint
GetMqttPilotDiagnostics()
ProcessMqttPilotCheckpoint()
```

The form adds one read-only action:

```text
Show MQTT Pilot Diagnostics
```

No configuration property, variable or action variable was added.

## 3. Checkpoint Implementation

`EnableMqttShadow` alone leaves inactive staging timerless. The first validated
connection attempt starts one internal observation session and schedules the
first checkpoint after 18,000 seconds. `ApplyChanges()` resumes an already
active persisted session after a restart.

Each checkpoint records:

- its absolute scheduled time;
- actual execution and delay;
- current lifecycle/configuration classification;
- last retained MQTT and WebSocket statuses;
- REST connection state and token horizon;
- selected connection, recovery, rotation and ingress counters.

After an overdue execution, whole missed intervals are skipped. One actual
entry is written and the next future absolute slot is scheduled.

## 4. Episode Implementation

Both existing unexpected-disconnect branches now open one episode:

```text
lifecycle-observation
kernel-reconciliation
```

The existing healthy observation closes it as `recovered`. Reconnect
exhaustion closes it as `reconnect-exhausted`. Disabling MQTT closes any open
episode as `disabled`.

Credential rotation:

- appends a bounded rotation summary;
- marks an overlapping open episode;
- captures the current retry count before existing lifecycle reset.

No reconnect delay, attempt bound, error classification or transport action
was changed.

## 5. Persistence and Privacy

The writer canonicalizes every registry update to a fixed top-level schema and
enforces:

```text
32 checkpoints
32 completed episodes
64 rotations
1 open episode
```

The public projection independently applies fixed output keys and bounds.
Unsupported stored keys and codes are not returned.

Excluded data remains:

- credentials and Authorization headers;
- MQTT topics and payloads;
- device identifiers and hashes;
- endpoints, hosts and ObjectIDs;
- position and geometry;
- private installation metadata.

Internal kernel start time is used only to derive
`kernelEpochChanged`; it is not exposed.

## 6. Compatibility Result

The implementation preserves:

- `GetMqttDiagnostics()` format version 2 and exact shape;
- REST authority over all public variables;
- six Account variables and eight Device variables;
- existing Archive Control settings;
- receive-only MQTT behavior;
- bounded three-attempt transient recovery;
- token rotation and Core-resume behavior;
- default-disabled MQTT.

The new timer is zero while MQTT is disabled.

## 7. Regression Coverage

Added:

```text
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
```

The test covers:

- disabled default and unchanged six-variable contract;
- enabled but inactive timerless staging;
- five-hour scheduling;
- read-only projection;
- restart with a 120-second overdue checkpoint;
- one-entry catch-up without replay or drift;
- episode duration and two used reconnect attempts;
- rotation overlap;
- kernel epoch change;
- fixed-schema sanitization;
- 32-entry read bound;
- disabled cleanup.

The test is part of:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
```

## 8. Validation Result

Local checks:

```text
PHP syntax:                         PASS
focused pilot checkpoint test:     PASS
existing shadow diagnostics:       PASS
existing transport lifecycle:      PASS
distribution validation:           PASS
PHPCS:                              PASS
PHPStan:                            PASS
```

Complete gate result:

```text
Navimow MQTT shadow offline checks passed.
```

## 9. Architecture Decisions

The implementation applies `AD-NAV-826` through `AD-NAV-831` without a new
public helper. The storage is implementation-specific and composes the
existing bounded Registry, Statistics and error-history patterns.

No reusable abstraction is introduced because only the Navimow Account owns
this evidence contract.

## 10. Live-State Boundary

This step did not:

- publish a module;
- update or reload Symcon;
- access OAuth or MQTT credentials;
- activate MQTT;
- start, pause, resume, dock or otherwise command the mower;
- create live objects;
- change logging.

The currently cleaned, disabled MQTT state is not altered by this local work.

## 11. Next Step

Create a publication and disabled-update plan for this exact implementation.
Only after publication validation and an explicitly authorized Symcon update
should the new read-only projection be inspected live. A new pilot activation
still requires a separate authorization and acceptance gate.
