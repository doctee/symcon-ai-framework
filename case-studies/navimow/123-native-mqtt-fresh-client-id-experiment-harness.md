# 123 Native MQTT Fresh-Client-ID Experiment Harness

**Case study:** Navimow native IP-Symcon module
**Status:** Private harness implemented and validated offline; publication and
live gates closed
**Date:** 2026-07-28
**Scope:** Implement the reversible one-variable experiment selected in step
122 without modifying the canonical distribution

## 1. Purpose

Step 122 selected one fresh MQTT client ID as the narrowest next experiment
after a healthy native connection produced zero Receiver ingress.

This step implements:

1. a reproducible private patch against the exact published Account module;
2. one temporary experimental Connect entry point;
3. one ownership-restoring cleanup entry point;
4. a synthetic offline lifecycle regression;
5. one bounded, still unauthorized live harness;
6. deterministic source and candidate hashes.

No canonical productive source, standalone module repository, Symcon
installation, broker connection or mower state was changed.

## 2. Private Artifact Set

The implementation resides only below:

```text
private/navimow-capture/fresh-client-id-experiment/
```

Files:

| File | Purpose |
|---|---|
| `account-module.patch` | Reproducible temporary Account-module delta |
| `offline-test.php` | Synthetic lifecycle, isolation and restoration tests |
| `live-one-shot.php` | Bounded future Symcon execution with `finally` cleanup |
| `validate.sh` | Patch, syntax, test, PHPCS, PHPStan and safety checks |
| `manifest.json` | Hashes, call counts and closed authorization gates |

The directory is ignored by Git and contains no real credential, endpoint,
topic, device identity, client ID or ObjectID.

## 3. Exact Patch Baseline

The local standalone publication clone was clean at:

```text
commit:
046529c518feefb15a51bd2f1c404401b3a7f474

subject:
feat: expose bounded MQTT Receiver diagnostics
```

The canonical and standalone Account modules were byte-equal:

```text
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a
```

Applying the private patch changes exactly:

```text
NavimowAccount/module.php
```

Patched candidate hash:

```text
04a69a573af052551e6e8202d4dd1057eeac063ef01128b9b52f4f89cc8aba2c
```

No generated candidate copy is retained outside the private harness. Validation
creates and removes a temporary complete distribution copy.

## 4. Temporary Account Entry Points

The patch adds exactly two public methods required for Symcon wrappers:

```text
ConnectMqttShadowWithFreshClientIdForExperiment()
RestoreMqttAfterFreshClientIdExperiment()
```

They are not form actions, productive APIs or release candidates.

### 4.1 Experimental Connect

The Connect method:

1. acquires the existing MQTT lifecycle semaphore;
2. requires a usable access token;
3. requires enabled and valid owned topology;
4. requires an inactive, header-empty and credential-empty baseline;
5. requires the retained native Client ID to equal the owned stable ID;
6. creates one random run-specific client identity;
7. configures the same credentials, keepalive and exact subscriptions;
8. applies MQTT changes before WebSocket activation;
9. validates the inactive transport shape against the fresh ID;
10. activates the WebSocket exactly once;
11. returns only a fixed non-private result string.

It does not rewrite:

- `MqttClientIdentity`;
- `MqttOwnershipRegistry`;
- Receiver pairing;
- subscriptions;
- REST state;
- Device variables.

### 4.2 Restoration

The Restore method deliberately does not call the normal owned Disconnect,
because ownership is expected to be temporarily invalid while the fresh ID is
active.

It:

1. revalidates the retained topology and pairing;
2. derives the stable Client ID from the unchanged Account attribute;
3. deactivates the WebSocket;
4. clears the authorization header and MQTT credentials;
5. restores the stable Client ID;
6. reapplies the exact keepalive and subscriptions;
7. rebuilds and validates the original ownership shape;
8. clears MQTT shadow and pending reconciliation state;
9. stops MQTT timers;
10. leaves the lifecycle disconnected or disabled.

The original adoption timestamp is retained.

## 5. Expected Ownership Mismatch

During the active fresh-ID window:

```text
ValidateMqttShadowConfiguration:
configuration-invalid
```

This is intentional. The experiment changes the native Client ID while leaving
the productive ownership record untouched.

Consequences:

- Receiver `receiveCalls` remains the primary result;
- a forwarded envelope may receive Account result `pairing-rejected`;
- Account acceptance is not required for this experiment;
- no private shadow or targeted REST reconciliation should be created;
- successful Receiver ingress still proves native child delivery.

## 6. Offline Regression

The synthetic test first establishes a realistic retained baseline through:

```text
adopt -> normal synthetic connect -> normal synthetic disconnect
```

It then verifies the experiment.

### 6.1 Positive path

Verified:

- exactly one credential retrieval;
- exactly one WebSocket activation;
- fresh ID differs from stable ID;
- expected Client ID shape;
- WebSocket active after Connect;
- synthetic credentials configured only in Core configuration;
- persistent `MqttClientIdentity` unchanged;
- persistent ownership byte-equal during the active window;
- ownership mismatch explicitly observable.

### 6.2 Restore path

Verified:

- WebSocket inactive;
- headers empty;
- MQTT username and password empty;
- stable Client ID restored;
- ownership valid and `ready`;
- persistent client identity unchanged;
- ownership registry byte-equal to baseline.

### 6.3 Failure path

A synthetic credential-endpoint failure verifies:

- no WebSocket activation;
- automatic internal restoration;
- credentials empty;
- stable Client ID restored;
- no second connection attempt.

## 7. Future Live Harness

`live-one-shot.php` remains unauthorized and unexecuted.

Its fixed sequence is:

1. locate exactly one Account, Device and Receiver;
2. verify the retained MQTT/WebSocket topology and module GUIDs;
3. require Account connected, no reauthentication and usable token;
4. require MQTT disabled, WebSocket inactive and credential slots empty;
5. require four exact QoS-0 subscriptions without wildcards;
6. retain the stable Client ID only in local script memory;
7. capture Receiver baseline counters;
8. enable the experimental shadow once;
9. invoke the fresh-ID Connect wrapper exactly once;
10. verify the configured Client ID differs without outputting either value;
11. observe Core state and Receiver counter deltas every two seconds;
12. stop early after the first Receiver ingress;
13. otherwise stop before the 165-second observation cutoff;
14. invoke Restore exactly once in `finally`;
15. disable the experiment;
16. validate the credential-empty final shape.

Hard deadline:

```text
180 seconds
```

Cleanup reserve:

```text
at least 15 seconds
```

## 8. Emergency Cleanup

The live harness retains the original stable Client ID only in memory.

If the module Restore wrapper throws or returns failure, the harness directly:

1. deactivates the retained WebSocket;
2. clears its authorization header;
3. clears MQTT username and password;
4. restores the captured stable Client ID;
5. applies both Core configurations;
6. disables the Account experiment.

The emergency path never reconnects. Its use is reported only as a boolean.

If even this cleanup fails, the run is a safety failure and no further MQTT
test is permitted.

## 9. Evidence Projection

The live output is bounded to:

- relative milliseconds;
- Core status codes;
- WebSocket active boolean;
- Receiver receive and forward deltas;
- fixed Receiver result codes;
- Connect and Restore call counts;
- fresh-ID-applied boolean;
- cleanup booleans;
- emergency-cleanup boolean;
- final pass and fixed error code.

No raw exception message is emitted because it could contain private transport
context.

The output contains no:

- credential or token;
- URL or hostname;
- topic;
- payload;
- device identity;
- client ID value;
- installation ObjectID;
- garden geometry.

## 10. Static Safety Contract

The frozen harness contains:

```text
fresh-ID Connect call sites: 1
Restore call sites:          1
MQTT publish call sites:     0
mower-command call sites:    0
module reload call sites:    0
instance create/delete:      0
```

`MC_ReloadModule()` is not used.

The future experiment requires a temporary module update because the two
wrappers do not exist in productive `main`. That update remains a separately
authorized gate.

## 11. Validation Result

Executed:

```text
sh private/navimow-capture/fresh-client-id-experiment/validate.sh
```

Passed:

- deterministic patch application;
- candidate PHP syntax;
- offline-test PHP syntax;
- live-harness PHP syntax;
- synthetic positive path;
- exact temporary ownership mismatch;
- exact restoration;
- credential-failure rollback;
- PHPCS;
- PHPStan;
- fixed Connect and Restore call counts;
- no MQTT publish;
- no mower command;
- no module reload;
- no instance creation or deletion;
- private-material scan.

`git diff --check` also passed for the new public documentation and private
harness sources.

## 12. Limitations

The offline test cannot prove:

- broker acceptance of the fresh Client ID;
- the native client's undocumented session behavior;
- a subscription acknowledgement;
- real Receiver ingress;
- whether retained Core instance state also contributes to the gap.

Those questions require the separately authorized one-shot live experiment.

## 13. Architecture Decisions

### AD-NAV-448: Patch only the temporary publication candidate

**Decision:** Keep the diagnostic methods in a private patch and leave the
canonical distribution unchanged.

**Reason:** One-use transport diagnosis is not productive module behavior.

### AD-NAV-449: Preserve ownership during the active mismatch

**Decision:** Do not rewrite persistent identity or ownership for Account
acceptance.

**Reason:** Receiver ingress alone tests the selected transport variable.

### AD-NAV-450: Retain the stable ID in live-script memory

**Decision:** Capture the stable value transiently for emergency cleanup but
never output or persist it in evidence.

**Reason:** Cleanup must remain possible even if the temporary module Restore
path fails.

### AD-NAV-451: Restore after every Connect invocation

**Decision:** Invoke Restore in `finally` whenever the Connect wrapper was
called, regardless of its return value.

**Reason:** An ambiguous wrapper outcome must not leave a live or mismatched
transport.

### AD-NAV-452: Stop observation after first ingress

**Decision:** End the active window once `receiveCalls` increases.

**Reason:** The earliest Receiver boundary fully answers this experiment and
minimizes credential exposure.

## 14. Gate Decision

Private implementation and offline validation:

```text
PASS
```

Canonical distribution:

```text
UNCHANGED
```

Temporary branch publication:

```text
NOT AUTHORIZED
```

Symcon Module Control update:

```text
NOT AUTHORIZED
```

Live broker connection:

```text
NOT AUTHORIZED
```

Mower commands and MQTT publish:

```text
PROHIBITED
```

## 15. Recommended Next Step

Create:

```text
124-native-mqtt-fresh-client-id-publication-and-live-test-plan.md
```

That step should define:

- the exact temporary branch lifecycle;
- pre-update and post-update compatibility baselines;
- one update without `MC_ReloadModule()`;
- the supervised one-shot live authorization text;
- evidence closure;
- immediate restoration to standalone `main`;
- deletion of the temporary branch only after byte-equality and Symcon
  compatibility are proven.
