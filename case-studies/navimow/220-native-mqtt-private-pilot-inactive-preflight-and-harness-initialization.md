# 220 Native MQTT Private Pilot Inactive Preflight and Harness Initialization

**Case study:** Navimow native IP-Symcon module
**Status:** Inactive preflight passed and private harness initialized at
`ready-for-acceptance`; persistence acceptance and MQTT activation remain
closed
**Date:** 2026-07-29
**Scope:** Execute the read-only inactive pilot preflight defined by steps 212,
213 and 219

## 1. Purpose

Step 219 installed and verified the version-2 MQTT shadow diagnostics while
MQTT remained disabled and credential-free.

This step:

1. validates the frozen private observation harness;
2. captures two bounded read-only Symcon projections;
3. proves at least 65 seconds of stable inactivity;
4. initializes the resumable private pilot state;
5. ingests both inactive baselines;
6. verifies the transition to `ready-for-acceptance`;
7. closes the preflight evidence without activating MQTT.

No module update, reload, `ApplyChanges()`, service restart, MQTT activation,
temporary Symcon object or mower command was performed.

## 2. Installed Target

Both projections proved the installed target:

```text
branch: main
commit: 3d223a9c24e396d4ba55ca40aede6742592fbe8f
clean:  true
valid:  true
```

IP-Symcon exposed the installed commit in abbreviated form. The private probe
was therefore corrected to accept a lowercase hexadecimal commit with 7 to 40
characters only when it prefix-matches the frozen full target commit. It then
emits the frozen full commit into the projection.

This is a private read-only probe compatibility correction. The productive
module was not changed.

## 3. Harness Validation

Executed before live use:

```text
sh private/navimow-capture/native-mqtt-private-pilot/validate.sh
```

Result:

```text
PASS
```

The validation includes syntax, offline behavior, mutation-boundary and
private-material checks. It was repeated after the commit-prefix correction.

Current private artifact hashes:

| Artifact | SHA-256 |
|---|---|
| `PilotHarness.php` | `49b2de3f4ad6ed8d7101a8063d49223ced10eae4937077eb7bf0404943d7d9a5` |
| `pilot.php` | `9614644843a447dd98ef7e8153697b0095a9d19950f82500ae3372e2a00fc6c9` |
| `offline-test.php` | `eef39df14197d179764d1231a3378751bf4476e902fbeefc09cc33d130de8d92` |
| `symcon-readonly-probe.php` | `cf07b6ba44e5327eb646923eff220418a430d0843e608b52b28087921fecd3a9` |
| `validate.sh` | `60acdca7c31c30270fcaf2eb54266bf63897ba314b28166bbfd21a304c1fdfb5` |

## 4. MCP Execution Contract

Each bounded projection was evaluated independently across:

```text
transportError
executionError
truncated
captured output
```

For both executions:

```text
transportError: null
executionError: null
truncated:      false
projection pass: true
```

Successful transport alone was not treated as successful PHP execution.

## 5. Inactive Baselines

The two projections were captured 82 seconds apart:

| Check | First | Second | Result |
|---|---:|---:|---|
| required spacing | 65 s | 82 s observed | PASS |
| repository clean and valid | true | true | EQUAL |
| variables | 14 | 14 | EQUAL |
| Archive loggings | 5 | 5 | EQUAL |
| MQTT feature | disabled | disabled | EQUAL |
| lifecycle | `Disabled` | `Disabled` | EQUAL |
| MQTT/WebSocket status | `104/104` | `104/104` | EQUAL |
| WebSocket active | false | false | EQUAL |
| MQTT credentials present | false | false | EQUAL |
| REST operational | true | true | EQUAL |
| REST authority | retained | retained | EQUAL |
| MQTT hint | `unavailable` | `unavailable` | EQUAL |

The OAuth token horizon decreased naturally between reads. That value is a
readiness input, not part of the structural baseline signature.

## 6. Contract Equality

Both snapshots produced the same baseline signature:

```text
5a208c822bd2fce18f1535625c63e6765c6c01621781489be4f84a9c07b151c2
```

The frozen component hashes were equal:

| Contract | SHA-256 |
|---|---|
| identity | `79d61d2b6d8feaf1a5f2638419641bf9a81b783c948d34691b1722d8e6bedad4` |
| archive | `9f83bac136fd4c5e444e0555486214848148aa7f16209f365b4167392d9b50a1` |
| command evidence | `f237c68db19ee3358a9d009b1e9acdc2aec6aa402dde487958425c4a7d72b9d9` |
| topology | `e2e2de1ca65b4c98de78a517fd98daba51436da901bda53a450c064e678af1d9` |
| subscriptions | `375dc242b1a0ae91e28a62abcd8da2df6a6496df7c49939839ba1ab8f69074fa` |

This proves that the retained variables, Archive Control assignments, command
evidence, owned Core topology and canonical subscriptions did not drift during
the inactive observation interval.

## 7. Harness Initialization

The private state was created for the exact 40-character target commit and
both projections were ingested as `inactive-baseline`.

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

`PENDING` is correct here. The 48-to-72-hour pilot clock starts only after
separate persistence acceptance, separately authorized activation and two
stable active baselines.

## 8. Private Evidence

Private machine-readable evidence is stored at:

```text
private/navimow-capture/output/native-mqtt-private-pilot/
  evidence-closure.json
  pilot-state.json
  snapshots/inactive-01.json
  snapshots/inactive-02.json
```

The public report contains no ObjectID, credential, topic, payload, coordinate,
hostname or private device identity.

## 9. Safety Result

The installation remains:

```text
module:       main@3d223a9c
MQTT:         disabled
WebSocket:    inactive
credentials: absent from MQTT Core instances
REST:         operational and authoritative
harness:      ready-for-acceptance
```

No credential was retrieved or persisted by this step.

## 10. Architecture Decisions

### AD-NAV-795: Normalize only a verified commit prefix

**Decision:** Accept an abbreviated repository commit only when it is valid
lowercase hexadecimal and prefix-matches the frozen full target commit.

**Reason:** This accommodates the live IP-Symcon representation without
weakening the harness binding to the exact published revision.

### AD-NAV-796: Keep live projections private

**Decision:** Store full read-only snapshots only below `private/` and publish
only bounded hashes, counts, Booleans and classifications.

**Reason:** Even sanitized operational timing and state evidence belongs to the
private installation context.

### AD-NAV-797: Separate readiness from acceptance and activation

**Decision:** Stop at `ready-for-acceptance`; do not interpret a successful
preflight as permission to persist credentials or activate MQTT.

**Reason:** Technical readiness, contextual persistence acceptance and live
mutation are separate authorization boundaries.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| frozen private harness validation | PASS |
| first inactive projection | PASS |
| minimum 65-second spacing | PASS, 82 seconds |
| second inactive projection | PASS |
| structural contract equality | PASS |
| disabled credential-free state | PASS |
| REST authority | PASS |
| MQTT hint unavailable while disabled | PASS |
| harness initialization | PASS |
| harness phase | `ready-for-acceptance` |
| persistence acceptance | NOT GIVEN |
| MQTT activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | PROHIBITED |

## 12. Next Step

Proceed with:

```text
221-native-mqtt-private-pilot-persistence-acceptance-and-activation-readiness.md
```

That step should:

1. bind the contextual 72-hour persistence acceptance to this exact commit,
   policy and initialized state;
2. perform no Symcon mutation;
3. require a fresh read-only token-readiness check before activation;
4. preserve the separate explicit activation authorization;
5. define immediate cleanup if the later active baseline cannot be established.
