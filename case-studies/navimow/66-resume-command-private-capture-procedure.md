# 66 Resume Command Private Capture Procedure

**Case study:** Navimow native IP-Symcon module
**Status:** Private Resume capture tool statically passed; one supervised run authorized
**Date:** 2026-07-12
**Scope:** Implement and validate one isolated Resume transition capture tool

## 1. Purpose

This step implements the private procedure approved by
`65-resume-command-evidence-and-readiness-plan.md`.

The tool:

- authenticates privately;
- selects one discovered mower;
- requires two consecutive current Paused reads;
- requires exact movement-aware typed confirmation;
- sends at most one Resume command;
- never retries the command;
- observes only read-only status afterward;
- stops early after current Running;
- performs no Pause, Dock, Stop or Start cleanup command.

No productive module PHP file or module repository reference is changed. No
live API request or mower command was made during implementation and static
validation.

## 2. Private Executable

Executable:

```text
private/navimow-capture/capture-resume-transition.sh
```

The script has mode:

```text
700
```

It is stored under the Git-ignored private overlay because it handles OAuth
material, real device selection, raw payloads and physical command evidence.

Only this SAEF procedure is public. The tool and its output remain private.

## 3. Output Isolation

Private output root:

```text
private/navimow-capture/output/resume-transition/
```

Subdirectories:

```text
raw/
sanitized/
```

Attempt marker:

```text
raw/resume-command-attempted.marker
```

The Resume tool does not reuse or modify Pause or Dock evidence directories.

The root `/private/` ignore rule covers the executable and all generated files.

## 4. Exact Command Contract

The tool builds exactly:

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

Static validation proves:

- one command;
- one device;
- exact `PauseUnpause` command string;
- `on` is JSON boolean `true`;
- no additional parameter;
- no boolean `false`;
- no Dock or StartStop command.

## 5. Credential Handling

The script contains no client-secret value.

It accepts the secret through either:

```text
NAVIMOW_CLIENT_SECRET
```

or the hidden prompt:

```text
Paste OAuth client secret for this private test (input hidden):
```

The authorization code or complete callback URL is also entered through a
hidden prompt.

After exchange, the shell variables holding authorization code and client
secret are unset. Raw token material remains only in the private raw directory.

When token exchange does not return an access token, the tool prints only a
bounded API code and description. It confirms that no command and no attempt
marker exist.

## 6. Device Selection

After private discovery, the tool:

- automatically selects only when exactly one device is present; or
- accepts a private `NAVIMOW_DEVICE_ID` override;
- verifies that the selected device exists in the discovery response;
- rejects empty, unknown or ambiguous selections;
- does not print the real selected identifier in normal output.

## 7. Static Validation Mode

No-network command:

```sh
NAVIMOW_CAPTURE_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-resume-transition.sh
```

Observed result:

```text
Resume capture static payload and sanitizer validation passed.
```

Validation mode:

- builds a synthetic request for `DEVICE_001`;
- validates exact Boolean and field set;
- tests sanitizer behavior with synthetic access and refresh tokens;
- tests request, device and command-number replacement;
- confirms no synthetic private value survives;
- exits before authentication, discovery or network access;
- creates no output root or live attempt marker.

## 8. Static Verification Results

| Check | Result |
| --- | --- |
| Bash syntax | passed |
| executable mode | `700` |
| Git-ignore coverage | passed |
| no-network validation | passed |
| command endpoint occurrences | exactly one |
| curl retry options | zero |
| attempt marker before POST | passed |
| marker creation | exclusive/no-clobber |
| command string | exact `PauseUnpause` |
| request boolean | exact JSON `true` |
| Dock command payload | absent |
| StartStop payload | absent |
| Paused precondition branch | present |
| Running terminal branch | present |
| sanitizer synthetic test | passed |
| productive distribution changes | none |

`shellcheck` is not installed in the local environment and was not run. Bash
syntax, source assertions and the tool's no-network validation passed.

## 9. Read Failure Behavior

Status transport failures do not terminate the script inside an active bounded
read phase.

Before the command:

- a failed read clears the consecutive Paused count;
- no malformed partial file is retained as evidence;
- checks continue only within the twelve-attempt limit;
- failure to confirm Paused twice exits without a command.

After an accepted command:

- a failed read is recorded as unknown terminal evidence;
- no command is resent;
- later scheduled reads may still confirm Running;
- observation ends after 60 seconds.

## 10. Marker and No-Retry Enforcement

Before command transport, the tool creates the marker using shell no-clobber
semantics.

Marker content is limited to:

```text
purpose=resume-transition-capture
attempted_at=<UTC timestamp>
command_retry=forbidden
```

It contains no device ID, account ID, token, request body or callback URL.

If another process created the marker first, the tool aborts without sending a
command.

The marker remains after every transport or API outcome. It must not be deleted
merely to repeat an ambiguous test.

## 11. Required Physical Preparation

Do not start the live procedure until:

- mower and expected initial route remain continuously visible;
- people, animals and obstacles are clear;
- nobody is beside or in front of the mower;
- weather and ground conditions allow mowing;
- battery is sufficient;
- official app is connected;
- physical stop is immediately reachable;
- automatic schedules cannot interfere;
- one operator has exclusive control;
- no Symcon command verification is active;
- enough time remains for observation and safe official-app cleanup.

Resume may begin drive and blade motion immediately.

## 12. Terminal Start

From the SAEF repository root:

```sh
./private/navimow-capture/capture-resume-transition.sh
```

The tool prints:

```text
Navimow supervised Resume transition capture

This tool can send exactly one Resume command.
It never starts, pauses, stops or docks the mower.
It never retries the Resume command.
```

It also prints the private raw output location and Navimow login URL.

## 13. Authentication Procedure

1. open the displayed Navimow login URL;
2. authenticate in the browser;
3. copy the complete callback URL or short-lived code;
4. return to Terminal;
5. paste the OAuth client secret at the hidden prompt;
6. paste the callback URL or code at the hidden prompt.

Nothing appears while hidden input is pasted.

Do not paste secret, callback URL, authorization code or raw terminal payloads
into chat.

## 14. Establish Paused Safely

After authentication, the tool waits at:

```text
In the official app, start normal mowing and then pause it. Confirm the mower
is visibly stationary, then press Enter here.
```

Operator sequence:

1. start normal mowing through the official app;
2. observe normal expected mowing;
3. pause through the official app;
4. verify visible stationary state;
5. clear the immediate movement path again;
6. retain supervision;
7. press Enter once in Terminal.

The tool itself sends no setup command.

## 15. Two Consecutive Paused Reads

The tool performs at most twelve read-only checks with approximately five
seconds between unsuccessful or incomplete attempts.

Terminal output:

```text
Read-only pre-state check N/12: <state>
```

It proceeds only after two consecutive reads report:

```text
isPaused
```

The sanitized pre-state candidates are:

```text
vehicle-status-resume-pre-paused-1.json
vehicle-status-resume-pre-paused-2.json
```

Any other state or failed read resets the consecutive count. If the gate does
not pass, the tool exits without a marker or command.

## 16. Final Movement Confirmation

After both Paused reads, the tool prints:

```text
Paused state is confirmed twice.
The next confirmation sends exactly one Resume command.
The mower may begin moving and cutting immediately.
There is no command retry after timeout or an ambiguous response.
The script will not Pause, Dock, Stop or Start the mower.
Type RESUME ONCE to continue:
```

Only exact input:

```text
RESUME ONCE
```

continues.

Any other input aborts without marker or command.

## 17. Command and Response Handling

After exact confirmation:

1. create the exclusive durable marker;
2. send one POST to `sendCommands`;
3. never repeat it;
4. retain raw response privately;
5. create a neutral sanitized response candidate;
6. evaluate top-level and nested command data;
7. create `command-resume-success.json` only after nested `SUCCESS`.

HTTP 200 alone is insufficient.

Classifications:

- `SUCCESS`;
- `ALREADY_IN_STATE`;
- `REJECTED`;
- `MALFORMED`;
- ambiguous transport failure.

Only `SUCCESS` can complete a passing capture.

## 18. Read-Only Observation

After transport, the script reads status at elapsed:

```text
2s, 5s, 10s, 20s, 30s, 60s
```

It:

- creates elapsed-time raw and sanitized candidates;
- stops early after current `isRunning`;
- creates `vehicle-status-running-after-resume.json` only from that read;
- treats continuing Paused as non-terminal progress;
- never resends Resume;
- exits non-zero after rejection, ambiguity or missing Running evidence.

## 19. Physical Observation and Recovery

The operator must observe whether:

- drive motion resumes;
- mowing/blade behavior appears normal;
- route and direction appear expected;
- any unsafe or surprising movement occurs.

For unsafe behavior, use physical stop immediately.

Otherwise use only the official app for Pause or Dock cleanup. Do not invoke a
Symcon or capture command as part of this run.

The later report must record physical observation and cleanup separately from
the REST result.

## 20. Expected Private Files

Raw files may include:

```text
auth-token.private.json
auth-list.private.json
status-request.private.json
vehicle-status-resume-pre-check-*.private.json
command-resume-request.private.json
command-resume-response.private.json
vehicle-status-resume-after-*.private.json
resume-command-attempted.marker
```

Sanitized candidates may include:

```text
vehicle-status-resume-pre-paused-1.json
vehicle-status-resume-pre-paused-2.json
command-resume-response.json
command-resume-success.json
vehicle-status-resume-after-*.json
vehicle-status-running-after-resume.json
```

Observation stops after Running, so later elapsed files may not exist.

Never share raw files.

## 21. Result Classification

| Result | Evidence | Action |
| --- | --- | --- |
| `CAPTURE PASS` | nested success, later Running, expected visible movement | review sanitized evidence |
| `BLOCKED SAFE` | Paused or safety gate failed before marker | no command evidence; review setup |
| `REJECTED` | cloud rejection | no retry; retain sanitized response |
| `AMBIGUOUS` | uncertain delivery | no retry; use reads and physical observation |
| `UNRESOLVED` | accepted but no Running by 60 seconds | no retry; analyze state contract |
| `UNSAFE` | unexpected movement | physical intervention; stop program |
| `AUTH FAILURE` | authentication failed before marker | use fresh code and correct secret later |

## 22. Architecture Decisions

### AD-NAV-204: Derive the tool from proven private capture mechanics

**Decision:** Reuse the established OAuth, sanitizer and one-write structure
while isolating Resume files and semantics.

**Rationale:** These mechanics already passed Dock and Pause evidence runs.

**Consequence:** Resume does not invent a second credential or evidence model.

### AD-NAV-205: Validate selected device against discovery

**Decision:** Reject even an explicitly configured private device ID when it is
not in the current discovery response.

**Rationale:** A stale or mistyped selection must not receive a physical
command.

**Consequence:** Device targeting fails before marker creation.

### AD-NAV-206: Make marker creation exclusive

**Decision:** Use no-clobber creation immediately before the command POST.

**Rationale:** Two concurrent terminal runs must not both pass an earlier file
existence check.

**Consequence:** At most one process obtains the write budget for an output set.

### AD-NAV-207: Keep failed status reads bounded and read-only

**Decision:** Continue the scheduled read phase after transient read failure
without preserving malformed partial evidence.

**Rationale:** A read failure does not justify repeating the actuator command.

**Consequence:** The tool may still collect terminal evidence within the fixed
window and otherwise exits unresolved.

### AD-NAV-208: Require current Running and expected physical motion

**Decision:** Technical capture completion requires REST Running; final report
completion additionally requires the operator's physical confirmation.

**Rationale:** Movement-initiating commands need software and physical evidence.

**Consequence:** The script can complete technically while the SAEF report
remains pending operator observation.

## 23. Gate Decision

| Gate | Result |
| --- | --- |
| private tool implementation | PASS |
| Bash syntax | PASS |
| no-network payload validation | PASS |
| sanitizer validation | PASS |
| one endpoint / zero retry scan | PASS |
| marker-before-POST ordering | PASS |
| Paused precondition logic | PASS |
| Running terminal logic | PASS |
| private-data isolation | PASS |
| productive distribution invariance | PASS |

**Decision: GO for one supervised private Resume capture using this exact
procedure.**

The GO is single-use for the private output set and depends on every physical
precondition in section 11.

Productive Resume implementation, publication and Symcon activation remain
**NO-GO**.

## 24. Recommended Next Step

Execute the procedure manually with continuous supervision. After the terminal
run, provide only:

- bounded terminal summary;
- whether visible expected mowing resumed;
- whether intervention was required;
- cleanup action;
- sanitized candidate filenames.

Then create SAEF step `67-resume-command-private-capture-report.md` to inspect
private evidence locally, promote nothing automatically and decide whether
Resume may enter fixture and implementation-readiness review.
