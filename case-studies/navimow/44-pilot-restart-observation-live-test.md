# 44 Pilot Restart Observation Live Test

**Case study:** Navimow native IP-Symcon module
**Status:** Safely concluded before restart; OBS-02 restart evidence remains pending
**Date:** 2026-07-10
**Scope:** Supervised attempt to restart Symcon during active Dock verification

## 1. Purpose

This step attempts the supervised `OBS-02` live restart scenario authorized by
`43-pilot-recovery-hardening-symcon-smoke-test-report.md`.

The target evidence was:

- confirm Running;
- send exactly one Dock command;
- observe Docking and Pending Verification;
- restart the Symcon service once during active verification;
- send no command after restart;
- confirm preserved command timestamp and final Verified state.

The mower reached Docked before the service restart was performed. The restart
part of the scenario was therefore deliberately not executed.

## 2. Safety Preconditions

The user confirmed:

- the mower was supervised;
- the station and operating area were in view;
- the mower was started manually through the official app;
- the official app and physical stop control remained available.

The test retained the existing safety boundary:

- one Dock command maximum;
- no command retry;
- read-only observations after acceptance;
- no restart after the verification had become terminal.

## 3. Read-Only Precheck

Before manual start, Symcon confirmed:

| Check | Result |
| --- | --- |
| account connection | `Connected` |
| reauthorization required | false |
| vehicle state | `Docked` |
| online | true |
| battery | 95 percent |

The temporary precheck script was deleted after result read-back.

## 4. Running Confirmation

After the user started mowing manually, a read-only module refresh confirmed:

| Check | Result |
| --- | --- |
| account connection | `Connected` |
| reauthorization required | false |
| vehicle state | `Running` |
| online | true |
| battery | 95 percent |

This satisfied the command safety gate.

## 5. Single Dock Command

Exactly one Dock command was sent through the Symcon module.

Observed sanitized result:

```text
Dock command was accepted.
```

Immediate assertions:

| Check | Result |
| --- | --- |
| command | `Dock` |
| command result | `Accepted` |
| command timestamp | advanced once |
| command count issued by test | one |

The test did not send another command afterward.

## 6. Active Verification Observation

A subsequent read-only observation confirmed:

| Variable | Observed value |
| --- | --- |
| `VehicleState` | `Docking` |
| `LastCommandResult` | `Pending Verification` |
| `Online` | true |
| `LastCommandAt` | captured for post-restart comparison |

At this point the intended next action was one manual Symcon service restart.

## 7. Restart Decision

Before the service restart was performed, the mower physically reached its
station.

The user asked whether a restart still provided useful evidence. The decision
was no.

Rationale:

- the target was recovery of an active verification state;
- Docked is a terminal physical state;
- the module had enough time to complete verification normally;
- restarting after completion would test ordinary module startup, already
  covered by step 43, not active command recovery.

The Symcon service was therefore not restarted for this attempt.

## 8. Terminal Read-Only Result

The final read-only check confirmed:

| Variable | Result |
| --- | --- |
| `VehicleState` | `Docked` |
| `LastCommandResult` | `Verified` |
| `Online` | true |
| `LastCommandAt` | identical to captured command timestamp |
| `LastCommandError` | empty |

This confirms a successful normal hardened transition with one command and no
duplicate delivery.

## 9. Result Classification

### Normal transition

**Result: PASS.**

The updated module handled:

```text
Running -> Accepted -> Docking / Pending Verification -> Docked / Verified
```

with one Dock command and stable command diagnostics.

### `OBS-02` live restart

**Result: ABORTED SAFELY / NOT COMPLETED.**

No restart occurred while verification was active. The test therefore adds no
direct live restart-recovery evidence.

This is not a module failure. It records that the physical transition
completed before the required observation action could be performed.

## 10. Safety and Privacy Review

Confirmed:

- mower remained supervised;
- exactly one Dock command was sent;
- no command was sent after Docking observation;
- no unnecessary service restart was performed after terminal success;
- no token, private device identifier or ObjectID entered the report;
- no raw cloud response was retained;
- all temporary Symcon scripts were deleted.

## 11. Gate Decision

The hardened normal Dock transition remains approved for controlled pilot use.

The supervised restart observation gate remains open because direct live
restart evidence is still missing.

Deterministic restart evidence from step 41 remains green, but it is not
reclassified as a live pass.

## 12. Architecture Decisions

### AD-NAV-105: Do not restart after terminal completion

**Decision:** Skip the service restart once the mower has reached Docked and
the module can complete verification normally.

**Rationale:** A post-terminal restart does not exercise active-state recovery
and adds operational disruption without relevant evidence.

**Consequence:** This attempt remains incomplete for `OBS-02`.

### AD-NAV-106: Record the attempt as safely concluded

**Decision:** Preserve the observed transition and explicitly classify the
restart portion as not completed.

**Rationale:** Test reports must distinguish missing evidence from failure and
must not claim a restart that did not occur.

**Consequence:** A later naturally suitable transition may repeat `OBS-02`.

### AD-NAV-107: Retain the successful normal transition evidence

**Decision:** Accept the one-command Running-to-Verified sequence as a useful
regression result.

**Rationale:** It confirms that the published hardening did not regress normal
Dock behavior.

**Consequence:** Only restart recovery remains unresolved.

## 13. Recommended Next Step

Create:

```text
45-pilot-restart-observation-live-retest.md
```

Run it only during a natural supervised mowing occasion with enough expected
return time to restart Symcon while Docking remains active.

Before that attempt:

- keep the Windows service control ready before sending Dock;
- pre-stage read-only MCP probes;
- send one Dock command only after Running confirmation;
- restart immediately after the first Docking confirmation;
- do not create an artificial long route or obstruct the mower merely to
  extend the test window.
