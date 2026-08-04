# 233 Native MQTT Pilot Diagnostics Disabled Symcon Update

**Case study:** Navimow native IP-Symcon module

**Status:** Disabled update and delayed read-only verification passed; MQTT
staging and activation remain closed

**Date:** 2026-07-30

**Scope:** Execute Gate C from steps 230 and 232 against the exact published
pilot-diagnostic commit

## 1. Purpose

Steps 231 and 232 established:

```text
published commit:     793249ece1c0944192ea28dade7ecd2340a5135f
metadata conformance: PASS
```

The user separately authorized the disabled Symcon update. This step:

1. re-establishes the current live baseline read-only;
2. executes exactly one supported module update;
3. verifies the new pilot diagnostic API immediately;
4. repeats the disabled-state verification after 70 seconds;
5. proves retention of variables and Archive Control contracts.

No MQTT activation, OAuth action, service restart or mower command occurred.

## 2. MCP Result Contract

Every PHP execution used the bounded structured MCP channel. Success required:

```text
transportError: null
executionError: null
truncated:      false
```

These conditions passed for the pre-update probe, the single update and all
post-update probes.

## 3. Pre-Update Gate

The live installation was re-read instead of relying on historical evidence:

```text
installed:            main@3d223a9c
repository:           clean and valid
MQTT lifecycle:       Disabled
MQTT/WebSocket:       104/104
WebSocket Active:     false
Authorization:        absent
MQTT user/password:   absent
REST:                 connected and operational
reauth required:      false
variables:            14/14
archive logging:      5/5 and queryable
```

The topology and exact-topic subscription contract also passed. This satisfied
the disabled and credential-free update gate.

## 4. Authorized Mutation

Executed:

```text
MC_UpdateModule(): 1
result:            true
```

Not executed:

```text
MC_ReloadModule(): 0
ApplyChanges():    0
service restart:   0
```

The supported update moved the installation directly from `3d223a9c` to:

```text
main@793249ec
```

The repository remained clean and valid.

## 5. Immediate Verification

All four productive module instances were compatible:

| Module | Status |
|---|---:|
| Account | `102` |
| Configurator | `102` |
| Device | `102` |
| MQTT Receiver | `102` |

The five frozen compatibility hashes remained equal:

| Contract | Result |
|---|---|
| variable identity | EQUAL |
| archive logging and aggregation | EQUAL |
| command evidence | EQUAL |
| native transport topology | EQUAL |
| exact-topic subscriptions | EQUAL |

Consequently all six Account and eight Device variables retained their
identities. The five user-enabled archive loggings remained attached and
queryable.

## 6. Pilot Diagnostic Contract

Two consecutive `GetMqttPilotDiagnostics()` reads were byte-equal and returned:

```text
formatVersion:              1
featureEnabled:             false
active:                     false
sessionSequence:            0
startedAt:                  0
nextCheckpointAt:           0
checkpointIntervalSeconds:  18000
checkpoints:                []
episodes:                   []
rotations:                  []
openEpisode:                null
```

The arrays satisfy their configured bounds. No pilot session or diagnostic
timer activity was started by installation or by the read-only calls.

The existing `GetMqttDiagnostics()` format-version-2 contract remained
available, while REST remained the authoritative device-state source.

## 7. Delayed Verification

After 70 seconds, more than one MQTT lifecycle interval:

- MQTT and WebSocket were still inactive with status `104`;
- no Authorization header, MQTT username or MQTT password was present;
- lifecycle state remained `Disabled`;
- all connection, reconnect, receive and rotation counters were unchanged;
- no pilot session, checkpoint, episode or rotation was created;
- two further pilot-diagnostic reads were equal;
- all 14 variable and five archive contracts remained unchanged;
- REST remained connected and operational.

An initial auxiliary assertion reported false because PHP null coalescing
cannot distinguish a missing field from a present field whose value is
`null`. The returned diagnostic content was already correct. A corrected
read-only assertion using `array_key_exists()` passed. This was a probe defect,
not a productive module defect.

## 8. Private Evidence

Sanitized machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-disabled-symcon-update/
  evidence-closure.json
```

It contains commit bindings, hashes, counts, Boolean credential-presence
results, operation counts and bounded diagnostic projections. It contains no
ObjectID, topic, token, credential, hostname, payload, coordinate or private
device identity.

## 9. Safety Result

```text
installed module: main@793249ec
MQTT:             disabled
credentials:      absent from native MQTT Core instances
REST:             operational and authoritative
module update:    exactly one
reload/restart:   none
mower commands:   none
```

Publication and installation do not authorize MQTT activation.

## 10. Architecture Decisions

### AD-NAV-846: Re-establish the live gate before mutation

Historical cleanup evidence does not replace a current disabled and
credential-free readback.

### AD-NAV-847: Preserve the supported one-update path

The exact public commit is installed with one `MC_UpdateModule()` and without
reload or service restart.

### AD-NAV-848: Treat diagnostic reads as immutable projections

Immediate and delayed equal reads prove that the public pilot API does not
start a session or advance persisted evidence while MQTT is disabled.

### AD-NAV-849: Preserve user-owned Archive Control settings

Variable identity and archive contracts are compared by hash before and after
installation. The update introduces no diagnostic variable or logging entry.

### AD-NAV-850: Separate probe defects from productive defects

An assertion-language mistake is documented and corrected read-only. It does
not trigger a module update retry or a productive code change.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B metadata conformance | PASS |
| current pre-update safety baseline | PASS |
| exactly one supported module update | PASS |
| installed target commit | PASS |
| module compatibility | PASS |
| variable and archive preservation | PASS |
| immediate pilot diagnostics | PASS |
| delayed disabled verification | PASS |
| Gate C disabled Symcon update | PASS |
| inactive MQTT staging | CLOSED |
| MQTT activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | NOT PERFORMED |

## 12. Next Step

The next SAEF step should be:

```text
234-native-mqtt-pilot-diagnostics-inactive-preflight.md
```

It should perform two read-only, disabled and credential-free snapshots across
more than one lifecycle interval, verify the newly installed pilot API and
prepare the private observation harness. It must not activate MQTT or retrieve
MQTT credentials without a separate explicit authorization.
