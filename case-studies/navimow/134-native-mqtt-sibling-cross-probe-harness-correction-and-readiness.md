# 134 Native MQTT Sibling Cross-Probe Harness Correction and Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Corrected V2 complete offline; publication and live retry not
authorized
**Date:** 2026-07-28
**Scope:** Correct the step-133 harness defect without changing historical
evidence, Symcon, the broker or the mower

## 1. Purpose

Step 133 safely restored all runtime and repository state but classified the
MQTT observation as inconclusive. The frozen harness rejected the valid
asynchronous Connect result before entering its observation loop.

This step:

1. preserves the exact executed V1 source;
2. creates a corrected V2 source;
3. binds the V2 expectation to the productive Account method;
4. adds a regression that detects future contract drift;
5. revalidates every no-publish, no-command and cleanup invariant;
6. decides whether a separately authorized repeat can be planned.

This is an offline-only step.

## 2. Historical Evidence Preservation

The source executed in step 133 remains unchanged:

```text
file:   live-one-shot.php
SHA-256:
3087b60f2d1fb02e3d20aedec47528c8c72e52da63e270909cd1da1fbe79701c
```

The corrected source is a separate file:

```text
file:   live-one-shot-v2.php
SHA-256:
7c2d01c1cee8d5faf3bf33fd5956283308659c0a1193062b19220270b77ccc3e
```

This separation prevents a corrected source from being presented as the source
that produced the historical live result.

## 3. Corrected Contract

V1 incorrectly accepted:

```text
MQTT transport connected.
```

V2 accepts the actual asynchronous Account contract:

```text
MQTT connection attempt started.
```

No productive module change was required.

The canonical case-study Account module and `symcon-navimow/main` Account
module are byte-equal:

```text
SHA-256:
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a
```

Both expose the same success result.

## 4. Contract Regression

The new private test:

```text
connect-contract-test.php
```

uses PHP's `token_get_all()` parser to inspect the productive
`ConnectMqttShadow()` method. It verifies:

- the productive method contains the asynchronous success result;
- V2 consumes that exact result;
- V2 no longer contains the invalid V1 expectation;
- V1 still has its exact historical SHA-256.

This closes the semantic gap left by the original validator, which checked call
counts and prohibited operations but did not couple the expected return value
to the productive implementation.

## 5. Retained Safety Contract

Static validation of V2 confirms:

| Contract | Result |
|---|---|
| normal Connect call sites | exactly 1 |
| normal Disconnect call sites | exactly 1 |
| probe Arm call sites | exactly 1 |
| probe Close call sites | exactly 1 |
| probe Delete call sites | exactly 1 |
| MQTT publish call sites | 0 |
| mower command call sites | 0 |
| Module reload call sites | 0 |
| Core transport creation | 0 |
| observation cutoff | 165 seconds |
| hard deadline | 180 seconds |

The corrected result check changes neither topology nor cleanup behavior.

## 6. Offline Validation

The complete private validation gate passed:

```text
PHP syntax:                  PASS
PHPCS:                       PASS
Connect contract regression:PASS
classification regression:  PASS
known-good probe regression: PASS
productive Receiver tests:   PASS
privacy scan:                PASS
```

The wider Navimow MQTT suite and distribution validator had already passed
after step 133. No productive file changed in this step.

## 7. Architecture Decisions

### AD-134-1: Preserve V1

Historical test sources are immutable evidence. A correction receives a new
identity and hash.

### AD-134-2: Treat Connect as asynchronous

Successful return from `ConnectMqttShadow()` means that the connection attempt
started. Transport readiness and MQTT delivery must be observed independently
through bounded status and child-ingress evidence.

### AD-134-3: Couple harness and productive contract

Safety-only source scans are insufficient for control-flow gates. The harness
must also be tested against the productive method's accepted result.

### AD-134-4: No implicit retry authorization

Correcting the offline defect does not reopen the consumed live authorization.
A second broker attempt requires a new plan, temporary publication, staged
Symcon update and explicit user approval.

## 8. Readiness Decision

| Gate | Decision |
|---|---|
| V2 source correctness | PASS |
| V1 evidence integrity | PASS |
| safety contract | PASS |
| offline regression | PASS |
| temporary publication | NOT AUTHORIZED |
| Symcon update | NOT AUTHORIZED |
| second broker attempt | NOT AUTHORIZED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

The corrected harness is ready to enter a new publication and live-test plan.
It is not yet authorized for execution.

## 9. Private Artifacts

```text
private/navimow-capture/mqtt-sibling-cross-probe/
  live-one-shot.php
  live-one-shot-v2.php
  connect-contract-test.php
  manifest-v2.json
  validate.sh
```

No credential, token, private topic, device identifier, ObjectID or garden data
was added.

## 10. Recommended Next Step

Create step 135:

```text
native-mqtt-sibling-cross-probe-v2-publication-and-live-retest-plan
```

It should define:

1. a new temporary branch based on unchanged `main`;
2. publication of the same five probe files as before;
3. inactive Symcon staging and repeated compatibility checks;
4. execution of the frozen V2 private harness exactly once;
5. mandatory runtime cleanup and restoration to `main`;
6. branch deletion regardless of the observation result;
7. a new explicit authorization gate for the broker attempt.
