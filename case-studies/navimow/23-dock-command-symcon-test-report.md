# 23 Dock Command Symcon Test Report

**Case study:** Navimow native IP-Symcon module  
**Status:** Supervised Dock command gate passed  
**Date:** 2026-07-09  
**Test boundary:** One Dock command was sent while the mower was already
docked and supervised. No other mower command was enabled or executed.

## 1. Purpose

This report records the first direct IP-Symcon test of a write operation in
the Navimow case study.

It verifies the Dock-first implementation from
`22-dock-command-implementation.md` against a real account and mower without
publishing private installation data.

## 2. Tested Distribution

The canonical distribution was synchronized to the dedicated module
repository:

```text
https://github.com/doctee/symcon-navimow
```

Published module commit:

```text
43e248e feat: add supervised Dock command path
```

IP-Symcon was manually updated from branch `main` before the test.

## 3. Test Environment

The test used:

- the existing authenticated Navimow Account instance;
- one configured Navimow Device instance;
- the installed public module distribution;
- a real mower already standing in its charging station;
- direct physical supervision by the user;
- MCP only for bounded test-script execution and sanitized result read-back.

No private hostname, ObjectID, token, device ID, mower name or account
identifier is recorded.

## 4. Safety Preconditions

Confirmed before sending the command:

- the updated `NAVDV_Dock()` module function was available;
- exactly one configured device instance was present;
- a fresh read-only status request succeeded;
- `VehicleState` reported `Docked`;
- the mower was visibly docked;
- the user confirmed active supervision;
- the user explicitly authorized exactly one Dock command.

The preflight result was:

```text
PASS Dock available, status fresh, state Docked
```

No command was sent during preflight.

## 5. Result Channel

As in the previous live test, generic script-execution acknowledgement was not
treated as proof.

The test procedure:

1. created one temporary private IP-Symcon script;
2. found the configured device by module ID and configured `DeviceId`;
3. invoked `NAVDV_Dock()` exactly once;
4. stored only bounded PASS/FAIL and public enum values in the script name;
5. read the script object name through MCP;
6. replaced the script content with read-only verification logic;
7. waited for the module-owned one-shot timer;
8. read only documented public variables by Ident;
9. deleted the temporary script.

No private runtime value entered this report.

## 6. Command Submission Result

The single Dock invocation produced:

| Check | Result |
| --- | --- |
| Module function available | passed |
| Exactly one configured device selected | passed |
| Dock invoked once | passed |
| Immediate command result | `Already In State` |
| Command transport exception | none |
| Automatic command retry | none |

Sanitized immediate result:

```text
PASS R3
```

`R3` is the public `NAVIMOW.CommandResult` association
`Already In State`.

This matches the sanitized fixture
`fixtures/rest/command-dock-already-in-state.json`.

## 7. Delayed Status Verification

The implementation scheduled its own one-shot verification timer. The test did
not invoke Dock again.

After the timer completed:

| Variable | Expected | Observed |
| --- | --- | --- |
| `LastCommand` | Dock | Dock |
| `LastCommandAt` | set | set |
| `LastCommandResult` | Already In State | Already In State |
| `LastCommandError` | empty | empty |
| `VehicleState` | Docked | Docked |
| `LastStatusUpdate` | newer than command baseline | passed |

Sanitized terminal result:

```text
PASS C6 R3 E0 S2 F1
```

Public association meanings:

- `C6`: Dock;
- `R3`: Already In State;
- `E0`: no command error;
- `S2`: Docked;
- `F1`: a post-command status sample was available.

## 8. Domain-State Protection

The observed terminal state confirms:

- command diagnostics changed independently from domain state;
- Dock did not optimistically overwrite `VehicleState`;
- the later REST read remained the source of `Docked`;
- `LastStatusUpdate` advanced through the verification read;
- the fixture-backed `alreadyInState` result remained visible.

This satisfies AD-NAV-012, AD-NAV-031, AD-NAV-037 and AD-NAV-038.

## 9. Retry and Concurrency Review

Only one test-script invocation called `NAVDV_Dock()`.

The implementation:

- contains no command retry loop;
- disables the one-shot timer before verification;
- performs only `RefreshStatus()` during verification;
- clears the active-command state after the terminal result;
- leaves normal account polling intact.

No second command was requested by the test or by the module verification
path.

## 10. Cleanup

After verification:

- the temporary test script was deleted;
- the configured account and device instances were retained;
- module variables and normal polling remained active;
- no private capture file was created;
- no credentials or identifiers were copied into public artifacts.

## 11. Gate Decision

**Decision:** Dock-first direct Symcon gate passed.

The gate passed because:

- the published module update loaded successfully;
- read-only preflight confirmed a safe starting state;
- explicit user authorization was obtained;
- exactly one Dock action entered the module command path;
- the cloud returned the fixture-backed non-fatal result;
- delayed read-only verification produced a fresh Docked status;
- command diagnostics matched the public contract;
- cleanup completed.

## 12. Scope Still Blocked

This result does not approve:

- Start;
- Stop;
- Pause;
- Resume;
- automatic command retries;
- MQTT/WSS transport;
- unattended command testing;
- a claim that true transition-success responses are fixture-backed.

The tested `alreadyInState` path proves the live command transport and
verification architecture, but not a physical state transition.

## 13. Residual Risks

| Risk | Current treatment |
| --- | --- |
| True command `SUCCESS` response not captured | movement commands remain blocked |
| Command rejection shape not captured | unknown results fail closed |
| Dock transition duration not tested | only already-docked path is approved |
| Cloud may accept a command when response is lost | no automatic write retry |
| Only one real mower tested | device matching is locally tested for multiple entries |
| Full service restart during active verification | not directly tested |

## 14. Recommendation and Next Step

Keep Dock as the only enabled remote command.

Before enabling Start, Stop, Pause or Resume, create a separate transition
evidence and safety plan. It should define:

- which command can be tested with the lowest physical risk;
- direct supervision and abort conditions;
- capture of one sanitized true `SUCCESS` response;
- expected pre-state and post-state;
- bounded read-only verification timing;
- explicit prohibition of command retries.

Recommended next SAEF artifact:

```text
case-studies/navimow/24-command-transition-evidence-plan.md
```

MQTT/WSS remains a separate later phase.
