# 248 Native MQTT Episode Diagnostic Hardening Pilot Failure and Cleanup

**Case study:** Navimow native IP-Symcon module

**Status:** Private pilot stopped after repeated transport episodes; immediate
and delayed credential-free cleanup passed

**Date:** 2026-08-03

**Scope:** Classify the running receive-only MQTT pilot and execute its
pre-authorized terminal cleanup

## 1. Read-Only Status Check

The bounded structured Symcon projection initially exceeded the historical
32-KiB and current 64-KiB output limits because retained pilot diagnostics had
grown. A compact projection preserved the complete validation logic while
returning only counts and the latest bounded entries.

The final channel result was:

```text
transportError: null
executionError: null
truncated:      false
projection:     PASS
```

At capture time the transport had recovered and was operational:

```text
lifecycle:             ShadowActive/healthy
MQTT/WebSocket:         102/102
connection failures:   0
reconnect exhausted:   0
REST:                   operational and authoritative
MQTT ingress:           current
variables:              14 retained
Archive loggings:       5 retained
```

## 2. Pilot Classification

The private harness provisionally compared the current counters with the
accepted active baseline and prior checkpoint:

```text
phase:                 stop-required
classification:        FAIL
completed cycles:      1
credential rotations:  65
transport episodes:    12
stop reasons:
  multiple-transport-episodes
```

The earliest 48-hour time threshold had elapsed, but the evidence contract was
not complete because only one mowing cycle was retained. The accepted maximum
of one transport episode was exceeded.

## 3. Recovery Evidence

The newest fully retained episode showed:

```text
detection source:       lifecycle observation
Core status at fault:   200/200
Core fault lead:        2 seconds
reconnect delay:        60 seconds
reconnect attempts:     1
recovery duration:      120 seconds
outcome:                recovered
REST available:         yes
MQTT ingress observed:  yes
rotation overlap:       no
kernel epoch changed:   no
```

Automatic recovery therefore continued to work. The pilot failed because
disconnect frequency exceeded the stability policy, not because recovery was
exhausted.

The native retained episode count and the cumulative unexpected-disconnect
delta are not identical. This is a diagnostic reconciliation question and must
be resolved before another pilot.

## 4. Checkpoint Reconciliation

The first compact projection retained only the latest native checkpoint before
the harness ingest. This produced a provisional `evidence-gap-exceeded` reason.

Step 249 subsequently read all native checkpoints and proved complete coverage:

```text
largest checkpoint gap: 18001 seconds
allowed maximum:        21600 seconds
coverage:               complete
```

The evidence-gap reason is therefore invalid and has been removed from the
reconciled classification. The pilot still fails independently because of
multiple transport episodes.

## 5. Mandatory Cleanup

The activation contract had already authorized deterministic cleanup for every
terminal result. Cleanup executed exactly:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit disconnect:       0
```

Immediate verification proved:

```text
MQTT/WebSocket:          104/104
WebSocket active:        false
Authorization present:  false
MQTT username/password: absent
lifecycle:               Disabled
pilot diagnostics:       inactive
REST:                    operational
```

The delayed verification after 217 seconds proved the same state without any
credential or transport reappearance.

## 6. Final Harness State

```text
phase:                 closed
classification:        FAIL
cleanup complete:      true
evidence complete:     false
completed cycles:      1
credential rotations: 65
unexpected-disconnect delta: 12
distinct episodes:     8
stop reason:           multiple-transport-episodes
```

## 7. Preserved Contracts

Both cleanup reads retained:

- the exact installed commit;
- all five structural hashes;
- 14 module variables;
- all 5 configured Archive loggings;
- REST authority and operation;
- the receive-only and no-command boundary.

## 8. Private Evidence

Machine-readable evidence is retained under:

```text
private/navimow-capture/output/
  native-mqtt-episode-diagnostic-hardening-activation/
  cleanup-result.json
  failure-evidence-closure.json
  pilot-state.json
  snapshots/checkpoint-20260803-200728.json
  snapshots/cleanup-immediate-20260803-200811.json
  snapshots/cleanup-delayed-20260803-201148.json
```

No credential value, topic, ObjectID, device identity, coordinate, hostname or
private payload is included in this public report.

## 9. Architecture Decisions

### AD-NAV-916: Treat repeated recovered disconnects as pilot failure

Successful recovery does not establish transport stability. The fixed pilot
policy remains stricter than runtime availability.

### AD-NAV-917: Keep unexpected-disconnect and episode counts distinct

The cumulative disconnect counter is the safety gate. Episode records provide
bounded causal detail but may not represent every counter increment.

### AD-NAV-918: Preserve checkpoint arrays used for coverage

Output compaction may reduce episode and transition detail, but it must retain
every checkpoint required by the harness coverage calculation.

### AD-NAV-919: Execute cleanup immediately on terminal classification

Once the pre-authorized stop condition is proven, retaining transport
credentials provides no further evidence benefit.

### AD-NAV-920: Block another pilot pending root-cause reconciliation

No new receive-only activation is justified until repeated Core transitions,
disconnect accounting and upstream or local causes have been analyzed.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| MQTT feature disable | 1 |
| Account cleanup `ApplyChanges()` | 1 |
| immediate read-only verification | 1 |
| delayed read-only verification | 1 |
| explicit MQTT disconnect | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| service restarts | 0 |
| created or deleted Symcon objects | 0 |

## 11. Gate Decision

| Gate | Decision |
|---|---|
| 48-hour threshold | REACHED |
| required mowing cycles | INCOMPLETE, 1 |
| credential rotation evidence | PASS |
| maximum transport episodes | FAIL |
| native evidence continuity | PASS AFTER RECONCILIATION |
| automatic recovery | FUNCTIONAL |
| pilot result | FAIL |
| cleanup | COMPLETE |
| MQTT transport | DISABLED |
| REST authority | RETAINED |
| another pilot | BLOCKED PENDING ANALYSIS |

## 12. Next Step

Perform a read-only root-cause reconciliation of:

1. all retained complete episode records;
2. cumulative unexpected-disconnect and reconnect counters;
3. credential-rotation timing;
4. Core transition sequences;
5. REST and MQTT ingress ages around each episode;
6. the missing external checkpoint automation.

No MQTT reactivation is part of that analysis.
