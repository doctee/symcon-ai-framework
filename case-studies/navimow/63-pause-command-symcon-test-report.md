# 63 Pause Command Symcon Test Report

**Case study:** Navimow native IP-Symcon module
**Status:** Published Pause implementation passed direct supervised Symcon test
**Date:** 2026-07-12
**Scope:** Read-only compatibility gate and one Running-to-Paused command

## 1. Purpose

This report executes the direct IP-Symcon test defined by
`61-pause-command-publication-and-symcon-test-plan.md` after publication in
`62-pause-command-publication.md`.

It verifies:

- loading of the published module update;
- authentication and read-only status transport;
- stable public variable identity and archive configuration;
- availability of Pause while later commands remain disabled;
- exactly one supervised Pause invocation;
- independent current Paused-state verification;
- physical stopping and official-app cleanup.

## 2. Tested Publication

Repository and branch:

```text
https://github.com/doctee/symcon-navimow.git
main
```

Commit:

```text
e82f73f752c4b588f13a5fb5331413279d2b77f7
feat: add bounded Pause command
```

The user manually updated the module on the private Win11 IP-Symcon host before
the test.

## 3. Test Method

The established sanitized MCP marker pattern was used:

1. create a temporary IP-Symcon PHP script;
2. discover module instances by module GUID;
3. perform bounded assertions in `try/catch`;
4. write only symbolic PASS/FAIL markers into the script name;
5. read the marker through MCP;
6. delete the temporary script.

Three isolated phases were used:

- read-only smoke and compatibility check;
- one live Pause invocation and read-only result observation;
- read-only cleanup-state confirmation.

No numeric ObjectID, token, device identifier, hostname or raw API response was
returned in a public marker or copied into this report.

## 4. Read-Only Smoke Result

Observed sanitized marker:

```text
Navimow Pause Smoke PASS M1 A1 S1 I1 T1 R1 L5 H1
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `M1` | account, configurator and device modules and instances available |
| `A1` | Pause exists; Resume, Stop and Start functions are absent |
| `S1` | authenticated status refresh and mapped values valid |
| `I1` | all eight stable variable ObjectIDs unchanged across `ApplyChanges()` |
| `T1` | variable types and profiles unchanged |
| `R1` | Archive Control logging and aggregation settings unchanged |
| `L5` | five stable variables currently have archive logging enabled |
| `H1` | archive histories for logged variables remain queryable |

The selected account and device instances reported active status. The account
remained Connected and did not require reauthorization.

## 5. Compatibility Detail

Stable device Idents checked:

```text
VehicleState
Online
BatteryLevel
LastStatusUpdate
LastCommand
LastCommandAt
LastCommandResult
LastCommandError
```

For each Ident, the test privately captured its current ObjectID, type, profile,
logging flag and aggregation type. It then called `IPS_ApplyChanges()` on the
updated device instance and compared the resulting objects.

Results:

- every ObjectID remained identical;
- every type remained identical;
- every effective profile remained identical;
- every logging flag remained identical;
- every aggregation type remained identical;
- logged histories remained readable;
- command diagnostics and REST error count were unchanged by the read-only
  status refresh.

### Baseline limitation

No numeric ObjectID snapshot was captured before the user had already updated
the module to `e82f73f`.

Therefore, this test directly proves idempotent identity across
`ApplyChanges()` on the published build and continued availability of existing
archive histories. It does not compare two retained numeric ID lists from
before and after the repository update.

The continued five-variable logging configuration and queryable histories are
strong operational evidence that the existing variables were retained. The
report does not overstate this as a separately captured pre-update ID proof.

## 6. Physical Safety Preconditions

Before actuation, the user confirmed:

- mowing had been started manually in the official Navimow app;
- the mower and area were supervised;
- the official app and physical safety control were available.

The Symcon test performed an additional current read-only state check and
continued only after Running was confirmed.

## 7. Single Pause Invocation

The temporary live script implemented one bounded path:

```text
explicit status refresh
  -> require Running
  -> call NAVDV_Pause() once
  -> never call it again
```

Observed dispatch marker:

```text
Navimow Pause Live SENT W1 A1
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `W1` | `LastCommandAt` advanced after the single invocation |
| `A1` | the module returned an accepted Pause result |

The test script contained exactly one call to `NAVDV_Pause()`. After execution,
its content was replaced with a read-only observer before any further run.

No retry or second manual action was issued.

## 8. Automatic Verification Result

Observed terminal marker:

```text
Navimow Pause Observe C4 R5 S4 E1 O1 B1 T1
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `C4` | `LastCommand` is Pause |
| `R5` | `LastCommandResult` is Verified |
| `S4` | current `VehicleState` is Paused |
| `E1` | `LastCommandError` is empty |
| `O1` | mower remains online |
| `B1` | battery remains within the valid range |
| `T1` | successful status observation is not older than command dispatch |

The final state came from normal status polling. Command acceptance did not
write Paused directly into `VehicleState`.

`Pending Verification` was not captured as a stable UI observation. Paused was
reached before the terminal marker was read, which is allowed by the plan and
consistent with the earlier two-second private capture.

## 9. Physical Observation

The user confirmed:

```text
Mower visibly stopped.
Official app reported Paused.
```

This independently agrees with the REST state and Symcon command result.

The test therefore has three distinct evidence layers:

- cloud command acceptance;
- current REST/Symcon Paused state;
- direct physical observation.

## 10. Cleanup

No Resume or Dock action was sent through Symcon.

The user selected return to station through the official Navimow app while
continuing supervision.

Final read-only cleanup marker:

```text
Navimow Pause Cleanup PASS C4 R5 S5 E1
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `C4` | the tested Symcon command remains Pause |
| `R5` | its terminal result remains Verified |
| `S5` | current mower state is Docking |
| `E1` | Pause diagnostic error remains empty |

This confirms that the official-app cleanup changed physical device state
without creating another Symcon command diagnostic.

All temporary test scripts were deleted after their markers were read.

## 11. Acceptance Criteria

| Criterion | Result |
| --- | --- |
| published commit loaded | passed |
| account and device instances active | passed |
| authenticated read-only status | passed |
| Pause available | passed |
| Resume, Stop and Start unavailable | passed |
| public variable identity across `ApplyChanges()` | passed |
| types and profiles preserved | passed |
| archive logging and aggregation preserved | passed |
| logged histories queryable | passed |
| current Running before Pause | passed |
| exactly one explicit Pause invocation | passed |
| cloud acceptance | passed |
| later current Paused state | passed |
| terminal Verified result | passed |
| physical mower stop | passed |
| empty command error | passed |
| official-app cleanup | passed; return to station observed as Docking |
| temporary object cleanup | passed |

## 12. Risks and Residual Limits

| Risk or limitation | Consequence |
| --- | --- |
| one mower and one direct Symcon transition | broader device coverage remains unknown |
| no natural Pause rejection captured | defensive failure behavior remains deterministic-test evidence |
| no live Pause timeout | 60-second timeout remains harness-backed |
| no service restart during Pause | restart recovery remains deterministic-test evidence |
| no pre-update numeric ObjectID snapshot | update identity is inferred from retained history and directly proven only across post-update `ApplyChanges()` |
| undocumented private cloud API | behavior may change without notice |
| vendor OAuth clarification unresolved | broader release remains blocked |
| Resume untested | Paused must not be treated as evidence for Resume |

## 13. Architecture Decisions

### AD-NAV-187: Accept immediate terminal verification without requiring visible Pending

**Decision:** Treat current Paused plus Verified as complete even when Pending
Verification was too brief to observe manually.

**Rationale:** Pending is an internal transition, while the fresh terminal state
is the stronger evidence.

**Consequence:** Fast successful transitions do not require artificial delay.

### AD-NAV-188: Keep physical confirmation separate from API verification

**Decision:** Record the user's visible stop confirmation in addition to the
module result.

**Rationale:** A physical command test should not rely solely on cloud and
software state.

**Consequence:** The live gate includes independent operational evidence.

### AD-NAV-189: Perform cleanup only through the official app

**Decision:** Do not send Resume or Dock from Symcon after the Pause test.

**Rationale:** A second Symcon command would weaken the one-write evidence and
expand the test boundary.

**Consequence:** The final Docking state is cleanup context, not another module
command test.

### AD-NAV-190: Preserve the baseline limitation explicitly

**Decision:** Distinguish post-update `ApplyChanges()` identity proof from a
missing retained pre-update ID list.

**Rationale:** Archive continuity is strong evidence, but it is not a numeric
before/after snapshot.

**Consequence:** Future update tests should capture anonymized equality
baselines before the user triggers the module update.

## 14. Gate Decisions

### Read-only compatibility gate

**PASS.**

The published module loads, authenticates, reads status and preserves the
stable variable and archive contract across `ApplyChanges()`.

### Supervised Pause gate

**PASS.**

Exactly one explicit Pause invocation was accepted and independently verified
as current Paused. The mower visibly stopped and the module reported no error.

### Broader command gate

**NO-GO.**

Resume, Stop and Start remain outside this evidence and must pass their own
SAEF analysis, capture, implementation and live-test gates.

### Release gate

**NO-GO for broader or Store release.**

Pause success does not resolve public OAuth or complete the planned command
set. The existing private pilot may continue on published `main`.

## 15. Recommended Next Step

Create SAEF step `64-pause-integration-review-and-resume-readiness.md` to:

1. consolidate publication, fixture, deterministic and live Pause evidence;
2. decide whether the current untagged build should receive a new private pilot
   tag now or only after Resume;
3. confirm existing Dock regression evidence remains sufficient;
4. define the independent Resume evidence and movement-safety gate;
5. retain Store preparation as planning-only until the supported command set is
   complete and public OAuth is resolved.
