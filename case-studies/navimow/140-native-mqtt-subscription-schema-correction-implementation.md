# 140 Native MQTT Subscription Schema Correction Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Canonical `QoS` correction and legacy migration complete offline;
publication closed
**Date:** 2026-07-28
**Scope:** Correct the retained native MQTT subscription schema, preserve
received-envelope semantics and harden future live evidence output

## 1. Purpose

Step 139 proved that:

- the native MQTT Client form expects subscription keys `Topic` and `QoS`;
- the successful step-94 disposable transport used that schema;
- the retained Navimow transport stores `Topic` and `QualityOfService`;
- the mismatch is the strongest root-cause candidate before child delivery.

This step:

1. emits only the canonical native subscription schema;
2. accepts the exact legacy shape only for migration;
3. rejects ambiguous or malformed subscription entries;
4. proves the next normal Connect rewrites the retained property canonically;
5. leaves the received MQTT envelope schema unchanged;
6. replaces unbounded live observations with a compact V3 aggregate;
7. runs the complete Navimow MQTT regression suite;
8. stops before publication or Symcon mutation.

## 2. Physical Context Correction

After step 138, the user clarified that the mower mowed only briefly and then
returned to the station.

The public step-138 report and private machine-readable evidence now record:

- physical supervision remained valid;
- continuous mowing throughout 163 seconds is no longer claimed;
- measured Core statuses and zero child counters remain factual;
- the direct native subscription-schema mismatch is independent of mower
  activity.

No test result was silently discarded or overstated.

## 3. Productive Source Change

Changed:

```text
case-studies/navimow/distribution/libs/Navimow/
  MqttTransportConfiguration.php
```

### Canonical Generation

New subscriptions are generated as:

```json
{
  "Topic": "<exact private topic>",
  "QoS": 0
}
```

The library no longer generates `QualityOfService` for Core subscription
configuration.

### Canonical Normalization

`configuredSubscriptions()` now returns only:

```text
Topic
QoS
```

regardless of whether its accepted input was canonical or the exact bounded
legacy shape.

## 4. Legacy Migration

The installed retained Core property still has the old shape. To preserve the
adopted topology, the parser accepts exactly:

```json
{
  "Topic": "<exact private topic>",
  "QualityOfService": 0
}
```

only as migration input.

It normalizes this immediately to:

```json
{
  "Topic": "<exact private topic>",
  "QoS": 0
}
```

This keeps ownership validation operational before the first corrected
connection. The normal `ConnectMqttShadow()` path then writes the canonical
subscription list and applies the MQTT Client before WebSocket activation.

No Core instance deletion, recreation or reparenting is needed.

## 5. Strict Rejection Rules

The transport configuration rejects:

- entries with both `QoS` and `QualityOfService`;
- entries with neither field;
- any additional field;
- non-integer QoS;
- nonzero QoS;
- missing or unsafe topic;
- wildcard topic;
- duplicate topic;
- malformed or non-list subscription collections.

Legacy compatibility is one-way normalization, not a second canonical schema.

## 6. Envelope Boundary Preserved

Unchanged:

```text
MqttEnvelopeParser
```

The proven native receive envelope continues to require:

```text
QualityOfService
```

This is correct because the native child envelope and the MQTT Client
configuration list are separate contracts.

Envelope fixtures and parser tests were intentionally not renamed.

## 7. Test Changes

Changed:

```text
case-studies/navimow/tests/
  mqtt-transport-lifecycle.php
```

Added regressions prove:

- canonical output uses exactly `Topic` and `QoS`;
- canonical configuration round-trips;
- exact legacy configuration normalizes to canonical;
- mixed schema is rejected;
- unknown fields are rejected;
- nonzero QoS is rejected;
- string QoS is rejected;
- missing QoS is rejected;
- an adopted legacy topology remains valid;
- normal Connect rewrites all four subscriptions canonically before
  activation;
- disconnect and rollback continue to work after migration.

## 8. Compact V3 Evidence Harness

Historical V1 and V2 sources remain unchanged.

Added privately:

```text
private/navimow-capture/mqtt-sibling-cross-probe/
  live-one-shot-v3.php
  compact-output-test.php
  validate-v3.sh
```

V3 retains:

- one Connect;
- one Disconnect;
- one Arm, Close and Delete;
- no retry;
- no publish;
- no mower command;
- 165-second observation cutoff;
- 180-second hard deadline;
- deterministic cleanup.

Instead of retaining every poll result, V3 returns only:

- sample count;
- first, first-healthy and final relative time;
- initial and final Core statuses;
- WebSocket-active continuity;
- maximum Receiver and probe deltas;
- maximum accepted probe messages;
- final classification.

This removes the step-138 MCP truncation mechanism.

## 9. Source Hashes

```text
MqttTransportConfiguration.php:
f9d6b6b826849c5c1cb125a01167c3f7931bfff12355447e1d43eb7dfe7a022b

mqtt-transport-lifecycle.php:
0278ce7e0482daf3618a5bf449519034040b4ddb6ebcee5e3a1c1de1f1ec1dd6

live-one-shot-v3.php:
09d0c7cc737e4e3579da558b0f18cebada8e76cf06170d315a518cdbb8095489

compact-output-test.php:
f11133663b1e658676fee9e7047a07c89bf002412110e67c7b2ab63ca284d8a1

validate-v3.sh:
fa06707dbb2e2c5eede45fa63ea00e20818409d1479b3430d1305080e462b2f0
```

## 10. Validation

Focused V3 gate:

```text
historical sibling harness: PASS
Connect contract:          PASS
V3 PHP syntax:             PASS
V3 compact-output test:    PASS
transport lifecycle:       PASS
PHPCS:                     PASS
```

Complete Navimow MQTT gate:

```text
MQTT fixtures:             PASS
REST authentication:       PASS
native envelope:           PASS
shadow payload:            PASS
Receiver diagnostics:      PASS
Account ingestion:         PASS
REST reconciliation:       PASS
transport lifecycle:       PASS
distribution validation:   PASS
PHPStan:                   PASS
```

No productive envelope fixture changed.

## 11. Architecture Decisions

### AD-NAV-497: Canonicalize at the Core boundary

**Decision:** The transport library emits only the native Core field `QoS`.

**Reason:** Core configuration must follow the schema declared by the native
form.

### AD-NAV-498: Keep a narrow legacy reader

**Decision:** Accept only the exact old `QualityOfService = 0` entry for
migration.

**Reason:** Existing adopted instances must reach canonical rewrite without
topology recreation.

### AD-NAV-499: Reject ambiguous dual schemas

**Decision:** An entry containing both field names is invalid.

**Reason:** Silent precedence would hide configuration drift.

### AD-NAV-500: Preserve the envelope parser

**Decision:** Keep `QualityOfService` in received native envelopes.

**Reason:** The live envelope contract is independent from subscription
configuration.

### AD-NAV-501: Aggregate live observations

**Decision:** V3 emits fixed aggregate evidence.

**Reason:** Evidence must remain complete within the MCP output bound.

## 12. Safety and Compatibility

This step performed:

```text
Git publication:     no
Symcon update:       no
Core mutation:       no
broker connection:   no
MQTT publish:        no
mower command:       no
```

No Device variable, profile, action, timer or Archive Control setting changed.
REST remains the only public state authority.

## 13. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-subscription-schema-correction/
    implementation-manifest.json
```

No credential, endpoint, topic, payload, Client ID, Device ID, ObjectID or
garden detail is included.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| canonical `QoS` implementation | PASS |
| legacy normalization | PASS |
| malformed schema rejection | PASS |
| envelope contract preservation | PASS |
| compact V3 harness | PASS |
| complete offline regression | PASS |
| publication | BLOCKED |
| Symcon update | BLOCKED |
| corrected live receive test | BLOCKED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 15. Recommended Next Step

Create step 141:

```text
native-mqtt-subscription-schema-correction-publication-and-live-test-plan.md
```

It should define separate authorization gates for:

1. exact publication of the productive correction;
2. read-only pre-update baseline and one Module Control update;
3. inactive verification that the legacy retained topology remains accepted;
4. one supervised corrected V3 connection;
5. proof that the live Core property is rewritten to `Topic` plus `QoS`;
6. receive evidence with compact output;
7. automatic cleanup and post-test compatibility;
8. publication retention or rollback based on the result.
