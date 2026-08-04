# 133 Native MQTT Sibling Cross-Probe Live Test and Restoration

**Case study:** Navimow native IP-Symcon module
**Status:** One-shot gate safely closed; MQTT observation inconclusive; main
restored
**Date:** 2026-07-28
**Scope:** Execute Gate C once, diagnose its early stop without retry and
complete runtime, Module Control and Git cleanup

## 1. Purpose

Step 132 installed the temporary probe branch and staged exactly one unarmed
sibling probe on the retained MQTT Client.

This step executes the separately authorized one-shot gate while the mower is
visibly mowing and supervised. It must:

1. verify every inactive precondition;
2. arm only the receive-only sibling probe;
3. perform exactly one normal Account MQTT connection attempt;
4. observe both compatible MQTT children within a fixed bound;
5. perform no publish or mower command;
6. clean up even when execution stops early;
7. restore verified `main`;
8. remove the temporary Git branch.

## 2. Authorization and Safety Boundary

The user explicitly authorized:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-Live-Test mit automatischem Cleanup
und Rückkehr zu main ist freigegeben.
```

The user additionally confirmed that the mower was visibly mowing and
supervised.

The authorization permitted one connection attempt. It did not permit a retry,
MQTT publication or mower command.

## 3. Frozen Source

The executed private source remained frozen at:

```text
SHA-256:
3087b60f2d1fb02e3d20aedec47528c8c72e52da63e270909cd1da1fbe79701c
```

This source is preserved unchanged after the run so the tested artifact remains
auditable.

## 4. Preconditions

All declared preconditions passed:

- temporary sibling and productive Receiver shared the retained MQTT parent;
- Account authentication was connected and usable;
- reauthentication was not required;
- MQTT shadow was disabled;
- WebSocket was inactive;
- authorization headers and MQTT credentials were empty;
- the stable MQTT client identity was present;
- exactly four QoS-0 subscriptions without wildcard were configured;
- Receiver counters were zero;
- no probe, publish or command activity existed.

## 5. One-Shot Execution

The harness:

- armed the sibling probe once;
- enabled the owned MQTT shadow;
- invoked the normal Account Connect wrapper exactly once;
- stopped before its observation loop;
- invoked deterministic cleanup from `finally`.

MCP execution itself was complete:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
elapsed:           348 ms
```

No MQTT observation was sampled:

```text
observations:       0
Receiver messages: 0
probe messages:    0
classification:    not-observed
```

This is not evidence of zero broker ingress because the observation interval
was never entered.

## 6. Root Cause

Static comparison of the frozen harness and the installed module found a fixed
return-contract mismatch:

```text
frozen harness expected:
MQTT transport connected.

productive Account contract:
MQTT connection attempt started.
```

The Account method correctly reports that an asynchronous connection attempt
has started. The harness treated that valid result as failure and immediately
entered cleanup.

Architecture decision:

- classify the live result as **inconclusive**;
- do not classify it as a Core, broker, subscription or sibling-delivery
  failure;
- do not retry under the consumed one-shot authorization;
- preserve the frozen source instead of silently rewriting historical
  evidence.

## 7. Automatic Runtime Cleanup

Cleanup completed without emergency fallback:

| Check | Result |
|---|---|
| disconnect calls | exactly 1 |
| shadow disable | PASS |
| WebSocket inactive | PASS |
| authorization headers empty | PASS |
| MQTT username/password empty | PASS |
| stable client identity retained | PASS |
| probe closed | PASS |
| probe deleted | PASS |
| productive Receiver retained | PASS |
| publish attempts | 0 |
| mower command attempts | 0 |

The temporary probe report closed with no accepted message and no external
action.

## 8. Main Restoration

Exactly one supported Module Control branch operation restored:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

A separate PHP process verified:

- 14/14 variable identities and metadata unchanged;
- 5/5 Archive Control logging contracts unchanged;
- logged history remains queryable;
- command evidence unchanged;
- Account authentication retained;
- MQTT disabled and credential-empty;
- productive diagnostics wrappers present;
- temporary probe wrappers absent;
- no temporary probe instance exists.

The user's configured mower-variable logging remains intact.

## 9. Git Cleanup

The publication clone was returned to:

```text
local main:  046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
```

The temporary local and remote experiment branches were deleted and their
absence was verified after pruning.

## 10. Evidence

Private machine-readable evidence:

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe/
    live-one-shot-result.json
    main-restoration.json
    gate-c-evidence-closure.json
```

No private ObjectID, credential, token, device identifier, topic or garden data
is copied into this public report.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| one-shot safety and cleanup | PASS |
| Module Control restoration | PASS |
| productive compatibility | PASS |
| sibling MQTT delivery evidence | INCONCLUSIVE |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

The run changes no production support claim. REST remains the authoritative
state path and MQTT remains disabled by default.

## 12. Recommended Next Step

Create step 134 as an offline-only harness correction and repeat-readiness
review:

1. derive the accepted Connect result from the productive contract;
2. add an offline regression that fails when the harness expectation drifts;
3. retain every one-shot, no-publish, no-command and cleanup invariant;
4. publish a new temporary branch only after a new explicit gate plan;
5. require a fresh, separate authorization before any second broker attempt.
