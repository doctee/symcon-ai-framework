# 27 Dock Transition Verification Design

**Case study:** Navimow native IP-Symcon module
**Status:** Design decision for implementation
**Date:** 2026-07-09
**Scope:** Dock command verification after an accepted REST command

## 1. Purpose

This document defines the next verification design for the Dock command.

The preceding transition capture proved that a real command can be accepted
successfully while the mower remains in `isDocking` for a significant period
before reaching `isDocked`.

The design goal is therefore to replace the current short one-shot terminal
check with a bounded, read-only verification state machine.

No productive PHP code is changed in this step.

## 2. Evidence Base

The live transition capture in `26-command-transition-capture-report.md`
observed:

```text
isRunning -> isDocking -> isDocked
```

with:

| Time after command | Observed state |
| --- | --- |
| 5 seconds | `isDocking` |
| 15 seconds | `isDocking` |
| 30 seconds | `isDocking` |
| 60 seconds | `isDocked` |

The command response itself returned HTTP 200 and nested command
`status == "SUCCESS"`.

The live evidence confirms that:

- command acceptance and physical completion are separate events;
- `isDocking` is a valid progress state;
- five seconds is too short for terminal verification;
- repeating the Dock command is not required and would be unsafe.

## 3. Additional Field Assumption

The working assumption for the next design is:

> `isDocking` may represent the full physical return-to-station phase.

Depending on garden layout, mower position, routing and obstacle handling,
that phase may last several minutes. A duration around ten minutes is plausible
and must not be treated as a failed command by itself.

This assumption is not yet backed by a ten-minute live capture. It is accepted
as a safety-oriented design input because it prevents false failure reporting
and avoids unsafe repeated actuator commands.

## 4. Design Boundary

The verification mechanism may:

- perform repeated read-only status requests;
- update module-owned diagnostics;
- update command result variables;
- stop on terminal success, terminal failure or timeout;
- resume safely after `ApplyChanges()` or service restart.

It must not:

- resend the Dock command;
- send any Start, Stop, Pause or Resume command;
- depend on private ObjectIDs;
- require a private capture script;
- store raw API payloads in public variables;
- loop indefinitely.

## 5. Verification State Machine

The Dock verification should be modelled as an explicit internal state machine.

Recommended internal states:

| State | Meaning |
| --- | --- |
| `Idle` | No command verification is active. |
| `Accepted` | Dock command response was accepted, but no status check has completed yet. |
| `Returning` | Status confirms a valid in-progress return, normally `isDocking`. |
| `Verified` | Status confirms `isDocked`. |
| `AlreadyInState` | Dock command found the mower already docked. |
| `TimedOut` | The maximum verification window elapsed without terminal success. |
| `Failed` | A terminal command or status error was detected. |

The public command-result variable may remain compact, but the internal
verification state must be recoverable and diagnosable.

## 6. Transition Rules

Recommended transitions:

| From | Event | To |
| --- | --- | --- |
| `Idle` | Dock command accepted | `Accepted` |
| `Idle` | Dock command already in state | `AlreadyInState` |
| `Accepted` | status is Docking | `Returning` |
| `Accepted` | status is Docked | `Verified` |
| `Returning` | status is Docking | `Returning` |
| `Returning` | status is Docked | `Verified` |
| `Accepted` or `Returning` | timeout exceeded | `TimedOut` |
| any active state | terminal error | `Failed` |
| terminal state | diagnostics recorded | `Idle` for next command |

Unknown status values during verification should not immediately resend a
command. They should be classified as ambiguous read evidence and handled
inside the bounded verification window.

## 7. Timing Model

The implementation should use a bounded timer-driven schedule.

Recommended first implementation:

| Offset | Purpose |
| --- | --- |
| 5 seconds | confirm that the command produced progress or terminal success |
| 15 seconds | early progress check |
| 30 seconds | normal progress check |
| 60 seconds | first long-running completion check |
| then every 60 seconds | continued long-running return observation |
| 15 minutes maximum | terminal timeout boundary |

Rationale:

- The first live evidence reached Docked at 60 seconds.
- The user's field assumption indicates that several minutes may be normal.
- A 15-minute maximum is long enough for plausible return paths while still
  bounded.
- Read-only status polling is safe to repeat; actuator commands are not.

The timeout should be configurable only if a clear user-facing need appears.
For the first implementation, a fixed documented maximum is simpler and more
reviewable.

## 8. Public Variable Behavior

Recommended user-visible behaviour after pressing Dock:

| Phase | `LastCommandResult` | Notes |
| --- | --- | --- |
| command accepted | `Accepted` | REST command accepted, physical completion pending. |
| status is Docking | `Accepted` or future `In Progress` | Do not report failure. |
| status is Docked | `Verified` | Physical completion confirmed. |
| already docked | `Already In State` | No transition required. |
| timeout | `Verification Timeout` | Command was not resent. |
| terminal error | `Failed` | Error text should explain the source. |

The existing profile can remain unchanged for the next implementation by
keeping `Accepted` visible during `isDocking`. A later refinement may add an
explicit `In Progress` association if live testing shows that users need that
distinction.

## 9. Persistence and Recovery

Because verification can span many minutes, the module must persist enough
state to recover after:

- `ApplyChanges()`;
- Symcon service restart;
- module update;
- temporary cloud read failure.

Minimum internal metadata:

| Field | Purpose |
| --- | --- |
| command kind | Confirms that the active verification belongs to Dock. |
| command timestamp | Calculates elapsed time and timeout. |
| deadline timestamp | Avoids extending the window accidentally after restart. |
| last observed verification state | Makes recovery transparent. |
| last status timestamp | Diagnoses stale or failing status reads. |

On restart, the module should resume read-only verification when the deadline
has not expired. It must not resend the Dock command.

## 10. Error Handling

Read failures during verification should be treated differently from command
failures.

Recommended behaviour:

- transient read errors keep verification active until the deadline;
- the latest read error is stored in bounded diagnostics;
- repeated read errors do not trigger another Dock command;
- terminal command rejection remains a command failure;
- timeout communicates uncertainty, not a guaranteed physical failure.

This follows SAEF's retry guidance: read operations may be repeated within a
clear bound; actuator commands must not be retried automatically.

## 11. Architecture Decisions

### AD-NAV-048: `isDocking` is a valid long-running state

**Decision:** Treat `isDocking` as legitimate progress for the full
return-to-station phase.

**Rationale:** The live capture already showed `isDocking` for at least
30 seconds, and field behaviour may plausibly extend to many minutes.

**Consequence:** `isDocking` must not produce `Verification Timeout` before
the documented verification deadline.

### AD-NAV-049: Dock verification is read-only after command acceptance

**Decision:** After a Dock command is accepted, the module verifies only with
status reads.

**Rationale:** The REST command produced an accepted response and observable
physical progress. Repeating actuator commands would add risk without evidence
that it is necessary.

**Consequence:** No automatic Dock retry is allowed in the MVP.

### AD-NAV-050: Verification timeout is a bounded uncertainty result

**Decision:** Timeout means "terminal Docked state was not confirmed within
the verification window", not "Dock command definitely failed".

**Rationale:** The cloud API and physical return path are asynchronous. The
module can only report what it has verified.

**Consequence:** Error wording and diagnostics must avoid overclaiming.

## 12. Test Plan for Implementation

The implementation step should include:

- fixture-backed parser tests for `isDocking`, `isDocked` and command
  `SUCCESS`;
- static validation that Dock verification schedules read-only status checks;
- static validation that no command retry path exists;
- local distribution validation;
- a supervised Symcon test with the mower already docked;
- a supervised Symcon transition test only after the read-only verification
  state machine is implemented.

The transition test should accept long `isDocking` durations and should not
require observing Docked within the first minute.

## 13. Implementation Recommendation

Proceed with a narrow implementation step:

1. add persistent internal verification metadata;
2. replace the one-shot five-second verification with timer-driven read-only
   verification;
3. treat Docking as progress;
4. keep the maximum verification window fixed at 15 minutes;
5. publish and test first with an already-docked case;
6. only then run a supervised Running-to-Docked Symcon test.

Suggested next SAEF step:

```text
28-dock-transition-verification-implementation.md
```
