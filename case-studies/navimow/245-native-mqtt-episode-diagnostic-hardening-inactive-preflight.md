# 245 Native MQTT Episode Diagnostic Hardening Inactive Preflight

**Case study:** Navimow native IP-Symcon module

**Status:** Inactive preflight passed and private harness initialized at
`ready-for-acceptance`; persistence acceptance and MQTT activation remain
closed

**Date:** 2026-07-31

**Scope:** Verify the installed v2 diagnostics while MQTT remains disabled and
prepare a commit-bound private observation state

## 1. Purpose

Step 244 installed and verified:

```text
main@79686e52f0bbaad77d37b9cd6e4b367797d96f2e
```

The user authorized the next inactive preflight. This step:

1. bound the private read-only projection to the exact installed commit;
2. validated the private observation harness offline;
3. captured two bounded inactive projections;
4. proved 89 seconds of stable credential-free inactivity;
5. corrected one private consumer rule for retained closed history;
6. initialized a fresh state for the exact installed commit;
7. stopped at the separate persistence-acceptance gate.

No Symcon mutation, MQTT credential retrieval, activation, OAuth action,
service restart or mower command occurred.

## 2. Private Probe Binding

The existing private Symcon probe was changed only from the previously
installed commit:

```text
793249ece1c0944192ea28dade7ecd2340a5135f
```

to:

```text
79686e52f0bbaad77d37b9cd6e4b367797d96f2e
```

The probe remains read-only and outputs sanitized aggregates rather than
ObjectIDs, topics, payloads, credentials or device identity.

## 3. Harness Validation

The private harness validation initially passed before live ingestion:

```text
PHP syntax:             PASS
offline behavior:       PASS
read-only boundary:     PASS
private-material scan:  PASS
```

The first real v2 snapshot then exposed a private consumer gap:

```text
inactive-snapshot-invalid
```

The snapshot and productive module were valid. The private `assertInactive()`
rule still required:

```text
sessionSequence: 0
empty checkpoints, episodes and rotations
```

That rule described an unused first session, but not a valid disabled module
with retained, fully closed pilot history.

## 4. Private Consumer Correction

The private harness now accepts exactly two inactive forms:

1. an unused session with sequence zero and empty evidence;
2. a closed session with positive sequence, start and stop timestamps.

Both forms still require:

```text
featureEnabled:   false
active:           false
nextCheckpointAt: 0
openEpisode:      null
```

The existing 32/32/64 bounds and format-version checks remain unchanged.

`assertCleanupInactive()` now reuses the same inactive invariant instead of
maintaining a second, partially duplicated rule.

A new offline case proves that closed retained v2 history can form two stable
inactive baselines. Existing diagnostic-drift and cleanup tests continue to
pass.

Final private validation:

```text
Navimow private-pilot harness validation passed.
```

No productive module file changed.

## 5. MCP Execution Contract

Both stored projections used the bounded structured MCP channel. Each passed:

```text
transportError:  null
executionError:  null
truncated:       false
projection pass: true
```

A successful transport was not treated as successful PHP execution.

## 6. Inactive Baselines

The projections were captured 89 seconds apart:

| Check | First | Second | Result |
|---|---:|---:|---|
| required spacing | 65 s | 89 s observed | PASS |
| installed commit | `79686e5` | `79686e5` | EQUAL |
| repository clean and valid | true | true | EQUAL |
| variables | 14 | 14 | EQUAL |
| Archive loggings | 5 | 5 | EQUAL |
| MQTT feature | disabled | disabled | EQUAL |
| MQTT/WebSocket status | `104/104` | `104/104` | EQUAL |
| MQTT credentials | absent | absent | EQUAL |
| lifecycle | `Disabled` | `Disabled` | EQUAL |
| REST operational | true | true | EQUAL |
| MQTT hint | unavailable | unavailable | EQUAL |
| pilot diagnostics | v2, closed | v2, closed | EQUAL |
| transport counters | retained | retained | EQUAL |

The OAuth token horizon decreased passively from 2950 to 2861 seconds. No
refresh, OAuth or authentication action was executed by this step.

The current horizon is an observation only. A later activation gate must
recheck it at mutation time.

## 7. Contract Equality

Both snapshots produced:

```text
baseline signature:
9e811ffb96a65d60e77d13e89dfa69d5c4c24f7ee2c70b1ac084a08e6ec9aa52

pilot diagnostics signature:
3f0fa737ad2f8d055d7700ec472d19bfbe190e20c22a802431168cf36ea79feb
```

The retained component hashes remained:

| Contract | SHA-256 |
|---|---|
| identity | `79d61d2b6d8feaf1a5f2638419641bf9a81b783c948d34691b1722d8e6bedad4` |
| archive | `9f83bac136fd4c5e444e0555486214848148aa7f16209f365b4167392d9b50a1` |
| command evidence | `f237c68db19ee3358a9d009b1e9acdc2aec6aa402dde487958425c4a7d72b9d9` |
| topology | `e2e2de1ca65b4c98de78a517fd98daba51436da901bda53a450c064e678af1d9` |
| subscriptions | `375dc242b1a0ae91e28a62abcd8da2df6a6496df7c49939839ba1ab8f69074fa` |

This preserves the identities of all 14 variables and the five user-owned
Archive Control logging contracts.

The retained v2 projection remained byte-stable with:

```text
checkpoints:          2
episodes:             2
rotations:            15
core transitions:     0
Core event drops:     0
open episode:         null
```

## 8. Harness State

The fresh private state is bound to the full 40-character installed commit.

Final status:

```text
phase:               ready-for-acceptance
classification:      PENDING
inactive baselines:  2
active baselines:    0
stop reasons:        none
pilot clock started: no
cleanup required:    no
```

`PENDING` is correct. A new pilot clock starts only after commit-bound
persistence acceptance, token readiness, explicit activation and two stable
active baselines.

## 9. Private Evidence

Machine-readable evidence is stored at:

```text
private/navimow-capture/output/
  native-mqtt-episode-diagnostic-hardening-inactive-preflight/
  evidence-closure.json
  pilot-state.json
  snapshots/inactive-01.json
  snapshots/inactive-02.json
```

The public report contains no ObjectID, credential, topic, payload, coordinate,
hostname or private device identity.

## 10. Safety Result

```text
Symcon writes:             0
MQTT activation:           0
credential retrieval:      0
OAuth actions:             0
service restarts:          0
mower commands:            0
temporary Symcon objects:  0
```

The only correction affected the private local observation harness.

## 11. Architecture Decisions

### AD-NAV-901: Bind every pilot state to the installed full commit

The private probe and state machine reject snapshots from any other module
commit.

### AD-NAV-902: Treat closed retained history as valid inactivity

A disabled transport may retain bounded history from an earlier closed pilot.
Inactivity is defined by disabled transport, stopped session and no open
episode, not by deleting historical evidence.

### AD-NAV-903: Keep empty and closed session invariants explicit

The harness accepts only an unused empty session or a positive, fully stopped
session. Active or partially closed evidence still fails closed.

### AD-NAV-904: Separate private consumer defects from module defects

The productive v2 projection passed its live contract. The local ingestion
failure caused no module update, rollback or Symcon mutation.

### AD-NAV-905: Keep persistence and activation separately closed

Read-only inactive stability does not authorize credential persistence, MQTT
activation, service restart or unattended operation.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| private probe commit binding | PASS |
| private harness validation | PASS |
| retained-history consumer correction | PASS |
| first inactive projection | PASS |
| 89-second inactive interval | PASS |
| second inactive projection | PASS |
| structural contract equality | PASS |
| native v2 diagnostic equality | PASS |
| disabled credential-free state | PASS |
| REST authority | PASS |
| harness initialization | PASS |
| harness phase | `ready-for-acceptance` |
| persistence acceptance for current commit | NOT GIVEN |
| token readiness | OBSERVED, MUST BE RECHECKED |
| MQTT activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | NOT PERFORMED |

## 13. Next Step

Proceed with:

```text
246-native-mqtt-episode-diagnostic-hardening-persistence-acceptance-and-token-readiness.md
```

That step should:

1. record explicit persistence and recovery acceptance for the exact installed
   commit and fresh private state;
2. perform no Symcon mutation while recording acceptance;
3. run a fresh bounded token-readiness projection;
4. observe passive refresh only if the horizon is below the activation
   threshold;
5. retain a separate explicit authorization gate for MQTT activation.
