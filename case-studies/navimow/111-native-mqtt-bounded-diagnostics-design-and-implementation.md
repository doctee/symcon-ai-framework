# 111 Native MQTT Bounded Diagnostics Design and Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation complete; publication, Symcon update and
receive retest remain blocked
**Date:** 2026-07-28
**Scope:** Add a privacy-safe read-only MQTT diagnostic contract required by
the inconclusive receive result from step 110

## 1. Purpose

Step 110 proved a healthy native WSS/MQTT transport but could not prove that
the productive Receiver accepted a message. The required evidence already
existed in private Account attributes, but no supported bounded readback
contract exposed it.

This step implements:

- one public read-only Account method;
- a fixed diagnostic schema;
- lifecycle and accepted-message evidence;
- bounded counters and timestamps;
- bounded error summary and private-shadow counts;
- strict normalization of malformed persistent state;
- a configuration-form action;
- exact fixture and privacy regressions.

It does not:

- publish or update the standalone module;
- change the live Symcon installation;
- enable MQTT shadow;
- retrieve credentials;
- connect to the broker;
- publish MQTT data;
- send a mower command;
- add or recreate a public variable;
- change Archive Control.

## 2. Public Contract

The new Account method is:

```text
GetMqttDiagnostics()
```

The generated Symcon wrapper is:

```text
NAVAC_GetMqttDiagnostics
```

The method returns bounded JSON:

```text
formatVersion
featureEnabled
configurationStatus
lifecycle
statistics
errors
shadow
```

It is read-only and performs no Core ApplyChanges, REST call, timer change,
attribute write or public-variable write.

## 3. Lifecycle Projection

The lifecycle section contains only:

```text
state
stateChangedAt
lastResult
lastResultAt
lastCoreStatus
observedAt
```

Allowed states are the implemented lifecycle vocabulary:

```text
Disabled
WaitingForAuthentication
WaitingForPairing
Ready
Configuring
Connecting
ShadowActive
Disconnected
ReauthenticationRequired
ConfigurationError
```

Missing values become `none` or `0`. A stored string outside the allowlist
becomes `unknown` and is never copied into the result.

`ShadowActive` together with an advancing accepted counter and receive
timestamp provides the evidence missing from step 110.

## 4. Statistics Projection

The fixed statistics section contains:

```text
connectionAttempts
received
accepted
rejected
reconciliationAttempts
comparisonMatches
comparisonMismatches
comparisonStale
lastConnectionAttemptAt
lastReceivedAt
lastReconciliationAt
lastComparisonAt
lastReconciliationResult
lastComparisonResult
```

Only nonnegative stored integers are returned. Missing, negative or
wrongly-typed values become `0`.

Result codes use explicit allowlists. Unknown strings become `unknown`;
missing values become `none`.

## 5. Error and Shadow Summary

The error projection contains only:

```text
count
latestReason
latestAt
```

At most the established 20 error-ring entries are inspected. Only known local
reason codes with positive timestamps count as valid.

The shadow projection contains counts only:

```text
trackedDeviceCount
pendingReconciliationCount
```

Both are capped at the established 64-device bound. No key, device identity,
state field or pending-entry content is returned.

## 6. Privacy Contract

The diagnostic result cannot contain:

- MQTT topic or payload;
- device or account identity;
- Receiver or Core ObjectID;
- WSS endpoint or host;
- authorization header;
- access or refresh token;
- MQTT username or password;
- MQTT client ID;
- ownership registry or hash;
- raw error text;
- raw persistent JSON.

Every string in the result originates from a local constant or a fixed
allowlist. Attribute strings are never passed through.

Diagnostic attribute input is rejected when it exceeds the fixed byte bound.
JSON decoding is depth-limited. The output shape is fixed and remains below
the regression size bound.

## 7. Configuration Form

The Account form gains:

```text
Show MQTT Diagnostics
```

The action prints only the bounded public result. It does not enable, connect,
disconnect, adopt or reset anything.

The German locale entry was added without changing any established form
property or lifecycle confirmation.

## 8. Compatibility

The implementation reuses the existing attributes:

```text
MqttLifecycleRegistry
MqttStatistics
MqttErrorHistory
MqttShadowState
MqttPendingReconciliation
```

It introduces:

- no property;
- no attribute;
- no variable;
- no timer;
- no instance;
- no profile;
- no Device action.

Therefore all established variable ObjectIDs, Idents, profiles, action
contracts and archive logging settings remain structurally unaffected.

REST remains the only authority for public mower variables.

## 9. Fixture Contract

The synthetic fixture:

```text
fixtures/mqtt/bounded-diagnostics-shadow-active.json
```

defines the exact expected projection after:

1. explicit adoption;
2. one successful connection setup;
3. one accepted synthetic state envelope.

It contains no private installation data and makes no additional vendor
payload claim.

## 10. Offline Evidence

The lifecycle regression verifies:

- adopted `Ready` diagnostics are read-only;
- exact top-level key order and format version;
- zero counters before connection;
- accepted synthetic state produces exact `ShadowActive` diagnostics;
- connection, receive and accepted counters;
- fixed timestamps;
- tracked and pending counts;
- output below the fixed size bound;
- no persistent-state change caused by diagnostics;
- no Core mutation caused by diagnostics.

A poisoned-state case injects:

- secret-like lifecycle strings;
- topic-like result strings;
- negative and wrongly typed counters;
- unknown error entries with payload fields;
- 100 synthetic shadow entries;
- 100 pending entries.

The result:

- normalizes unknown strings;
- normalizes invalid integers;
- drops invalid errors;
- caps both counts at 64;
- contains none of the injected private strings;
- leaves persistent state unchanged.

The complete MQTT shadow gate passes:

- fixtures;
- REST authentication regressions;
- native envelope parsing;
- shadow payload parsing;
- Receiver scaffold;
- Account ingestion;
- targeted reconciliation;
- lifecycle and diagnostics;
- distribution validation;
- PHPCS;
- PHPStan.

## 11. Files Changed

Productive:

```text
distribution/NavimowAccount/module.php
distribution/NavimowAccount/form.json
distribution/NavimowAccount/locale.json
```

Evidence and regression:

```text
fixtures/mqtt/bounded-diagnostics-shadow-active.json
fixtures/mqtt/README.md
tests/mqtt-transport-lifecycle.php
```

## 12. Architecture Decisions

### AD-NAV-432: Project existing state

**Decision:** Expose a fixed read-only projection of existing Account
attributes instead of adding diagnostic variables or another registry.

**Reason:** The required evidence already exists, and new variables would
unnecessarily expand the stable variable and archive contract.

**Consequence:** Existing logging-enabled variables remain untouched.

### AD-NAV-433: Allowlist every diagnostic string

**Decision:** Return only local constant and allowlisted strings. Normalize
everything else to `unknown` or `none`.

**Reason:** Even malformed persistent state must not become an exfiltration
path.

**Consequence:** Diagnostics remain useful for lifecycle verification without
returning raw context.

### AD-NAV-434: Keep diagnostics read-only

**Decision:** Diagnostic reads may not repair, initialize or normalize stored
attributes.

**Reason:** Observation must not change the state being investigated.

**Consequence:** Malformed state is represented safely in the output while the
original evidence remains available for a separately authorized repair.

### AD-NAV-435: Require exact receive evidence

**Decision:** A future live test must observe both an accepted-counter delta
and `ShadowActive`; Core status alone remains insufficient.

**Reason:** Transport health and productive Receiver acceptance are different
claims.

**Consequence:** Gate E can be retested deterministically after publication.

## 13. Gate Decision

| Gate | Result |
|---|---|
| fixed public diagnostic schema | PASS offline |
| read-only behavior | PASS offline |
| accepted-message evidence | PASS synthetic |
| `ShadowActive` evidence | PASS synthetic |
| malformed-state normalization | PASS |
| privacy regression | PASS |
| variable/archive contract expansion | none |
| complete MQTT offline gate | PASS |
| standalone publication | NOT PERFORMED |
| Symcon update | NOT PERFORMED |
| live receive retest | BLOCKED |

**Offline implementation gate: CLOSED.**

**Publication and live Gate-E retest: BLOCKED pending separate planning and
authorization.**

## 14. Next Step

The next SAEF step is:

```text
112-native-mqtt-diagnostics-publication-and-retest-plan.md
```

It should define:

1. the exact standalone publication delta;
2. pre-update variable and archive compatibility baselines;
3. module update without `MC_ReloadModule()`;
4. read-only verification of the new diagnostic wrapper;
5. re-enablement of the retained adopted chain;
6. exactly one newly authorized connection attempt;
7. an absolute 180-second cleanup deadline established before activation;
8. accepted-counter and `ShadowActive` deltas;
9. mandatory disconnect, credential cleanup and final disable.
