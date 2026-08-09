# 292 Combined MQTT Position Pilot Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Combined private pilot prepared through two stable disabled
format-3 baselines, accepted passive token evidence and renewed persistence
acceptance; MQTT activation remains separately closed

**Date:** 2026-08-05

**Scope:** Prepare Gate L2 for standalone commit
`4b4b4d7b577df2639ed4a82049aa127c56bdc989` without enabling MQTT,
requesting credentials, refreshing a token, restarting Symcon or sending a
mower command

## 1. Result

The private combined-pilot harness is bound to the exact published and
installed commit. Its offline behavior, failure paths and privacy constraints
pass.

Two bounded read-only Symcon projections more than 65 seconds apart were
accepted by the format-3 state machine:

```text
snapshot format:              3
policy:                       NAV-MQTT-POSITION-PRIVATE-PILOT-72H
position required:            true
phase after second baseline:  ready-for-acceptance
stop reasons:                 none
MQTT transport:               disabled
position status:              disabled
position observation:         null
credentials:                  absent
REST:                         operational and authoritative
```

This establishes structural readiness only. It does not authorize activation.

## 2. Readiness Correction

The initial combined harness assumed that position diagnostics remained
enabled while the MQTT transport was inactive. Gate L1 instead proved the
published disabled-default contract:

```text
EnableMqttShadow:               false
EnableMqttPositionDiagnostics:  false
position status:                disabled
position observation:           null
```

The private harness now accepts this exact inactive baseline. The position
feature flag is no longer part of the immutable baseline signature because it
must change during the separately gated activation.

## 3. Bounded Transition Scripts

The private activation candidate now:

1. binds the exact standalone commit and established contract hashes;
2. requires disabled, credential-free transport and position diagnostics;
3. requires a passive token horizon of at least 2400 seconds;
4. sets both feature properties before one Account `ApplyChanges()` call;
5. disables both properties through one cleanup `ApplyChanges()` if an
   activation postcondition fails.

The normal cleanup candidate disables both properties before one Account
`ApplyChanges()` call and requires credential removal plus an empty disabled
position projection.

Neither script was executed in this step.

## 4. Offline Validation

The private validation passed:

- PHP syntax for harness, CLI, probe and tests;
- legacy transport-only compatibility;
- combined format-3 creation and inactive baselines;
- active position windows and two position-covered natural cycles;
- bounded retained-track handling;
- immediate and delayed cleanup behavior;
- private-source privacy scan.

## 5. Read-Only Live Evidence

Both MCP calls reported:

```text
transportError:  null
executionError:  null
truncated:       false
projection:      PASS
```

Both observations proved the exact commit, clean and valid repository state,
14 public variables, five queryable Archive loggings, unchanged command and
subscription evidence, disabled Core instances, absent credentials and an
empty position projection.

The token horizon first decreased naturally from 756 to 660 seconds. One
further read after the productive 300-second refresh margin observed a new
horizon of 3569 seconds. Account, REST, disabled transport and all contracts
remained healthy. Codex performed no OAuth or token mutation.

The technical passive token criterion therefore passes. The user subsequently
confirmed that no manual OAuth, login or token-update action occurred during
the complete observation window. The refresh is therefore accepted as passive
scheduler evidence.

The user also accepted that Authorization and MQTT credentials may be stored
temporarily in the installation's own IP-Symcon Core instances during the
receive-only pilot. Mandatory cleanup disables both MQTT and position
diagnostics and removes those credentials independently of the pilot outcome.

## 6. Remaining Gates

| Requirement | Status |
|---|---|
| exact installed commit | PASS |
| private format-3 validation | PASS |
| two stable disabled baselines | PASS |
| REST authoritative | PASS |
| MQTT disabled and credential-free | PASS |
| token horizon at least 2400 seconds | PASS, 3569 SECONDS |
| confirmation of no manual authentication action | ACCEPTED |
| renewed credential-persistence acceptance | ACCEPTED |
| explicit Gate L2 activation authorization | CLOSED |

## 7. Architecture Decisions

### AD-NAV-1231: Baseline the published disabled contract

Readiness starts with both MQTT feature properties false and no retained
position observation.

### AD-NAV-1232: Exclude feature activation from structure identity

The position feature flag is an expected lifecycle transition, not an
immutable module contract.

### AD-NAV-1233: Couple combined activation and cleanup

Transport and position diagnostics change together through one Account
application boundary so that no partially enabled pilot mode is retained.

### AD-NAV-1234: Keep passive token readiness independent

Stable disabled baselines do not compensate for a short token horizon. No
manual refresh is used to open the gate.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only Symcon projections | 3 |
| Account property mutations | 0 |
| Account ApplyChanges calls | 0 |
| MQTT activations | 0 |
| credential requests | 0 |
| OAuth or manual token actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

## 9. Next Step

Request one separate Gate L2 authorization bound to the exact installed
commit, the validated activation and cleanup script hashes, the initialized
format-3 state and a fresh mutation-time token check. Only that authorization
may execute the combined activation candidate.
