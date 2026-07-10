# 24 Command Transition Evidence Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Transition evidence planning complete
**Date:** 2026-07-09
**Execution boundary:** This document plans one supervised transition capture.
It changes no productive code, sends no command and does not enable additional
module actions.

## 1. Purpose

This plan defines how to obtain the missing fixture for a true successful
Navimow command transition with the lowest practical additional risk.

The required evidence is:

- a status sample before the command;
- exactly one successful command response;
- read-only status samples after the command;
- a sanitized public fixture for the nested `SUCCESS` response;
- proof that no command retry occurred.

The plan does not approve Start, Stop, Pause or Resume in the IP-Symcon module.

## 2. Current Evidence Gap

The case study currently has:

- fixture-backed Dock `alreadyInState`;
- a successful supervised Dock command while already docked;
- live account, discovery and status reads;
- a Dock-only command allowlist;
- a synthetic local test for nested command `status == "SUCCESS"`.

It does not yet have:

- a raw sanitized command response from a real state transition;
- measured state progression after an accepted transition command;
- evidence that the API uses the assumed `SUCCESS` shape in that case.

Synthetic parser coverage is not a substitute for a real sanitized fixture.

## 3. Selected Transition

**Selected transition:** Running to Docking or Docked.

Procedure at a high level:

1. the user starts mowing manually through the official Navimow app;
2. read-only REST status confirms `isRunning`;
3. the private capture tool sends exactly one Dock command;
4. the tool stores the raw private response;
5. only read-only status requests observe the transition;
6. the user supervises the mower until it is safely docked or aborts the test.

### AD-NAV-039: Use Dock as the only test command

**Decision:** The evidence capture uses the already implemented Dock command.

**Rationale:** Dock directs an active mower toward its safe resting state. It
does not require enabling an untested Start, Stop, Pause or Resume path in
IP-Symcon.

**Consequence:** Start is performed manually through the manufacturer's app and
is outside the module command test.

### AD-NAV-040: Capture and Symcon command execution are not combined

**Decision:** The private capture tool is the only component that sends Dock
during this transition test.

**Rationale:** Pressing Dock in IP-Symcon as well would create a duplicate
write and destroy proof that exactly one command produced the response.

**Consequence:** IP-Symcon may be used only for read-only observation during
the capture.

## 4. Safety Preconditions

All conditions must be true immediately before starting:

- the mower and charging station are physically visible;
- the mowing area is clear of people, animals, loose objects and active work;
- weather and ground conditions permit normal mower operation;
- the official app is connected and available for manual intervention;
- the user can reach the mower's physical stop control;
- the route back to the station is unobstructed;
- the mower has sufficient battery for a short supervised run and return;
- no automatic schedule can start or interfere during the test;
- only one operator controls the mower;
- the private capture environment contains valid credentials without printing
  them;
- raw output remains in the ignored private workspace.

If any condition is uncertain, the test is No-Go.

## 5. Explicit Abort Conditions

Abort immediately if:

- the mower leaves the expected safe area;
- another person or animal enters the operating area;
- the mower reports an error, lift event or unexpected state;
- the mower cannot be continuously supervised;
- the official app loses control connectivity;
- the pre-command REST state is not `isRunning`;
- more than one command request would be required;
- the Dock response is lost or ambiguous;
- the mower does not begin a safe return;
- any credential or private identifier appears in terminal output intended for
  sharing.

After an ambiguous command response, do not resend Dock for evidence.

Use the official app or physical stop control only as required to restore a
safe physical state. Safety recovery is not part of evidence collection.

## 6. Capture Responsibilities

### User

The user:

- confirms all physical preconditions;
- starts mowing through the official app;
- confirms visible movement in the safe area;
- authorizes the single Dock capture;
- supervises the mower throughout;
- performs any necessary physical or official-app safety intervention.

### Capture tool

The private tool:

- authenticates without echoing secrets;
- discovers exactly one selected device;
- obtains a read-only pre-state;
- requires `isRunning` before enabling the prompt;
- asks for explicit confirmation;
- sends Dock exactly once;
- records HTTP status and response body privately;
- performs bounded read-only status polling;
- never retries the command;
- sanitizes output into separate candidate fixtures;
- stops after the bounded observation window.

### IP-Symcon

During the raw transition capture, IP-Symcon:

- may display its existing status variables;
- may perform normal read-only polling;
- must not invoke `NAVDV_Dock()`;
- must not enable or invoke another mower command.

## 7. Planned Private Capture Sequence

### Phase A: Preparation

1. verify the private capture directory is ignored by Git;
2. review the tool for exactly one `sendCommands` call;
3. disable any previous optional Dock prompt that could cause a second call;
4. prepare separate raw and sanitized output names;
5. authenticate and discover the target without exposing identifiers.

### Phase B: Establish Running pre-state

1. refresh status while still docked;
2. user starts mowing in the official app;
3. wait until the mower is visibly operating safely;
4. request status until `vehicleState == "isRunning"` is observed;
5. stop after a bounded wait if Running is not observed;
6. store one private pre-state response.

No module command is used to create the pre-state.

### Phase C: Single Dock command

1. show a final prompt stating that one Dock command will be sent;
2. obtain explicit user confirmation;
3. construct the fixture-backed Dock envelope;
4. send one POST to `/openapi/smarthome/sendCommands`;
5. store HTTP status and response body;
6. never repeat the POST, regardless of response or timeout.

### Phase D: Read-only observation

After the command:

- request status after approximately 5 seconds;
- continue bounded read-only observations at conservative intervals;
- stop when `isDocked` is observed or after a maximum observation window;
- preserve the first observed `isDocking` state if available;
- do not treat missing `isDocking` as failure if Docked is reached;
- do not infer success solely from the command response.

The detailed procedure must define the exact polling interval and maximum
window before execution. A recommended initial ceiling is two minutes.

### Phase E: Cleanup

1. confirm the mower is safely docked or otherwise in a user-controlled safe
   state;
2. stop the private capture process;
3. inspect raw files only in the private workspace;
4. sanitize candidate fixtures;
5. review the raw directory for accidental public staging;
6. do not delete raw evidence until sanitization is verified;
7. never commit raw files.

## 8. Required Private Evidence

Private raw evidence should include:

| Evidence | Purpose |
| --- | --- |
| pre-command status | proves Running before Dock |
| exact request envelope | proves one Dock request shape |
| HTTP status | distinguishes transport from API behavior |
| command response | establishes real nested success shape |
| first post-command status | observes immediate cloud/device state |
| terminal status | confirms Docked or bounded timeout |
| local timestamps | orders evidence without entering public fixtures |

Raw evidence may contain private values and therefore remains excluded from
Git.

## 9. Planned Public Fixtures

Required new fixture:

```text
case-studies/navimow/fixtures/rest/command-dock-success.json
```

Optional status fixture if a new useful state is observed:

```text
case-studies/navimow/fixtures/rest/vehicle-status-docking.json
```

The existing fixtures remain unchanged:

```text
fixtures/rest/vehicle-status-mowing.json
fixtures/rest/vehicle-status-docked.json
fixtures/rest/command-dock-already-in-state.json
```

## 10. Sanitization Contract

The public command fixture must preserve:

- top-level `code` and `desc`;
- response nesting below `data.payload.commands[]`;
- nested command `status`;
- nested `errorCode` only if present;
- per-device result structure;
- nullable or scalar command result fields needed by the mapper.

It must replace or remove:

- real device ID;
- request ID;
- account identifiers;
- exact timestamps;
- tokens and authorization headers;
- private mower name;
- private host or local path information.

Use deterministic placeholders:

- `DEVICE_001`;
- `REQUEST_001`.

The status fixture must additionally remove:

- coordinates;
- map data;
- location history;
- garden-specific metadata.

### AD-NAV-041: Preserve schema, not private values

**Decision:** Sanitization may replace values but must not flatten or reshape
the response.

**Rationale:** Parser tests require authentic nesting and types.

**Consequence:** A fixture that cannot be made public without changing its
structure is rejected rather than committed.

## 11. Evidence Acceptance Criteria

The transition evidence passes only when:

- pre-state is fixture-backed as Running;
- exactly one Dock POST was attempted;
- HTTP and API response are parseable;
- nested command status is present;
- the response is not `alreadyInState`;
- the nested response indicates real acceptance or success;
- later read-only status demonstrates progression toward or arrival at Docked;
- no command retry occurred;
- sanitization review passes;
- mower safety was maintained throughout.

If the response differs from assumed `SUCCESS`, preserve the observed
non-private shape and update the contract before changing code.

## 12. Failure Classification

| Outcome | Evidence decision |
| --- | --- |
| Pre-state never becomes Running | abort; no command evidence |
| HTTP request fails before a response | retain private diagnostic; do not retry |
| HTTP succeeds but API code fails | candidate failure evidence, not success fixture |
| Nested result is `alreadyInState` | inconsistent pre-state; reject transition claim |
| Nested result has unknown success value | retain sanitized candidate and review contract |
| Command accepted but no Docked status within window | record verification uncertainty; do not claim complete transition |
| Safety intervention required | abort engineering test; physical safety takes precedence |
| Sanitization cannot be proven | keep all evidence private |

## 13. Post-Capture Engineering Work

After an accepted fixture is available:

1. validate JSON and redaction;
2. add `command-dock-success.json`;
3. update `fixtures/README.md`;
4. replace the synthetic `SUCCESS` parser input with the real fixture;
5. verify device matching and fail-closed behavior;
6. review whether five-second verification is realistic for transitions;
7. do not enable additional commands automatically;
8. document the capture and fixture validation in a separate report.

The current Dock action may require a longer bounded verification strategy for
an actual return journey. That change must be evidence-driven and must repeat
status reads only, never the command.

### AD-NAV-042: Transition timing is measured before timer changes

**Decision:** The existing five-second verification delay is not changed in
this planning step.

**Rationale:** A real transition should first provide timing evidence.

**Consequence:** Any future multi-read verification window requires a separate
documented implementation decision.

## 14. Scope Not Approved by This Plan

This plan does not approve:

- enabling Start in IP-Symcon;
- enabling Stop, Pause or Resume;
- unattended testing;
- automatic command retries;
- repeated Dock attempts for fixture collection;
- MQTT/WSS implementation;
- map or location capture;
- productive code changes before evidence review.

### AD-NAV-043: One success fixture does not approve all commands

**Decision:** A successful Dock transition validates the shared transport
shape, not the physical semantics of every command.

**Rationale:** Start, Stop and Pause/Resume have different physical effects and
state preconditions.

**Consequence:** Each newly enabled action class still requires a focused
readiness and supervised test decision.

## 15. Go/No-Go Gate

| Scope | Decision |
| --- | --- |
| Prepare focused private capture procedure | Go |
| User starts mower through official app under supervision | Conditional Go |
| Send exactly one private Dock capture while Running | Conditional Go |
| Read-only post-command polling | Go |
| Commit sanitized successful fixture after review | Conditional Go |
| Invoke Dock additionally through IP-Symcon | No-Go |
| Enable Start, Stop, Pause or Resume | No-Go |
| Retry an ambiguous command | No-Go |

## 16. Recommendation and Next Step

Proceed by creating a focused executable capture procedure that implements this
plan without modifying productive module code.

Recommended next SAEF artifact:

```text
case-studies/navimow/25-command-transition-capture-procedure.md
```

That step should define and verify the private terminal workflow, exact prompts,
bounded read intervals, file names and sanitization review before the mower is
started.
