# 25 Command Transition Capture Procedure

**Case study:** Navimow native IP-Symcon module
**Status:** Executable private procedure ready; live execution pending
**Date:** 2026-07-09
**Execution boundary:** This step prepares and statically verifies a private
terminal workflow. It sends no command and changes no productive module code.

## 1. Purpose

This procedure implements the approved evidence plan from
`24-command-transition-evidence-plan.md`.

It provides a directly executable Mac terminal workflow for one supervised
Running-to-Docked capture:

- the user starts mowing in the official app;
- the tool confirms Running through REST;
- the user authorizes exactly one Dock request;
- the tool captures the command response privately;
- bounded read-only requests observe the return;
- sanitized candidate fixtures are produced separately.

## 2. Private Tool

The executable helper is:

```text
private/navimow-capture/capture-dock-transition.sh
```

It is deliberately stored under the Git-ignored private overlay.

The existing general-purpose `capture-rest.sh` is not used for this test,
because its broader workflow could create unrelated evidence or another
optional Dock request.

### AD-NAV-044: Use a dedicated one-command capture tool

**Decision:** Transition evidence has its own executable script.

**Rationale:** A focused tool makes the single write operation, pre-state gate,
observation window and retry prohibition reviewable.

**Consequence:** The transition run does not share optional command branches
with the earlier fixture collection workflow.

## 3. Tool Safety Properties

The private script:

- does not start the mower;
- requires a real REST state of `isRunning`;
- polls the pre-state at most twelve times with ten-second spacing;
- asks for the exact phrase `DOCK ONCE`;
- creates a persistent attempt marker before the command call;
- contains exactly one `/openapi/smarthome/sendCommands` call;
- does not use curl retry options;
- does not follow up with another command;
- observes post-state only at bounded elapsed times;
- stops observation after at most two minutes;
- keeps raw and sanitized data in separate directories;
- refuses a later run while the attempt marker remains.

The marker is:

```text
output/transition/raw/dock-command-attempted.marker
```

It is written before the Dock POST. Therefore a transport interruption cannot
silently make the same tool repeat an uncertain command.

## 4. Static Verification

The following checks passed without network access:

- Bash syntax validation;
- executable file permission;
- Git ignore coverage;
- exactly one command-endpoint occurrence;
- marker creation appears before the command POST;
- no productive distribution file changed.

`shellcheck` was not installed in the local environment and was therefore not
run. Bash syntax validation passed.

No mower command was sent during these checks.

## 5. Required Physical Preconditions

Do not start the procedure until:

- the mower and charging station are continuously visible;
- the operating area and return route are clear;
- no person or animal can unexpectedly enter the area;
- the official Navimow app is connected;
- the physical stop control is immediately reachable;
- automatic schedules cannot interfere;
- weather and ground conditions are suitable;
- one operator has exclusive control;
- the mower is initially docked and has sufficient battery;
- enough uninterrupted time is available for supervision.

The user remains responsible for physical safety throughout the run.

## 6. Terminal Start

From the SAEF repository root:

```sh
./private/navimow-capture/capture-dock-transition.sh
```

The tool prints:

- that it can send exactly one Dock command;
- that it never starts the mower;
- that it never retries the command;
- the private raw output location;
- the OAuth login URL.

## 7. Authentication

1. open the printed Navimow login URL;
2. complete login;
3. copy the full failing localhost redirect URL or only its authorization
   code;
4. paste it at:

```text
Paste authorization code or full redirect URL:
```

The terminal does not need the Navimow account password.

The tool exchanges the short-lived authorization code, discovers the device
and creates a private status request.

If exactly one device cannot be selected, the tool aborts before a command.

## 8. Establish Running State

The tool displays the physical safety gate and then prompts:

```text
Start mowing manually in the official app, then press Enter here.
```

Required user actions:

1. start mowing in the official Navimow app;
2. verify that the mower operates normally in the supervised safe area;
3. return to the terminal;
4. press Enter once.

The tool then performs only read-only status checks:

```text
Read-only pre-state check N/12: <state>
```

It continues only after:

```text
isRunning
```

If Running is not confirmed within the bounded window, the tool exits and no
Dock command is sent.

## 9. Final Write Confirmation

After Running is confirmed, the tool states that:

- the next confirmation sends one Dock command;
- no command retry occurs after timeout or ambiguity.

The prompt is:

```text
Type DOCK ONCE to continue:
```

Only the exact input below is accepted:

```text
DOCK ONCE
```

Any other input aborts without sending Dock.

Before typing the phrase, confirm again:

- the mower remains visible;
- the route to the station is clear;
- the official app and physical stop remain available.

## 10. Single Command Attempt

After confirmation, the tool:

1. creates the exact Dock request envelope;
2. writes the attempt marker;
3. sends one POST to `/openapi/smarthome/sendCommands`;
4. captures the HTTP status and response;
5. sanitizes the response if transport completed.

Expected progress text:

```text
Sending the single Dock command now...
Dock response received with HTTP <status>.
```

If curl reports a transport failure, the command result is ambiguous.

In that case:

- do not rerun the capture;
- do not remove the marker to try again;
- use the official app or physical stop control if safety requires it;
- retain the private evidence for review.

## 11. Bounded Read-Only Observation

After the single command, the tool performs status reads at approximately:

```text
5, 15, 30, 60, 90 and 120 seconds
```

It prints:

```text
Read-only post-state at <seconds>s: <state>
```

The observation stops early when `isDocked` is seen.

Expected useful states include:

- `isRunning`;
- `isDocking`;
- `isDocked`.

The tool does not require `isDocking`, because the polling interval may skip a
short transitional state.

If Docked is not observed after two minutes:

- the tool exits with a non-zero status;
- it does not resend Dock;
- the user restores or verifies safety through the official app if necessary;
- the evidence is not yet considered a complete transition.

## 12. Private Output

Raw files remain under:

```text
private/navimow-capture/output/transition/raw/
```

They may include:

- OAuth token response;
- account discovery;
- real device ID;
- private request payload;
- raw command response;
- raw pre-state and post-state;
- local attempt timestamp.

Never commit, paste or share these files.

## 13. Sanitized Candidates

Reviewable candidates are written under:

```text
private/navimow-capture/output/transition/sanitized/
```

Expected files:

```text
vehicle-status-running.json
command-dock-success.json
vehicle-status-after-5s.json
vehicle-status-after-15s.json
vehicle-status-after-30s.json
vehicle-status-after-60s.json
vehicle-status-after-90s.json
vehicle-status-after-120s.json
```

Observation stops early after Docked, so later files may not exist.

The presence of `command-dock-success.json` does not by itself prove success.
Its payload must still pass semantic and redaction review.

## 14. Sanitization Review

Before a candidate enters the public fixture directory, verify:

- all device IDs use `DEVICE_001`;
- request IDs use a placeholder;
- no access or refresh token exists;
- no authorization code exists;
- no account identifier or private name exists;
- no local path exists;
- no exact coordinate or map data exists;
- command nesting and scalar types are preserved;
- top-level and nested status fields remain authentic.

Only the reviewed command response should become:

```text
case-studies/navimow/fixtures/rest/command-dock-success.json
```

A status candidate should be added only if it contributes a new stable state
such as `isDocking`.

## 15. Result Return Format

After the terminal run, return only:

- the final terminal summary;
- the list of generated sanitized filenames;
- whether the mower is safely docked;
- optionally the sanitized command candidate after local review.

Do not return:

- authorization input;
- tokens;
- real device ID;
- raw command response;
- raw status files;
- private paths containing installation-specific names.

Preferred short result:

```text
Transition capture completed.
Docked observed: yes/no
Command candidate: generated/not generated
Post-state files: <sanitized filenames only>
```

## 16. Acceptance Gate

The procedure is successful only when:

- Running was confirmed before the command;
- one Dock attempt was made;
- command response transport completed;
- the candidate is not `alreadyInState`;
- nested command status indicates acceptance;
- later read-only status reaches Docked;
- no retry occurred;
- mower safety was maintained;
- sanitized evidence passes review.

An incomplete or unexpected result is still useful private diagnostic evidence,
but it must not be labelled a successful transition fixture.

## 17. Cleanup and Marker Policy

After the run:

1. keep the marker and raw evidence while reviewing the capture;
2. confirm the mower is physically safe;
3. copy only reviewed sanitized fixtures into the public case study;
4. archive or delete raw evidence only after review is complete;
5. do not clear the marker for another attempt without a new SAEF decision.

The marker is an operational safety control, not disposable temporary output.

## 18. Scope Still Blocked

This procedure does not enable or approve:

- Start in IP-Symcon;
- Stop;
- Pause;
- Resume;
- repeated transition captures;
- automatic command retries;
- unattended operation;
- MQTT/WSS;
- productive timer changes.

## 19. Recommendation and Next Step

The executable private procedure is ready for a supervised run.

The next step should be performed only when physical conditions are suitable
and the user is ready to supervise the complete transition.

After execution, create:

```text
case-studies/navimow/26-command-transition-capture-report.md
```

That report should validate the sanitized command response, measured state
progression, redaction and impact on the command contract before any productive
code changes are considered.
