# 56 Pause Command Evidence and Readiness Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Conditional GO for one supervised private Pause capture
**Date:** 2026-07-12
**Scope:** Define Pause evidence, safety and capture readiness without sending it

## 1. Purpose

This step applies the command program from
`55-command-integration-sequence-and-safety-plan.md` to Pause.

It defines:

- the current static Pause contract;
- why one physical capture is justified;
- exact physical and API preconditions;
- the single-write and no-retry boundary;
- bounded read-only timing observations;
- raw and sanitized output contracts;
- fixture acceptance criteria;
- implementation readiness gates;
- abort and cleanup behavior.

No productive PHP code is changed and no mower command is sent in this step.

## 2. Current Evidence

| Evidence | Status | Impact |
| --- | --- | --- |
| original ioBroker Pause mapping | available | request shape is statically known |
| current official Navimow integration | Pause exposed | current support signal exists |
| command endpoint | proven by Dock | shared transport endpoint is known |
| generic successful command envelope | captured for Dock | response parser shape is known |
| `isRunning` status | sanitized fixture available | safe precondition can be verified |
| `isPaused` mapping | static only | terminal state needs real fixture |
| Pause success response | missing | cloud acceptance is not fixture-backed |
| Running-to-Paused timing | missing | deadline cannot yet be selected |
| Pause already-in-state response | missing | not required for first transition capture |
| Pause rejection response | missing | must fail closed if naturally observed |

The evidence is sufficient to design one capture but not to implement or
expose Pause in the productive module.

## 3. Static Request Contract

Provisional request:

```json
{
  "commands": [
    {
      "devices": [
        { "id": "DEVICE_001" }
      ],
      "execution": {
        "command": "action.devices.commands.PauseUnpause",
        "params": {
          "on": false
        }
      }
    }
  ]
}
```

Contract requirements:

- `commands` is an array;
- exactly one command entry exists;
- exactly one configured device is addressed;
- command string is exact and allowlisted;
- `params` is an object;
- `on` is the JSON boolean `false`, not a string or integer;
- no token or account data enters the JSON body;
- the request is sent once to the existing command endpoint.

The exact payload must be asserted locally before any private capture script is
allowed to run.

## 4. State Transition Hypothesis

Expected physical and API transition:

```text
physically mowing
-> current REST state isRunning
-> one Pause command
-> cloud command accepted
-> mower stops mowing movement
-> current REST state isPaused
```

Expected terminal source value:

```text
isPaused
```

Provisional state contract:

| State | Interpretation |
| --- | --- |
| `isRunning` before command | required current precondition |
| `isRunning` immediately after acceptance | temporary unresolved state only |
| `isPaused` after command | terminal verification candidate |
| `isDocking` or `isDocked` | unexpected for Pause; do not classify as Pause success |
| `isIdle` | unexpected until evidence explains semantics |
| `Offline`, `Error`, unknown | abort verification and retain diagnostic evidence |

Only a successful read after the command baseline may verify Paused. Cached
state or physical observation alone does not satisfy the API fixture gate.

## 5. Why One Live Capture Is Justified

Pause is the first planned command after Dock because:

- it has current official-source support;
- its request mapping is statically consistent across sources;
- it moves the mower toward a stationary condition;
- the mower can be placed in Running through the official app;
- the expected terminal state is explicit;
- the official app and physical stop remain available;
- no module Start implementation is needed;
- the missing response and timing evidence cannot be obtained from static
  analysis alone.

The capture is justified only as one bounded evidence run. It is not repeated
to improve sample size.

## 6. Capture Boundary

The dedicated private procedure may:

- authenticate privately;
- discover exactly one selected mower;
- perform bounded read-only status requests;
- require two consecutive `isRunning` observations;
- require an exact typed confirmation;
- send exactly one Pause command;
- capture the single response;
- observe bounded read-only post-state;
- create sanitized candidate files;
- stop early after `isPaused`.

It must not:

- start the mower;
- send Dock, Resume, Stop or Start;
- retry Pause;
- use curl or transport retry options;
- follow redirects containing authorization material into logs;
- continue after physical supervision is lost;
- clear its attempt marker automatically;
- publish raw output;
- modify the productive Symcon module.

## 7. Physical Preconditions

Before starting the private procedure:

- mower and operating area are continuously visible;
- people, animals and removable obstacles are clear;
- weather and ground conditions are suitable;
- battery is sufficient for a short supervised run;
- automatic schedules cannot take control;
- one operator has exclusive control;
- official Navimow app is connected and available;
- physical stop control is immediately reachable;
- current Symcon command verification is not active;
- enough time exists to supervise through safe cleanup;
- operator understands that Pause may leave the mower stationary in the lawn.

The user starts normal mowing through the official Navimow app. The capture
tool never starts motion.

## 8. API Preconditions

Before enabling the write confirmation, the procedure must prove:

1. OAuth exchange succeeded;
2. exactly one intended device was selected;
3. latest status request succeeded;
4. online state is not false;
5. `vehicleState` is exactly `isRunning` twice consecutively;
6. consecutive reads are separated by approximately five seconds;
7. no unexpected state occurs between the two reads;
8. no previous Pause attempt marker exists;
9. the private output location is writable;
10. request payload passes local structural assertions.

If any precondition fails, exit before the command endpoint is called.

Two current Running observations reduce the risk of sending Pause against a
state that changed while the operator moved between app and terminal.

## 9. Typed Safety Gate

After the second Running confirmation, print:

```text
Running state is confirmed twice.
The next confirmation sends exactly one Pause command.
There is no command retry after timeout or an ambiguous response.
The script will not Resume, Dock, Stop or Start the mower.
```

Required input:

```text
PAUSE ONCE
```

Any other input aborts without creating the attempt marker and without sending
a command.

Immediately before entering the phrase, the user must reconfirm that the mower
is visible, the official app is available and physical intervention remains
possible.

## 10. Attempt Marker and Single Write

The private procedure must create a durable marker before the command request:

```text
pause-command-attempted.marker
```

The marker records only a local attempt timestamp and contains no credential or
device identifier.

Required order:

```text
validate payload
-> create marker
-> perform one command POST
-> capture response or transport ambiguity
-> perform read-only observations
```

The script refuses to run when the marker already exists.

If transport times out or the response is lost, the attempt is ambiguous. Do
not delete the marker and do not rerun the command.

## 11. Post-Command Observation Schedule

The initial capture should perform read-only status requests at approximately:

```text
2, 5, 10, 20, 30 and 60 seconds
```

Rules:

- stop early after the first successful `isPaused` read;
- retain elapsed time for engineering analysis, not as a private exact clock;
- do not treat physical stopping as a substitute for REST confirmation;
- do not resend Pause if Running remains visible;
- do not infer failure from one stale Running response;
- stop after 60 seconds and classify the transition as unresolved if Paused was
  not observed;
- never perform a cleanup command from the capture script.

The schedule is intentionally denser than Dock because Pause is expected to be
a short state transition. It remains bounded to six post-command reads.

## 12. Physical Observation

The user should report only:

- whether mowing movement stopped;
- whether the mower remained physically safe;
- whether app or physical intervention was required;
- whether the mower was later returned to a safe state using the official app.

Do not report exact garden position, route, boundary or private mower name.

Physical stop without REST `isPaused` is useful diagnostic evidence but does
not complete the Pause API contract.

## 13. Safe Cleanup

After capture observation ends:

- the capture script performs no more command;
- the user may keep the mower paused or use the official app to return it to
  Dock;
- physical stop may be used whenever safety requires it;
- cleanup action is recorded only as `official app`, `physical control` or
  `none`;
- cleanup is not counted as module command evidence;
- no Resume module test is combined with this run.

The user should confirm the final physical condition before raw evidence is
reviewed.

## 14. Private Raw Output

Raw files remain in the ignored private capture area and may include:

- token response;
- discovery response;
- real device selection;
- exact Pause request;
- command response;
- two Running pre-state responses;
- bounded post-state responses;
- attempt marker and local timestamps.

Raw files must never be committed, pasted into chat or attached to the public
vendor issue.

## 15. Sanitized Candidate Set

Expected candidate names:

```text
vehicle-status-pause-pre-running-1.json
vehicle-status-pause-pre-running-2.json
command-pause-success.json
vehicle-status-pause-after-2s.json
vehicle-status-pause-after-5s.json
vehicle-status-pause-after-10s.json
vehicle-status-pause-after-20s.json
vehicle-status-pause-after-30s.json
vehicle-status-pause-after-60s.json
```

Observation stops after Paused, so later files may not exist.

Only these candidates are expected to become canonical fixtures:

```text
command-pause-success.json
vehicle-status-paused.json
```

The existing `vehicle-status-mowing.json` remains canonical unless the new
Running payload reveals a relevant structural difference.

## 16. Pause Command Fixture Contract

`command-pause-success.json` must preserve:

- top-level response code and description type;
- `data.requestId` position;
- `data.payload.commands` array;
- nested device array;
- nested command number field if present;
- nested command status;
- nested error code including `null` type when applicable.

It must replace:

- request ID with `REQUEST_001`;
- device ID with `DEVICE_001`;
- command number with `COMMAND_001`;
- any private name or account identifier with deterministic placeholders.

Acceptance requires nested success semantics. HTTP 200 or top-level code alone
is insufficient.

## 17. Paused Status Fixture Contract

`vehicle-status-paused.json` must preserve:

- normal status response envelope;
- devices array structure;
- placeholder device ID;
- battery structure and scalar types when present;
- exact `vehicleState` source value;
- descriptive battery field when present;
- request ID location;
- absence or sanitized treatment of unrelated private fields.

Expected essential field:

```json
{
  "vehicleState": "isPaused"
}
```

If the source value differs, do not normalize it to `isPaused`. Preserve the
actual value and reopen the mapping contract.

## 18. Sanitization Gate

Before a candidate enters the case study, verify:

- valid JSON;
- no access or refresh token;
- no authorization code or callback URL;
- no real request, account, device, serial or command identifier;
- no private mower name;
- no hostname, private IP or local path;
- no coordinates, map, boundary or location history;
- original key names and nesting preserved;
- boolean `false` remains boolean in any retained request fixture;
- null values remain null;
- no raw header or cookie data;
- small and reviewable file size.

The raw and sanitized files must be compared locally before acceptance.

## 19. Capture Result Classification

| Outcome | Meaning | Next action |
| --- | --- | --- |
| `CAPTURE PASS` | one accepted Pause and later current `isPaused` | validate fixtures and timing |
| `ALREADY IN STATE` | unexpected because pre-state was Running | retain diagnostic; investigate stale/race behavior |
| `REJECTED` | cloud explicitly rejected Pause | retain sanitized rejection; no implementation |
| `AMBIGUOUS` | response lost or malformed after attempt | no retry; review status and physical result |
| `UNRESOLVED` | accepted but no Paused read within 60 seconds | no retry; review polling/state contract |
| `ABORTED SAFELY` | precondition or physical gate stopped procedure | no module defect inferred |
| `UNSAFE` | unexpected behavior required intervention | suspend command program and review |

A physical pause plus ambiguous response does not authorize a second capture.

## 20. Implementation Readiness After Capture

Pause may advance to implementation design only when:

- one command attempt occurred;
- no retry occurred;
- command response is structurally and semantically accepted;
- a current read observed the actual paused state;
- transition time is known within the observation schedule;
- physical behavior matched the expected de-escalating action;
- no unsafe intervention was required;
- sanitized fixtures pass review;
- existing Running fixture remains valid or is deliberately updated;
- no private data enters the case study.

Even after capture, implementation requires a separate readiness decision. The
capture itself does not modify the productive allowlist.

## 21. Static Procedure Gate

Before live execution, the next private tool must pass:

- shell syntax validation;
- executable permission check;
- ignored-path verification;
- exactly one command-endpoint write occurrence;
- no generic retry option;
- marker creation textually and behaviorally before POST;
- exact payload assertion including boolean false;
- bounded pre- and post-read counts;
- no Start, Stop, Resume or Dock payload;
- dry abort before confirmation;
- sanitization function test with synthetic identifiers;
- no productive distribution change.

No live command is allowed during static validation.

## 22. Go/No-Go Matrix

| Scope | Decision |
| --- | --- |
| static Pause contract | PASS |
| physical capture justification | PASS WITH SUPERVISION |
| create dedicated private capture tool | GO |
| static tool validation | GO |
| execute one live capture | CONDITIONAL GO after tool validation and user safety confirmation |
| accept fixtures | NO-GO until capture review |
| implement Pause transport | NO-GO |
| expose Pause in Symcon | NO-GO |
| test Resume in same run | NO-GO |
| send Stop for research | NO-GO |
| automatic command retry | NO-GO |

## 23. OAuth and Store Independence

The open vendor OAuth inquiry does not block a controlled private Pause
capture using the already established private-pilot boundary.

The capture does not imply:

- public OAuth approval;
- broad release readiness;
- Store preparation completion;
- approval of Resume, Stop or Start;
- permission to publish a credential.

## 24. Variable and Archive Boundary

The evidence procedure changes no Symcon variable.

Any later Pause implementation must preserve:

- existing Device instance identity;
- `VehicleState` type and Ident;
- `BatteryLevel`, `Online` and `LastStatusUpdate` objects;
- all `LastCommand*` Idents and types;
- user-configured Archive Control logging;
- existing historical data.

Paused is already a reserved association in the stable vehicle-state profile.
No variable recreation is required.

## 25. Architecture Decisions

### AD-NAV-156: Require two current Running reads

**Decision:** Gate the Pause write on two successful consecutive `isRunning`
responses separated by approximately five seconds.

**Rationale:** A single stale or transitional read is insufficient evidence
that Pause is being sent against the intended active state.

**Consequence:** The procedure aborts before transmission if Running is not
stable.

### AD-NAV-157: Use one dedicated Pause capture tool

**Decision:** Keep Pause in a command-specific private script with one durable
attempt marker.

**Rationale:** Reusing a multi-command tool would weaken the single-write and
no-retry audit boundary.

**Consequence:** The script cannot send Resume, Stop, Start or Dock.

### AD-NAV-158: Observe short-transition timing before design

**Decision:** Use bounded reads through 60 seconds and select productive timing
only after real Paused evidence exists.

**Rationale:** Dock timing cannot be reused for a short local state change.

**Consequence:** Pause verification constants remain undecided in this step.

### AD-NAV-159: Separate capture cleanup from command evidence

**Decision:** End the script after read-only observation and leave safe cleanup
to the user through the official app or physical control.

**Rationale:** A second command would combine evidence, weaken command-count
proof and introduce an unapproved actuator path.

**Consequence:** No Resume module test is paired with Pause capture.

### AD-NAV-160: Keep productive Pause blocked until fixture review

**Decision:** Approve preparation and one capture only, not implementation.

**Rationale:** Static source support does not prove response semantics, actual
paused source value or transition timing.

**Consequence:** The production command allowlist remains Dock-only.

## 26. Gate Decision

**Decision: CONDITIONAL GO for one supervised private Pause capture.**

The next step may create and statically validate the dedicated private tool.
Live execution still requires a separate immediate safety confirmation after
the tool is ready.

**Productive Pause implementation remains NO-GO.**

## 27. Recommended Next Step

Create:

```text
57-pause-command-private-capture-procedure.md
```

That step should implement the dedicated private one-command tool, validate
its marker and no-retry properties, document exact terminal instructions and
stop before live execution until the user confirms supervision and Running
setup readiness.
