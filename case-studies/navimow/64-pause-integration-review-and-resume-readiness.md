# 64 Pause Integration Review and Resume Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Pause evidence closed; conditional GO for Resume evidence planning
**Date:** 2026-07-12
**Scope:** Consolidate Pause and decide the next Resume safety gate

## 1. Purpose

This step reviews the complete Pause integration chain and decides:

- whether Pause is sufficiently proven for continued private-pilot use;
- whether the untagged Pause build should receive a pilot tag now;
- whether Resume may enter evidence preparation;
- which safety and evidence requirements apply before any Resume write;
- which release and Store boundaries remain unchanged.

It adds no productive PHP code, changes no module repository reference and
sends no mower command.

## 2. Reviewed Inputs

The review consolidates:

- `55-command-integration-sequence-and-safety-plan.md`;
- `56-pause-command-evidence-and-readiness-plan.md`;
- `57-pause-command-private-capture-procedure.md`;
- `58-pause-command-private-capture-report.md`;
- `59-pause-command-fixture-validation-and-implementation-readiness.md`;
- `60-pause-command-implementation.md`;
- `61-pause-command-publication-and-symcon-test-plan.md`;
- `62-pause-command-publication.md`;
- `63-pause-command-symcon-test-report.md`;
- canonical Pause fixtures and deterministic harness evidence;
- the current published module commit.

No private capture, credential, ObjectID or installation-specific identifier is
copied into this document.

## 3. Reviewed Module State

Published module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Current reviewed `main` commit:

```text
e82f73f752c4b588f13a5fb5331413279d2b77f7
feat: add bounded Pause command
```

Enabled commands:

```text
Pause
Dock
```

Disabled commands:

```text
Resume
Stop
Start
```

The published commit is intentionally untagged.

## 4. Pause Evidence Matrix

| Evidence area | Result | Source |
| --- | --- | --- |
| static request | passed | `PauseUnpause`, boolean `false` |
| successful response fixture | passed | `command-pause-success.json` |
| terminal state fixture | passed | `vehicle-status-paused.json` |
| private one-shot API capture | passed | Running to Paused |
| transition timing | passed with sample limit | Paused at first two-second read |
| productive account allowlist | passed | Pause and Dock only |
| current-state eligibility | passed | fresh Running read required |
| deterministic success | passed | Paused becomes Verified |
| deterministic timeout | passed | bounded 60-second deadline |
| deterministic unexpected state | passed | fail closed |
| restart reconstruction | passed | reads resume, command does not |
| command retry prevention | passed | one write maximum |
| official schema validation | passed | ten candidate files |
| remote publication parity | passed | seven expected files only |
| direct Symcon loading | passed | existing instances active |
| variable identity | passed across `ApplyChanges()` | all eight stable Idents |
| archive compatibility | passed | five logged variables retained |
| live Symcon command | passed | Accepted and later Verified |
| current REST state | passed | Paused |
| physical observation | passed | mower visibly stopped |
| operational cleanup | passed | official app initiated Docking |
| temporary test cleanup | passed | no retained test scripts |

## 5. Pause Closure Decision

**Pause integration evidence: CLOSED for the current private pilot.**

This means the current published build may continue to expose Pause under the
documented supervised private-pilot boundary.

It does not mean:

- the cloud API is public or vendor-supported;
- every mower model or firmware is proven;
- Pause error semantics are complete;
- unattended physical command use is approved;
- broader public release or Store submission is ready.

Residual Pause limitations remain documented rather than treated as blockers
for the current single-installation pilot.

## 6. Dock Regression Review

Pause changed the shared device command lifecycle and account allowlist, but
did not change Dock's payload or public variable contract.

Current Dock regression evidence remains sufficient for the next Resume
evidence-planning step because:

- exact Dock payload tests remain green;
- all prior Dock harness cases remain green;
- Dock retains its dedicated 15-minute deadline and progress state;
- active legacy commands without a stored kind default safely to Dock;
- the published Pause test did not invoke Dock through Symcon;
- official-app cleanup changed current state without corrupting the recorded
  Pause result.

No additional live Dock command is required before planning Resume. A full
command regression is required again before Resume publication.

## 7. Pilot Tag Decision

**Decision: DEFER a new pilot tag until Resume reaches a terminal release
decision.**

Rationale:

- `e82f73f` is already an immutable auditable commit for the proven Pause
  milestone;
- no rollback or multi-installation distribution need currently requires a
  separate Pause-only tag;
- Pause and Resume form one user-facing suspended-task lifecycle;
- a tag after successful Resume would identify a more coherent pilot feature
  boundary;
- avoiding a tag now reduces release-marker churn without losing traceability.

This decision does not reserve a future tag number. If Resume is rejected or
materially delayed, a later release review may reconsider a Pause-only pilot
tag without moving existing tags.

Existing tags remain immutable:

```text
pilot-0.1.0.1
pilot-0.1.0.2
```

## 8. Resume Evidence Currently Available

| Evidence | Status | Interpretation |
| --- | --- | --- |
| legacy ioBroker mapping | available | request uses the shared Pause/Resume command family |
| current official integration support signal | available | Resume remains represented upstream |
| exact static request | known | `PauseUnpause` with boolean `true` |
| command endpoint | proven | shared command endpoint works |
| generic success response envelope | proven | Dock and Pause share it |
| Paused precondition state | fixture-backed and live-observed | safe starting-state evidence exists |
| Running terminal state | fixture-backed and live-observed | expected target mapping exists |
| account transport and serialization | proven | reusable without endpoint change |
| command diagnostics | proven | reusable without new variables |
| restart-safe verification | proven structurally | command-specific policy still required |
| Resume success response | missing | acceptance is not yet live evidence |
| Paused-to-Running transition | missing | physical behavior and timing unproven |
| Resume rejection response | missing | defensive fallback required |
| Resume already-in-state semantics | missing | must not be inferred |
| productive implementation | absent | correctly disabled |

The inverse boolean is supported by static and upstream evidence, but Pause
success alone does not prove Resume behavior.

## 9. Resume Risk Classification

Resume is a **movement-initiating command**.

Its risk is higher than Pause because it may:

- start blade and drive movement from a stationary mower;
- continue an existing mowing task without a new route preview;
- move while a person approached the paused mower;
- resume after environmental conditions changed;
- be confused with Start even though the task lifecycle differs;
- produce Running before the UI visibly updates.

Resume is lower scope than Start only when the current Paused state is known to
belong to the same supervised mowing task.

## 10. Provisional Resume Contract

| Item | Requirement |
| --- | --- |
| symbolic command | `Resume` |
| cloud command | `action.devices.commands.PauseUnpause` |
| request parameter | JSON boolean `on == true` |
| required current state | Paused |
| required task context | mower was paused from a currently supervised normal mowing task |
| expected terminal state | Running |
| permitted transient state | Paused |
| unsafe terminal states | Docking, Docked, Error or any unknown movement state |
| command retries | none |
| verification retries | bounded reads only |
| recovery | official app Pause or Dock; physical stop when required |

The contract remains provisional until a dedicated private capture confirms
the real request response and transition.

## 11. Required Resume Precondition

Before one private Resume capture, all of these must be true:

1. mower was started manually through the official app;
2. mower entered normal Running under continuous supervision;
3. mower was paused through the official app or another explicitly documented
   safe preparation path;
4. two consecutive current read-only status responses report Paused;
5. mower is visibly stationary;
6. no person has entered the mower's immediate movement area;
7. people, animals and obstacles are clear from the expected route;
8. official app and physical stop remain immediately available;
9. weather, ground and battery permit continued mowing;
10. no automatic schedule or Symcon command verification can interfere;
11. exactly one operator has exclusive control;
12. no previous Resume attempt marker exists for the capture set.

The two-read Paused gate reduces the risk of issuing Resume against an already
moving or independently transitioning mower.

## 12. Preparation Path Decision

The first Resume evidence capture must **not** depend on the productive Symcon
Pause implementation.

Prepare Paused through the official Navimow app, then confirm it through two
REST status reads.

Rationale:

- the Resume capture should isolate one command write;
- a Symcon Pause followed by Resume would create a two-command test;
- a Pause failure must not contaminate Resume readiness;
- official-app preparation keeps the capture tool's write budget at one.

The successful Symcon Pause evidence remains relevant to the product, but it
is not reused as actuation inside the first Resume capture procedure.

## 13. Proposed Resume Capture Sequence

```text
manual Start in official app
  -> normal supervised Running
  -> manual Pause in official app
  -> visibly stationary
  -> current REST Paused read 1
  -> current REST Paused read 2
  -> explicit typed RESUME ONCE confirmation
  -> exactly one Resume command
  -> no command retry
  -> bounded current status reads
  -> Running or terminal unresolved result
  -> cleanup through official app or physical control
```

The capture tool must stop without sending Pause, Dock, Stop or Start.

## 14. Provisional Verification Schedule

Resume is expected to transition locally and quickly, but no real timing
evidence exists yet.

Use the evidence-collection schedule:

```text
2s -> 5s -> 10s -> 20s -> 30s -> 60s
```

Rules:

- stop early after the first current Running read;
- Paused remains a permitted transient state;
- never resend Resume while Paused remains visible;
- an unexpected state does not verify Resume;
- after 60 seconds classify the transition as unresolved;
- physical motion without REST Running is diagnostic evidence, not complete API
  verification.

The productive deadline remains undecided until this evidence is reviewed.

## 15. Required Private Evidence

The capture must retain privately:

- two Paused pre-state responses;
- exact one-command Resume request;
- HTTP status and complete command response;
- bounded post-state responses with relative elapsed times;
- durable command-attempt marker created before the write;
- terminal classification;
- operator physical observation and cleanup action.

Only these sanitized candidates should be considered for later promotion:

```text
command-resume-success.json
vehicle-status-running-after-resume.json
```

The existing generic Running fixture should be reused unless the new payload
contains a structurally relevant difference. A duplicate fixture must not be
published merely because its measurements differ.

## 16. Capture Acceptance Criteria

Resume evidence passes only when:

- both current pre-state reads are Paused;
- the command request contains exact boolean `true`;
- one and only one Resume write is attempted;
- nested command result reports accepted success;
- a later successful current status read reports Running;
- the mower visibly resumes expected normal mowing;
- no unexpected route, direction or task reset is observed;
- no second module command is used for cleanup;
- sanitized candidates pass privacy review;
- raw evidence remains under `private/`.

HTTP 200 or visible movement alone is insufficient.

## 17. Abort and Recovery Rules

Abort before transmission when:

- current state is not Paused twice;
- mower is not visibly stationary;
- task context is unknown;
- area, weather or battery is unsuitable;
- supervision, official app or physical stop is unavailable;
- another command or schedule may interfere.

After transmission:

- never resend Resume;
- observe through read-only status only;
- use official app Pause or Dock if safe recovery is needed;
- use physical stop immediately when motion is unsafe;
- classify an ambiguous response as unresolved delivery;
- do not pair the capture with a Symcon Pause test.

## 18. Variable and Archive Contract

Resume work must preserve:

- all eight stable device variable Idents;
- VehicleState and Command association numbers;
- existing ObjectIDs;
- types and profiles;
- five currently observed archive-enabled variables and any later user changes;
- aggregation settings and accumulated history.

No Resume-specific public variable is required. Existing `LastCommand*`
diagnostics and VehicleState Running represent the complete public lifecycle.

For the next published module update, capture an anonymized compatibility
baseline **before** the user updates Symcon, correcting the evidence limitation
recorded in step 63.

## 19. Resume Gate Matrix

| Gate | Decision |
| --- | --- |
| static request contract | PASS |
| upstream support signal | PASS |
| Paused precondition mapping | PASS |
| Running terminal mapping | PASS |
| shared transport | PASS |
| one private capture planning | CONDITIONAL GO |
| private live transmission | NO-GO until dedicated procedure passes static review |
| fixture promotion | NO-GO pending capture |
| productive implementation | NO-GO |
| publication | NO-GO |
| Symcon live Resume | NO-GO |
| pilot tag | DEFERRED |

## 20. Release and Store Boundary

Pause closure does not change the broader release decision.

Still blocked:

- public OAuth support model;
- complete command set;
- Stop support classification;
- Start evidence and implementation;
- consolidated command regression;
- broader device/firmware evidence;
- Store setup or submission.

Store work remains planning-only until Pause, Resume and Start are complete,
Stop is completed or formally excluded, and vendor-supported OAuth is resolved.

## 21. Architecture Decisions

### AD-NAV-191: Close Pause for private-pilot use

**Decision:** Treat the current Pause evidence chain as complete for the
controlled private pilot.

**Rationale:** Static, fixture, deterministic, publication, direct Symcon and
physical evidence all agree.

**Consequence:** Further Pause work is driven by observed defects, broader
compatibility evidence or release needs rather than another nominal transition.

### AD-NAV-192: Defer the next pilot tag until Resume decision

**Decision:** Keep `e82f73f` untagged for now.

**Rationale:** The commit is traceable, while a Pause/Resume tag would form a
more coherent suspended-task milestone.

**Consequence:** No Git reference changes in this step.

### AD-NAV-193: Classify Resume as movement-initiating

**Decision:** Apply a stronger physical safety gate than Pause.

**Rationale:** Resume starts drive and blade movement from a stationary state.

**Consequence:** Clear-area confirmation and immediate physical intervention
remain mandatory.

### AD-NAV-194: Prepare Paused through the official app

**Decision:** The first Resume capture contains one module/API write only.

**Rationale:** Isolating Resume avoids coupling its evidence to a preceding
Pause command.

**Consequence:** The capture tool cannot send Pause as setup.

### AD-NAV-195: Reuse mappings, not command conclusions

**Decision:** Reuse fixture-backed Paused and Running mapping while requiring a
new Resume response and transition capture.

**Rationale:** Shared state vocabulary does not prove inverse physical command
behavior.

**Consequence:** Productive Resume remains blocked after this review.

### AD-NAV-196: Capture compatibility baseline before the next update

**Decision:** Record anonymized pre-update identity and archive metadata before
publishing Resume to Symcon.

**Rationale:** Step 63 proved continuity across post-update `ApplyChanges()` but
did not retain a numeric pre-update snapshot.

**Consequence:** The next migration report can directly prove repository-update
identity without publishing ObjectIDs.

## 22. Decision

**Pause integration review: PASS and CLOSED for controlled private-pilot use.**

**New Pause-only pilot tag: DEFERRED.**

**Resume evidence preparation: CONDITIONAL GO.**

**Resume implementation, publication and Symcon activation: NO-GO.**

## 23. Recommended Next Step

Create SAEF step `65-resume-command-evidence-and-readiness-plan.md` to turn the
provisional contract into an executable evidence gate. It must:

1. revalidate current upstream Resume support without exposing credentials;
2. define the exact one-shot request and response fixture contracts;
3. define private tool isolation and durable attempt evidence;
4. specify two consecutive Paused pre-state reads;
5. specify the movement-safety confirmation and abort path;
6. define sanitization and terminal result classification;
7. decide whether one supervised private Resume capture is authorized.
