# 26 Command Transition Capture Report

**Case study:** Navimow native IP-Symcon module
**Status:** Supervised transition evidence gate passed
**Date:** 2026-07-09
**Test boundary:** The mower was started manually through the official app.
The private capture tool then sent exactly one Dock command and used only
read-only requests afterward.

## 1. Purpose

This report validates the first real successful Navimow command transition
captured for the case study.

It records:

- the supervised Running pre-state;
- the single Dock command result;
- observed return-to-dock timing;
- newly accepted sanitized fixtures;
- impact on the command and verification design.

No raw capture or private installation identifier is included.

## 2. Procedure Used

The live run followed:

- `24-command-transition-evidence-plan.md`;
- `25-command-transition-capture-procedure.md`;
- the private executable `capture-dock-transition.sh`.

The tool's static gates had already confirmed:

- exactly one command endpoint call;
- marker creation before the call;
- no command retry;
- bounded read-only observation;
- private raw and sanitized output separation.

## 3. Safety Preconditions

The live run was performed with:

- the mower and charging station supervised;
- the area and return route clear;
- the official Navimow app available;
- physical stop access available;
- manual Start through the official app;
- explicit confirmation before the single Dock request.

The mower returned to its charging station normally.

## 4. Execution Summary

Observed terminal sequence:

```text
Authentication succeeded
Pre-state check 1/12: isRunning
Single Dock command sent
HTTP 200
Post-state at 5 seconds: isDocking
Post-state at 15 seconds: isDocking
Post-state at 30 seconds: isDocking
Post-state at 60 seconds: isDocked
```

No second command was sent.

## 5. Command Response

The sanitized response confirms:

| Field | Observed value |
| --- | --- |
| HTTP status | `200` |
| top-level `code` | `1` |
| top-level `desc` | `Operation successful` |
| command device | `DEVICE_001` |
| `cmdNum` type | string |
| sanitized `cmdNum` | `COMMAND_001` |
| nested `status` | `SUCCESS` |
| nested `errorCode` | `null` |

This is the previously missing real success evidence.

### AD-NAV-045: `SUCCESS` is now fixture-backed

**Decision:** Nested command `status == "SUCCESS"` maps to
`NAVIMOW.CommandResult` value `Accepted`.

**Rationale:** The shape is now confirmed by a sanitized real-device response,
not only by static source analysis or a synthetic test.

**Consequence:** The parser's Accepted branch is evidence-backed.

## 6. Accepted Public Fixtures

Added:

```text
fixtures/rest/command-dock-success.json
fixtures/rest/vehicle-status-docking.json
```

The command fixture preserves:

- response envelope;
- nested command array;
- nested device array;
- string command number type;
- `SUCCESS`;
- nullable `errorCode`.

The status fixture preserves:

- `isDocking`;
- percentage battery structure;
- request and device nesting.

No additional Docked fixture was required because
`vehicle-status-docked.json` already covers the terminal state.

## 7. Sanitization Finding

The first sanitized candidate retained the real `cmdNum`.

It was not accepted publicly in that form.

Corrective action:

- replace the command number with deterministic placeholder `COMMAND_001`;
- update both private sanitizer implementations to recognize `cmdNum` and
  `cmd_num`;
- document the placeholder in `fixtures/README.md`;
- rerun privacy checks before accepting the fixture.

### AD-NAV-046: Command numbers are private identifiers

**Decision:** Command numbers are sanitized even when their exact semantics are
unknown.

**Rationale:** They may be traceable to a cloud request, device or account.
Their type and location matter to parser evidence, but their real value does
not.

**Consequence:** Public fixtures preserve a string placeholder only.

## 8. State Progression

The capture observed:

```text
isRunning -> isDocking -> isDocked
```

`isDocking` remained visible at 5, 15 and 30 seconds. `isDocked` was first
observed at 60 seconds.

This confirms:

- the REST state model represents an in-progress Dock transition;
- `isDocking` maps to the existing `Docking` association;
- command acceptance and terminal Docked state are separate events;
- a verification design must tolerate a legitimate transitional state.

## 9. Verification Timing Finding

The current module uses one verification read after five seconds and expects
Docked.

The real transition evidence shows that after five seconds the correct state
was still Docking.

Therefore, the current implementation would report
`Verification Timeout` for this successful real transition.

This does not affect the already-docked test from
`23-dock-command-symcon-test-report.md`, but it is a functional limitation for
Dock while Running.

### AD-NAV-047: Five-second terminal verification is insufficient

**Decision:** Do not treat Docking after five seconds as a failed command.

**Rationale:** The measured successful transition required approximately 60
seconds to reach Docked.

**Consequence:** Productive verification should become a bounded multi-read
state machine. It may repeat status reads, never the command.

## 10. Required Future Verification Behavior

A future design should:

1. classify `Accepted` as pending verification;
2. read status after a short initial delay;
3. treat Docking as valid progress;
4. continue bounded read-only checks;
5. finish as Verified when Docked appears;
6. finish as Verification Timeout only after a documented maximum window;
7. stop immediately on a terminal error state;
8. never resend Dock;
9. survive or safely recover from `ApplyChanges()` and service restart;
10. retain bounded command diagnostics.

The measured evidence supports a verification window longer than 60 seconds.
The exact interval schedule and timeout require a separate design decision.

## 11. Parser and Fixture Validation

The case-study REST test now:

- loads `command-dock-success.json`;
- maps its real `SUCCESS` response to Accepted;
- loads `vehicle-status-docking.json`;
- maps `isDocking` to Docking;
- retains the `alreadyInState` fixture test;
- retains fail-closed tests for unknown or mismatched results.

Executed checks passed:

```text
Navimow REST client and authentication checks passed.
Navimow distribution structure is valid.
```

Both new fixtures are valid JSON and passed the redaction scan.

## 12. Retry and Single-Write Evidence

The private tool:

- wrote its attempt marker before the command POST;
- contained one command endpoint call;
- received HTTP 200;
- performed only status reads afterward;
- stopped after Docked;
- retained its marker after completion.

The live transcript confirms one Dock attempt and no command retry.

## 13. Cleanup and Private Evidence

After the run:

- the mower was safely docked;
- raw evidence remained in the ignored private workspace;
- the attempt marker remained in place;
- only reviewed sanitized structures entered public fixtures;
- no token, real device ID, command number, request ID or local path entered
  the public fixtures.

Raw evidence must remain private.

## 14. Gate Decision

**Decision:** Command transition evidence gate passed.

The gate passed because:

- Running was confirmed before Dock;
- exactly one Dock request was attempted;
- HTTP and API success were observed;
- nested real command status was `SUCCESS`;
- Docking progress was observed;
- Docked was observed within the bounded window;
- no retry occurred;
- sanitization findings were corrected before publication;
- fixture-based tests passed.

## 15. Scope Still Blocked

This evidence does not yet approve:

- Start in IP-Symcon;
- Stop;
- Pause;
- Resume;
- automatic command retries;
- unattended Dock transitions;
- MQTT/WSS;
- the current five-second verification as sufficient for real transitions.

The evidence validates shared command success parsing and Dock progression. It
does not validate the physical semantics of other actions.

## 16. Recommendation and Next Step

Keep Dock as the only enabled command and redesign its verification before
claiming support for Dock from an active mowing state.

Recommended next SAEF artifact:

```text
case-studies/navimow/27-dock-transition-verification-design.md
```

That document should define the bounded verification state machine, read
intervals, timeout, restart behavior and tests. It must preserve the strict
rule that only status reads may repeat.
