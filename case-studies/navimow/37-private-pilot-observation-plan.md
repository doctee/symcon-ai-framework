# 37 Private Pilot Observation Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Observation plan complete; execution pending
**Date:** 2026-07-10
**Scope:** Bounded private-pilot evidence for recovery and error behavior

## 1. Purpose

This step defines the private-pilot observation plan after publication of
`pilot-0.1.0.1` and consolidation of the evidence-backed REST MVP.

The plan addresses the remaining operational gaps identified in steps 30, 31
and 36:

- verification timeout;
- Symcon restart during active Dock verification;
- temporary cloud read failures;
- token expiry and refresh during status polling;
- repeated normal operation without duplicate command delivery.

No productive PHP code and no public module contract are changed in this step.

## 2. Current Evidence Baseline

The pilot already has direct evidence for:

| Path | Evidence state |
| --- | --- |
| OAuth authorization | passed in Symcon |
| token refresh | passed in supervised authentication test |
| discovery | passed in Symcon |
| read-only status | passed in Symcon |
| Dock while already docked | passed in Symcon |
| Running to Docking to Docked | passed in Symcon |
| long-running read-only verification | passed for a normal live transition |
| command retry absence | fixture-backed static check passed |

The remaining scenarios concern resilience and recovery. They do not justify
expanding the mower command set.

## 3. Safety Boundary

All pilot observations must preserve these rules:

- Dock remains the only command sent by the module.
- Each supervised live scenario may send at most one Dock command.
- No command is resent after timeout, restart or ambiguous response.
- Verification after command acceptance uses read-only status calls only.
- The mower and station remain supervised for every physical transition.
- The official app and physical stop control remain available.
- A test stops immediately when physical behavior becomes unexpected.
- Raw responses, tokens and private identifiers remain outside the case study.

The pilot must not deliberately keep a moving mower away from its station for
15 minutes merely to reach the software timeout. That would create physical
risk without being necessary to validate the state machine.

## 4. Two-Layer Test Strategy

The remaining scenarios are split into two layers.

### Layer A: Non-actuating deterministic tests

Use a local test harness or controlled fake account transport to validate:

- exact timeout boundary;
- consecutive status-read failures followed by recovery;
- expired-token and rejected-token responses;
- command-call count across restart reconstruction;
- timer decisions with a controllable clock.

Layer A must not connect to the real mower and must not contain real OAuth
material or device identifiers.

### Layer B: Supervised live observations

Use the installed pilot module to validate:

- service restart while a real Dock return is already in progress;
- natural token refresh during read-only operation;
- a bounded series of normal Dock transitions;
- absence of duplicate user-visible command delivery.

Layer B begins only after the corresponding deterministic safety assertions
exist where practical.

## 5. Observation Matrix

| ID | Scenario | Layer | Actuator command | Priority | Current readiness |
| --- | --- | --- | --- | --- | --- |
| `OBS-01` | verification timeout | A | none | high | harness required |
| `OBS-02` | restart during active verification | A then B | one Dock in live test | high | design ready |
| `OBS-03` | temporary cloud read failures | A | none | high | harness required |
| `OBS-04` | token expiry and refresh | A then read-only B | none | high | partly ready |
| `OBS-05` | repeated normal Dock operation | B | one Dock per observation | medium | ready |

The order is intentional. Deterministic failure-path evidence comes before
additional physical testing, except for passive natural token-refresh
observation.

## 6. Common Evidence Contract

Each observation must record only the minimum sanitized evidence:

| Evidence field | Rule |
| --- | --- |
| scenario ID | use `OBS-01` through `OBS-05` |
| module commit or tag | record immutable ref only |
| start and end time | rounded timestamps are sufficient |
| precondition | state name, online flag and auth state only |
| command count | integer count; no raw command payload |
| read count | integer count where harness-supported |
| state sequence | symbolic states only |
| terminal result | `Verified`, `Verification Timeout` or explicit failure class |
| error class | sanitized category and bounded message |
| safety intervention | yes/no plus generic reason |

Never record:

- access or refresh tokens;
- OAuth authorization codes;
- client secrets;
- private device, account or command identifiers;
- raw HTTP headers or payloads;
- private Symcon ObjectIDs;
- garden, map or location data.

## 7. OBS-01: Verification Timeout

### Objective

Prove that verification remains active before the fixed deadline, terminates
at or after 900 seconds and never resends Dock.

### Procedure

Run only against a non-actuating harness with a controllable clock:

1. return an accepted Dock result from the fake account transport;
2. advance the clock through repeated `Docking` or unavailable read results;
3. execute every scheduled verification step before the deadline;
4. assert `Pending Verification` and an active verification state;
5. advance the clock to the deadline;
6. execute the final verification step;
7. assert `Verification Timeout` and an inactive timer;
8. assert that the fake transport received exactly one command call.

### Pass criteria

- no timeout occurs before the deadline;
- timeout occurs after the deadline is reached;
- the error text communicates unverified completion, not physical failure;
- command-call count remains exactly one;
- no live mower is contacted.

### Stop criteria

Any unexpected real network request or actuator call invalidates the harness
and stops the test.

## 8. OBS-02: Restart During Active Verification

### Deterministic pretest

Reconstruct an active command state with:

- fixed command start time;
- unchanged deadline;
- `CommandActive == true`;
- `CommandVerificationState == Returning`;
- previous command-call count equal to one.

Apply the equivalent of `ApplyChanges()` and assert:

- the verification timer is rescheduled;
- the deadline is not extended;
- no command is sent;
- a later `Docked` read produces `Verified`.

### Supervised live procedure

1. confirm mower supervision and safe surroundings;
2. start mowing manually through the official app;
3. confirm `Running` through a read-only module refresh;
4. send exactly one Dock command through Symcon;
5. wait until `Docking` and `Pending Verification` are observed;
6. record sanitized pre-restart state and `LastCommandAt`;
7. restart only the Symcon service once;
8. do not press Dock again after restart;
9. confirm that verification resumes with the original command timestamp;
10. observe read-only status until `Verified` or the original deadline;

### Pass criteria

- one Dock command was sent before restart;
- no Dock command was sent during or after restart;
- the original deadline remains effective;
- `Docking` remains progress;
- final `Docked` produces `Verified`;
- temporary diagnostic scripts are deleted afterward.

### Stop criteria

Stop observation and use the official app or physical stop control if the mower
behaves unexpectedly. Do not restart Symcon repeatedly during one transition.

## 9. OBS-03: Temporary Cloud Read Failures

### Objective

Prove that temporary status-read failures preserve bounded verification and do
not trigger a second mower command.

### Procedure

Use the non-actuating harness:

1. return one accepted command result;
2. return two classified transport or API failures for `GetStatus`;
3. verify that the command remains pending before the deadline;
4. verify that each subsequent operation is read-only;
5. return `Docking` and then `Docked`;
6. verify final `Verified` state;
7. assert one command call and the expected number of read calls.

Also test an uninterrupted failure sequence through the timeout boundary.

### Pass criteria

- transient read failures do not end verification early;
- failures are visible in bounded sanitized diagnostics;
- recovery to `Docking` and `Docked` remains possible;
- continuous failure ends as `Verification Timeout`;
- command-call count remains one.

### Live boundary

Do not deliberately disable the network, change the productive Base URL or
damage live credentials during an active mower transition. Naturally occurring
cloud failures may be recorded, but they must not be induced for this pilot.

## 10. OBS-04: Token Expiry and Refresh

### Deterministic tests

The harness should cover:

| Case | Expected result |
| --- | --- |
| valid token before margin | read succeeds |
| refresh succeeds before expiry | expiry moves forward; polling remains active |
| access token expired | read fails as authentication without command retry |
| API rejects token | `ReauthRequired` becomes true |
| refresh transport failure | offline/warning diagnostics; no secret exposure |
| refresh token rejected | reauthorization is required |

### Passive live observation

While the mower is docked:

1. record only authentication state, rounded token-expiry time,
   `LastRestSuccess` and error counter;
2. wait for the module's normal scheduled refresh;
3. confirm that token expiry moves forward;
4. confirm that read-only polling continues;
5. confirm that no reauthorization is requested;
6. inspect logs only for sanitized error categories.

No mower command is required for the live part of this scenario.

### Pass criteria

- scheduled refresh completes before token use becomes invalid;
- polling continues with the refreshed token;
- failures are classified as offline, warning or reauthorization-required;
- tokens and client credentials never appear in evidence or logs.

## 11. OBS-05: Repeated Normal Operation

### Objective

Detect duplicate command delivery or state leakage across ordinary pilot use.

### Sample size

Observe at most three normal transitions over separate real usage occasions.
Do not create artificial mowing runs solely to increase the sample count.

### Procedure per observation

1. confirm supervision and `Running` state;
2. record the previous terminal command result;
3. perform one Dock action in Symcon;
4. perform no further command action;
5. observe `Accepted -> Pending Verification -> Verified`;
6. record one sanitized command-response event;
7. confirm that the next normal status poll remains read-only;
8. confirm that a later independent Dock action starts with clean internal
   command state.

### Pass criteria

- each user action produces one command request;
- no automatic command retry occurs;
- every successful run reaches one terminal result;
- command state from a previous run does not block the next valid run;
- no growing or unsanitized diagnostic payload is retained.

## 12. Roles and Tool Boundary

### User actions

The user must:

- supervise the mower and station;
- start mowing manually in the official app when required;
- confirm physical state and safety readiness;
- restart the Symcon service for `OBS-02`;
- operate the official app or physical stop control if intervention is needed.

### Agent and MCP actions

The agent may:

- perform read-only prechecks and state observations;
- create temporary scripts that expose sanitized PASS/FAIL summaries;
- send one Dock command only after an explicit live safety confirmation;
- compare command timestamps and public result variables;
- inspect bounded diagnostics without returning private ObjectIDs;
- delete every temporary test script after the observation.

MCP must not be used to alter live credentials, simulate network failure or
restart the Symcon host without an explicit user action and recovery plan.

## 13. Result Classification

Each scenario receives one of these outcomes:

| Outcome | Meaning |
| --- | --- |
| `PASS` | all criteria met with complete sanitized evidence |
| `PASS WITH LIMITATION` | safety property passed but observability was incomplete |
| `FAIL` | expected state, bound or no-retry property was violated |
| `ABORTED SAFELY` | physical or operational stop criterion was used |
| `NOT EXECUTABLE` | required deterministic test capability does not yet exist |

`ABORTED SAFELY` is not a module failure by itself. It records that safety took
priority over evidence collection.

## 14. Release Impact

Private-pilot use may continue under the existing supervision boundary while
this plan is executed.

A broader release remains blocked until at least:

- `OBS-01` passes deterministically;
- `OBS-02` passes deterministic restart recovery and one supervised live run;
- `OBS-03` passes deterministic failure and recovery;
- `OBS-04` passes deterministic auth cases and passive live refresh;
- `OBS-05` has no duplicate-delivery finding.

Any duplicate command delivery is a release blocker and requires immediate
suspension of live command testing.

## 15. Architecture Decisions

### AD-NAV-071: Separate deterministic failure tests from physical tests

**Decision:** Validate timeout, cloud failure and token-error paths without a
real mower before considering any related live observation.

**Rationale:** These paths can be reproduced with controlled time and transport
responses. Deliberately creating physical or credential failures adds risk but
does not improve state-machine evidence.

**Consequence:** A non-actuating observation harness is required before the
full pilot matrix can pass.

### AD-NAV-072: Do not induce a physical timeout

**Decision:** Do not obstruct or delay the mower solely to trigger the
15-minute verification timeout.

**Rationale:** Timeout is a software boundary and can be tested with a fake
clock. A physical timeout experiment would be disproportionate.

**Consequence:** `OBS-01` is harness-only for the private pilot.

### AD-NAV-073: Restart evidence must prove no command replay

**Decision:** Accept restart recovery only when the original deadline and
command timestamp remain stable and no second command call is observed.

**Rationale:** Resuming read-only verification is required; replaying an
actuator command after restart is not.

**Consequence:** Restart testing needs command-call counting or equivalent
sanitized transport evidence.

### AD-NAV-074: Observe natural token refresh without mower movement

**Decision:** Perform the live token-refresh observation while the mower is
docked and use no command.

**Rationale:** Authentication lifecycle evidence is independent of physical
mower movement.

**Consequence:** `OBS-04` can gain live evidence without actuator risk.

### AD-NAV-075: Bound repeated pilot operation

**Decision:** Limit planned repeated normal observations to three naturally
occurring use occasions.

**Rationale:** The objective is to detect state leakage and duplicate delivery,
not to create unnecessary mower cycles.

**Consequence:** Additional runs require a specific finding or hypothesis.

## 16. Recommended Next Step

Create:

```text
38-pilot-observation-harness-design.md
```

That step should design the smallest non-productive test seam needed for:

- controllable time;
- fake account responses;
- command and read call counting;
- restart-state reconstruction;
- deterministic timeout, cloud-failure and token-expiry tests.

No live mower test should be started for the unresolved failure scenarios
before that design is reviewed.
