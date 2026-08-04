# Native MQTT Core-Resume Deadline Hardening Evidence Closure

**Case study:** Navimow native IP-Symcon module
**Status:** Deadline-hardening increment accepted for the private pilot;
live state disabled and credential-free
**Date:** 2026-07-29
**Scope:** Close regression, fixture and pilot decisions after step 210

## 1. Purpose

Step 210 proved retained-Core adoption at the scheduled `+90 s` point under
the new six-point `+180 s` horizon, with zero Account reconnects and complete
mandatory cleanup.

This step determines whether that result requires:

- a new sanitized fixture;
- another productive module change;
- another publication;
- a broader activation decision.

It performs no live-system access or mutation.

## 2. Evidence Comparison

The decisive step-210 signature was:

```text
+15 s:  200 / 200 / unhealthy
+30 s:  200 / 200 / unhealthy
+60 s:  200 / 200 / unhealthy
+90 s:  102 / 102 / healthy -> ShadowActive / core-resumed
Account reconnect delta: 0
```

This is the same installation behavior observed in step 198. The hardening
changes only the available bounded reserve after `+90 s`; it must not delay an
earlier healthy adoption.

Step 210 therefore confirms:

1. absolute observation offsets are preserved across a real restart;
2. unhealthy intermediate points do not trigger cleanup or reconnect;
3. the first healthy scheduled point is adopted immediately;
4. later `+120/+180 s` points are canceled after adoption;
5. the extended final deadline does not alter successful `+90 s` behavior.

## 3. Fixture Decision

No new public fixture is promoted.

The existing synthetic fixture:

```text
case-studies/navimow/fixtures/mqtt/
  core-resume-bounded-health-observation.json
```

already freezes:

- absolute offsets `[15, 30, 60, 90, 120, 180]`;
- a 180-second deadline;
- maximum history length six;
- healthy adoption at every scheduled offset, including `+90 s`;
- no connection operation during retained-Core adoption;
- unhealthy continuation through `+120 s`;
- exactly one bounded recovery transition after an unhealthy `+180 s`;
- cancellation of later observations after an earlier healthy result.

Step 210 adds no payload shape, state transition or failure signature absent
from this regression contract. Copying private live metadata into another
fixture would add privacy and maintenance cost without increasing coverage.

## 4. Implementation Decision

No productive module change is required.

The installed and published implementation behaved as designed:

```text
installed commit:          main@8fdab84b
first healthy point:       +90 seconds
Account reconnects:        0
automatic recovery:        not entered
cleanup:                   complete
```

The cleanup finding belongs only to the private test harness. The corrected
bounded probe is:

```text
private/navimow-capture/
  native-mqtt-deadline-hardening-normal-cleanup.php
```

It does not change the standalone module, its public APIs or any installation
object.

## 5. Validation Closure

After live cleanup, the focused MQTT gate passed:

```text
fixture checks:                 PASS
REST/authentication checks:     PASS
native envelope checks:         PASS
partial payload checks:         PASS
receive probe checks:           PASS
shadow payload checks:          PASS
Receiver diagnostics:           PASS
Account ingestion:              PASS
REST reconciliation:            PASS
transport lifecycle:            PASS
distribution validation:        PASS
static analysis:                 PASS
```

The active live sequence introduced no new expected behavior requiring a
regression update.

## 6. Pilot Decision

The deadline hardening is accepted for the existing private-pilot boundary.

This means:

- the six-point state machine is the current implementation;
- `+90 s` remains the observed installation expectation;
- `+120/+180 s` remain bounded reserve points;
- REST remains the sole public state authority;
- MQTT remains receive-only and disabled by default;
- each future credential-bearing activation still requires its own current
  readiness and authorization gates.

It does not authorize:

- always-on or unattended MQTT operation;
- a new activation or restart;
- MQTT publishing;
- mower commands;
- a public release tag.

## 7. Architecture Decisions

### AD-NAV-748: Treat the live result as regression confirmation

**Decision:** Retain the current synthetic fixture without adding a live-derived
variant.

**Reason:** The `+90 s` signature and all resulting transitions are already
covered, while private installation timing adds no new contract.

### AD-NAV-749: Keep reserve points synthetic until exercised naturally

**Decision:** Do not claim live `+120/+180 s` readiness from this run.

**Reason:** Healthy adoption at `+90 s` correctly canceled those later points.
Their failure and recovery semantics remain exhaustively offline-tested.

### AD-NAV-750: Keep the cleanup correction private

**Decision:** Correct the bounded evidence harness without changing productive
module code.

**Reason:** The module's normal Account cleanup passed; only the historical
probe's precondition was unreachable.

### AD-NAV-751: Accept the hardening only within the private-pilot boundary

**Decision:** Close the increment as technically successful without enabling
persistent operation.

**Reason:** A successful supervised restart validates the mechanism but does
not broaden consent or operating policy.

## 8. Final State

| Contract | Decision |
|---|---|
| standalone `main@8fdab84b` | PUBLISHED AND INSTALLED |
| six-point deadline hardening | PRIVATE-PILOT PASS |
| live first healthy point | `+90 s` |
| Account reconnect delta | 0 |
| mandatory cleanup | PASS |
| MQTT feature | DISABLED |
| Core credentials | EMPTY |
| new public fixture | NOT REQUIRED |
| productive correction | NOT REQUIRED |
| persistence acceptance | CONSUMED |
| new activation/restart | NOT AUTHORIZED |
| REST state authority | RETAINED |
| MQTT publish | PROHIBITED |

**The deadline-hardening engineering increment is complete.**

## 9. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-core-resume-deadline-hardening-closure/
    closure-review.json
```

No further live test is required for this increment.
