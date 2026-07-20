# 67 Resume Command Private Capture Report

**Case study:** Navimow native IP-Symcon module
**Status:** Private Resume capture passed; productive implementation remains gated
**Date:** 2026-07-12
**Scope:** Evaluate one supervised Paused-to-Running REST transition

## 1. Purpose

This report evaluates the private live run authorized by
`66-resume-command-private-capture-procedure.md`.

The run was limited to one Resume command. Paused preparation and final return
to station were performed through the official Navimow app.

The run did not test productive IP-Symcon Resume, Start, Stop, Dock, MQTT/WSS
communication or command retries.

## 2. Procedure Boundary

The private tool:

- authenticated with local private OAuth material;
- selected one currently discovered mower;
- required two current Paused reads;
- created a durable marker before transport;
- sent one Resume request;
- performed only read-only status requests afterward;
- stopped after current Running was observed;
- sent no setup or cleanup command.

Raw evidence, credentials and real identifiers remain under `private/`.

## 3. Execution Summary

Observed bounded sequence:

```text
current pre-state 1: isPaused
current pre-state 2: isPaused
single Resume attempt marker: created
nested command result: SUCCESS
read-only post-state at 2 seconds: isRunning
```

No later post-state files were required because the tool stopped at the first
terminal Running observation.

## 4. Technical Acceptance

| Criterion | Observed result |
| --- | --- |
| private output isolation | passed |
| two consecutive Paused reads | passed |
| durable pre-POST marker | present |
| Resume writes | one procedure-controlled attempt |
| top-level API code | `1` |
| nested command status | `SUCCESS` |
| nested command error | `null` |
| first terminal read | `isRunning` at 2 seconds |
| command retry | none |
| setup command from tool | none |
| cleanup command from tool | none |

The capture satisfies the technical acceptance contract from steps 65 and 66.

## 5. Request Contract Evidence

The statically validated request was:

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

The live response proves that the provisional boolean-`true` contract was
accepted for a mower whose current state was Paused.

The later current Running response independently proves the terminal state.
Cloud acceptance alone was not used as execution evidence.

## 6. Physical Observation

The operator confirmed:

```text
Normal mowing visibly resumed.
No further intervention was required during the test.
```

The physical observation agrees with the current REST `isRunning` result.

No unsafe route, direction, movement or blade behavior was reported.

## 7. Cleanup

After the test, the operator sent the mower home through the official Navimow
app.

The capture tool did not send Pause, Dock, Stop or Start for cleanup. This
preserves the one-write Resume evidence boundary.

The report records the selected official-app recovery action without including
garden, route or device information.

## 8. Timing Finding

Running was observed at the first scheduled status read, two seconds after the
Resume command.

This supports a short asynchronous productive verification design, but one run
does not establish a guaranteed service-level deadline.

A later implementation may use the evidence schedule:

```text
2s -> 5s -> 10s -> 20s -> 30s -> 60s
```

It must stop early at Running, repeat reads only and never repeat Resume.

The exact productive deadline remains subject to fixture and implementation-
readiness review.

## 9. Sanitized Candidates

Private sanitized files generated:

```text
command-resume-response.json
command-resume-success.json
vehicle-status-resume-after-2s.json
vehicle-status-resume-pre-paused-1.json
vehicle-status-resume-pre-paused-2.json
vehicle-status-running-after-resume.json
```

Validation results:

- every file parses as valid JSON;
- targeted token, secret and private-identifier scan is clear;
- placeholder structure is retained;
- nested `SUCCESS` and nullable error shape are retained;
- terminal source state remains exact `isRunning`;
- files remain under the Git-ignored private output root.

These are candidates only. Nothing is promoted automatically into the public
fixture set.

## 10. Fixture Minimization Finding

Only the successful Resume response adds clearly new command-specific evidence:

```text
command-resume-success.json
```

The terminal Running candidate appears to use the same status structure and
source value already represented by:

```text
fixtures/rest/vehicle-status-mowing.json
```

The next step must compare those files structurally while ignoring expected
measurement differences. It should reuse the existing Running fixture unless a
new key, nesting or scalar type is relevant to parsing or verification.

The two Paused pre-state candidates are also likely redundant with the
canonical `vehicle-status-paused.json` fixture.

## 11. Evidence Closure

Previously open Resume questions now have these results:

| Question | Result |
| --- | --- |
| Is boolean `true` accepted? | yes, one private live response |
| Does nested command status report success? | yes |
| Does current state become Running? | yes |
| How quickly was Running first observed? | 2 seconds |
| Did normal physical mowing resume? | yes |
| Was intervention required? | no |
| Could cleanup remain outside the tool? | yes, official app |
| Is rejection behavior known? | no |
| Is already-in-state behavior known? | no |
| Is productive Symcon Resume proven? | no |

## 12. Risks and Limits

| Risk or limitation | Consequence |
| --- | --- |
| one mower and one Resume run | broader model and firmware support remains unknown |
| no natural rejection | failure semantics remain defensive-design work |
| no already-in-state response | must not be inferred from Dock |
| no timeout or delayed live case | longer timing remains deterministic-test scope later |
| no restart during Resume | recovery remains implementation and harness work |
| private undocumented REST API | behavior may change without notice |
| no productive Symcon path | lifecycle and migration remain untested |
| public OAuth unresolved | broader release remains blocked |

## 13. Safety Review

The run satisfied the movement-initiation safety contract:

- Paused was current twice;
- mower was visibly stationary before confirmation;
- the area remained supervised;
- exact typed confirmation preceded the write;
- one Resume attempt was made;
- official app and physical control were available;
- expected normal movement resumed;
- no intervention was required;
- cleanup used the official app.

No evidence supports unattended Resume use.

## 14. Architecture Decisions

### AD-NAV-209: Accept boolean true as fixture-backed Resume request evidence

**Decision:** Treat `PauseUnpause` with JSON boolean `true` as the captured
Resume transport contract.

**Rationale:** The private API accepted the exact request and returned nested
`SUCCESS`.

**Consequence:** A later account allowlist may map symbolic Resume to this
payload after an independent implementation gate.

### AD-NAV-210: Separate command acceptance, REST Running and physical movement

**Decision:** Preserve all three as distinct evidence layers.

**Rationale:** Each layer proves a different part of the physical command
lifecycle.

**Consequence:** Productive verification must continue to use status reads and
must not infer Running from command success.

### AD-NAV-211: Reuse the canonical Running fixture when structurally equivalent

**Decision:** Do not publish a Resume-specific Running fixture merely because
battery or request measurements differ.

**Rationale:** Fixtures should add parser evidence rather than duplicate device
samples.

**Consequence:** The next review performs structural comparison before
promotion.

### AD-NAV-212: Keep cleanup outside the Resume evidence write budget

**Decision:** Record official-app return to station as operational cleanup.

**Rationale:** A second module/API command would weaken the one-shot evidence.

**Consequence:** The capture remains attributable to exactly one Resume write.

### AD-NAV-213: Do not promote private candidates automatically

**Decision:** Require a separate privacy, structure and implementation-readiness
review.

**Rationale:** Sanitized output generation is not equivalent to public fixture
approval.

**Consequence:** Productive Resume remains blocked after this report.

## 15. Gate Decision

**Private Resume capture: PASS.**

The exact request was accepted, current Running was observed after two seconds,
normal mowing visibly resumed and no intervention was required.

**Fixture and implementation-readiness review: GO.**

**Productive Resume implementation, publication and Symcon activation: NO-GO
in this step.**

## 16. Recommended Next Step

Create SAEF step
`68-resume-command-fixture-validation-and-implementation-readiness.md` to:

1. compare private sanitized candidates against raw structure locally;
2. promote only the minimal successful command fixture;
3. determine whether the existing Running fixture is sufficient;
4. define fresh Paused eligibility and command-specific verification;
5. define unexpected-state and timeout behavior;
6. preserve existing Dock, Pause, variable and archive contracts;
7. issue the explicit Go/No-Go decision for productive Resume implementation.
