# 21 Command Implementation Readiness

**Case study:** Navimow native IP-Symcon module  
**Status:** Conditional command implementation decision  
**Date:** 2026-07-09  
**Build boundary:** This step reviews command readiness only. It introduces no
productive PHP code and sends no mower command.

## 1. Purpose

This document decides whether the tested read-only REST MVP is ready to gain
its first mower command path.

The review separates:

- readiness to implement and test command transport locally;
- readiness to expose a command in IP-Symcon;
- readiness to execute that command against a real mower;
- readiness to enable the complete MVP action set.

This separation is required because a command can have a physical side effect
even when its HTTP exchange appears successful.

## 2. Inputs

The decision uses:

- `03-variable-and-action-contract.md`;
- `04-implementation-plan.md`;
- `08-fixture-validation-report.md`;
- `09-rest-mvp-readiness-review.md`;
- `19-discovery-and-readonly-status-implementation.md`;
- `20-discovery-and-status-symcon-test-report.md`;
- `fixtures/rest/command-dock-already-in-state.json`;
- the current installable distribution under `distribution/`.

No private capture, credential, device identifier or IP-Symcon ObjectID is
copied into this document.

## 3. Current Evidence

| Evidence | Status | Command impact |
| --- | --- | --- |
| OAuth and refresh in IP-Symcon | passed | Account can obtain a usable bearer token. |
| Discovery and device routing | passed | A command can be scoped to one configured `DeviceId`. |
| Read-only status in IP-Symcon | passed | Post-command state can be verified independently. |
| Account serialization | implemented | REST operations can share one bounded account lock. |
| Command endpoint and request shape | statically known | `/openapi/smarthome/sendCommands` and the Google-Smart-Home-like envelope are documented. |
| Sanitized `Dock` response | available | `alreadyInState` parsing is fixture-backed. |
| True command transition response | missing | `Accepted` cannot yet be claimed from captured evidence. |
| Command rejection response | missing | Unknown command errors require a defensive fallback. |
| Safe retry evidence | absent | Remote commands must not be retried automatically. |

## 4. Decision Summary

**Decision:** Conditional Go for a Dock-first command implementation slice.

The next implementation may add:

- command transport and request validation;
- response parsing;
- command diagnostics;
- a one-shot delayed status verification;
- the `Dock` action only.

The following actions remain No-Go for public activation:

- `Start`;
- `Stop`;
- `Pause`;
- `Resume`.

They may be represented in pure local mapping tests, but they must not be
registered as active user actions or sent to the cloud in the next slice.

## 5. Architecture Decisions

### AD-NAV-030: Dock is the first enabled remote command

**Decision:** `Dock` is the only remote command enabled in the first command
slice.

**Rationale:** Its request shape and `alreadyInState` response are backed by a
sanitized capture. A supervised test can be performed while the mower is
already docked, minimizing physical movement.

**Consequence:** The shared command architecture can be verified without
prematurely exposing the complete action set.

### AD-NAV-031: Command acceptance is not device state

**Decision:** A command response updates only `LastCommand`,
`LastCommandAt`, `LastCommandResult` and `LastCommandError`.

`VehicleState`, `Online` and `BatteryLevel` remain owned by status reads.

**Rationale:** Cloud acceptance does not prove execution by the mower.

**Consequence:** Status verification is required after every submitted remote
command and remains the only source of domain state.

### AD-NAV-032: Remote commands are never automatically retried

**Decision:** Transport failure, timeout, malformed response and unknown API
failure terminate the command attempt.

**Rationale:** A failed response does not prove that the cloud did not receive
the command. Repeating an actuator command could create an unintended second
physical action.

**Consequence:** Recovery requires an explicit new user action after current
status has been refreshed.

### AD-NAV-033: Verification repeats reads, not writes

**Decision:** The module schedules one delayed status refresh after a command
result that is accepted or already in state.

If the expected state is not observed, a bounded additional read may be added
only after its timing is specified and tested. The command itself is never
resent.

**Rationale:** Read operations are safer to repeat than actuator commands.

**Consequence:** A verification timeout is diagnostic; it does not trigger a
new command.

### AD-NAV-034: Command actions use module action semantics

**Decision:** User-facing commands must enter through
`NavimowDevice::RequestAction()` or an equivalent module-owned action method.

**Rationale:** This preserves validation, serialization, diagnostics and the
SAEF action boundary from ADR-0001.

**Consequence:** Status variables remain read-only and scripts must not emulate
commands with `SetValue()`.

## 6. Required Command Contract

The device sends a bounded child request:

| Field | Requirement |
| --- | --- |
| `DataID` | existing Navimow parent-child interface |
| `SchemaVersion` | existing version `1` |
| `Function` | `SendCommand` |
| `DeviceId` | non-empty configured device identifier |
| `Command` | allowlisted symbolic command, initially `Dock` only |

The account owns:

- bearer-token access;
- `/openapi/smarthome/sendCommands`;
- request ID creation;
- command envelope construction;
- account-level serialization;
- HTTP and API response classification.

The device owns:

- action preconditions;
- command diagnostic variables;
- one active command attempt per instance;
- delayed status verification;
- user-facing bounded error text.

Child modules must never receive tokens, authorization headers or raw
credential-bearing transport data.

## 7. Response Classification

| Condition | `LastCommandResult` | Error text |
| --- | --- | --- |
| Request prepared, not yet completed | `Requested` | empty |
| Command result reports `alreadyInState` | `Already In State` | empty |
| Fixture-backed future success status | `Accepted` | empty |
| Known command rejection | `Rejected` | bounded sanitized reason |
| HTTP, transport or malformed payload failure | `Failed` | bounded sanitized reason |
| Waiting for status confirmation | `Pending Verification` | empty |
| Expected state confirmed by later status | `Verified` | empty |
| Verification window expires | `Verification Timeout` | bounded generic reason |

Top-level API `code == 1` is necessary but not sufficient. The nested command
result must also be parsed.

Unknown nested statuses and error codes fail closed as `Failed`; they are not
treated as accepted.

## 8. Concurrency and Timing

The implementation must:

1. reject a second device command while one attempt is active;
2. use the existing account semaphore for the HTTP operation;
3. release the account semaphore before delayed verification;
4. avoid sleeping inside `RequestAction()`;
5. use a one-shot device timer for verification;
6. clear or disable the timer after its terminal result;
7. preserve normal account polling.

The initial verification delay must be a named constant or explicit property.
Its value is to be selected in the implementation step and recorded with a
rationale; it must not become an undocumented magic number.

## 9. Test Gates Before Live Activation

### Local fixture gate

Required tests:

- exact Dock request envelope;
- rejection of unknown symbolic commands;
- rejection of empty device IDs;
- parsing of `alreadyInState`;
- defensive handling of missing `commands[]`;
- defensive handling of unknown status or error code;
- HTTP and malformed-JSON failure mapping;
- proof that command parsing never changes status variables;
- proof that no command retry is scheduled.

### Static distribution gate

Required checks:

- PHP syntax;
- official Symcon metadata and form validation;
- module validator;
- distribution consistency;
- no credential or private identifier in changed files.

### Direct IP-Symcon gate

The first live test must:

1. run with the mower docked and supervised;
2. refresh status immediately before the action;
3. invoke `Dock` through module action semantics;
4. expect `Already In State`;
5. verify that command diagnostics are updated;
6. verify that domain variables were not written by the command path;
7. verify that delayed status refresh still reports `Docked`;
8. verify that exactly one command request was sent;
9. record only sanitized PASS/FAIL evidence;
10. remove temporary test scripts afterward.

## 10. Risk Review

| Risk | Treatment |
| --- | --- |
| Cloud accepted command but response was lost | no write retry; refresh status and report failure |
| Top-level success hides nested command error | parse nested result before classification |
| Duplicate user action | per-device active-attempt guard |
| Incorrect local mower state | command path cannot write domain state |
| Unknown command response | fail closed with bounded diagnostics |
| Token or device ID leakage | account-owned transport and sanitized output |
| Physical movement during first test | Dock-only test while already docked and supervised |
| Verification never completes | one-shot bounded timer and terminal timeout |

## 11. Go/No-Go Matrix

| Scope | Decision |
| --- | --- |
| Local command envelope and parser implementation | Go |
| Account `SendCommand` transport | Go with fixture tests |
| Device command diagnostics and verification timer | Go |
| Register and expose `Dock` | Conditional Go |
| Supervised live `Dock` while already docked | Go after local and static gates pass |
| Expose `Start`, `Stop`, `Pause`, `Resume` | No-Go |
| Automatic command retry | No-Go |
| MQTT/WSS command transport | No-Go |

## 12. Exit Criteria

The Dock-first slice is complete only when:

- all local and static gates pass;
- the supervised live Dock test passes;
- `alreadyInState` is visible as a non-error terminal result;
- delayed status verification remains read-only;
- no duplicate command is emitted;
- no private data enters tests, logs or documentation;
- the result is documented in a dedicated Symcon test report.

The complete MVP action set requires additional evidence:

- at least one sanitized true transition-success response;
- one bounded rejection or failure response if safely obtainable;
- a supervised transition test plan for each newly enabled action class.

## 13. Recommendation and Next Step

Proceed with a narrowly scoped Dock-first implementation.

Recommended next SAEF artifact:

```text
case-studies/navimow/22-dock-command-implementation.md
```

That step should implement and locally verify the shared command transport,
Dock action, diagnostics and one-shot status verification. It must not enable
`Start`, `Stop`, `Pause`, `Resume` or MQTT/WSS.
