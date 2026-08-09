# 307 Position Accounting Pilot Activation Safe Abort

**Case study:** Navimow native IP-Symcon module

**Status:** The single authorized activation attempt stopped fail-closed after
an overstrict private immediate postcondition; automatic cleanup passed
immediately and after the mandatory delay

**Date:** 2026-08-09

## 1. Result

The operator supplied all three activation prerequisites:

1. no manual OAuth, login or token-refresh action occurred between the
   readiness observations at 07:36 and 07:59 UTC;
2. temporary storage of Authorization and MQTT credentials in the owned
   IP-Symcon Core instances was accepted for the bounded receive-only pilot,
   with mandatory cleanup afterward; and
3. exactly one activation attempt was authorized after a renewed fresh token
   and safety preflight.

The final mutation-time preflight passed on standalone commit
`50b365200e0c5c55990214c31f4a46f28b1406c7` with a 2857-second token horizon.
The activation candidate then performed exactly one Account `ApplyChanges()`.

The immediate runtime state was valid but transitional:

```text
lifecycle:          ReconnectScheduled
transition reason:  restart-scheduled
session sequence:   3, unchanged
position accounting zero: yes
```

The private candidate incorrectly required the native pilot session to have
already advanced to sequence 4 in this immediate state. It therefore classified
the valid delayed connection transition as a failed postcondition and executed
its mandatory disable cleanup.

The bounded pilot did not start.

## 2. Channel Verification

Every bounded Symcon MCP call was evaluated through its independent result
channels:

| Phase | `transportError` | `executionError` | `truncated` |
|---|---|---|---:|
| final preflight | `null` | `null` | `false` |
| activation candidate | `null` | `null` | `false` |
| immediate cleanup verification | `null` | `null` | `false` |
| delayed cleanup verification | `null` | `null` | `false` |

Transport success was not used as a substitute for PHP execution success.

## 3. Final Preflight

Immediately before the one mutation attempt, the read-only probe proved:

- exact standalone `main` commit, clean and valid;
- all identity, Archive, command-evidence, topology and subscription hashes
  unchanged;
- 14 public variables retained;
- all five user-enabled Archive logging contracts retained and queryable;
- REST operational and Account authentication connected;
- no reauthentication requirement;
- token horizon 2857 seconds, above the 2400-second minimum;
- MQTT and position diagnostics disabled;
- MQTT and WebSocket inactive and credential-free;
- previous native pilot session closed with no reconnect pending; and
- pilot-wide position accounting present and exactly zero.

No MQTT credentials were requested by the preflight.

## 4. Activation and Cleanup Accounting

| Operation | Count |
|---|---:|
| authorized activation attempts | 1 |
| Account `ApplyChanges()` for activation | 1 |
| Account `ApplyChanges()` for safety cleanup | 1 |
| automatic activation retries | 0 |
| OAuth actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

The cleanup `ApplyChanges()` was not a second activation attempt. It was the
pre-authorized mandatory fail-safe path after the candidate had already changed
the two feature properties.

## 5. Cleanup Evidence

The first read-only verification ran immediately after cleanup. A second one
ran after more than the required 180-second delay. Both passed.

The delayed state proved:

```text
MQTT feature:             disabled
position diagnostics:    disabled
MQTT Core status:         104
WebSocket Core status:    104
WebSocket active:         false
Authorization present:   false
MQTT username present:   false
MQTT password present:   false
lifecycle:                Disabled
next reconnect attempt:  0
position accounting:     all counters zero
REST authority:           operational
```

All public variable and Archive contracts remained unchanged.

## 6. Cause Analysis

The productive module starts its native pilot observation from the bounded
connection path, not synchronously from Account `ApplyChanges()`.

`ReconnectScheduled` therefore has a deliberate intermediate contract:

- both receive-only features are configured;
- transport validation is ready;
- connection recovery is scheduled;
- the preceding pilot session remains closed; and
- the next session sequence is created only when the connection attempt starts.

The productive module followed this contract. The defect was confined to the
private activation candidate, which conflated accepted configuration with the
later native-session start.

This result is not evidence of a new Account `101/102` defect, an MQTT transport
failure or a position-accounting defect.

## 7. Candidate Correction

The private candidate has been corrected locally. Its immediate postcondition
now accepts exactly two transition classes:

1. `ReconnectScheduled` with the previous session still closed, unchanged
   session sequence and no checkpoint scheduled; or
2. `Ready`, `Connecting` or `ShadowActive` with the session sequence advanced
   exactly once and the native checkpoint schedule active.

In both classes, receive-only configuration, validation and zero initial
position accounting remain mandatory. The later active baselines must still
prove sequence advancement and stable transport; the relaxed immediate check
does not declare the pilot active.

## 8. Architecture Decisions

### AD-NAV-1282: Model connection startup as an asynchronous transition

An immediate `ApplyChanges()` result may prove accepted configuration without
proving that the scheduled transport attempt has already started.

### AD-NAV-1283: Keep session start in the active-baseline gate

Session-sequence advancement and checkpoint scheduling belong to the later
read-only active baseline, where the runtime transition is observable.

### AD-NAV-1284: Count cleanup separately from activation attempts

Mandatory fail-safe cleanup may require another `ApplyChanges()` but must never
be represented as an activation retry.

### AD-NAV-1285: Consume the one-attempt authorization on mutation

The activation property change occurred, so the authorization is consumed even
though cleanup followed immediately. A corrected retry needs a new explicit
authorization.

## 9. Gate State

| Gate | Status |
|---|---|
| operator authentication attribution | PASS |
| temporary credential-persistence acceptance | PASS FOR CONSUMED ATTEMPT |
| final mutation-time readiness | PASS |
| single authorized activation attempt | CONSUMED |
| active pilot established | FAIL, NOT STARTED |
| mandatory immediate cleanup | PASS |
| mandatory delayed cleanup | PASS |
| corrected private candidate | LOCALLY PREPARED |
| corrected candidate review | OPEN |
| renewed token and safety preflight | OPEN |
| any further activation | CLOSED, SEPARATE AUTHORIZATION REQUIRED |

## 10. Next Step

Perform a read-only review of the corrected private activation state contract
and bind a new readiness candidate to the exact installed commit and current
disabled baseline. Only after that review may a fresh token preflight and one
new, separately authorized activation attempt be considered.
