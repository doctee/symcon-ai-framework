# 55 Command Integration Sequence and Safety Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Command program sequenced; all new commands remain gated
**Date:** 2026-07-12
**Scope:** Plan Start, Stop, Pause and Resume without implementation

## 1. Purpose

This step defines the command-integration program allowed in parallel with the
pending OAuth vendor inquiry from
`54-navimow-oauth-vendor-inquiry-execution.md`.

It decides:

- which commands belong to the intended integration scope;
- the order in which they may be analyzed and implemented;
- the evidence required before each command becomes available;
- command-specific physical safety boundaries;
- deterministic timeout, restart and no-retry requirements;
- fixture, publication and direct Symcon gates;
- how existing variables and archive logging remain stable.

No productive PHP code is changed and no mower command is sent in this step.

## 2. Current Command Baseline

The current published private pilot supports only:

```text
Dock
```

Dock has:

- a captured request and response contract;
- already-in-state evidence;
- supervised Running-to-Docked transitions;
- long-running Docking progress handling;
- deterministic deadline behavior;
- deterministic and live restart recovery;
- proof that restart, timeout and read failure do not replay the command.

The current command allowlist rejects every symbolic command other than Dock.
This remains the correct production state until each later gate passes.

## 3. Source Comparison

### Original community source

The analyzed ioBroker implementation defines:

| Symbolic command | API command | Parameters |
| --- | --- | --- |
| `Start` | `action.devices.commands.StartStop` | `{ "on": true }` |
| `Stop` | `action.devices.commands.StartStop` | `{ "on": false }` |
| `Pause` | `action.devices.commands.PauseUnpause` | `{ "on": false }` |
| `Resume` | `action.devices.commands.PauseUnpause` | `{ "on": true }` |
| `Dock` | `action.devices.commands.Dock` | empty object |

### Current official integration source

At the revision reviewed in step 52, the official Navimow Home Assistant
integration exposes:

- Start;
- Pause;
- Resume;
- Dock.

It does not expose a separate Stop operation. Its lawn-mower entity declares
Start, Pause and Dock capabilities and implements Resume as a method.

### Interpretation

Start, Pause and Resume have current official-source support in addition to
the original community mapping.

Stop has only legacy/community static evidence in this case study. Its omission
from the current official integration may mean:

- the command is unsupported;
- its semantics overlap Pause;
- it terminates a task in a way not represented by Home Assistant;
- it is model- or firmware-dependent;
- it was omitted for product or safety reasons.

The case study must resolve this ambiguity before treating Stop as an intended
public command.

## 4. Meaning of Complete Command Integration

Complete integration does not mean enabling every opcode observed in an older
source.

It means:

1. all commands confirmed as supported for the target Navimow Smart Home API
   are represented;
2. each command has an explicit state and safety contract;
3. every enabled command has fixture, deterministic and supervised evidence;
4. unsupported or model-specific commands are documented as excluded;
5. no command remains silently half-enabled.

For the current evidence set:

| Command | Target status |
| --- | --- |
| Dock | complete and enabled |
| Pause | intended, gated |
| Resume | intended, gated |
| Start | intended, gated |
| Stop | support decision pending; not yet part of the confirmed target set |

Store setup remains blocked until Start, Pause and Resume are complete and the
Stop support decision is closed by either implementation or an evidenced
exclusion.

## 5. Sequencing Decision

The command program order is:

```text
0. resolve Stop support and semantics without sending it
1. Pause
2. Resume
3. Stop, only if the support gate passes
4. Start
5. consolidated command-set verification
```

Stop research begins first but does not block Pause analysis. Its productive
position is conditional.

### Why Pause first

Pause changes an already moving mower toward a stationary state and has a
clear expected state, `Paused`. The mower can be started through the official
app, so module Start is not required to test it.

### Why Resume follows Pause

Resume is the inverse operation and can reuse the proven Paused precondition.
It initiates motion, so it follows successful Pause handling and receives its
own explicit confirmation and live test.

### Why Stop is conditional

The legacy payload is known, but current official support and terminal state
are not. It must not be marketed as a safer Pause or task cancellation until
real semantics are established.

### Why Start is last

Start initiates a new mowing task and mower movement from a non-running state.
It has the highest physical initiation risk and should use the fully hardened
shared command framework proven by the lower-risk state transitions.

## 6. Universal Command Safety Contract

Every command must satisfy:

- one explicit user action sends at most one cloud command;
- no transport, HTTP, parsing, timeout or restart path retries the command;
- cloud acceptance is not treated as physical completion;
- only later read-only status confirms expected state;
- domain variables are never written optimistically by command code;
- only one command attempt may be active per Device instance;
- a second action while verification is active is rejected;
- account serialization covers transport only, not delayed verification;
- verification timers repeat reads only;
- restart reconstruction resumes reads and never writes;
- missing or ambiguous evidence fails closed;
- all error messages are bounded and sanitized;
- command diagnostics contain no token, device ID or raw payload;
- the official app and physical stop control remain available during live use.

These rules apply even when a command appears idempotent. No actuator request
is assumed safe to replay after an ambiguous response.

## 7. Shared State-Machine Direction

The existing Dock state machine must evolve conservatively into a
command-parameterized verifier.

Shared persistent state should represent:

| State item | Purpose |
| --- | --- |
| active command | exact symbolic command being verified |
| cloud result | Accepted or Already In State |
| start time | original command timestamp |
| deadline | absolute terminal verification boundary |
| verification state | accepted, progress, waiting read or terminal |
| baseline update | distinguish current from stale status evidence |

Each command definition must provide:

- exact allowlisted transport payload;
- required observed precondition for live testing;
- accepted terminal state set;
- optional progress state set;
- verification delay and maximum deadline;
- already-in-state interpretation;
- timeout message;
- safety class.

Do not replace the explicit Dock behavior with a broad dynamic configuration
array before two commands have demonstrated the common shape. Reuse proven
logic, but keep command-specific rules reviewable.

## 8. Command-Specific Contracts

### Pause

Provisional contract:

| Item | Requirement |
| --- | --- |
| precondition for live test | current successful read reports Running |
| request | `PauseUnpause` with `on == false` |
| expected terminal state | Paused |
| progress states | Running may persist briefly after acceptance |
| unsafe outcomes | continued movement beyond bounded verification, unexpected task termination |
| recovery | official app or physical stop; no module retry |

Pause must not be accepted as Verified merely because the cached state was
already Paused before the command. Verification requires a successful status
read after the command baseline.

### Resume

Provisional contract:

| Item | Requirement |
| --- | --- |
| precondition for live test | current successful read reports Paused |
| request | `PauseUnpause` with `on == true` |
| expected terminal state | Running |
| progress states | Paused may persist briefly after acceptance |
| unsafe outcomes | unexpected movement direction, failure to resume predictably |
| recovery | Pause or Dock through official app, physical stop if required |

Resume is movement-initiating. The live confirmation must explicitly state
that the mowing area is clear before the command is sent.

### Stop

No productive contract exists yet.

Questions to resolve:

- Is `StartStop` with `on == false` currently accepted by the API?
- Is Stop distinct from Pause?
- Does it terminate the task, become Idle, remain Paused or trigger Docking?
- Can the task be resumed afterward?
- Is the operation available on all target models?
- Why is it absent from the current official integration?
- What does `alreadyInState` mean for Stop?

No Stop command may be sent merely to discover its behavior. First obtain
current source, vendor, SDK or non-actuating fixture evidence. A supervised
capture may be proposed only after the expected physical outcome and abort
path are documented.

### Start

Provisional contract:

| Item | Requirement |
| --- | --- |
| precondition for live test | current state is Docked or Idle and start conditions are known |
| request | `StartStop` with `on == true` |
| expected terminal state | Running |
| progress states | Idle, Self-Checking or another fixture-backed preparation state only |
| unsafe outcomes | unexpected departure, blocked area, weather or schedule conflict |
| recovery | official app Pause or Dock, physical stop if required |

The first live Start must not be combined with a later command merely to make
the test convenient. Start reaches its own terminal evidence and cleanup gate
before another module command is considered.

## 9. Per-Command Work Packages

Each command passes the same ordered packages.

### CP-1: Static and current-source analysis

- confirm exact API command and parameter types;
- compare community and official implementations;
- identify model or firmware conditions;
- define precondition and expected terminal state;
- document uncertainty without sending a command.

### CP-2: Private capture plan

- define one safe already-in-state or transition scenario;
- specify physical supervision and abort controls;
- define raw/sanitized file boundary;
- prohibit automatic retries;
- require an exact typed confirmation before transmission.

### CP-3: Fixture acquisition

Required where safely obtainable:

- accepted command response;
- already-in-state response;
- pre-state status;
- post-state status;
- intermediate status when observed;
- one natural rejection only if it occurs without manufacturing unsafe state.

Raw data remains private. Only sanitized structural fixtures enter the case
study.

### CP-4: Contract and readiness review

- update command mapping and variable/action contract;
- define command result semantics;
- decide verification timeout from observed transition timing;
- issue explicit Go/No-Go for implementation;
- keep the production allowlist unchanged on No-Go.

### CP-5: Deterministic implementation

- extend the exact command allowlist;
- add payload tests;
- add success, already-in-state, malformed and rejection parsing;
- test no retry after ambiguous transport result;
- test current-read-only verification;
- test deadline and restart reconstruction;
- prove command-call count remains one;
- preserve all existing Dock tests.

### CP-6: Publication and read-only Symcon smoke test

- validate distribution and official Module Validator;
- publish only reviewed productive files;
- update Symcon module;
- confirm instances and variables persist;
- confirm Archive Control logging remains configured;
- execute read-only status refresh;
- send no command during the smoke test.

### CP-7: One supervised live transition

- confirm physical safety and exact pre-state;
- send one command through the module action;
- observe command diagnostics and later status;
- perform no second module command;
- use official app or physical control only for safety recovery;
- record sanitized evidence;
- remove temporary scripts.

### CP-8: Release review

- verify no duplicate request;
- verify no retained pending state;
- verify existing Dock and prior commands still pass;
- update README limitations;
- create a new immutable pilot tag only after evidence is complete;
- never move an existing tag.

No two new commands may share a single implementation or release gate.

## 10. Fixture Matrix

Planned sanitized fixture names:

| Command | Command response | Required status evidence |
| --- | --- | --- |
| Pause | `command-pause-success.json` | `vehicle-status-mowing.json`, new `vehicle-status-paused.json` |
| Resume | `command-resume-success.json` | `vehicle-status-paused.json`, `vehicle-status-mowing.json` |
| Stop | `command-stop-success.json` only after support Go | new terminal-state fixture determined by evidence |
| Start | `command-start-success.json` | `vehicle-status-docked.json` or Idle fixture, `vehicle-status-mowing.json` |

Optional already-in-state fixtures must be captured only when naturally safe.

Fixture acceptance requires:

- structural parity with the raw private capture;
- deterministic placeholders;
- no token, account, device or location information;
- exact JSON types preserved;
- mapping assertion in the test suite;
- related contract update.

## 11. Timing Policy

The Dock 15-minute deadline is specific to physical return travel and must not
be copied to Pause, Resume, Stop or Start.

For each new command:

1. collect observed transition timing;
2. choose a bounded deadline with explicit margin;
3. use a short initial read delay;
4. reduce read frequency after unresolved or failed reads;
5. align the final read exactly with the deadline;
6. terminate without command replay;
7. preserve the original deadline across restart.

No deadline value becomes productive before fixture or supervised timing
evidence exists.

## 12. UI and Action Contract

Commands remain module-owned actions. Status variables remain read-only.

The first implementation for each command may expose a clearly named form
button or an equivalent module action method. It must:

- require an explicit click or `RequestAction()` call;
- avoid a selector whose value change can accidentally trigger a command;
- show only commands that have passed their release gate;
- reject unavailable commands inside the module even if invoked by script;
- use existing `LastCommand*` diagnostics;
- avoid new archive-enabled command variables;
- preserve existing Device variable ObjectIDs and archive logging.

Do not enable all buttons merely because transport mapping has been added.

## 13. Variable and Migration Boundary

The command program must preserve:

- `VehicleState` Ident and integer type;
- `Online` Ident and boolean type;
- `BatteryLevel` Ident and integer type;
- `LastStatusUpdate` Ident and integer type;
- all existing `LastCommand*` Idents and types;
- user-configured Archive Control logging;
- existing Device instance identity.

The reserved `NAVIMOW.Command` profile already assigns stable values to Start,
Stop, Pause, Resume and Dock. These association values must not be renumbered.

Adding command support updates behavior and associations only; it does not
justify recreating variables or the Device instance.

## 14. Deterministic Harness Expansion

The case-study harness should add command-neutral tests before the second
productive command:

- configured expected terminal state;
- command-specific progress states;
- current status baseline enforcement;
- short-transition deadline;
- failed read followed by recovery;
- exact deadline final read;
- restart during Pending Verification;
- restart after elapsed deadline;
- command-call count remains one;
- unknown command remains rejected;
- one command cannot overwrite another active command;
- old terminal state is cleared on a new explicit action;
- existing Dock behavior remains unchanged.

Command-specific tests then add Pause, Resume, conditional Stop and Start
payload and state semantics.

Harness calls remain non-network and non-actuating.

## 15. Live Safety Roles

### User

The user must:

- keep mower and relevant area in sight;
- confirm the physical pre-state;
- keep people, animals and obstacles clear;
- keep the official app available;
- be ready to use the physical stop control;
- provide the typed send confirmation;
- report physical behavior independently from cloud state;
- decide whether to abort.

### Agent and MCP

The agent may:

- perform read-only prechecks;
- expose only sanitized state markers;
- send exactly one approved command after confirmation;
- observe read-only post-state and diagnostics;
- delete temporary scripts;
- stop the procedure when evidence is ambiguous.

The agent must not:

- send a preparatory movement command;
- retry a command;
- use a second command to complete the planned test;
- induce credential or network failures during movement;
- continue after loss of supervision;
- substitute app state for physical user observation.

## 16. Failure and Abort Rules

Abort the live step before transmission when:

- mower state differs from the required precondition;
- status is stale or offline;
- authentication is warning or reauthorization-required;
- another command is active;
- area or weather is unsafe;
- official app or physical control is unavailable;
- user supervision is interrupted.

After transmission:

- do not send the module command again;
- use read-only verification;
- if physical safety is uncertain, intervene through the official app or
  physical stop without waiting for module evidence;
- classify lost response as ambiguous, not failed delivery;
- record recovery action separately from command success;
- stop data collection after safety intervention if necessary.

An aborted-safe test is valid safety evidence and does not automatically fail
the implementation.

## 17. Per-Command Go/No-Go Matrix

| Gate | Pause | Resume | Stop | Start |
| --- | --- | --- | --- | --- |
| static payload | known | known | legacy only | known |
| current official source | present | present | absent | present |
| terminal status fixture | missing | available Running; Paused pre-state missing | unknown | available Running |
| command response fixture | missing | missing | missing | missing |
| implementation | No-Go | No-Go | No-Go | No-Go |
| live transmission | No-Go | No-Go | No-Go | No-Go |

The table changes one command at a time through dedicated SAEF steps.

## 18. Publication Policy

Each newly proven command requires a separate pilot release review.

Possible tags follow the existing immutable sequence, but no tag number is
reserved in advance. A tag is created only after:

- productive implementation passes deterministic gates;
- direct Symcon update passes;
- one supervised live transition passes;
- documentation matches actual enabled scope;
- canonical distribution and publish repository are byte-equivalent.

OAuth issue 82 remains independent. Command pilot tags must not imply public
OAuth or Store readiness.

## 19. Store Boundary

Preparatory Store gap analysis may continue only as planning.

Concrete Store setup or submission remains blocked until:

- Pause, Resume and Start are complete;
- Stop is either complete or formally excluded with current evidence;
- the consolidated command set passes regression and migration review;
- public OAuth receives a supportable vendor-backed model;
- current official Store requirements are revalidated.

## 20. Architecture Decisions

### AD-NAV-151: Sequence commands by physical initiation risk

**Decision:** Implement Pause before Resume and Start, with Start last among the
confirmed commands.

**Rationale:** Pause moves toward a stationary state, while Resume and Start
initiate motion.

**Consequence:** The shared framework gains evidence incrementally before the
highest-risk command.

### AD-NAV-152: Resolve Stop support before implementation

**Decision:** Treat Stop as a support question because it is present in the
community source but absent from the current official integration.

**Rationale:** A legacy payload alone does not define current physical or
terminal semantics.

**Consequence:** Stop is implemented only after evidence, or formally excluded
from the complete supported command set.

### AD-NAV-153: Keep command gates independent

**Decision:** Require separate fixture, implementation, publication and live
evidence for every new command.

**Rationale:** Shared payload families do not imply shared physical behavior.

**Consequence:** No batch activation of Start, Stop, Pause and Resume is
allowed.

### AD-NAV-154: Generalize verification only from proven common behavior

**Decision:** Extend the Dock state machine conservatively and keep
command-specific terminal, progress and deadline rules explicit.

**Rationale:** Premature generic abstraction could hide unsafe assumptions
about timing and state transitions.

**Consequence:** Shared logic grows only as real command evidence demonstrates
the common contract.

### AD-NAV-155: Preserve variable identity throughout command expansion

**Decision:** Reuse existing command diagnostics and stable public variables
without deletion, renaming or type changes.

**Rationale:** User automation and archive history depend on current Symcon
object identity.

**Consequence:** Command integration cannot reset logging or require Device
instance recreation.

## 21. Gate Decision

**Command program plan: PASS.**

**Productive Pause implementation: NO-GO pending fixture and readiness work.**

**Resume, Stop and Start implementation: NO-GO.**

The next work is evidence preparation for Pause and non-actuating Stop support
research. No command is enabled or transmitted by this decision.

## 22. Recommended Next Step

Create:

```text
56-pause-command-evidence-and-readiness-plan.md
```

That step should derive the exact Pause capture procedure, define the Paused
status fixture contract, choose safe preconditions and decide when one
supervised private capture is justified. Stop support research may be recorded
in parallel but must not send a Stop command.
