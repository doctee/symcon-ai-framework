# 327 Start Command Private Capture Procedure

**Case study:** Navimow native IP-Symcon module

**Status:** Private helper statically passed; live Start remains separately gated

**Date:** 2026-08-20

## 1. Purpose

This step prepares the causal evidence missing from step 82. It provides one
private, supervised generic Start capture without implementing Start in the
productive module.

The procedure:

- authenticates privately;
- selects exactly one mower;
- requires two consecutive current Docked reads;
- sends exactly one generic Start after exact typed confirmation;
- never retries the write;
- verifies Running through read-only REST checks;
- accepts no area or zone parameter; and
- sends no cleanup command.

No live API request or mower command was made during implementation or static
validation.

## 2. Private Helper

Executable:

```text
private/navimow-capture/capture-start-transition.sh
```

Private output:

```text
private/navimow-capture/output/start-transition/raw/
private/navimow-capture/output/start-transition/sanitized/
```

Attempt marker:

```text
private/navimow-capture/output/start-transition/raw/start-command-attempted.marker
```

The root `/private/` ignore rule covers helper, local schedule context and all
capture output.

## 3. Exact Request

The helper constructs exactly:

```json
{
  "commands": [
    {
      "devices": [{"id": "DEVICE_001"}],
      "execution": {
        "command": "action.devices.commands.StartStop",
        "params": {"on": true}
      }
    }
  ]
}
```

There is no zone, area, partition, map, order, schedule or cutting parameter.

## 4. Preconditions

The live run requires:

- mower and station continuously visible;
- departure path and mowing area clear;
- suitable dry weather and ground conditions;
- no app-visible delayed-mowing state caused by wetness;
- official app connected and immediately available;
- physical stop control immediately reachable;
- no expected schedule start during the capture;
- no active Symcon command verification;
- sufficient battery; and
- enough supervised time for observation and later Dock cleanup.

The helper proceeds only after two consecutive REST reads report `isDocked`.
It does not support Idle in this first evidence run.

## 5. Safety And No-Retry Contract

Immediately before the write, the operator must type:

```text
START ONCE
```

The helper then creates the attempt marker using no-clobber semantics before
transport. After that point:

- a timeout is ambiguous;
- HTTP success alone is insufficient;
- `alreadyInState` is not accepted as command success;
- no Start replay is allowed;
- transient status-read failures do not trigger a command retry; and
- only later read-only checks may complete the evidence.

The official app or physical stop remains the immediate safety path.

## 6. Post-State Observation

The bounded status schedule is:

```text
2, 5, 10, 20, 30, 45, 60, 90, 120 and 180 seconds
```

Every successful response is retained privately and sanitized separately. The
capture succeeds only when:

1. the nested command result is `SUCCESS`; and
2. two consecutive post-command reads report `isRunning`.

Self-Checking, Idle and other observed states are evidence, not terminal
success. A failed read is recorded as unknown and later scheduled reads
continue within the same bounded window.

## 7. Cleanup Boundary

The helper never sends Pause, Resume, Stop or Dock. After evidence closes, the
operator returns the mower using either:

- the already verified Symcon Dock action; or
- the official Navimow app.

Dock cleanup is a distinct operator action. An ambiguous Start response must
not be followed by an automated compensating command.

## 8. Static Validation

No-network validation command:

```sh
NAVIMOW_CAPTURE_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-start-transition.sh
```

Observed result:

```text
Start capture static payload and sanitizer validation passed.
```

Validated properties:

| Check | Result |
|---|---|
| Bash syntax | passed |
| executable mode | `700` |
| private ignore coverage | passed |
| exact one-command request | passed |
| exact `StartStop` operation | passed |
| JSON Boolean `on=true` | passed |
| unexpected request parameters | rejected |
| synthetic token redaction | passed |
| synthetic device/request redaction | passed |
| automatic command retry | absent |
| attempt marker before write | present |
| Dock/Pause/Resume/Stop cleanup write | absent |
| productive module change | none |

No OAuth, discovery, status or command request was executed by validation.

## 9. Live Procedure

The later separately approved run is:

From the SAEF repository root:

```sh
./private/navimow-capture/capture-start-transition.sh
```

The operator:

1. opens the displayed login URL;
2. completes authentication;
3. pastes the client secret and callback URL through hidden prompts;
4. confirms the physical safety gate;
5. waits for two Docked reads;
6. types `START ONCE` only when movement and cutting are safe;
7. supervises until Running is confirmed or the bounded window ends; and
8. performs Dock cleanup separately after the evidence result is known.

Raw output must never be shared. Sanitized candidates require review before
promotion to fixtures.

## 10. Rain And Schedule Boundary

This first capture must not intentionally coincide with rain, an app-visible
wetness delay or an automatic schedule slot. It is not a safety-interlock
bypass test. If cloud or device protection refuses movement despite a clear
preflight, that outcome is retained as rejection evidence and Start is not
retried.

The official SDK contains a Rain error category but defines no force or weather
override parameter for generic Start. Current upstream discussion also
indicates that Start after an interrupted task may carry continue/reset-task
semantics. A wet-delay Start therefore requires separate vendor-backed safety
and task-lifecycle evidence and is outside this procedure.

The command must not be described as starting a particular area. Its only
supported label is `generic Start`.

## 11. Architecture Decisions

### AD-NAV-1342: Implement Start evidence independently from Stop

**Decision:** Allow Start to progress while Stop remains excluded.

**Rationale:** They are independent cloud operations, and Dock already provides
an evidence-backed return action.

### AD-NAV-1343: Restrict the first Start precondition to Docked

**Decision:** Do not include Idle or Paused in the first capture.

**Rationale:** Docked is already fixture-backed; Paused belongs to Resume.

### AD-NAV-1344: Require two Running reads

**Decision:** One transient Running projection is insufficient for causal
success.

**Rationale:** Consecutive reads strengthen movement-state evidence without a
second command.

### AD-NAV-1345: Keep cleanup outside the capture write

**Decision:** Do not automatically Dock after Start.

**Rationale:** A second write could conceal an ambiguous Start result and is a
separate physical action.

## 12. Decision And Next Gate

**Capture helper implementation: PASS.**

**No-network validation: PASS.**

**Live generic Start: not executed.**

**Productive Start implementation: still blocked by causal evidence.**

The next Start gate is one explicitly timed, supervised execution of the
private helper while the mower is Docked and the weather and departure path
are suitable. After sanitized review, a fixture-validation and implementation-
readiness step may decide the productive action contract.
