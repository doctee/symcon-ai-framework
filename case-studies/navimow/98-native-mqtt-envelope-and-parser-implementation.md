# 98 Native MQTT Envelope and Parser Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline MQTT parser increment complete; runtime integration and
publication remain blocked
**Date:** 2026-07-28
**Scope:** Execute `WP-1` through `WP-3` from the approved MQTT shadow plan

## 1. Purpose

This step implements the first offline increment after the distribution/main
drift closure in step 97.

It adds:

- synthetic native Symcon MQTT envelope fixtures;
- a strict native envelope parser;
- the promoted and hardened semantic payload parser;
- an array-based partial-state reducer;
- focused offline regression tests and a case-study-local runner.

It does not add:

- an MQTT Receiver module;
- an Account ingestion method;
- a WebSocket or MQTT Client topology;
- credential retrieval;
- a timer, variable, action or public diagnostic;
- MQTT publish;
- a mower command;
- standalone publication or a Symcon update.

## 2. Authority Boundary

REST remains authoritative for:

- vehicle state;
- battery level;
- online state;
- public status timestamps;
- every existing Symcon variable.

The new MQTT code produces only internal semantic candidates and
reconciliation hints. No current module file loads or invokes it.

## 3. Implemented Artifacts

### Native envelope fixtures

```text
fixtures/mqtt/symcon-envelope-location.json
fixtures/mqtt/symcon-envelope-state.json
fixtures/mqtt/symcon-envelope-retained.json
fixtures/mqtt/symcon-envelope-invalid-data-id.json
```

Every fixture:

- uses `DEVICE_001`;
- is explicitly marked `synthetic: true`;
- references step 94 as its shape evidence;
- contains a JSON string in `Payload`;
- records its intended channel and retained classification;
- states that the synthetic packet-type value is not an observation claim.

No captured topic, device identity, timestamp, coordinate or credential was
promoted.

### Native envelope parser

```text
distribution/libs/Navimow/MqttEnvelopeException.php
distribution/libs/Navimow/MqttEnvelopeParser.php
```

The parser enforces:

| Boundary | Contract |
| --- | --- |
| outer size | at most 65,536 bytes |
| JSON depth | at most 32 |
| root | non-empty JSON object |
| keys | exact six-key contract; unknown and missing keys fail |
| `DataID` | exact native MQTT receive interface |
| `PacketType` | integer from 0 through 15 |
| `QualityOfService` | exact integer `0` |
| `Retain` | boolean |
| topic | non-empty string, at most 512 bytes |
| payload | string, at most 32,768 bytes |

Its normalized result contains only:

```text
topic
payload
qualityOfService
retained
packetType
```

It deliberately performs no topic allowlist check and no payload decode.

### Semantic payload parser

```text
distribution/libs/Navimow/MqttPayloadException.php
distribution/libs/Navimow/MqttPayloadParser.php
```

The former analysis candidate was not copied mechanically. Promotion changed
the contract as required by steps 95 and 96:

- payload maximum reduced from 1 MiB to 32,768 bytes;
- local receipt time is required and remains distinct from source time;
- exact per-device topics remain mandatory;
- direct `state` and partial `location` remain the only supported channels;
- `event` and `attributes` fail closed;
- state payload device identity must equal the topic identity;
- known direct states use the existing REST state map;
- unknown state strings become only `unknown-state`;
- unknown state strings never enter returned state, diagnostics or errors;
- timestamp-less location messages are classified without mutation;
- geometry is validated but reduced before parser output.

### Shared state normalization

`PayloadMapper::mapVehicleStateName()` now owns direct state-name
normalization. The existing REST `mapStatus()` path delegates to the same
method.

This removes a potential second state table while preserving all established
REST mappings.

### Array-based reducer

```text
distribution/libs/Navimow/MqttPartialStateAccumulator.php
```

Despite retaining the historical class name, the promoted component is no
longer a long-lived mutable object. It exposes pure array operations:

```text
initialState()
reduce(previous, patch, receivedAt)
serializeState(state)
restoreAfterRestart(serialized)
```

The reducer returns:

```text
accepted
reason
state
changedSemanticFields
reconciliationHint
diagnosticDeltas
```

It supports only a fixed field allowlist, rejects older source timestamps
atomically, preserves absent fields and keeps serialization below 4,096 bytes.

`restoreAfterRestart()` validates the serialized shape and then returns an
empty initial state. Old MQTT semantic candidates therefore never regain
authority after a restart.

## 4. Geometry Boundary

The payload parser recognizes the three evidenced pose fields only to validate
their numeric shape.

It returns:

```text
geometryPresent: boolean
```

It does not return:

- a geometry field name;
- a coordinate;
- an angle;
- geometry in unknown or null-field diagnostics;
- geometry in the reducer state;
- geometry in serialized state.

Regression input uses unique synthetic coordinate values and proves that
neither the field names nor values survive parser output.

## 5. State Semantics

The fixture-backed direct mappings are:

| MQTT state | Internal state |
| --- | --- |
| `isRunning` | `PayloadMapper::VEHICLE_STATE_RUNNING` |
| `isDocking` | `PayloadMapper::VEHICLE_STATE_DOCKING` |
| `isDocked` | `PayloadMapper::VEHICLE_STATE_DOCKED` |

An unknown syntactically valid state:

- does not reject the whole message;
- does not overwrite a semantic state;
- increments the bounded `unknownState` diagnostic delta;
- returns reason `unknown-state`;
- retains the REST reconciliation hint.

Observed numeric location state codes remain numeric candidates. They are not
mapped to public `NAVIMOW.VehicleState` values.

## 6. Retained Messages

The envelope parser classifies `Retain` but does not decide semantic
acceptance. This keeps the transport parser independent.

The future Receiver from `WP-4` must reject retained semantic input before
Account handoff. The retained fixture proves classification only; it does not
claim that the Navimow broker sends retained state.

## 7. Focused Verification

The new runner is:

```text
tools/check-mqtt-shadow.sh
```

It executes:

```text
tests/mqtt-fixtures.php
tests/mqtt-envelope.php
tests/mqtt-shadow-payload.php
tools/validate-distribution.php
PHPCS for the changed distribution libraries
```

Result:

```text
Navimow MQTT shadow offline checks passed.
```

The tests cover:

- positive location, state and retained envelopes;
- invalid `DataID`;
- missing and unknown envelope keys;
- invalid QoS, retain flag and packet type;
- outer and payload size limits;
- invalid UTF-8;
- known direct-state normalization;
- unknown-state reduction without raw-state retention;
- geometry removal;
- partial-field preservation;
- out-of-order rejection;
- timestamp-less rejection without mutation;
- bounded serialization;
- semantic-state clearing after restart;
- cross-device and unsupported-channel rejection.

## 8. Regression Verification

The following existing checks also passed:

```text
composer test:navimow-rest-auth
composer test:navimow-pilot
php tests/Navimow/payload-mapper-fixtures.php
php case-studies/navimow/tests/mqtt-parser.php
composer phpstan
make check
```

The historical candidate parser test remains green. It documents the earlier
analysis contract, while the promoted distribution classes are authoritative
for future runtime work.

## 9. Runtime and Compatibility Result

No existing module file was changed except the internal state-normalization
delegation in `PayloadMapper`.

Unchanged:

- `library.json`;
- all three `module.json` files;
- all forms and locales;
- Account, Configurator and Device module loaders;
- properties, attributes, timers and public methods;
- profiles, variable Idents and actions;
- Archive Control configuration;
- command behavior;
- REST polling behavior.

No direct Symcon test is required at this gate because no installed runtime
path can reach the new classes.

## 10. Distribution and Publication State

Step 97 established byte equality between the case-study distribution and
standalone `main` at commit `2c32b86`.

Step 98 intentionally opens a new reviewed delta consisting of:

- five new MQTT library classes;
- one compatible `PayloadMapper` extension;
- offline fixtures, tests and tooling.

This delta is not copied to the standalone repository and is not published.
The standalone module installed in Symcon therefore remains unchanged at
`2c32b86`.

## 11. Architecture Decisions

### AD-NAV-379: Fail closed on unknown envelope keys

**Decision:** Require the exact proven six-key envelope.

**Rationale:** There is no compatibility evidence for additional fields.

**Consequence:** Any future native envelope expansion requires a fixture-backed
decision.

### AD-NAV-380: Keep transport and semantic parsing separate

**Decision:** The envelope parser does not inspect topic semantics or decode
`Payload`.

**Rationale:** Each JSON boundary needs independent size, type and trust
checks.

**Consequence:** Receiver and Account can enforce their own responsibilities
without parser ambiguity.

### AD-NAV-381: Reuse the REST state map

**Decision:** Add one narrow state-name method to `PayloadMapper`.

**Rationale:** A second MQTT state table would create avoidable drift.

**Consequence:** Direct MQTT candidates and REST status share identical known
state constants.

### AD-NAV-382: Reduce geometry before returning a patch

**Decision:** Validate geometry but return only a boolean presence marker.

**Rationale:** Coordinates are unnecessary for state reconciliation and carry
privacy risk.

**Consequence:** No later reducer or diagnostic layer can accidentally persist
them.

### AD-NAV-383: Use an array reducer and clear on restart

**Decision:** Replace mutable process state with bounded pure array reduction
and deliberate empty restoration.

**Rationale:** Symcon lifecycle behavior must be serializable and testable, but
stale MQTT candidates must not regain authority.

**Consequence:** A restart requires fresh MQTT evidence or REST reconciliation.

### AD-NAV-384: Keep the promoted libraries dormant

**Decision:** Add no loader or runtime call in this increment.

**Rationale:** Parser correctness should close before Receiver ownership and
pairing are introduced.

**Consequence:** Existing Symcon behavior and object identity remain unchanged.

### AD-NAV-385: Treat synthetic packet type as non-evidence

**Decision:** Validate a bounded integer range rather than claim one observed
packet-type value.

**Rationale:** Step 94 proved the field type but intentionally retained no
private value.

**Consequence:** A stricter allowlist requires new sanitized evidence.

### AD-NAV-386: Do not publish the offline increment

**Decision:** Retain the new delta only in the case-study distribution.

**Rationale:** The approved plan requires a disabled Receiver integration and
compatibility gate before feature-branch publication.

**Consequence:** Standalone `main`, Symcon and pilot tags remain unchanged.

## 12. Decision

**`WP-1` synthetic envelope fixtures: COMPLETE.**

**`WP-2` native envelope parser: COMPLETE.**

**`WP-3` payload parser and reducer promotion: COMPLETE.**

**Offline focused gate: PASS.**

**Full repository gate: PASS.**

**REST authority: RETAINED.**

**Geometry persistence: PROHIBITED AND TESTED.**

**MQTT runtime integration: NOT STARTED.**

**Standalone publication: NOT AUTHORIZED.**

**Symcon mutation: NONE.**

## 13. Recommended Next Step

Create:

```text
99-native-mqtt-receiver-scaffold.md
```

That step should execute `WP-4` only:

1. generate the Receiver GUID with an official Symcon tool;
2. add the disabled, stateless Receiver module;
3. connect its parser boundary without Account ingestion;
4. reject retained and malformed input safely;
5. prove the absence of variables, actions, timers, publish and command paths;
6. update distribution validation and metadata tests;
7. keep publication and live Symcon testing blocked.
