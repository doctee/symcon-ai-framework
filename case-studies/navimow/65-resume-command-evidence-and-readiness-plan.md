# 65 Resume Command Evidence and Readiness Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Conditional GO for one supervised private Resume capture procedure
**Date:** 2026-07-12
**Scope:** Define Resume evidence, safety and capture readiness without sending it

## 1. Purpose

This step turns the conditional evidence-planning decision from
`64-pause-integration-review-and-resume-readiness.md` into a concrete Resume
capture gate.

It defines:

- current upstream support evidence;
- exact provisional request and response contracts;
- private tool and output isolation;
- movement-safety and two-read Paused preconditions;
- one-write enforcement and read-only observation;
- sanitization, classification and fixture promotion rules;
- the Go/No-Go decision for implementing the capture procedure.

No productive PHP code, private executable or module repository reference is
changed. No mower command is sent in this step.

## 2. Source Revalidation

Current upstream support was rechecked on 2026-07-12.

The official Segway Navimow Home Assistant repository still documents:

- Start mowing;
- Pause mowing;
- Resume mowing;
- send mower to dock;
- a `lawn_mower` entity exposing start, pause, dock and resume.

Primary source:

```text
https://github.com/segwaynavimow/NavimowHA
```

The current integration delegates command execution to `navimow-sdk`. This is
a strong present support signal for the Resume capability, but it is not direct
proof of the private REST envelope used by this case study.

The exact provisional REST request remains derived from the analyzed
`TA2k/ioBroker.navimow` implementation:

```text
action.devices.commands.PauseUnpause
params.on = true
```

### Source conclusion

| Question | Finding |
| --- | --- |
| Is Resume currently represented by Navimow? | yes |
| Is Resume exposed by the official HA integration? | yes |
| Is a Resume-to-Running state concept current? | yes |
| Does the official HA README prove the private REST boolean? | no |
| Does prior Pause success prove the inverse boolean? | no |
| Is one private REST evidence capture still required? | yes |

## 3. Current Evidence Matrix

| Evidence | Status | Impact |
| --- | --- | --- |
| current official support signal | passed | Resume remains a plausible supported command |
| legacy exact REST mapping | available | provisional request is known |
| shared command endpoint | proven | no endpoint discovery needed |
| OAuth and device discovery | proven | private tool foundation exists |
| generic command success envelope | proven | response shape parser is known |
| Paused source state | fixture-backed and live-observed | precondition can be measured |
| Running source state | fixture-backed and live-observed | expected terminal state can be measured |
| one-write safety pattern | proven by Dock and Pause | capture mechanics are reusable |
| Resume command response | missing | acceptance is not fixture-backed |
| Paused-to-Running transition | missing | physical semantics and timing unproven |
| Resume rejection | missing | unknown failures must fail closed |
| Resume already-in-state behavior | missing | must not be inferred |
| productive Resume implementation | absent | correctly blocked |

The matrix is sufficient to design one private procedure. It is not sufficient
to expose Resume in IP-Symcon.

## 4. Safety Classification

Resume is classified as **movement-initiating and blade-initiating**.

Unlike Pause, Resume may move a stationary mower immediately. The operator may
have approached the mower during the paused interval, and conditions may have
changed since mowing was paused.

Therefore:

- current Paused state alone is not a complete safety condition;
- visible stationary state and a newly confirmed clear area are mandatory;
- the capture confirmation must explicitly mention movement;
- official app and physical stop access must remain immediate;
- no unattended or remotely unsupervised capture is allowed.

## 5. Provisional REST Request Contract

Endpoint:

```text
POST /openapi/smarthome/sendCommands
```

Request envelope:

```json
{
  "commands": [
    {
      "devices": [
        {
          "id": "DEVICE_001"
        }
      ],
      "execution": {
        "command": "action.devices.commands.PauseUnpause",
        "params": {
          "on": true
        }
      }
    }
  ]
}
```

Required static assertions:

- exactly one command object;
- exactly one device object;
- exact command string `action.devices.commands.PauseUnpause`;
- `params.on` is JSON boolean `true`;
- no additional parameter;
- no Pause boolean `false`;
- no Dock or StartStop command;
- no second command-endpoint call in the script.

The request remains provisional until accepted by the live private API.

## 6. Required Physical Setup

Before launching the future procedure:

- mower, operating area and likely first movement path are continuously visible;
- people, animals and obstacles are clear;
- no person is standing beside or in front of the paused mower;
- ground and weather permit continued mowing;
- battery is sufficient;
- official Navimow app is connected;
- physical stop control is immediately reachable;
- automatic schedules cannot interfere;
- one operator has exclusive control;
- no Symcon command verification or private capture is active;
- enough time remains for observation and official-app recovery;
- the operator accepts that successful Resume may start drive and blade motion
  immediately after confirmation.

The procedure must print these conditions before authentication and again
before the command confirmation.

## 7. Paused Preparation Path

The private tool must not create its own precondition with another API command.

Required preparation:

1. start normal mowing manually in the official Navimow app;
2. observe expected normal mowing;
3. pause manually in the official app;
4. confirm the mower is visibly stationary;
5. leave the mower supervised and the area clear;
6. return to the terminal and begin current-state checks.

The tool must not send Pause, Dock, Stop or Start as setup.

This isolates the evidence budget to one Resume write.

## 8. Two-Read Paused Gate

Before presenting the command confirmation, the tool must obtain two
consecutive successful current status responses reporting exactly:

```text
isPaused
```

Required behavior:

- use read-only `getVehicleStatus` requests;
- require two consecutive Paused results;
- separate successful reads by five seconds;
- reset the consecutive count after another state or failed read;
- check at most twelve times;
- stop without a command after the bounded precondition window;
- reject Offline, Running, Docking, Docked, Error and unknown states.

The tool must not use a cached file or an earlier Pause fixture as current
evidence.

## 9. Private Tool Isolation

Planned executable:

```text
private/navimow-capture/capture-resume-transition.sh
```

Planned private output root:

```text
private/navimow-capture/output/resume-transition/
```

Subdirectories:

```text
raw/
sanitized/
```

Durable attempt marker:

```text
raw/resume-command-attempted.marker
```

The tool and output remain covered by the repository's root `/private/` ignore
rule.

Resume must not reuse Pause or Dock output roots, attempt markers or terminal
fixture names.

## 10. Credential Handling

The future tool must follow the established private OAuth pattern:

- no embedded client secret;
- hidden client-secret prompt or private `NAVIMOW_CLIENT_SECRET` environment
  variable;
- hidden authorization code or callback URL prompt;
- no secret in shell history;
- unset short-lived authorization code and client-secret shell variables after
  exchange;
- raw token response stored only under the private raw directory;
- no token, callback URL or authorization header in sanitized output;
- bounded authentication error display without response-body dumping.

Authentication failure must occur before device discovery, marker creation and
command preparation.

## 11. Device Selection

The tool must:

- discover devices through the authenticated account response;
- continue automatically only when exactly one device is available; or
- accept `NAVIMOW_DEVICE_ID` privately for explicit selection;
- verify that an explicit device exists in discovery;
- reject empty, unknown or multiple ambiguous selections;
- never print the real device identifier in normal terminal output.

Sanitized candidates use `DEVICE_001` only.

## 12. Static Validation Mode

The executable must provide:

```sh
NAVIMOW_CAPTURE_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-resume-transition.sh
```

This mode must:

- use synthetic device `DEVICE_001`;
- create the exact Resume request;
- prove `params.on` is boolean `true`;
- prove there is one command and one device;
- reject extra fields;
- run sanitizer tests with synthetic secrets and identifiers;
- confirm all synthetic private values are removed;
- confirm command number placeholders preserve string type;
- exit before network access;
- create no live attempt marker.

Static validation must also include Bash syntax, executable mode, Git-ignore
coverage and a source scan for command retries.

## 13. Command Confirmation

After both Paused reads, the tool must display:

```text
Paused state is confirmed twice.
The next confirmation sends exactly one Resume command.
The mower may begin moving and cutting immediately.
There is no command retry, even after timeout or an ambiguous response.
The tool will not Pause, Dock, Stop or Start the mower.
Type RESUME ONCE to continue:
```

Only exact input:

```text
RESUME ONCE
```

authorizes the write.

Empty, partial, differently cased or repeated input aborts without creating the
attempt marker or sending a command.

## 14. One-Write Enforcement

Immediately before the command POST, the tool must atomically create:

```text
resume-command-attempted.marker
```

The marker must contain only non-secret local evidence such as:

- attempt purpose;
- UTC attempt time;
- script version or local evidence label;
- explicit no-retry notice.

It must not contain the device ID, account ID, token, request body or callback
URL.

If the marker already exists, the tool exits before command preparation.

The script source must contain one literal call site to
`/openapi/smarthome/sendCommands` and no curl retry option.

The marker is retained after success, rejection, timeout, interruption or
ambiguous delivery.

## 15. Command Response Classification

HTTP 200 is transport evidence only.

The tool must evaluate:

1. valid JSON;
2. top-level `code == 1`;
3. command result matching the selected device;
4. nested command `status`;
5. nested `errorCode` including nullable type;
6. command number location and type when present.

Classification:

| Response | Classification |
| --- | --- |
| nested `SUCCESS` | accepted; begin read-only observation |
| `alreadyInState` | retain separately; do not assume Resume success |
| explicit rejection | rejected; no retry |
| HTTP or transport ambiguity | ambiguous delivery; no retry |
| malformed or unknown response | unresolved; no retry |
| authentication rejection | auth failure; no retry |

Only nested success permits creation of a sanitized success candidate.

## 16. Read-Only Post-State Schedule

After accepted Resume, the tool may perform only status reads at elapsed:

```text
2s, 5s, 10s, 20s, 30s, 60s
```

Rules:

- stop early after the first successful current `isRunning` read;
- treat `isPaused` as permitted transient state;
- never resend Resume while Paused remains visible;
- do not treat Docking, Docked, Idle, Error or Unknown as Resume success;
- after 60 seconds classify accepted-but-unverified as unresolved;
- retain every observed candidate privately with elapsed-time filenames;
- no fixed sleep may hold a productive Symcon process because this is a private
  terminal-only capture tool.

## 17. Physical Observation Contract

The operator must separately record:

- whether drive motion resumed;
- whether blade/mowing behavior appeared normal;
- whether direction and route appeared expected;
- whether any unsafe or surprising movement occurred;
- whether physical stop or official-app intervention was required;
- final cleanup action.

REST `isRunning` without visible expected movement is incomplete evidence.
Visible movement without current REST `isRunning` is also incomplete API
evidence.

## 18. Abort and Emergency Behavior

Before transmission, abort when any safety or Paused precondition fails.

After transmission:

- never issue Resume again;
- use physical stop immediately for unsafe movement;
- otherwise use official app Pause or Dock for recovery;
- stop capture reads when continued observation could delay safety action;
- record intervention as an operational event, not a command failure rewrite;
- do not use the Symcon Pause implementation as automated cleanup;
- do not remove the attempt marker to repeat the run.

One aborted-safe run is useful safety evidence and does not justify another
write in the same session.

## 19. Raw Evidence Contract

Private raw evidence should include:

```text
auth-token.private.json
auth-list.private.json
vehicle-status-resume-pre-check-1.private.json
vehicle-status-resume-pre-check-2.private.json
command-resume-request.private.json
command-resume-response.private.json
vehicle-status-resume-after-2s.private.json
vehicle-status-resume-after-5s.private.json
vehicle-status-resume-after-10s.private.json
vehicle-status-resume-after-20s.private.json
vehicle-status-resume-after-30s.private.json
vehicle-status-resume-after-60s.private.json
resume-command-attempted.marker
```

Later post-state files may be absent when Running is observed early.

Raw evidence must never be shared or committed.

## 20. Sanitized Candidate Contract

Expected sanitized private candidates:

```text
vehicle-status-resume-pre-paused-1.json
vehicle-status-resume-pre-paused-2.json
command-resume-response.json
command-resume-success.json
vehicle-status-resume-after-*.json
vehicle-status-running-after-resume.json
```

Only these may later be considered canonical:

```text
command-resume-success.json
vehicle-status-running-after-resume.json
```

The existing `vehicle-status-mowing.json` remains canonical when the new
Running response adds no structurally relevant field.

## 21. Resume Success Fixture Contract

`command-resume-success.json` must preserve:

- top-level response code and description types;
- `data.requestId` location;
- `data.payload.commands` array;
- nested devices array;
- command number key and type when present;
- nested `SUCCESS` status;
- nullable `errorCode` type.

Required replacements:

- request ID to `REQUEST_001`;
- device ID to `DEVICE_001`;
- command number to `COMMAND_001`;
- any private account, mower or user value to deterministic placeholders.

The response fixture does not encode the request boolean. The exact request is
validated separately by static tool tests.

## 22. Running-After-Resume Fixture Contract

The terminal candidate must preserve:

- normal status response envelope;
- selected device array entry;
- placeholder device ID;
- exact source state `isRunning`;
- battery structure and scalar types;
- request ID location;
- absence or sanitization of unrelated private fields.

If the response is structurally identical to `vehicle-status-mowing.json`, do
not add a duplicate public fixture. Record structural equivalence in the later
report and reuse the existing fixture.

## 23. Sanitization Gate

Before any candidate enters the case study, verify:

- valid JSON;
- no access token, refresh token or authorization code;
- no callback URL, authorization header, cookie or secret;
- no real request, account, device, serial or command identifier;
- no private mower name;
- no hostname, IP address or local filesystem path;
- no coordinates, maps, boundaries or location history;
- original key names, nesting and JSON scalar types retained;
- boolean `true` remains boolean in any retained synthetic request proof;
- `null` remains `null`;
- files remain small and reviewable.

Raw and sanitized files must be compared locally before promotion.

## 24. Result Classification

| Outcome | Required evidence | Next decision |
| --- | --- | --- |
| `CAPTURE PASS` | accepted Resume, later current Running, expected visible movement | validate fixtures and timing |
| `BLOCKED SAFE` | precondition or physical gate failed before write | retain no command result; review setup |
| `REJECTED` | explicit command rejection | retain sanitized rejection; no implementation |
| `AMBIGUOUS` | delivery may have occurred but response missing | no retry; inspect reads and physical behavior |
| `UNRESOLVED` | accepted but no current Running within 60 seconds | no retry; review state/timing |
| `UNSAFE` | unexpected or dangerous movement | physical intervention; stop program |
| `AUTH FAILURE` | token exchange or authorization failed before write | refresh private auth procedure only |

No result authorizes a second Resume command automatically.

## 25. Readiness Gates After Capture

Resume may advance to fixture review only when:

- nested success is captured;
- current Running is observed after the command baseline;
- expected physical mowing resumed;
- one-write evidence is intact;
- no unsafe outcome occurred;
- sanitization passes;
- operator cleanup is recorded.

Productive implementation additionally requires a separate SAEF readiness
review covering:

- allowlist extension;
- fresh Paused precondition;
- command-specific verification deadline;
- unexpected-state handling;
- restart behavior;
- deterministic no-retry tests;
- preservation of Dock and Pause behavior;
- public variable and archive identity.

## 26. Variable and Archive Protection

The evidence tool does not change the Symcon module.

Any later implementation must preserve:

- all current stable variable Idents and ObjectIDs;
- `NAVIMOW.VehicleState` association values;
- `NAVIMOW.Command` value `5` reserved for Resume;
- all `LastCommand*` types and profiles;
- user-selected Archive Control logging and aggregation;
- accumulated archive histories.

Before the next published Symcon update, capture the anonymized compatibility
baseline before the user presses Update.

## 27. Architecture Decisions

### AD-NAV-197: Treat official Resume support as capability evidence only

**Decision:** Accept the current official HA integration as evidence that Resume
is intended, not as proof of this private REST request.

**Rationale:** The integration delegates to a separate SDK and does not establish
the case-study transport envelope by documentation alone.

**Consequence:** One private REST capture remains mandatory.

### AD-NAV-198: Require two current Paused reads

**Decision:** Gate the write on two consecutive successful `isPaused` responses.

**Rationale:** Resume initiates movement and must not be sent against stale or
already-changing state.

**Consequence:** A failed or changed precondition aborts without command.

### AD-NAV-199: Prepare Pause through the official app

**Decision:** Do not use Symcon or the capture tool to create Paused.

**Rationale:** The evidence run must contain exactly one API/module write.

**Consequence:** Resume is isolated from preceding Pause command behavior.

### AD-NAV-200: Persist the Resume attempt marker before transport

**Decision:** Create a command-specific durable marker immediately before the
single POST.

**Rationale:** Ambiguous network outcomes must not permit accidental repetition.

**Consequence:** The capture set is single-use regardless of outcome.

### AD-NAV-201: Use a capture schedule, not a productive deadline

**Decision:** Observe at 2, 5, 10, 20, 30 and 60 seconds without committing the
module to those constants yet.

**Rationale:** Real transition timing is still missing.

**Consequence:** Productive Resume verification remains a later evidence-based
decision.

### AD-NAV-202: Require physical and REST agreement

**Decision:** Resume passes only when expected visible motion and current
`isRunning` both occur.

**Rationale:** Software state alone is insufficient for a movement-initiating
physical test.

**Consequence:** Divergent evidence remains unresolved or unsafe.

### AD-NAV-203: Keep productive Resume disabled

**Decision:** This step authorizes procedure implementation only.

**Rationale:** No live Resume response or transition fixture exists yet.

**Consequence:** The module allowlist and UI remain Pause and Dock only.

## 28. Gate Decision

| Gate | Decision |
| --- | --- |
| current Resume capability signal | PASS |
| provisional request contract | PASS for procedure construction |
| physical safety design | PASS |
| private tool implementation | CONDITIONAL GO |
| static tool validation | pending |
| private live Resume transmission | NO-GO until the tool passes static review |
| fixture promotion | NO-GO |
| productive Resume implementation | NO-GO |
| publication and Symcon activation | NO-GO |

**Decision: CONDITIONAL GO to implement and statically validate one private
Resume capture procedure.**

No live command is authorized by this document alone.

## 29. Recommended Next Step

Create SAEF step `66-resume-command-private-capture-procedure.md` to:

1. implement `capture-resume-transition.sh` under the private overlay;
2. add no-network payload and sanitizer validation;
3. prove exactly one command endpoint and zero retry paths;
4. prove two consecutive Paused pre-state reads;
5. implement the durable pre-POST marker;
6. implement bounded read-only observation and early Running completion;
7. document exact operator steps;
8. issue the final Go/No-Go for one supervised private Resume capture.
