# 244 Native MQTT Episode Diagnostic Hardening Disabled Symcon Update

**Case study:** Navimow native IP-Symcon module

**Status:** Disabled update and delayed read-only verification passed; MQTT
staging and activation remain closed

**Date:** 2026-07-31

**Scope:** Execute Gate C from steps 241 and 243 against the exact published
episode-diagnostic-hardening commit

## 1. Purpose

Steps 242 and 243 established:

```text
published commit:     79686e52f0bbaad77d37b9cd6e4b367797d96f2e
metadata conformance: PASS
```

The user separately authorized:

```text
Symcon-Update auf die MQTT-Episoden-Diagnosehärtung mit deaktiviertem
MQTT freigegeben.
```

This step:

1. established two current disabled and credential-free live baselines;
2. retained private rollback and v1 pilot-history evidence;
3. executed exactly one supported Module Control update;
4. verified the v2 diagnostic projection and retained history immediately;
5. repeated the complete safety check after more than 70 seconds;
6. ran the complete local SAEF regression.

No MQTT activation, OAuth action, service restart or mower command occurred.

## 2. MCP Result Contract

Every PHP execution used the bounded structured MCP channel. Success required:

```text
transportError: null
executionError: null
truncated:      false
```

All five calls passed these conditions:

| Purpose | Result |
|---|---|
| detailed preflight | PASS |
| independent preflight confirmation | PASS |
| single module update | PASS |
| immediate verification | PASS |
| delayed verification | PASS |

Transport success was never treated as PHP execution success by itself.

## 3. Pre-Update Gate

Two current read-only snapshots established:

```text
installed:            main@793249ec
repository:           clean and valid
module statuses:      102 / 102 / 102 / 102
MQTT lifecycle:       Disabled
MQTT/WebSocket:       104 / 104
WebSocket Active:     false
Authorization:        absent
MQTT user/password:   absent
REST:                 connected and operational
reauth required:      false
variables:            14/14
archive logging:      5/5 and queryable
pending reconnect:    none
Core observation:     none
```

The five compatibility hashes matched the previous installed baseline:

| Contract | Result |
|---|---|
| variable identity | EQUAL |
| archive logging and aggregation | EQUAL |
| command evidence | EQUAL |
| native transport topology | EQUAL |
| exact-topic subscriptions | EQUAL |

The two reads of the retained pilot projection were byte-equal.

## 4. Private Rollback Baseline

Before mutation, private evidence retained:

- installed branch and commit;
- previous Account source hash and Git blob;
- all five compatibility hashes;
- complete format-version-1 pilot evidence;
- module, transport, REST and credential-presence state;
- operation counters initialized to zero.

The retained v1 history contained:

```text
session sequence: 1
checkpoints:      2
episodes:         2
rotations:        15
open episode:     none
```

The previous commit is evidence, not an automatic rollback instruction.

## 5. Authorized Mutation

Executed exactly:

```text
MC_UpdateModule(): 1
result:            true
```

Not executed:

```text
MC_ReloadModule():        0
explicit ApplyChanges():  0
service restart:          0
```

The supported update moved the installation directly from:

```text
main@793249ec
```

to:

```text
main@79686e52
```

The repository remained clean and valid. No update retry occurred.

## 6. Immediate Verification

All four productive module instances remained compatible:

| Module | Status |
|---|---:|
| Account | `102` |
| Configurator | `102` |
| Device | `102` |
| MQTT Receiver | `102` |

All five compatibility hashes remained equal. Consequently:

- all six Account and eight Device variables retained identity and metadata;
- the five user-enabled archive loggings remained attached and queryable;
- command evidence was unchanged;
- transport topology and exact-topic subscriptions were unchanged.

REST remained connected, operational and authoritative.

## 7. Version-2 Diagnostic Projection

`GetMqttPilotDiagnostics()` now reports:

```text
formatVersion:         2
featureEnabled:        false
active:                false
sessionSequence:       1
checkpoints:           2
episodes:              2
rotations:             15
coreTransitions:       0
coreStatusEventDrops:  0
openEpisode:           null
```

Two consecutive reads were byte-equal.

Each historical v1 episode retained:

- sequence and session sequence;
- detection and recovery timestamps;
- detection source;
- MQTT and WebSocket status;
- reconnect attempts;
- duration and outcome;
- rotation overlap and kernel-epoch classification.

Each projected episode is explicitly:

```text
diagnosticCompleteness: legacy
new timestamps:         0
coreReadySource:        unknown
MQTT/REST presence:     false
coreTransitions:        []
```

No missing timestamp or Core event was fabricated. The read-only projection
did not force a registry rewrite or start a pilot session.

## 8. Delayed Verification

The delayed verification ran 164 seconds after the immediate snapshot, beyond
the required 70-second minimum.

It proved:

- installed commit still `79686e52`;
- repository still clean and valid;
- all four modules still status `102`;
- MQTT and WebSocket still inactive at `104/104`;
- Authorization, MQTT username and MQTT password still absent;
- lifecycle still `Disabled`;
- no reconnect or Core observation pending;
- all transport, reconnect, ingress and diagnostic counters unchanged;
- pilot projection hash byte-equal;
- no Core transition or drop added;
- all variable and archive hashes unchanged;
- REST still connected and operational.

## 9. Local Regression

The complete repository gate passed:

```text
make check: PASS
```

This included Navimow syntax, REST and command behavior, MQTT parsing,
transport lifecycle, pilot diagnostics, distribution validation, PHPCS and
PHPStan, alongside the framework-wide regression suite.

## 10. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-episode-diagnostic-hardening-disabled-symcon-update/
  pre-update-baseline.json
  evidence-closure.json
```

The public report contains no ObjectID, credential, topic, payload, hostname,
device identity or private installation path.

## 11. Safety Result

```text
installed module: main@79686e52
MQTT:             disabled
credentials:      absent from native MQTT Core instances
REST:             operational and authoritative
module update:    exactly one
reload/restart:   none
mower commands:   none
```

Publication and installation do not authorize MQTT staging or activation.

## 12. Architecture Decisions

### AD-NAV-896: Require two current pre-update snapshots

Historical cleanup evidence is insufficient. The live gate used a detailed
snapshot and an independent confirmation before mutation.

### AD-NAV-897: Preserve one supported update without reload

The exact published commit was installed with one `MC_UpdateModule()` call.
No reload, explicit `ApplyChanges()` or service restart was used.

### AD-NAV-898: Verify migration semantically and byte-stably

Every historical v1 episode field was checked in the v2 projection while new
unknown evidence remained zero, false, unknown or empty. Repeated projection
hashes prove read-only stability.

### AD-NAV-899: Preserve user-owned archive contracts

The variable and Archive Control hashes were compared before, immediately
after and after the delayed interval. All five user-enabled loggings remained
attached and queryable.

### AD-NAV-900: Keep activation behind a new gate

Successful installation does not authorize MQTT credentials, staging,
activation, restart or device action.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B metadata conformance | PASS |
| current two-snapshot safety baseline | PASS |
| private rollback evidence | PASS |
| exactly one supported module update | PASS |
| installed target commit | PASS |
| module compatibility | PASS |
| variable and archive preservation | PASS |
| retained v1-to-v2 history projection | PASS |
| immediate verification | PASS |
| delayed disabled verification | PASS |
| complete local regression | PASS |
| Gate C disabled Symcon update | PASS |
| inactive MQTT staging | CLOSED |
| MQTT activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | NOT PERFORMED |

## 14. Next Step

The next SAEF step should be:

```text
245-native-mqtt-episode-diagnostic-hardening-inactive-preflight.md
```

It should collect two further read-only disabled snapshots, validate the
updated v2 projection through the private pilot harness and initialize the
exact installed commit as the next inactive observation baseline.

It must not activate MQTT, retrieve MQTT credentials or restart Symcon without
separate explicit authorization.
