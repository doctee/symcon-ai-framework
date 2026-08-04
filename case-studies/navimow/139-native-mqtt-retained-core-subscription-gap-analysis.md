# 139 Native MQTT Retained Core Subscription Gap Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Subscription schema mismatch proven; bounded correction approved
for offline implementation
**Date:** 2026-07-28
**Scope:** Compare successful disposable and zero-ingress retained transports,
identify the smallest root-cause candidate and define a migration-safe fix

## 1. Purpose

Step 138 proved:

- the retained WebSocket and MQTT Core instances reached healthy status;
- neither the productive Receiver nor a known-good sibling probe received a
  message;
- the gap is before child distribution;
- runtime, Module Control and Git were fully restored.

The user later clarified that the mower mowed only briefly and then returned
to the station. Therefore step 138 is not treated as proof of continuous
active-mowing traffic. Its direct Core-status and child-counter evidence
remains valid.

This step:

1. compares the successful step-94 disposable transport with the retained
   transport;
2. reads only the relevant native MQTT Client schema metadata;
3. distinguishes subscription configuration from received envelope fields;
4. identifies the smallest corrective change;
5. defines compatibility and migration requirements;
6. stops before productive code changes or a new live connection.

## 2. Evidence Set

The analysis uses:

- step 94 successful disposable native receive evidence;
- steps 115, 121 and 127 retained zero-ingress evidence;
- step 138 sibling cross-probe evidence;
- the current canonical `MqttTransportConfiguration`;
- the retained MQTT Client's configuration key shapes;
- the native MQTT Client's own configuration form;
- the official IP-Symcon MQTT Client documentation.

No new broker connection or mower activity was performed.

## 3. Confirmed Common Properties

Both the successful disposable and retained transports used:

- native WebSocket Client followed by native MQTT Client;
- WSS on port 443;
- binary WebSocket mode;
- certificate verification;
- one Bearer authorization header;
- non-empty MQTT username and password during activation;
- one client identity;
- four exact device topics;
- no wildcard;
- QoS 0 intent;
- the same known-good receive probe implementation;
- healthy Core status during their observation windows.

These common properties do not explain the different receive result.

## 4. Previously Tested Differences

| Difference | Evidence | Decision |
|---|---|---|
| stable versus fresh Client ID | step 127 fresh-ID run also received zero | not sufficient |
| productive Receiver implementation | step 138 known-good sibling also received zero | rejected as sole cause |
| Core health | retained clients stayed on status `102` | not proof of ingress |
| child compatibility | both children share the native interface | not the gap |
| visible mower activity | confirmed during retained tests | not the gap |

The investigation must move to configuration consumed by the retained MQTT
parent.

## 5. Subscription Schema Finding

The successful step-94 private input helper created each native subscription
entry as:

```json
{
  "Topic": "<private exact topic>",
  "QoS": 0
}
```

That disposable transport received two valid location messages.

The canonical retained transport currently creates:

```json
{
  "Topic": "<private exact topic>",
  "QualityOfService": 0
}
```

This difference was previously hidden because the local validator normalized
and checked the same self-defined field it had generated.

## 6. Native Live Schema Readback

A bounded read-only Symcon probe returned no topic or value. It projected only
field names.

Installed retained entries:

```text
configured subscription count: 4
entry keys:
  QualityOfService
  Topic
```

The native MQTT Client configuration form declares:

```text
property: Subscriptions
type:     List
columns:
  Topic
  QoS
```

MCP result:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
readback pass:     true
```

This proves that the retained configuration does not follow the native Core
subscription-entry schema.

## 7. Official MQTT Semantics

The
[official IP-Symcon MQTT Client documentation](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/)
states that subscriptions are configured on the MQTT Client.

The
[official MQTT device support documentation](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/geraeteliste/)
states that subscribed topics are sent with QoS 0.

The public documentation does not expose the internal JSON column name. The
native live configuration form supplies that missing schema evidence:
`QoS`.

## 8. Root-Cause Classification

```text
finding:
subscription-entry schema mismatch

confidence:
high

exact causal proof:
not yet complete
```

The mismatch is:

- directly observed in the live retained configuration;
- inconsistent with the native form;
- inconsistent with the successful disposable transport;
- located exactly at the parent subscription boundary selected by step 138.

It is therefore the smallest and strongest next correction.

One corrected live receive test is still required to prove that this mismatch
caused the zero-ingress behavior.

## 9. Critical Field Separation

Two different schemas use different names.

### MQTT Client Subscription Configuration

Required Core property entry:

```text
Topic
QoS
```

### Native MQTT Receive Envelope

Proven step-94 child envelope:

```text
DataID
PacketType
Payload
QualityOfService
Retain
Topic
```

`QualityOfService` remains correct in the received envelope parser and its
fixtures. It must not be renamed there.

Only subscription configuration generation and validation require correction.

## 10. Affected Productive Scope

Primary source:

```text
case-studies/navimow/distribution/libs/Navimow/
  MqttTransportConfiguration.php
```

Affected methods:

- `createSubscriptions()`;
- `configuredSubscriptions()`;
- subscription equality and shape regressions derived from them.

Expected direct tests:

- `mqtt-transport-lifecycle.php`;
- any transport configuration fixtures using subscription entries.

Not affected:

- `MqttEnvelopeParser`;
- native envelope fixtures;
- Account MQTT payload ingestion;
- Device variables;
- REST authority;
- command implementation;
- Archive Control contracts.

Historical private harnesses and reports retain their original field names as
executed evidence. They are not rewritten.

## 11. Migration Constraint

The installed retained MQTT Client currently stores the legacy entry shape.
An abrupt validator change to `QoS` only would make the adopted topology fail
validation before `ConnectMqttShadow()` can rewrite it.

The implementation must therefore:

1. emit only canonical `QoS` entries for all new configuration;
2. accept the exact legacy `QualityOfService = 0` shape only as a bounded
   migration input;
3. normalize both forms internally to canonical `QoS`;
4. reject entries containing both names;
5. reject unknown fields, duplicates, nonzero or non-integer QoS;
6. preserve topic validation and no-wildcard rules;
7. let the next normal Connect write canonical subscriptions before WebSocket
   activation;
8. verify that the Core stores `Topic` and `QoS` afterward.

This avoids deleting or recreating the retained Core chain.

## 12. Ownership Compatibility

The ownership registry hashes subscription semantics:

- device count;
- channel set;
- QoS 0;
- topic count.

It does not need to encode the legacy spelling. Normalizing the legacy shape to
canonical semantics preserves the current ownership contract until the normal
Connect rewrites the Core property.

The implementation must prove:

- legacy retained topology remains valid while inactive;
- canonical topology remains valid;
- a canonical rewrite preserves instance identities and ownership;
- cleanup works for both pre- and post-migration shapes.

## 13. Output Hardening

Step 138 also exposed an evidence-channel defect: full per-poll observations
exceeded the bounded MCP output.

Any future live harness shall return only:

- sample count;
- first, first-healthy and final relative timestamps;
- initial and steady Core status classes;
- maximum child deltas;
- final classification;
- cleanup booleans.

It may retain no unbounded observation array.

This hardening is private test infrastructure and remains separate from the
productive subscription fix.

## 14. Architecture Decisions

### AD-NAV-492: Use the native `QoS` subscription key

**Decision:** Canonical subscription configuration shall contain `Topic` and
`QoS`.

**Reason:** The native form and successful disposable transport agree on that
schema.

### AD-NAV-493: Preserve envelope `QualityOfService`

**Decision:** Do not rename the native receive-envelope field.

**Reason:** Subscription configuration and received child envelopes are
different contracts.

### AD-NAV-494: Migrate by normalization

**Decision:** Temporarily accept the exact legacy entry only at the transport
configuration boundary and normalize it to canonical form.

**Reason:** This lets the existing adopted topology reach the normal canonical
rewrite without recreation.

### AD-NAV-495: Do not recreate Core instances

**Decision:** Preserve retained WebSocket, MQTT and Receiver identities.

**Reason:** The correction concerns one property schema and does not justify
topology replacement.

### AD-NAV-496: Compact future live evidence

**Decision:** Replace per-sample output with bounded aggregate milestones.

**Reason:** MCP truncation must not obscure successful cleanup evidence.

## 15. Risk Review

| Risk | Control |
|---|---|
| envelope field renamed accidentally | separate source and fixture tests |
| existing topology rejected before migration | exact legacy normalization |
| malformed mixed schema accepted | reject entries containing both keys |
| ownership invalidated | semantic hash regression |
| MQTT activation during update | feature remains disabled |
| variable or logging churn | no Device or profile changes |
| another oversized live result | compact aggregate harness |

## 16. Gate Decision

| Gate | Decision |
|---|---|
| subscription schema mismatch | PROVEN |
| exact causality | PENDING LIVE CORRECTION TEST |
| bounded offline implementation | GO |
| immediate publication | BLOCKED |
| immediate Symcon update | BLOCKED |
| another broker connection | BLOCKED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 17. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-subscription-gap-analysis/
    schema-readback.json
```

The artifact contains field names and booleans only. It contains no topic,
credential, endpoint, Client ID, Device ID, ObjectID or garden detail.

## 18. Recommended Next Step

Create step 140:

```text
native-mqtt-subscription-schema-correction-implementation.md
```

It should:

1. implement canonical `QoS` generation;
2. add strict one-way legacy normalization;
3. update transport lifecycle tests without touching envelope tests;
4. add explicit canonical, legacy, mixed and malformed regressions;
5. implement compact V3 live evidence output privately;
6. run the complete Navimow MQTT suite;
7. stop before publication or Symcon mutation.
