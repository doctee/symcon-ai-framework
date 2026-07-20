# 59 Pause Command Fixture Validation and Implementation Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Conditional GO for bounded productive Pause implementation
**Date:** 2026-07-12
**Scope:** Validate Pause fixtures and decide implementation readiness

## 1. Purpose

This step reviews the private evidence accepted in
`58-pause-command-private-capture-report.md`, promotes only the minimal public
fixtures and decides whether Pause may enter productive implementation.

It adds no productive PHP code and sends no mower command.

## 2. Inputs

The review uses:

- `03-variable-and-action-contract.md`;
- `21-command-implementation-readiness.md`;
- `55-command-integration-sequence-and-safety-plan.md`;
- `56-pause-command-evidence-and-readiness-plan.md`;
- `57-pause-command-private-capture-procedure.md`;
- `58-pause-command-private-capture-report.md`;
- the existing Dock command and Running status fixtures;
- the two private sanitized Pause candidates.

Raw captures, credentials and installation-specific identifiers were not
copied into the case study.

## 3. Promoted Fixtures

The following candidates passed review and are now canonical fixtures:

```text
fixtures/rest/command-pause-success.json
fixtures/rest/vehicle-status-paused.json
```

The two Running pre-state candidates were not promoted. The existing
`vehicle-status-mowing.json` already preserves the required `isRunning`
structure, so additional copies would add mower-specific measurements without
new parser evidence.

The neutral command-response candidate was also not promoted because it is
structurally identical to the accepted candidate.

## 4. Structural Validation

### Command fixture

| Contract item | Result |
| --- | --- |
| valid JSON | passed |
| top-level code and description types | preserved |
| `data.requestId` location | preserved |
| `data.payload.commands` array | preserved |
| nested devices array | preserved |
| `cmdNum` string type | preserved |
| nested `SUCCESS` status | preserved |
| nullable `errorCode` | preserved as `null` |
| deterministic placeholders | passed |

The response shape is equal to the existing successful Dock command fixture.
The shared command-response parser can therefore classify Pause acceptance
without a command-specific response format.

### Status fixture

| Contract item | Result |
| --- | --- |
| valid JSON | passed |
| normal device status envelope | preserved |
| device placeholder | `DEVICE_001` |
| battery array and scalar types | preserved |
| exact source state | `isPaused` |
| descriptive battery value | preserved |
| request ID location | preserved |

The existing payload mapper already reserves Paused in the stable vehicle-state
profile. No public variable type or association renumbering is required.

## 5. Privacy Validation

Both promoted fixtures satisfy the fixture redaction checklist:

- no access token, refresh token or authorization code;
- no real account, request, device, serial or command identifier;
- no private mower name;
- no hostname, IP address or local path;
- no map, coordinate, boundary or location history;
- deterministic SAEF placeholders only;
- original key names, nesting and JSON value types retained;
- small, reviewable payloads.

The promoted files contain response evidence only. The command request with
boolean `false` remains documented as a contract rather than duplicated as a
synthetic response fixture.

## 6. Evidence Closure

The previously open Pause evidence gaps are now classified as follows:

| Evidence | Result | Consequence |
| --- | --- | --- |
| exact request contract | statically validated | `PauseUnpause`, `on == false` |
| stable Running precondition | live observed twice | supervised capture gate passed |
| accepted response | fixture-backed | nested `SUCCESS` maps to Accepted |
| terminal Paused state | fixture-backed | `isPaused` maps to Paused |
| transition timing | observed at first 2-second read | supports short asynchronous verification |
| command retry safety | passed | exactly one write, reads only afterward |
| Pause rejection response | missing | unknown failures must fail closed |
| already-paused response | missing | must not be assumed from Dock evidence |
| Resume behavior | untested | remains excluded |

Missing rejection and already-in-state fixtures do not block a private pilot
implementation when the existing defensive parser fails closed. They do block
claims of complete Pause error semantics.

## 7. Productive Implementation Boundary

The next implementation may add only:

- symbolic command `Pause` to the existing account command allowlist;
- exact request mapping to `PauseUnpause` with JSON boolean `false`;
- an explicit Pause action through the module's `RequestAction()` boundary;
- command eligibility based on a fresh successful Running status;
- existing `LastCommand*` diagnostics;
- asynchronous read-only verification for terminal Paused;
- fixture and deterministic harness coverage;
- form and localization changes required to expose Pause.

It must not add:

- Resume, Stop or Start;
- automatic Pause retry;
- direct writes to `VehicleState`;
- inference that boolean `true` is supported Resume behavior;
- a destructive migration or recreation of existing variables;
- new archive-enabled command variables;
- MQTT/WSS behavior;
- public OAuth assumptions or Store publication work.

## 8. Action and Eligibility Contract

Pause is a command action, not a state variable write.

Required behavior:

1. enter through `NavimowDevice::RequestAction()` or the existing equivalent
   module-owned command method;
2. reject the action when another command attempt is active;
3. require a configured device and usable parent connection;
4. require a recent successful status read whose state is Running;
5. record Requested before transport;
6. classify nested `SUCCESS` as Accepted, not Verified;
7. start read-only verification after acceptance;
8. classify a later current Paused read as Verified;
9. fail closed on malformed, unknown or rejected responses;
10. require a new explicit user action after any ambiguous outcome.

The implementation must define the maximum acceptable age of the Running
precondition as a named constant or documented property. Cached Running without
a bounded freshness check is insufficient.

## 9. Verification Contract

The live evidence observed Paused at the first two-second check. One run does
not establish a guaranteed cloud deadline.

The implementation should therefore use command-specific verification rules:

| Item | Pause rule |
| --- | --- |
| initial read delay | 2 seconds |
| expected terminal state | Paused |
| permitted transient state | Running |
| suggested bounded schedule | 2, 5, 10, 20, 30 and 60 seconds |
| maximum deadline | 60 seconds after accepted command |
| write retries | none |
| read retries | bounded by schedule |
| timeout result | Verification Timeout |

The schedule mirrors the successful capture procedure and remains deliberately
separate from Dock's long return-to-station deadline.

An unexpected state such as Docking or Docked must not verify Pause. It should
terminate or remain unresolved according to the existing defensive command
state machine, with a bounded diagnostic message.

## 10. Variable and Archive Stability Gate

The implementation must preserve the existing IP-Symcon objects behind:

- `VehicleState`;
- `Online`;
- `BatteryLevel`;
- `LastStatusUpdate`;
- all `LastCommand*` variables.

Their Idents, types and profile association values must remain unchanged.
Registration must remain idempotent and must not alter user-configured Archive
Control logging. This is a release-blocking regression gate because existing
battery and status histories are installation state.

No new state variable is required for Pause. The existing VehicleState profile
already includes Paused, and existing command diagnostics represent the action
lifecycle.

## 11. Required Tests Before Publication

### Fixture and unit tests

- exact Pause envelope uses boolean `false`;
- allowlist accepts Pause and still rejects Resume, Stop and Start;
- command fixture maps nested `SUCCESS` to Accepted;
- Paused fixture maps to the existing stable association;
- command acceptance does not change `VehicleState` directly;
- stale or non-Running precondition rejects Pause without transport;
- unknown response status fails closed;
- no transport path retries Pause;
- Paused after a later read becomes Verified;
- Running may remain pending within the deadline;
- unexpected state does not become Verified;
- deadline expiry becomes Verification Timeout;
- existing Dock tests remain green.

### Static distribution tests

- PHP syntax and repository validation;
- official IP-Symcon Module Validator;
- metadata, form and localization consistency;
- no credential or private identifier in changed files;
- no existing variable Ident, type or association change.

### Direct IP-Symcon gate

Publication to the private module repository may occur only after local tests
pass. The first live Symcon test must be separately documented and supervised:

1. refresh status and confirm Running;
2. invoke Pause exactly once through the module action;
3. verify Accepted followed by Verified;
4. verify Paused comes from status polling;
5. verify all existing variable ObjectIDs and archive settings remain intact;
6. perform recovery through the official app or physical control as needed;
7. do not test Resume in the same step.

## 12. Architecture Decisions

### AD-NAV-170: Promote only two non-duplicative Pause fixtures

**Decision:** Publish the accepted response and terminal Paused status only.

**Rationale:** Existing fixtures already cover Running and the generic success
envelope. The two selected files add the exact missing evidence with minimal
private-data surface.

### AD-NAV-171: Reuse command transport, specialize command policy

**Decision:** Pause reuses the existing account transport and response parser,
while eligibility, payload and verification remain explicit Pause rules.

**Rationale:** The response envelope is shared, but physical semantics and
deadlines differ from Dock.

### AD-NAV-172: Require fresh Running before productive Pause

**Decision:** A bounded, successful status observation of Running is a runtime
precondition for sending Pause.

**Rationale:** A stale cached state is insufficient evidence for a physical
command whose intended effect depends on current movement.

### AD-NAV-173: Verify Pause asynchronously for at most 60 seconds

**Decision:** Repeat only status reads on the capture-backed schedule and never
repeat the command.

**Rationale:** Paused was observed quickly, but cloud and device timing can
vary. The bounded schedule distinguishes acceptance from state confirmation.

### AD-NAV-174: Archive identity is a blocking compatibility contract

**Decision:** Pause implementation must not recreate, rename or retype existing
variables or alter archive configuration.

**Rationale:** User-enabled logging and accumulated history depend on stable
IP-Symcon object identity.

## 13. Decision

**Conditional GO for bounded productive Pause implementation.**

The fixture, state, timing and safety evidence are sufficient for a private
implementation slice. The GO is conditional on all tests in section 11 and on
strict adherence to the boundary in section 7.

Resume, Stop and Start remain **No-Go**. Broader release and Store preparation
remain blocked by the command-completeness and public OAuth decisions already
recorded in this case study.

## 14. Recommended Next Step

Create SAEF step `60-pause-command-implementation.md` to implement and test the
bounded Pause slice without publishing it. After local validation, use a
separate publication and supervised Symcon test step.
