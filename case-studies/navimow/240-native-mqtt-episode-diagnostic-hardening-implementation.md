# 240 Native MQTT Episode Diagnostic Hardening Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Local implementation and offline validation complete; publication
and live gates remain closed

**Date:** 2026-07-31

**Scope:** Implement the bounded native Core status and episode evidence from
step 239 without changing transport recovery or device behavior

## 1. Purpose

Step 239 designed earlier native Core status evidence and separate timing for
transport detection, reconnect, Core readiness and lifecycle recovery. This
step implements that design locally and validates the result without accessing
Symcon, OAuth, MQTT or the mower.

## 2. Productive Delta

Changed productive distribution file:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

The Account now:

- registers `IM_CHANGESTATUS` for the owned MQTT and WebSocket Core instances
  only while an actual pilot session is active;
- removes stale or disabled status registrations idempotently;
- reads current Core state through `IPS_GetInstance()`;
- ignores raw, undocumented `MessageSink()` data;
- stores bounded, identity-free Core transition evidence;
- snapshots existing MQTT ingress and REST health when an episode opens;
- records scheduled reconnect, actual reconnect start, Core readiness and
  later lifecycle confirmation separately;
- exposes pilot diagnostics format version 2;
- migrates retained version-1 episode evidence as explicit `legacy` data.

No form, locale, property, variable, action or module metadata changed.

## 3. Message Registration and Callback

`MessageSink()` preserves the existing kernel-start branch and adds one
strictly filtered branch:

```text
message: IM_CHANGESTATUS
sender:  owned MQTT Client or owned WebSocket Client only
```

The callback:

1. verifies enabled pilot state, topology and ownership;
2. acquires the existing lifecycle semaphore with the bounded MQTT timeout;
3. reads both current Core statuses;
4. reduces the sender to `mqtt` or `websocket`;
5. appends one sanitized transition;
6. releases the semaphore.

It does not connect, disconnect, schedule recovery, rotate credentials, call
REST or operate the mower. Lock contention increments only the bounded
`coreStatusEventDrops` diagnostic counter.

SDK methods are called through verified callable names. This keeps the
productive file compatible with the real IP-Symcon base class while avoiding a
case-study-external change to the shared static-analysis stub.

## 4. Kernelstart Ordering Protection

The first complete gate identified one integration defect in the initial local
implementation: status-registration reconciliation attempted an additional
Core configuration read before the established kernel Core-readiness barrier.

The final implementation gives existing kernel reconciliation absolute
precedence:

```text
kernel reconciliation pending or required
    -> leave status registrations untouched
    -> perform no topology or Core configuration read
```

The existing transient-readiness fixture now proves that no registration read
bypasses the durable startup barrier. Registration reconciliation resumes only
after kernel reconciliation no longer has precedence.

## 5. Registry Version 2

`MqttPilotObservationRegistry` and `GetMqttPilotDiagnostics()` now use format
version 2.

New session-level evidence:

```text
coreTransitionSequence
coreTransitions (maximum 32)
coreStatusEventDrops
```

Each transition contains fixed fields only:

```text
sequence
sessionSequence
observedAt
senderRole
mqttStatus
webSocketStatus
classification
openEpisodeSequence
```

Duplicate equal-status callbacks from the same role and second collapse into
one entry.

## 6. Expanded Episode Evidence

At the established unexpected-disconnect point, the Account now retains:

- the nearest error-class Core transition from the preceding 120 seconds;
- Core-fault-to-lifecycle-detection lead time;
- at most eight relevant Core transitions;
- last MQTT ingress time, presence and age;
- last REST success time, presence and age;
- REST connection state;
- nearest prior credential rotation and separation;
- reconnect schedule time and due time.

Later lifecycle stages add:

- first actual reconnect start;
- first observed Core readiness and its source;
- lifecycle recovery confirmation;
- Core-ready-to-confirmation lag.

The episode completeness is one of:

```text
complete
polling-fallback
partial
legacy
```

The original `durationSeconds` remains detection-to-confirmed-recovery
lifecycle duration. It is not reinterpreted as exact network outage duration.

## 7. Migration and Privacy

Productive writes now canonicalize checkpoints, episodes, rotations and Core
transitions to fixed nested schemas. Unknown fields are removed from storage,
not merely hidden by the read-only projection.

Version-1 evidence:

- retains its original valid timestamps, statuses and outcomes;
- receives zero values only for absent new timestamps;
- is marked `diagnosticCompleteness: legacy`;
- never receives fabricated transition or transport timing;
- remains within the established 32/32/64 bounds.

The new evidence contains no:

- ObjectID;
- MQTT topic or payload;
- device ID or serial number;
- endpoint or hostname;
- Authorization header;
- username, password or token;
- raw `MessageSink()` data.

## 8. Test Delta

Changed test and private compatibility files:

```text
case-studies/navimow/tests/harness/SymconRuntime.php
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
private/navimow-capture/native-mqtt-private-pilot/PilotHarness.php
private/navimow-capture/native-mqtt-private-pilot/symcon-readonly-probe.php
```

The runtime fake now models:

```text
RegisterMessage()
UnregisterMessage()
GetMessageList()
IM_CHANGESTATUS
```

Focused tests prove:

- disabled and staged operation have no pilot status registrations;
- active operation has exactly two owned status registrations;
- repeated registration is idempotent;
- disconnect removes both while retaining the kernel-start registration;
- unrelated senders and raw callback data are ignored;
- valid status callbacks are sanitized;
- duplicate transitions collapse;
- 33 transitions retain the newest 32;
- episode transition history retains at most eight;
- Core fault lead time and MQTT/REST ages are correct;
- reconnect, readiness and confirmation times remain distinct;
- missing callbacks produce `polling-fallback`;
- version-1 records migrate as `legacy`;
- unknown nested fields are removed;
- all six Account and eight Device variables remain unchanged.

The private pilot harness accepts historical v1 and candidate v2 projections.
For v2 it additionally enforces the 32-entry Core-transition bound.

## 9. Validation Result

Focused checks:

```text
PHP syntax:                            PASS
MQTT pilot checkpoint tests:          PASS
MQTT transport lifecycle tests:       PASS
private pilot harness tests:          PASS
```

Complete gate:

```text
MQTT fixtures:                         PASS
REST client and authentication:        PASS
MQTT envelope and payload parsing:     PASS
native receive probe:                  PASS
Receiver and Account ingestion:        PASS
shadow diagnostics and reconciliation: PASS
pilot checkpoint diagnostics:          PASS
transport lifecycle:                   PASS
distribution structure:                PASS
PHPCS:                                  PASS
PHPStan:                                PASS
```

Final result:

```text
Navimow MQTT shadow offline checks passed.
```

## 10. Preserved Contracts

The implementation preserves:

- REST as sole public-state authority;
- MQTT as receive-only hint and diagnostic transport;
- reconnect delays `[60, 300, 900]`;
- exactly three bounded reconnect attempts;
- no retry for authentication or configuration errors;
- the one-episode pilot acceptance threshold;
- six Account and eight Device variables;
- all existing variable Idents and profiles;
- all Archive Control logging and aggregation;
- default-disabled MQTT;
- cleanup and credential-removal behavior;
- `GetMqttDiagnostics()` format and shape.

No new public helper or reusable framework abstraction was introduced.

## 11. Architecture Decisions

### AD-NAV-876: Preserve kernel reconciliation ahead of status registration

Core status registration performs no topology read while kernel reconciliation
is pending or required. Existing startup readiness remains authoritative.

### AD-NAV-877: Canonicalize nested pilot evidence on write

Fixed-schema storage prevents unsupported or sensitive nested fields from
surviving a productive registry update.

### AD-NAV-878: Use explicit evidence completeness

`complete`, `polling-fallback`, `partial` and `legacy` distinguish actual
evidence quality without fabricating missing timestamps.

### AD-NAV-879: Keep status callbacks observational

Native Core messages improve timing only. Existing lifecycle polling remains
the sole owner of recovery and retry decisions.

### AD-NAV-880: Keep the private harness migration-compatible

The harness accepts historical format 1 and candidate format 2 while enforcing
the additional v2 bounds. This supports a disabled update and read-only
comparison before any future activation.

## 12. Live-State Boundary

This step did not:

- publish or commit the candidate;
- update or reload an IP-Symcon module;
- access a live Symcon installation;
- read or write OAuth or MQTT credentials;
- activate MQTT;
- connect or disconnect a live transport;
- publish MQTT data;
- command the mower;
- change live variables, logging or objects;
- restart a service.

The cleaned live MQTT state from step 237 remains untouched.

## 13. Next Gate

The local implementation is ready for a publication and disabled-update plan,
not for direct publication or activation.

The next SAEF step is:

```text
241-native-mqtt-episode-diagnostic-hardening-publication-and-symcon-test-plan.md
```

It must freeze the exact candidate files, validate metadata, publish only after
explicit authorization, update Symcon with MQTT disabled, migrate and inspect
retained v1 evidence read-only, and keep any MQTT activation behind a later
separate gate.
