# 57 Pause Command Private Capture Procedure

**Case study:** Navimow native IP-Symcon module
**Status:** Executable private procedure ready; live execution pending
**Date:** 2026-07-12
**Scope:** Implement and statically validate one supervised Pause capture tool

## 1. Purpose

This step implements the dedicated private capture tool approved by
`56-pause-command-evidence-and-readiness-plan.md`.

The procedure:

- authenticates privately;
- selects exactly one mower;
- requires two consecutive current Running reads;
- sends exactly one Pause command after typed confirmation;
- never retries the command;
- performs bounded read-only post-state observations;
- creates raw and sanitized files separately;
- stops without sending Resume, Stop, Start or Dock.

No productive module PHP file is changed and no live command was sent while
creating or validating this procedure.

## 2. Private Tool

Executable helper:

```text
private/navimow-capture/capture-pause-transition.sh
```

The tool is stored under the Git-ignored private overlay because it handles:

- a short-lived authorization code;
- OAuth token responses;
- real device selection;
- raw command and status payloads;
- local attempt evidence.

Only this SAEF procedure is public. The executable and all output remain
private installation artifacts.

## 3. Tool Isolation

The Pause capture uses its own output root:

```text
private/navimow-capture/output/pause-transition/
```

Subdirectories:

```text
raw/
sanitized/
```

Attempt marker:

```text
raw/pause-command-attempted.marker
```

The tool does not share the Dock transition marker or its output directory.
Existing Dock evidence is not modified.

## 4. Credential Handling

The new Pause tool contains no built-in client-secret value.

It accepts the client secret through either:

```text
NAVIMOW_CLIENT_SECRET
```

or a hidden terminal prompt:

```text
Paste OAuth client secret for this private test (input hidden):
```

The authorization code or callback URL is also entered through a hidden
prompt.

After token exchange, the shell variables containing authorization code and
client secret are unset. Raw token data remains only in the private raw output.

Do not place the secret in shell history. Using the hidden prompt is the
recommended interactive path.

## 5. Static Validation Mode

The tool provides a no-network validation mode:

```sh
NAVIMOW_CAPTURE_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-pause-transition.sh
```

This mode:

- builds a synthetic Pause request for `DEVICE_001`;
- verifies exactly one command and one device;
- verifies the exact `PauseUnpause` command string;
- verifies `params.on` is the JSON boolean `false`;
- rejects unexpected parameters;
- runs the sanitizer against synthetic private values;
- verifies synthetic token and identifier values are removed;
- exits before authentication or network access.

Observed result:

```text
Pause capture static payload and sanitizer validation passed.
```

## 6. Static Verification Results

Checks completed:

| Check | Result |
| --- | --- |
| Bash syntax | passed |
| private script mode | `700` |
| Git ignore coverage | passed through root `/private/` rule |
| no-network validation mode | passed |
| command endpoint occurrences | exactly one |
| curl retry options | zero |
| attempt marker before command POST | passed |
| Pause payload boolean false | passed |
| Dock payload in script | absent |
| Start/Stop payload in script | absent |
| sanitizer synthetic test | passed |
| productive distribution changes | none |

`shellcheck` is not installed in the local environment and was not run. Bash
syntax and the tool's own structural validation passed.

No OAuth request, status request or command request was made by these checks.

## 7. Tool Safety Properties

The executable:

- refuses a run when the Pause attempt marker exists;
- creates the marker before the single command POST;
- has one literal command-endpoint call;
- uses no curl retry option;
- requires two consecutive `isRunning` reads;
- checks Running at most twelve times with five-second spacing;
- asks for exact phrase `PAUSE ONCE`;
- sends only `PauseUnpause` with boolean false;
- observes post-state at bounded elapsed times;
- stops early after `isPaused`;
- does not classify HTTP 200 alone as command success;
- creates `command-pause-success.json` only after nested `SUCCESS`;
- creates `vehicle-status-paused.json` only after a current `isPaused` read;
- exits non-zero when acceptance and Paused evidence are incomplete;
- performs no cleanup command.

## 8. Required Physical Setup

Do not start the live procedure until:

- mower and operating area can remain continuously visible;
- people, animals and obstacles are clear;
- ground and weather conditions permit normal mowing;
- mower battery is sufficient;
- official Navimow app is connected;
- physical stop control is immediately reachable;
- automatic schedules cannot interfere;
- one operator has exclusive control;
- no Symcon command verification is active;
- enough time remains to return the mower safely through the official app;
- the operator accepts that Pause may leave the mower stationary in the lawn.

The user, not the script, starts normal mowing in the official app.

## 9. Terminal Start

From the SAEF repository root:

```sh
./private/navimow-capture/capture-pause-transition.sh
```

The tool states:

```text
Navimow supervised Pause transition capture

This tool can send exactly one Pause command.
It never starts, resumes, stops or docks the mower.
It never retries the Pause command.
```

It also prints the private raw output location and the Navimow login URL.

## 10. Authentication

1. open the displayed Navimow login URL;
2. complete login in the browser;
3. copy the complete failing callback URL or its short-lived code;
4. return to the terminal;
5. paste the OAuth client secret at the hidden secret prompt;
6. paste the callback URL or code at the hidden authorization prompt.

Nothing is displayed while hidden input is pasted.

The tool then:

- exchanges the authorization code once;
- reads the access token privately;
- calls device discovery;
- continues only when exactly one device is available or
  `NAVIMOW_DEVICE_ID` was set privately.

Do not paste the authorization code, callback URL or terminal raw output into
chat.

## 11. Establish Running State

After authentication, the tool prints the physical safety gate and waits at:

```text
Start mowing manually in the official app, then press Enter here.
```

User actions:

1. start mowing in the official Navimow app;
2. observe normal movement in the safe supervised area;
3. return to the terminal while keeping supervision;
4. press Enter once.

The tool performs at most twelve read-only checks:

```text
Read-only pre-state check N/12: <state>
```

It proceeds only after two consecutive checks report:

```text
isRunning
```

The checks are separated by approximately five seconds.

If two consecutive Running reads are not obtained, the tool exits and no
attempt marker or Pause request is created.

If token exchange returns an API error such as `CODE_OAUTH_INFO_ILLEGAL`, the
tool reports only the bounded code and description. It confirms that no mower
command or attempt marker exists. A new run is safe at that point, but it must
use a fresh authorization code and the correct OAuth client secret.

## 12. Final Write Confirmation

After the second Running read, the tool prints:

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

Any other input aborts without sending Pause.

Before entering the phrase, confirm again:

- mower remains visible;
- movement is normal;
- official app remains available;
- physical intervention remains possible.

## 13. Single Pause Attempt

After exact confirmation, the tool:

1. has already structurally validated the private request;
2. writes `pause-command-attempted.marker`;
3. sends one POST to `/openapi/smarthome/sendCommands`;
4. captures HTTP status and raw response;
5. sanitizes the response;
6. reads the nested command result;
7. never calls the command endpoint again.

Expected progress:

```text
Sending the single Pause command now...
Pause response received with HTTP <status>.
Nested command result: <result>
```

Possible nested results:

- `SUCCESS`;
- `ALREADY_IN_STATE`;
- `REJECTED`;
- `MALFORMED`.

If transport fails, the result is ambiguous. Do not remove the marker or rerun
the procedure.

## 14. Read-Only Post-State Observation

The tool reads status at approximately:

```text
2, 5, 10, 20, 30 and 60 seconds
```

It prints:

```text
Read-only post-state at <seconds>s: <state>
```

Observation stops early after:

```text
isPaused
```

No command is sent during observation.

If `isPaused` is not seen within 60 seconds:

- the tool reports an incomplete capture;
- it exits non-zero;
- it does not resend Pause;
- the marker remains;
- physical safety is restored through the official app or physical control.

## 15. Success Conditions

The tool reports successful completion only when both are true:

```text
nested command result == SUCCESS
current post-command state == isPaused
```

Expected message:

```text
Capture completed: Paused state was observed after one accepted command.
```

HTTP 200, physical stopping or a sanitized response file alone is not
sufficient.

## 16. Sanitized Candidates

The tool always uses a neutral response candidate first:

```text
command-pause-response.json
```

It creates the success candidate only after nested success:

```text
command-pause-success.json
```

It creates the canonical status candidate only after current Paused evidence:

```text
vehicle-status-paused.json
```

Additional candidates:

```text
vehicle-status-pause-pre-running-1.json
vehicle-status-pause-pre-running-2.json
vehicle-status-pause-after-2s.json
vehicle-status-pause-after-5s.json
vehicle-status-pause-after-10s.json
vehicle-status-pause-after-20s.json
vehicle-status-pause-after-30s.json
vehicle-status-pause-after-60s.json
```

Later files may be absent when observation stops early.

Candidate presence does not authorize public fixture acceptance. Sanitization
and semantic review remain a separate gate.

## 17. Raw Output Boundary

Raw output may contain:

- token response;
- discovery response;
- real device ID;
- exact command request and response;
- exact private timestamps;
- status payloads;
- attempt marker.

Never share files from:

```text
private/navimow-capture/output/pause-transition/raw/
```

Do not attach raw output to OAuth issue 82 or another public report.

## 18. Safe Cleanup

After the script finishes:

1. confirm the mower is physically stationary and safe;
2. use only the official app or physical controls for cleanup;
3. return the mower to Dock through the app if desired;
4. do not use the module or script to Resume as part of this capture;
5. report cleanup only as `official app`, `physical control` or `none`;
6. retain the marker and raw evidence until review is complete.

The cleanup operation is not part of Pause command evidence.

## 19. Result Return Format

Return only the terminal summary and sanitized filenames.

Preferred form:

```text
Pause capture completed/incomplete.
Nested result: SUCCESS/ALREADY_IN_STATE/REJECTED/MALFORMED/AMBIGUOUS
Paused observed: yes/no
Physical safety maintained: yes/no
Cleanup: official app/physical control/none
Sanitized files: <filenames only>
```

Do not return:

- client secret;
- authorization code or callback URL;
- access or refresh token;
- raw output;
- real device identifier;
- exact garden position;
- private local path beyond the generic directories in this procedure.

## 20. Marker Policy

The marker proves that a Pause transmission may have occurred.

After any attempted command:

- do not remove it merely to repeat the test;
- do not rerun after transport ambiguity;
- review raw and sanitized evidence first;
- require a new SAEF decision before any second Pause capture;
- archive or remove private evidence only after review and explicit intent.

The script itself never clears the marker.

## 21. Live Execution Gate

Static procedure status:

```text
PASS
```

Live execution status:

```text
PENDING USER SAFETY CONFIRMATION
```

Before execution, the user must confirm in the conversation that:

- mower is available for a supervised mowing run;
- area is clear;
- official app and physical stop are ready;
- enough time is available for capture and safe cleanup.

No further code change is required before that confirmation.

## 22. Architecture Decisions

### AD-NAV-161: Remove the credential from the executable

**Decision:** Require hidden input or a private environment variable instead
of embedding a client-secret default in the Pause tool.

**Rationale:** A private ignored script still should not retain a credential
when interactive entry is practical.

**Consequence:** The tool is directly executable but requires local secret
input for every unauthenticated run.

### AD-NAV-162: Add a no-network validation mode

**Decision:** Provide `NAVIMOW_CAPTURE_VALIDATE_ONLY=1` for payload and
sanitizer checks.

**Rationale:** Structural safety can be reproduced without authentication or
physical mower access.

**Consequence:** Static review cannot accidentally transmit Pause.

### AD-NAV-163: Name success files only after semantic success

**Decision:** Create neutral response output first and copy to the success
candidate only after nested `SUCCESS`.

**Rationale:** HTTP success or file creation does not prove command acceptance.

**Consequence:** Rejected and malformed responses cannot masquerade as success
fixtures.

### AD-NAV-164: Keep the first Pause run single-purpose

**Decision:** End after Pause observation and require official controls for
cleanup.

**Rationale:** Pairing Resume would create a second actuator event and merge
two evidence gates.

**Consequence:** Resume remains entirely untested by this procedure.

## 23. Gate Decision

**Procedure implementation: PASS.**

**Live Pause capture: CONDITIONAL GO after immediate user confirmation.**

**Productive Pause implementation: remains NO-GO.**

The private tool is ready, ignored, executable and statically validated. It has
not authenticated, contacted Navimow or sent a command during this step.

## 24. Recommended Next Step

After the user confirms physical readiness, execute the private tool once and
create:

```text
58-pause-command-private-capture-report.md
```

That report should review the command response, Paused state, timing, physical
result, cleanup and sanitized candidates before accepting any public fixture.
