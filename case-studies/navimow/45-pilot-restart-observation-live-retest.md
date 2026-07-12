# 45 Pilot Restart Observation Live Retest

**Case study:** Navimow native IP-Symcon module
**Status:** Supervised active-verification restart passed
**Date:** 2026-07-10
**Scope:** Direct Symcon restart while Dock verification is active

## 1. Purpose

This step repeats the incomplete restart observation from
`44-pilot-restart-observation-live-test.md` with a naturally longer return
path.

The target is direct evidence that the published hardening build:

- persists active Dock verification;
- survives one Symcon service restart;
- does not replay the Dock command;
- resumes read-only verification automatically;
- preserves the original command timestamp;
- reaches final `Verified` after Docked.

## 2. Tested Build

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Published commit:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

The module had already passed the read-only Symcon smoke test in step 43.

## 3. Safety Preparation

The user prepared the test as follows:

- mower and station remained supervised;
- official app and physical stop control remained available;
- Windows service control was opened before the command;
- mower was started manually through the official app;
- mower was allowed to mow naturally farther from the station;
- no Stop or Pause command was used to prolong the return;
- no obstacle or artificial route extension was introduced.

The user explicitly confirmed:

```text
läuft weiter entfernt, beaufsichtigt, Symcon-Dienst bereit
```

## 4. Running Gate and Dock Command

One temporary Symcon script performed this bounded sequence:

1. execute one read-only status refresh;
2. require account `Connected` and no reauthorization;
3. require vehicle state `Running` and online;
4. record the preceding command timestamp;
5. send exactly one Dock command;
6. require accepted command result and one advanced command timestamp.

Observed sanitized result:

| Check | Result |
| --- | --- |
| account connection | `Connected` |
| vehicle pre-state | `Running` |
| Dock calls issued by test | one |
| cloud/module response | `Dock command was accepted.` |
| immediate command result | `Accepted` |
| command timestamp | advanced once and captured |

The temporary command script was deleted after read-back.

## 5. Active Verification Gate

Before restart, a separate read-only observation confirmed:

| Variable | Observed value |
| --- | --- |
| `VehicleState` | `Docking` |
| `LastCommandResult` | `Pending Verification` |
| `Online` | true |
| `LastCommandAt` | matched the captured command timestamp |

This confirmed that the restart would occur during the intended active
verification state rather than after terminal completion.

The temporary observation script was deleted.

## 6. Symcon Service Restart

After the explicit instruction `JETZT NEU STARTEN`, the user:

- restarted the IP-Symcon service exactly once;
- sent no further mower command;
- performed no action in the official app;
- continued supervising the mower.

The Symcon runtime became reachable again without manual module repair or
reauthorization.

## 7. Immediate Post-Restart State

The first post-restart probe intentionally performed no status refresh.

It read only persisted and current public state.

Observed sanitized result:

| Check | Result |
| --- | --- |
| account instance | active |
| device instance | active |
| account connection | `Connected` |
| reauthorization required | false |
| `VehicleState` | `Docking` |
| `LastCommandResult` | `Pending Verification` |
| `Online` | true |
| `LastCommandAt` | unchanged |
| `LastCommandError` | empty |

This proves that active command state survived object reconstruction and that
restart did not reset or terminally fail verification.

## 8. Automatic Verification Resume

After the immediate post-restart read, the test waited without invoking:

- `RefreshStatus()`;
- `VerifyCommand()`;
- Dock;
- any official-app action.

The next probe only read public variables.

Observed result:

| Variable | Final value |
| --- | --- |
| `VehicleState` | `Docked` |
| `LastCommandResult` | `Verified` |
| `Online` | true |
| `LastCommandAt` | unchanged from the single Dock action |
| `LastCommandError` | empty |

The transition to `Verified` therefore came from the restored module timer and
its read-only verification path.

## 9. No-Command-Replay Evidence

Confirmed:

- the test invoked Dock exactly once before restart;
- no script invoked Dock after restart;
- the command timestamp did not change across restart;
- command result continued from `Pending Verification` to `Verified`;
- no second Requested or Accepted phase was observed;
- no command error appeared.

Together with the deterministic transport call counter from step 41, this is
direct runtime evidence that restart resumes verification rather than command
delivery.

## 10. Result Classification

### `OBS-02` supervised live restart

**Result: PASS.**

Observed sequence:

```text
Running
-> one Dock command
-> Accepted
-> Docking / Pending Verification
-> Symcon service restart
-> Docking / Pending Verification with same command timestamp
-> automatic timer verification
-> Docked / Verified
```

All required restart acceptance criteria from steps 37 and 40 were met.

## 11. Safety and Privacy Review

Confirmed:

- mower remained supervised throughout;
- no Stop or Pause command was used;
- no route was artificially extended;
- exactly one Dock command was sent;
- no command was sent after restart;
- no private ObjectID, device ID or timestamp is recorded in this report;
- no token or raw API response entered the result channel;
- all temporary Symcon scripts were deleted.

No safety intervention was required.

## 12. Gate Decisions

### Live restart recovery gate

**Decision: PASS.**

The deterministic restart behavior now has matching direct Symcon evidence.

### Hardening runtime gate

**Decision: PASS for active normal-duration restart recovery.**

The published hardening build preserved state, resumed read-only verification
and reached terminal success.

### Broader release gate

**Decision: remains pending.**

The restart result closes `OBS-02`, but the private-pilot matrix still requires
a status review of passive token-refresh evidence and repeated normal
operation before deciding whether to create another immutable pilot tag or
broaden release scope.

## 13. Architecture Decisions

### AD-NAV-108: Extend return time through natural operation

**Decision:** Let the mower move naturally farther from the station before
Dock, without Stop, Pause or obstruction.

**Rationale:** Additional actuator commands or artificial delay would change
the tested transition and weaken safety evidence.

**Consequence:** The restart window was long enough without modifying command
semantics.

### AD-NAV-109: Read persisted state before any post-restart refresh

**Decision:** Make the first post-restart probe observational only.

**Rationale:** A manual refresh could mask whether active state and timer
recovery occurred through the module lifecycle.

**Consequence:** The observed Pending Verification state is attributable to
persisted recovery state.

### AD-NAV-110: Attribute final verification to the restored timer

**Decision:** Wait for automatic state change and avoid manual verification
calls after restart.

**Rationale:** The target behavior is autonomous recovery after service start.

**Consequence:** Final Verified provides direct timer-resume evidence.

### AD-NAV-111: Close live restart evidence separately from release

**Decision:** Mark `OBS-02` passed without declaring broad release readiness.

**Rationale:** Other pilot observations and packaging decisions remain
independent gates.

**Consequence:** The next step reviews the complete observation matrix.

## 14. Recommended Next Step

Create:

```text
46-private-pilot-observation-status-review.md
```

That step should map current evidence to `OBS-01` through `OBS-05`, identify
which pilot gates remain open and recommend whether to:

- collect passive token-refresh evidence;
- complete naturally occurring repeated-operation observations;
- create `pilot-0.1.0.2`;
- or retain the current controlled-pilot boundary.
