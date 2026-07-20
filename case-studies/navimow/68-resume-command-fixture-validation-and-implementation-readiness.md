# 68 Resume Command Fixture Validation and Implementation Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Conditional GO for bounded productive Resume implementation
**Date:** 2026-07-12
**Scope:** Validate Resume evidence and decide implementation readiness

## 1. Purpose

This step reviews the private sanitized evidence accepted in
`67-resume-command-private-capture-report.md`, promotes only the minimal public
fixture and decides whether Resume may enter productive implementation.

It adds no productive PHP code, changes no module repository reference and
sends no mower command.

## 2. Inputs

The review uses:

- `03-variable-and-action-contract.md`;
- `55-command-integration-sequence-and-safety-plan.md`;
- `64-pause-integration-review-and-resume-readiness.md`;
- `65-resume-command-evidence-and-readiness-plan.md`;
- `66-resume-command-private-capture-procedure.md`;
- `67-resume-command-private-capture-report.md`;
- the private raw and sanitized Resume evidence;
- existing successful Dock and Pause command fixtures;
- canonical Paused and Running status fixtures;
- the current published Pause/Dock implementation and tests.

Raw captures, credentials and installation-specific identifiers were not
copied into the case study.

## 3. Structural and Raw-Parity Review

The two terminal private candidates were compared with their raw sources using
recursive key, array and JSON scalar-type shapes.

| Candidate | Raw/sanitized shape parity | Result |
| --- | --- | --- |
| `command-resume-success.json` | exact | passed |
| `vehicle-status-running-after-resume.json` | exact | passed |

Sanitization changed private values only. It did not change key names, nesting,
array positions, boolean types, numeric types or nullable values.

## 4. Command Fixture Decision

Promoted:

```text
fixtures/rest/command-resume-success.json
```

The fixture preserves:

- top-level success code and description;
- `data.requestId` location;
- `data.payload.commands` array;
- nested devices array;
- string command-number type;
- nested `SUCCESS` status;
- `null` error code;
- deterministic request, device and command placeholders.

The JSON payload is byte-equivalent to both:

```text
command-dock-success.json
command-pause-success.json
```

The new fixture is retained despite response-shape duplication because it is
the canonical provenance marker for a separately captured and physically
verified Resume command. It does not require a new response parser branch.

## 5. Running Fixture Decision

Not promoted:

```text
vehicle-status-running-after-resume.json
```

Its complete key, nesting and JSON-type structure equals:

```text
fixtures/rest/vehicle-status-mowing.json
```

Both contain exact source state:

```text
isRunning
```

Differences are limited to expected sample values such as battery measurement
and sanitized request identity. Those differences add no parser or state-
verification evidence.

The existing Running fixture is therefore the canonical terminal Resume state
fixture.

The two private Paused pre-state files are also not promoted because
`vehicle-status-paused.json` already represents the required state and shape.

## 6. Privacy Validation

The promoted command fixture satisfies the public redaction checklist:

- valid JSON;
- no access token, refresh token or authorization code;
- no authorization header, cookie, callback URL or client secret;
- no real request, account, device, serial or command identifier;
- no private mower name;
- no hostname, IP address or filesystem path;
- no coordinates, maps, boundaries or location history;
- deterministic SAEF placeholders only;
- original JSON scalar types retained;
- small and directly reviewable.

Private sanitized candidates remain under the ignored private output directory.

## 7. Resume Evidence Closure

| Evidence | Result | Implementation impact |
| --- | --- | --- |
| exact request | captured and statically validated | `PauseUnpause`, boolean `true` |
| Paused precondition | current twice | eligibility is evidence-backed |
| command acceptance | nested `SUCCESS` | Accepted mapping is fixture-backed |
| terminal state | current `isRunning` | Running verification is evidence-backed |
| physical result | normal mowing visibly resumed | API and physical behavior agree |
| transition timing | first observed at 2 seconds | short asynchronous verification supported |
| command attempts | one | no-retry contract passed |
| intervention | none required | no unsafe result observed |
| cleanup | official app return to station | command isolation retained |
| rejection response | missing | unknown failures must fail closed |
| already-in-state response | missing | must not be inferred as success |

The evidence is sufficient for a bounded private-pilot implementation slice.
It is not evidence for broad model support, unattended Resume or complete error
semantics.

## 8. Productive Implementation Boundary

The next implementation may add only:

- symbolic `Resume` to the account command allowlist;
- exact `PauseUnpause` request with JSON boolean `true`;
- explicit Resume action through the device module action boundary;
- a fresh successful Paused pre-read in the same invocation;
- existing `LastCommand*` diagnostics with stable Resume value `5`;
- asynchronous read-only verification for Running;
- command-specific timeout and unexpected-state handling;
- deterministic fixture, lifecycle and restart tests;
- form, localization and README changes needed to expose Resume.

It must not add:

- Stop or Start;
- automatic Resume retry;
- direct or optimistic write to `VehicleState`;
- a second command for cleanup;
- a new public Resume state variable;
- destructive variable migration or profile renumbering;
- changes to archive configuration;
- MQTT/WSS functionality;
- Store publication work;
- assumptions that public OAuth is resolved.

## 9. Action and Fresh-State Contract

Resume must enter through:

```text
NavimowDevice::RequestAction()
```

or the existing module-owned public method called by the configuration form.

Required sequence:

1. reject while another command is active;
2. reject missing device configuration;
3. perform one current read-only status request;
4. reject after failed status transport or mapping;
5. require exact current Paused state;
6. send exactly one symbolic Resume command;
7. record Requested before transport;
8. classify nested `SUCCESS` as Accepted;
9. start asynchronous read-only verification;
10. complete only after a later current Running read.

The same-invocation pre-read is stronger than an arbitrary cache-age threshold
and matches the productive Pause eligibility pattern.

Rejected eligibility must not update command diagnostics and must not call the
command endpoint.

## 10. Response Classification

| Response condition | Productive result |
| --- | --- |
| nested `SUCCESS` | Accepted, then Pending Verification |
| explicit rejection | Rejected or Failed with bounded reason |
| malformed or unknown response | Failed |
| HTTP or transport ambiguity | Failed without retry |
| authentication failure | Failed and account-owned auth state |
| `alreadyInState` | fail closed for Resume pending real evidence |

An `alreadyInState` result contradicts the fresh Paused precondition when the
expected Resume terminal state is Running. The implementation must not reuse
Dock's non-error interpretation for Resume without evidence.

A read-only current status refresh may still be performed after such an
ambiguous result for safety and diagnosis, but the command must not become
Verified solely from the unsupported response classification.

## 11. Verification Contract

Evidence schedule:

```text
2s -> 5s -> 10s -> 20s -> 30s -> 60s
```

Productive rules:

| Item | Resume rule |
| --- | --- |
| initial delay | 2 seconds |
| expected terminal state | Running |
| permitted transient state | Paused |
| maximum deadline | 60 seconds after acceptance |
| write retries | none |
| status reads | bounded by schedule |
| terminal success | later current Running |
| timeout | Verification Timeout |
| unexpected current state | Failed with bounded generic reason |

The one live run observed Running at two seconds. The full schedule protects
against cloud and device variance without claiming a guaranteed two-second
service level.

Dock retains its independent 15-minute physical return policy.

## 12. State-Machine Evolution

The current implementation already persists command kind for Pause and Dock.
Resume must become a third explicit recognized kind.

Required changes:

- add stable command value `5` as internal active kind;
- ensure restart reconstruction recognizes Resume rather than defaulting it to
  Dock;
- select Running as terminal target for Resume;
- select Paused as its only permitted transient state;
- use the short 60-second schedule;
- use a Resume-specific timeout message;
- clear command kind only at terminal completion;
- never dispatch from `ApplyChanges()` or timer callbacks.

The short timing scheduler may be renamed from Pause-specific to an explicit
shared short-transition scheduler because Pause and Resume now have equivalent
captured schedules. Eligibility, request boolean, terminal state and error
policy must remain command-specific and reviewable.

## 13. Variable and Archive Compatibility

No new public variable or profile association is required.

The implementation must preserve:

```text
VehicleState
Online
BatteryLevel
LastStatusUpdate
LastCommand
LastCommandAt
LastCommandResult
LastCommandError
```

Release-blocking invariants:

- existing ObjectIDs remain unchanged;
- variable types remain unchanged;
- effective profiles remain unchanged;
- VehicleState association numbers remain unchanged;
- Command value `5` remains Resume;
- existing registration positions remain unchanged;
- Archive Control logging and aggregation remain unchanged;
- accumulated histories remain queryable.

Before the later Symcon update, capture the anonymized identity and archive
baseline **before** the user updates the module.

## 14. Required Deterministic Tests

### Command contract

- Resume produces exact Boolean `true` envelope;
- Dock and Pause payloads remain byte-identical to their prior contracts;
- Stop and Start remain rejected;
- empty or invalid device ID remains rejected;
- Resume success fixture maps to Accepted;
- unsupported Resume `alreadyInState` fails closed.

### Eligibility and one-write safety

- fresh Paused permits one Resume write;
- Running, Docked, Docking, Error and Unknown reject without write;
- failed pre-read rejects without write;
- active command rejects without write;
- malformed response produces no retry;
- timeout produces no retry;
- restart produces no retry.

### Verification

- Running after a later read becomes Verified;
- Paused remains Pending within the deadline;
- failed reads remain bounded Pending while time remains;
- unexpected successful state fails closed;
- exact schedule aligns to 2, 5, 10, 20, 30 and 60 seconds;
- deadline becomes Verification Timeout;
- restart resumes the correct Resume schedule and target state.

### Regression

- every existing Dock harness case remains green;
- every existing Pause harness case remains green;
- OAuth, discovery and status tests remain green;
- no public variable registration changes;
- no command endpoint retry path exists.

## 15. Static and Publication Gates

Before publication:

- PHP syntax passes for all distribution files;
- all distribution JSON parses;
- official IP-Symcon Module Validator or established exact-schema fallback
  passes all ten files;
- distribution structure validator passes;
- canonical and publish-clone trees are byte-equivalent;
- private-data and whitespace scans pass;
- remote diff contains only reviewed productive files;
- no tag is created before live Symcon evidence.

## 16. Direct Symcon Test Gate

The first live productive Resume test requires a separate plan and report.

Before updating Symcon:

1. capture anonymized variable ObjectID, type, profile and archive baseline;
2. update from the exact published commit;
3. compare all baseline markers before actuation;
4. require healthy account authentication and read-only status;
5. confirm Resume is exposed and Stop/Start remain absent.

Before one Resume action:

- prepare normal mowing and Paused through the official app;
- confirm current Paused through Symcon;
- confirm visible stationary state and clear movement path;
- invoke Resume once;
- observe only read-only status afterward;
- require Running, Verified and expected physical mowing;
- perform cleanup through the official app;
- do not pair the test with a Symcon Pause command.

## 17. Risks and Residual Limits

| Risk | Required treatment |
| --- | --- |
| movement and blade initiation | explicit confirmation and continuous supervision |
| undocumented private REST API | defensive parsing and private-pilot boundary |
| one-device evidence | no broad compatibility claim |
| missing rejection fixture | fail closed |
| missing already-in-state semantics | reject for Resume |
| fast single sample | retain 60-second bounded schedule |
| shared short scheduler regression | retain all Pause tests |
| variable/archive history | pre-update baseline and post-update equality gate |
| public OAuth unresolved | broader release remains blocked |

## 18. Architecture Decisions

### AD-NAV-214: Promote command provenance, not duplicate Running state

**Decision:** Add the Resume success response fixture and reuse the existing
Running fixture.

**Rationale:** The command capture is independent evidence; the Running payload
adds no new parser structure.

**Consequence:** The public fixture set remains minimal while preserving Resume
traceability.

### AD-NAV-215: Require a current Paused read in the productive action

**Decision:** Perform status transport in the same Resume invocation before the
single write.

**Rationale:** Resume initiates movement and must not trust stale cached state.

**Consequence:** Status failure or state mismatch rejects without command.

### AD-NAV-216: Fail closed on Resume already-in-state

**Decision:** Do not inherit Dock's non-error handling for an unobserved Resume
response.

**Rationale:** Fresh Paused and an already-Running interpretation contradict
each other.

**Consequence:** Real evidence is required before relaxing this policy.

### AD-NAV-217: Share only the captured short verification schedule

**Decision:** Pause and Resume may share scheduling mechanics while preserving
explicit command policies.

**Rationale:** Both transitions were observed at two seconds and use the same
bounded evidence schedule.

**Consequence:** Shared timing code may reduce duplication without turning the
command contract into an opaque dynamic table.

### AD-NAV-218: Treat pre-update archive baseline as mandatory

**Decision:** Capture anonymized baseline before the next Symcon update.

**Rationale:** Step 63 lacked a retained numeric pre-update ObjectID snapshot.

**Consequence:** Resume publication cannot proceed directly to update without
the compatibility baseline.

### AD-NAV-219: Keep Stop and Start disabled

**Decision:** Resume implementation does not expand any other command.

**Rationale:** Their evidence and physical semantics remain independent.

**Consequence:** The account allowlist after Resume contains Dock, Pause and
Resume only.

## 19. Decision

**Fixture validation: PASS.**

**Conditional GO for bounded productive Resume implementation.**

The GO is limited to the implementation and deterministic validation described
in this document.

**Publication, direct Symcon Resume and pilot tagging remain NO-GO in this
step.**

Stop and Start remain disabled.

## 20. Recommended Next Step

Create SAEF step `69-resume-command-implementation.md` to implement the bounded
Resume slice without publishing it. The step must preserve all existing Dock,
Pause, variable and archive contracts and pass the complete deterministic
regression suite before any publication plan is opened.
