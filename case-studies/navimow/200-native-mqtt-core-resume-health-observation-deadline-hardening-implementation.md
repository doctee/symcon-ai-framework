# 200 Native MQTT Core Resume Health Observation Deadline Hardening Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation complete; complete Navimow gate passed,
publication remains closed
**Date:** 2026-07-29
**Scope:** Implement only the extended retained-Core observation contract from
step 199

## 1. Purpose

Step 198 proved retained native Core adoption at the exact former `+90 s`
deadline. Step 199 therefore approved two bounded reserve observations without
changing the canonical health predicate or immediate-adoption behavior.

This step:

1. extends the absolute observation schedule through `+180 s`;
2. raises the bounded diagnostic history from four to six entries;
3. updates the synthetic contract fixture;
4. proves healthy adoption independently at every scheduled offset;
5. proves final recovery only after an unhealthy `+180 s` observation;
6. preserves late-timer, epoch and idempotency behavior;
7. passes the complete Navimow offline gate.

No publication, Symcon update, MQTT activation, restart or mower command was
performed.

## 2. Productive Implementation

Only these internal constants changed:

```php
private const MQTT_KERNEL_CORE_OBSERVATION_OFFSETS_SECONDS =
    [15, 30, 60, 90, 120, 180];
private const MQTT_KERNEL_CORE_OBSERVATION_MAX_ENTRIES = 6;
```

The existing implementation already derives:

- the absolute deadline from the maximum offset;
- the next timer from the first future absolute offset;
- the retained observation limit from the maximum-entry constant.

No additional production branch, timer, state or storage mechanism was
required.

## 3. Productive Scope Verification

The canonical distribution was compared directly with the clean standalone
module repository at:

```text
main@45c7bd509f95
```

Result:

```text
modified productive files: 1
NavimowAccount/module.php: 2 insertions / 2 deletions
added productive files:    0
deleted productive files:  0
```

The complete productive diff consists only of the two constant changes. Form,
locale, module metadata, Account properties, Device, Receiver, REST, commands,
variables, profiles, actions and archive contracts are unchanged.

Current canonical Account SHA-256:

```text
6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f
```

## 4. Updated Synthetic Fixture

The public credential-free fixture now freezes:

```text
absolute offsets:     15, 30, 60, 90, 120, 180 seconds
deadline:             180 seconds
maximum observations: 6
```

Its never-ready timeline contains one unhealthy projection at every offset.
The expected final result remains:

```text
state:                    ReconnectScheduled
reason:                   core-disconnected
classification:           unhealthy-with-credentials
unexpected disconnects:  +1
Core cleanup operations:  7
Account connection calls: 0
```

Fixture SHA-256:

```text
e9acb461a00e34e01fd2f0c8a55b5e53c3826b8b3ec57c79f4fad692cea8a71e
```

## 5. Independent Adoption Matrix

The lifecycle regression creates a fresh synthetic restart epoch for every
target offset:

| First healthy offset | Expected result | Account reconnect |
|---:|---|---:|
| `+15 s` | immediate `ShadowActive/core-resumed` | 0 |
| `+30 s` | immediate `ShadowActive/core-resumed` | 0 |
| `+60 s` | immediate `ShadowActive/core-resumed` | 0 |
| `+90 s` | immediate `ShadowActive/core-resumed` | 0 |
| `+120 s` | immediate `ShadowActive/core-resumed` | 0 |
| `+180 s` | immediate `ShadowActive/core-resumed` | 0 |

For every case the test proves:

- exactly one observation per reached offset;
- all earlier entries remain `healthy=false`;
- the decisive entry is `healthy=true`;
- the deadline remains `kernelStartObservedAt + 180`;
- `coreResumeObservations` increases exactly once;
- no native Core mutation occurs;
- a later lifecycle call does not add or replay a Core-resume observation.

Health is evaluated before deadline failure. A Core first becoming healthy at
the exact `+180 s` boundary is therefore adopted and is not disconnected.

## 6. Never-Ready Recovery

The never-ready regression now advances through:

```text
+15 -> +30 -> +60 -> +90 -> +120 -> +180 seconds
```

Before `+180 s`:

```text
state:          CoreResumeObserving
reason:         core-readiness-pending
classification: pending-with-credentials
Core mutations: 0
```

At `+180 s`, after recording the sixth unhealthy projection:

1. classification becomes `unhealthy-with-credentials`;
2. `unexpectedDisconnects` increases exactly once;
3. the owned Core is cleaned through the existing seven operations;
4. the kernel epoch is marked reconciled;
5. the existing first 60-second reconnect delay is scheduled.

The retry sequence itself remains `[60, 300, 900]` and is not extended by this
change.

## 7. Timer and Epoch Regression

Existing regression paths were retained and adjusted to the new deadline:

- a delayed first timer at `+47 s` records one actual observation;
- the next absolute observation remains `+60 s`;
- missed offsets are not replayed;
- first execution beyond the deadline at `+186 s` records one projection and
  performs one recovery;
- duplicate timer, ready message and `ApplyChanges()` preserve pending
  evidence;
- a new kernel epoch clears the old six-point window and creates a fresh
  `+180 s` deadline.

The extended horizon therefore changes only the bound, not the absolute-timer
semantics.

## 8. Diagnostic Contract

The diagnostic serializer now retains at most six sanitized observation
entries. The malformed-state regression injects ten entries and proves that
only six survive.

Each retained entry still exposes only:

```text
ordinal
observedAt
offsetSeconds
mqttStatus
webSocketStatus
webSocketActive
authorizationPresent
mqttUsernamePresent
mqttPasswordPresent
lastReceivedAt
healthy
```

Private-looking extra fields are discarded. Credential values, topics,
payloads, device identity and ObjectIDs are not added to the public fixture or
diagnostic contract.

The incorrect per-entry `classification` and `failedPredicates` names from the
step 198 transient live probe were not copied into production. No reusable
probe file containing those incorrect names exists in the repository. The
next live-test procedure must read per-entry `healthy` plus the existing
top-level classification and failed-predicate fields.

## 9. Public and Operational Compatibility

Unchanged contracts:

- REST remains the authoritative state source;
- MQTT remains an optional receive-only shadow;
- MQTT publish remains prohibited;
- commands remain REST-only;
- OAuth and token-refresh behavior is unchanged;
- transient network recovery remains exactly three bounded attempts;
- authentication and configuration failures remain non-retryable;
- all existing variable Idents and profiles remain unchanged;
- existing Archive Control logging and accumulated history remain addressable;
- module and instance GUIDs remain unchanged.

The feature remains disabled and credential-free in the live installation from
the closure of step 198.

## 10. Changed Offline Artifacts

| Artifact | SHA-256 |
|---|---|
| `distribution/NavimowAccount/module.php` | `6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f` |
| `tests/mqtt-transport-lifecycle.php` | `21a5d34d42a5bfdea2ddc95f47c461707e71cedaa7769541d9be70db1677bbcd` |
| `tests/mqtt-fixtures.php` | `2cd749abf48b0811e1012f21d35778cb2f25263a6d6a64c22d0cf081ba03a153` |
| `fixtures/mqtt/core-resume-bounded-health-observation.json` | `e9acb461a00e34e01fd2f0c8a55b5e53c3826b8b3ec57c79f4fad692cea8a71e` |

The MQTT fixture index and case-study index are also updated.

## 11. Validation

Executed:

```text
php case-studies/navimow/tests/mqtt-fixtures.php
php case-studies/navimow/tests/mqtt-transport-lifecycle.php
sh case-studies/navimow/tools/check-mqtt-shadow.sh
git diff --check -- case-studies/navimow
```

Passed in the complete gate:

- MQTT fixture checks;
- REST client and authentication checks;
- native MQTT envelope checks;
- partial-payload parser checks;
- Symcon MQTT receive-probe checks;
- MQTT shadow payload checks;
- Receiver diagnostics checks;
- Account ingestion checks;
- REST reconciliation checks;
- MQTT transport lifecycle checks;
- distribution structure and JSON validation;
- PHP syntax through executable test loading;
- PHPCS;
- PHPStan.

The official browser-based Symcon Module Validator was not presented with an
unpublished local candidate. Its metadata inputs are unchanged, and the local
distribution structure is valid. A fresh official validator result remains a
mandatory publication gate before any Symcon update.

## 12. Architecture Decisions

### AD-NAV-712: Implement the longer horizon through existing constants

**Decision:** Change only the offset list and bounded history size.

**Reason:** Existing absolute scheduling, deadline and sanitization logic
already composes the required behavior correctly.

### AD-NAV-713: Prove every adoption offset independently

**Decision:** Use a fresh restart epoch for each of the six target offsets.

**Reason:** A single timeline cannot prove that each boundary independently
adopts before recovery.

### AD-NAV-714: Let health win at the exact final boundary

**Decision:** Preserve the existing health-before-deadline decision order.

**Reason:** Disconnecting a canonically healthy retained Core at `+180 s`
would contradict the purpose of the reserve horizon.

### AD-NAV-715: Do not expand diagnostics for a transient probe error

**Decision:** Keep `healthy` as the per-entry decision and use top-level
classification fields.

**Reason:** Duplicate schema fields would add public surface without new
runtime evidence.

### AD-NAV-716: Keep publication and live validation separate

**Decision:** Stop after local implementation and complete offline validation.

**Reason:** Publishing the module, updating Symcon and retaining credentials
through another restart each require their own explicit gates.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| six-point fixture | PASS |
| healthy adoption at every offset | PASS |
| exact-boundary `+180 s` adoption | PASS |
| never-ready final recovery | PASS |
| late timer and no replay | PASS |
| diagnostic bound and privacy | PASS |
| complete offline validation | PASS |
| productive scope | ONE FILE, TWO CONSTANTS |
| official Module Validator | PENDING PUBLICATION GATE |
| publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| live restart | CLOSED |
| MQTT publish | PROHIBITED |

## 14. Next Step

Proceed with:

```text
201-native-mqtt-core-resume-health-observation-deadline-hardening-publication-and-live-test-plan.md
```

That plan should freeze the exact one-file delta and separate:

1. publication to the dedicated module repository;
2. official Module Validator verification;
3. Symcon update while MQTT remains disabled;
4. inactive topology and diagnostic staging;
5. renewed contextual credential-persistence acceptance;
6. one threshold-gated receive-only restart;
7. observation through the first healthy offset or `+180 s`;
8. mandatory immediate and delayed cleanup.
