# 04 Implementation Plan

**Case study:** Navimow native IP-Symcon module  
**Status:** Implementation planning draft  
**Date:** 2026-07-08  
**Build boundary:** This document plans implementation work only. No productive PHP code is introduced.

## 1. Purpose

This document defines the implementation path for the future native
IP-Symcon Navimow module. It translates the requirements, module design and
contract documents into ordered work packages, verification gates and
decision points.

The goal is to prevent the first PHP implementation from starting before the
module boundary, public contract, privacy rules and test evidence are clear.

## 2. Input Documents

This plan depends on:

- `01-requirements.md` for API and source analysis;
- `02-module-design.md` for module structure, state machines and diagnostics;
- `03-variable-and-action-contract.md` for public variables, profiles,
  actions and archive defaults.

If these documents change, this implementation plan must be reviewed before
module code is created.

## 3. Implementation Strategy

The recommended strategy is REST-first and evidence-driven:

1. Validate API behavior with sanitized fixtures.
2. Create the smallest module skeleton that can express the documented
   instance responsibilities.
3. Implement account authentication and REST discovery before device actions.
4. Implement read-only device state before remote commands.
5. Implement command actions only after state mapping is verified.
6. Defer MQTT/WSS until the REST MVP is stable.

### SAEF-Entscheidung AD-NAV-013: Evidence before production module code

**Entscheidung:** Productive PHP module files should be created only after
sanitized fixtures and the MVP variable/action contract exist.

**Rationale:** The API is inferred from a community implementation and may
vary by model, region or firmware. Starting with code before fixture evidence
would increase the risk of unstable public variables and later migrations.

**Consequence:** The next practical work is fixture collection and contract
validation, not immediate module implementation.

## 4. Work Packages

### WP-01: Fixture Collection

**Goal:** Build a sanitized evidence base for the REST MVP.

Tasks:

- capture `authList` success response;
- capture `getVehicleStatus` while docked;
- capture `getVehicleStatus` while mowing if possible;
- capture one successful command response;
- capture one `alreadyInState` command response;
- capture expired-token or auth failure response;
- capture representative transport or cloud failure metadata if practical.

Deliverables:

- sanitized fixture set in a future location chosen before collection;
- fixture notes describing device model and firmware without private data;
- field inventory for status, battery and online state.

Gate:

- no tokens, authorization codes, private device IDs, hostnames or garden
  details remain in fixtures.

### WP-02: Contract Validation

**Goal:** Check the MVP contract against real payloads before implementation.

Tasks:

- verify exact field names for `VehicleState`;
- verify exact battery field name and range;
- verify online/offline representation;
- verify command response shape;
- verify `alreadyInState` representation;
- decide whether `BatteryLevel == 0` is acceptable as initial unknown value;
- decide whether `LastCommandError` remains public or moves to internal
  diagnostics.

Deliverables:

- update `03-variable-and-action-contract.md` if field names or variable
  choices change;
- record unresolved API differences as open questions.

Gate:

- required MVP variables can be mapped from at least one sanitized real-device
  status payload.

### WP-03: Module Structure Decision

**Goal:** Finalize the first module family shape before PHP files exist.

Tasks:

- decide whether `NavimowAccount` acts as a Symcon Splitter instance;
- decide whether `NavimowConfigurator` is included in MVP or deferred;
- define parent-child message contract between account and device instances;
- define where protected token state will live;
- define how timers represent polling, token refresh and command verification.

Deliverables:

- short design update in `02-module-design.md` if the structure changes;
- list of planned module folders and instance types.

Gate:

- account/device responsibilities remain separate, or a documented ADR-style
  case-study decision explains why a temporary single-instance MVP is used.

### WP-04: Test Harness Plan

**Goal:** Define how the module can be verified without leaking private data.

Tasks:

- decide how sanitized REST fixtures are loaded by tests;
- define payload parser tests for known and unknown states;
- define command mapping tests for all MVP actions;
- define redaction tests for raw diagnostics;
- define archive default checks;
- define manual integration test checklist for one real mower.

Deliverables:

- future test plan or test checklist before module code is written;
- fixture redaction checklist.

Gate:

- at least parser and command-mapping behavior can be tested without a live
  mower.

### WP-05: Account MVP Implementation

**Goal:** Implement account-level authentication, token refresh, discovery and
diagnostics.

Planned scope:

- configuration fields from `02-module-design.md`;
- OAuth2 authorization code exchange;
- protected token storage;
- token refresh scheduling;
- REST request wrapper with request ID;
- `authList` device discovery;
- account variables from `03-variable-and-action-contract.md`;
- sanitized error handling.

Out of scope:

- MQTT/WSS;
- device command actions;
- map rendering.

Gate:

- login, refresh after restart and discovery work without token leakage.

### WP-06: Device Read-Only MVP Implementation

**Goal:** Implement one mower instance with stable read-only status variables.

Planned scope:

- configured `DeviceId`;
- variables and profiles from `03-variable-and-action-contract.md`;
- status polling through account instance;
- payload validation and mapping;
- unknown-state handling;
- optional bounded `RawStatusJson`;
- archive defaults.

Gate:

- docked and active fixture payloads map correctly without creating dynamic
  variables.

### WP-07: Command MVP Implementation

**Goal:** Add user-controllable mower actions through Symcon action semantics.

Planned scope:

- `Refresh`;
- `Start`;
- `Stop`;
- `Pause`;
- `Resume`;
- `Dock`;
- command diagnostics;
- delayed verification refresh;
- `alreadyInState` mapping;
- no automatic retry for remote commands.

Gate:

- every action updates command diagnostics and never directly overwrites domain
  state.

### WP-08: MVP Hardening

**Goal:** Prepare the REST MVP for real operation and review.

Tasks:

- run all fixture-based tests;
- run manual integration checklist;
- verify no private data in logs or diagnostics;
- verify no public variable writes bypass action semantics;
- verify polling cannot overlap itself;
- verify token refresh failure enters `ReauthRequired`;
- review archive defaults;
- update case-study lessons learned.

Gate:

- the module satisfies the verification contract from
  `03-variable-and-action-contract.md`.

### WP-09: MQTT/WSS Technical Spike

**Goal:** Decide whether phase 2 can use Symcon-native MQTT infrastructure or
needs an internal MQTT-over-WebSocket client.

Tasks:

- verify WSS connection with required authorization header;
- verify MQTT credentials from `/openapi/mqtt/userInfo/get/v2`;
- subscribe to `state`, `event`, `attributes` and `location` topics;
- capture sanitized topic payload fixtures;
- evaluate reconnect and staleness behavior;
- update phase 2 contract.

Gate:

- MQTT design is backed by working connection evidence and sanitized payloads.

## 5. Recommended Order

| Order | Work package | Can start when |
| --- | --- | --- |
| 1 | WP-01 Fixture Collection | Current documents exist. |
| 2 | WP-02 Contract Validation | Initial fixtures are available. |
| 3 | WP-03 Module Structure Decision | Contract validation confirms MVP scope. |
| 4 | WP-04 Test Harness Plan | Structure decision is made. |
| 5 | WP-05 Account MVP Implementation | Test plan and protected-token decision exist. |
| 6 | WP-06 Device Read-Only MVP Implementation | Account discovery works. |
| 7 | WP-07 Command MVP Implementation | Device state mapping works. |
| 8 | WP-08 MVP Hardening | Account, device and commands work. |
| 9 | WP-09 MQTT/WSS Technical Spike | REST MVP is stable or spike is explicitly isolated. |

## 6. Definition of Ready for PHP Module Files

Creating real module PHP files is justified when:

- `03-variable-and-action-contract.md` is accepted for MVP;
- sanitized fixtures exist for discovery and at least one status payload;
- token storage approach is chosen;
- module structure decision is recorded;
- test harness approach is defined;
- private data handling rules are explicit;
- the implementation starts with the REST MVP, not MQTT or map rendering.

If these conditions are not met, more case-study analysis or fixture work is
needed before production code begins.

## 7. Definition of Done for REST MVP

The REST MVP is done when:

- account login and token refresh work;
- discovery returns devices without leaking private identifiers in logs;
- one device instance maps status into the contract variables;
- unknown fields are ignored or diagnostically recorded;
- command actions use Symcon action semantics;
- commands update command diagnostics;
- command success is verified by later state where practical;
- no command directly writes domain state;
- archive defaults match the contract;
- fixtures and manual tests cover happy path and at least one failure path;
- README or module documentation explains setup and privacy boundaries.

## 8. Manual Verification Checklist

Before calling the MVP operational:

1. Install module in a non-critical Symcon environment.
2. Configure account region and authorization code.
3. Confirm the authorization code is no longer visible after exchange.
4. Confirm no token appears in logs or variables.
5. Run discovery.
6. Create or link one device instance.
7. Run manual refresh while mower is docked.
8. Confirm `VehicleState`, `Online`, `BatteryLevel` and `LastStatusUpdate`.
9. Send `Refresh` repeatedly and confirm no overlapping polling behavior.
10. Send one safe command whose physical effect is expected and supervised.
11. Confirm command diagnostics before and after delayed verification.
12. Force an auth or network error if practical and confirm diagnostics.
13. Review archive settings before leaving the module active.

Commands that move the mower should only be tested when the physical area is
safe and the device can be supervised.

## 9. Privacy and Fixture Handling

Fixture handling rules:

- store only sanitized payloads;
- replace device IDs with deterministic placeholders;
- remove tokens, authorization codes, MQTT passwords and account identifiers;
- remove exact garden coordinates or map data;
- avoid private hostnames and local system paths;
- keep raw unsanitized captures outside the public repository.

Any future fixture directory should include a README that describes its
sanitization policy.

## 10. Risk Register for Implementation

| Risk | Mitigation in plan |
| --- | --- |
| API schema differs by model | Fixture collection before contract freeze. |
| Token leakage | Protected storage decision and redaction tests. |
| Incorrect command state | Delayed verification and no direct domain writes. |
| Dynamic variable sprawl | Curated contract and parser tests. |
| Archive growth | Conservative archive defaults. |
| MQTT complexity delays MVP | REST-first work packages and MQTT phase 2 spike. |
| Physical mower side effects | Manual supervised command checklist. |

## 11. Case-Study Updates During Implementation

During later implementation work, this case study should record:

- API fields that differed from the initial analysis;
- command responses that require contract changes;
- Symcon lifecycle decisions that affect module architecture;
- diagnostics decisions that might become SAEF knowledge;
- helper needs that repeat across more than one module or reference;
- risks found during real-device verification.

Reusable helpers or public SAEF patterns should not be created from this case
study until repeated use has been demonstrated.

## 12. Next SAEF Step

The next step is not productive module code yet unless the Definition of Ready
in section 6 is satisfied.

Recommended next artifact:

```text
case-studies/navimow/05-fixture-plan.md
```

That document should define fixture locations, sanitization rules, required
payload examples and review checks before any real API captures are committed.
