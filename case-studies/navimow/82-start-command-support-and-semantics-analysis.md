# 82 Start Command Support and Semantics Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Generic Start statically supported; zone targeting unavailable; implementation remains gated
**Date:** 2026-07-27
**Scope:** Analyze Start transport, state contract, area selection and capture readiness without actuation

## 1. Purpose

This step performs the non-actuating Start analysis required by
`55-command-integration-sequence-and-safety-plan.md`.

It determines:

- whether current official sources support Start;
- the exact generic Start payload;
- expected pre-state, progress and terminal-state semantics;
- whether a mowing area, zone or order can be selected;
- which evidence is still missing before a private capture or implementation;
- how the existing variable and archive contract remains stable.

No Navimow command, OAuth operation, Symcon mutation, fixture promotion or
productive PHP change occurs.

## 2. Reviewed Source Revisions

| Source | Revision | Role |
| --- | --- | --- |
| official [`segwaynavimow/navimow-sdk`](https://github.com/segwaynavimow/navimow-sdk) | `6596aa0a65dcf05ed248da87c36975f2ea236ab8` | command mapping and state model |
| official [`segwaynavimow/NavimowHA`](https://github.com/segwaynavimow/NavimowHA) | `2331841f1fbb5b28440228426469d2ceab0cbb28` | current product integration |
| community [`TA2k/ioBroker.navimow`](https://github.com/TA2k/ioBroker.navimow) | `8f8f00d7cdac258ea70437c1bb0ed4f6e69e4a42` | independent payload comparison |

The official SDK revision is unchanged from the Stop inquiry review.

## 3. Generic Start Transport

All three current source paths agree on the generic request:

```text
endpoint: POST /openapi/smarthome/sendCommands
command: action.devices.commands.StartStop
params: {"on": true}
```

The official SDK exposes:

```text
MowerCommand.START
MowerAPI.async_send_command(...)
MowerClient.async_start_mowing(...)
MowerClient.start_mowing(...)
```

The official Home Assistant integration declares
`LawnMowerEntityFeature.START_MOWING` and calls `MowerCommand.START`.

This is materially stronger evidence than the current Stop evidence because
Start is present in the low-level SDK, high-level SDK client, SDK README and
official product integration.

## 4. State Semantics

The official SDK normalizes:

| Raw state | Canonical SDK state | Current Symcon state |
| --- | --- | --- |
| `isDocked` | `docked` | Docked |
| `isIdel` / `isIdle` | `idle` | Idle |
| `Self-Checking` | `idle` | Self-Checking remains separately represented |
| `isRunning` | `mowing` | Running |
| `isPaused` | `paused` | Paused |
| `isDocking` | `returning` | Docking |

The official Start feature and state model support `Running/isRunning` as the
terminal verification target.

Potential preparation states such as Self-Checking or Idle are plausible, but
their ordering and duration after a real Start have not been captured. They
must not be accepted as successful terminal states.

## 5. Area and Zone Selection

The current generic Start request contains only:

```json
{"on": true}
```

It contains no:

- zone ID;
- area ID;
- map ID;
- ordered zone list;
- mowing height;
- task profile;
- schedule selector.

Current code searches found no zone or area command API in either official
repository. The ioBroker command mapping also contains no area parameter.

Official public issue evidence confirms the distinction:

- [SDK issue 4](https://github.com/segwaynavimow/navimow-sdk/issues/4) requests
  zone/area targeting for `async_start_mowing`;
- the official account states that zone selection, ordering and retrieval are
  planned future SDK work;
- [NavimowHA issue 66](https://github.com/segwaynavimow/NavimowHA/issues/66)
  describes the current integration as all-zone Start only;
- [NavimowHA issue 80](https://github.com/segwaynavimow/NavimowHA/issues/80)
  confirms zone/map selection is still in planning.

The supported current conclusion is:

```text
Generic Start: available
Zone-specific Start: unavailable
Area/order selection: unavailable
```

It is a source-based inference that generic Start uses the mowing task or area
configuration already stored by the Navimow app/cloud. The public API does not
expose enough information to promise exactly which configured zones will run.

## 6. Start Versus Resume

Start and Resume use different cloud commands:

| Operation | Command | Intended meaning |
| --- | --- | --- |
| Start | `StartStop`, `on=true` | initiate a mowing task |
| Resume | `PauseUnpause`, `on=true` | continue a paused task |

Therefore:

- Paused must continue to use Resume;
- Start must not be offered as an alternative Resume path;
- Start testing should begin from Docked or fixture-backed Idle;
- a current Running state must reject Start as ineligible;
- Docking, Error, Software Update, Self-Checking and Offline must fail closed
  until evidence defines safe behavior.

## 7. Provisional Start Contract

| Item | Provisional requirement |
| --- | --- |
| operator action | one explicit Start action |
| precondition | fresh successful REST state is Docked or fixture-backed Idle |
| transport | one `StartStop` write with `on=true` |
| automatic retry | prohibited |
| terminal state | Running |
| progress states | none accepted until captured |
| already-in-state | not treated as success without evidence |
| timeout | bounded and determined from real transition evidence |
| restart behavior | resume read-only verification; never replay Start |
| cleanup | official app Pause or Dock only after Start evidence closes |
| supervision | mower, station and departure path continuously visible |

The first Start capture must not include a zone selector because no supported
selector exists.

## 8. Safety Boundary

Start initiates movement and potentially cutting. A future live procedure must
require:

- clear mowing and departure area;
- mower and station in sight;
- official app immediately available;
- physical stop control available;
- acceptable weather and mower conditions;
- no people, animals or obstacles in the departure path;
- exact typed confirmation immediately before the single write;
- no command retry after timeout or ambiguous response;
- read-only post-state checks only;
- explicit final cleanup through the official app.

No scheduled or unattended first capture is acceptable.

## 9. Missing Evidence

Before implementation, the case study still needs sanitized evidence for:

1. fresh Docked or proven Idle pre-state;
2. one accepted generic Start response;
3. first post-command state;
4. any Self-Checking or Idle transition actually observed;
5. final Running state;
6. command timing;
7. cleanup outcome;
8. natural rejection only if it occurs without manufacturing risk.

The existing mowing fixture proves the shape of `isRunning`, but it is not
causally linked to a Start command. It cannot replace Start transition
evidence.

## 10. Existing Module Contract

The current productive module correctly rejects Start:

- `CommandContract` has no Start constant;
- the allowlist contains only Dock, Pause and Resume;
- deterministic tests require Start rejection;
- the device form states that Start remains disabled.

The reserved `NAVIMOW.Command` association value for Start remains stable and
must not be renumbered.

No variable, profile, ObjectID or archive stream needs to be added or recreated
for future Start support.

## 11. Readiness Matrix

| Gate | Decision | Evidence |
| --- | --- | --- |
| official generic Start support | PASS | SDK and NavimowHA |
| exact generic payload | PASS | official SDK and community agreement |
| terminal Running state | PASS for capture planning | official state model |
| zone/area selection | NO-GO | planned, not implemented |
| safe precondition | CONDITIONAL | Docked strong; Idle needs fixture |
| progress-state set | BLOCKED | no transition capture |
| command response fixture | BLOCKED | absent |
| already-in-state semantics | BLOCKED | absent |
| private capture procedure design | CONDITIONAL GO | may be prepared without actuation |
| private Start command | NO-GO | explicit supervised procedure absent |
| productive implementation | NO-GO | fixtures and transition evidence absent |
| public Start action | NO-GO | implementation not verified |

## 12. Sequencing Decision

The established command sequence keeps Start last. Pause, Resume and Dock are
complete; Stop remains in its second clarification window.

Static Start analysis is complete now, but the first command capture should
wait until Stop is either:

- supported and processed through its own evidence sequence; or
- formally excluded after the Stop decision gate.

This preserves the agreed command-program order without losing preparatory
work.

## 13. Validation

The analysis changes no executable contract. Validation passed:

- public diff whitespace validation;
- Navimow REST/auth checks;
- all 33 deterministic pilot harness cases, including enforced Start
  rejection;
- Navimow distribution validation;
- complete repository `make check`, including PHPStan and PHPCS.

No Start fixture was created because no Start command was sent.

## 14. Architecture Decisions

### AD-NAV-284: Recognize generic Start as officially supported

**Decision:** Treat plain `StartStop/on=true` as a current official operation.

**Rationale:** It is exposed at every official SDK and integration layer.

**Consequence:** Start may advance to capture-procedure design, but not
actuation.

### AD-NAV-285: Exclude zone targeting from the current Start contract

**Decision:** Do not invent zone, map or area parameters.

**Rationale:** Official maintainers describe these capabilities as future
work, and current payloads contain no selector.

**Consequence:** The first Start scope is generic mowing only.

### AD-NAV-286: Keep Start distinct from Resume

**Decision:** Reject Start from Paused and preserve Resume as the only supported
paused-task continuation.

**Rationale:** The cloud API defines separate command families.

**Consequence:** Task lifecycle semantics remain explicit in UI and tests.

### AD-NAV-287: Require causal transition evidence

**Decision:** Do not promote existing independent Docked and Running fixtures
as proof of Start.

**Rationale:** Matching endpoint states do not prove command causality,
intermediate states or timing.

**Consequence:** A dedicated one-shot capture remains mandatory.

### AD-NAV-288: Preserve the existing variable and archive contract

**Decision:** Reuse current command diagnostics and `VehicleState`.

**Rationale:** Start requires behavior, not a new public state model.

**Consequence:** Existing user logging remains stable through future updates.

## 15. Decision

**Generic Start static support: PASS.**

**Zone- or area-specific Start: NO-GO.**

**Private capture-procedure preparation: CONDITIONAL GO.**

**Private Start actuation: NO-GO.**

**Productive implementation and publication: NO-GO.**

## 16. Recommended Next Step

After the Stop scope decision, create a private one-shot Start capture
procedure with Docked precondition, exact typed confirmation, one write,
read-only transition capture and official-app cleanup.
