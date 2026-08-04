# 99 Native MQTT Receiver Scaffold

**Case study:** Navimow native IP-Symcon module
**Status:** Offline Receiver scaffold complete; Account handoff and publication
remain blocked
**Date:** 2026-07-28
**Scope:** Execute `WP-4` from the approved MQTT shadow implementation plan

## 1. Purpose

This step adds the optional native MQTT child module that will later sit below
an IP-Symcon MQTT Client.

The Receiver currently:

- accepts the native MQTT receive interface;
- applies the strict envelope parser from step 98;
- rejects malformed, oversized and retained envelopes;
- validates an optional Navimow Account selection;
- emits only bounded sanitized debug metadata;
- drops every accepted envelope before semantic Account ingestion.

It does not:

- decode a semantic payload;
- invoke the Account;
- create or update a variable;
- persist MQTT state;
- publish MQTT;
- send a mower command;
- retrieve credentials;
- create or connect a core instance;
- change an existing REST path.

## 2. Official GUID

The module GUID was generated on 2026-07-28 with the official Symcon GUID
Generator:

```text
{1B9960A2-A30C-D846-DF55-800F583AA812}
```

The GUID is unique across all current modules in the Navimow distribution.

## 3. Module Artifacts

Added:

```text
distribution/NavimowMqttReceiver/module.json
distribution/NavimowMqttReceiver/module.php
distribution/NavimowMqttReceiver/form.json
distribution/NavimowMqttReceiver/locale.json
```

The distribution validator now expects:

```text
NavimowAccount
NavimowConfigurator
NavimowDevice
NavimowMqttReceiver
```

## 4. Native Data-Flow Contract

The Receiver metadata declares:

```text
type:
3

parentRequirements:
{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}

implemented:
{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}

childRequirements:
empty
```

These are the native MQTT parent and receive interfaces proven by the isolated
Symcon spike in step 94.

The module has no parent relationship to `NavimowAccount`. Account pairing is
an explicit property, not a change to the physical MQTT chain.

## 5. Configuration Contract

The only Receiver property is:

```text
AccountInstanceId: integer, default 0
```

`form.json` uses:

```text
SelectInstance
validModules:
  {3C2693FC-1068-4A63-856B-8AC0376556CC}
```

The selector therefore accepts only a Navimow Account instance. A static label
states that MQTT shadow handoff remains inactive until Account pairing is
implemented.

The form contains:

- no secret field;
- no enable switch;
- no connect button;
- no publish control;
- no command action;
- no test action.

## 6. Drop-Only Runtime

`ReceiveData()` applies this order:

1. reject an outer envelope above 65,536 bytes;
2. call `MqttEnvelopeParser`;
3. reject an invalid envelope;
4. reject `Retain = true`;
5. read `AccountInstanceId`;
6. reject an unpaired Receiver;
7. reject a missing instance or another module type;
8. stop with `account-ingestion-not-enabled`.

Even a syntactically valid envelope and a valid Account selection cannot cross
the final gate in this step.

There is no fallback call, retry, queue or delayed action.

## 7. Debug Boundary

The Receiver emits one JSON object containing only:

```text
result
envelopeBytes
```

Bounded result codes are:

```text
oversized-envelope
invalid-envelope
retained-rejected
unpaired
invalid-account
account-ingestion-not-enabled
```

Debug output contains no:

- topic;
- payload;
- device ID;
- state;
- battery;
- credential;
- Account ObjectID;
- exception text.

## 8. Prohibited Runtime Surface

Static and executable tests prove the absence of:

```text
SendDataToParent
SendDataToChildren
MQTT_Publish
RequestAction
RegisterVariable
RegisterAttribute
RegisterTimer
ApiClient
CommandContract
action.devices.commands
/uplink/
```

The Receiver therefore has:

- zero variables;
- zero actions;
- zero timers;
- zero persistent payload attributes;
- zero outbound transport methods;
- zero command paths.

## 9. Offline Tests

Added:

```text
tests/mqtt-receiver-scaffold.php
```

The test covers:

- exact module identity and type;
- globally unique module GUID;
- native parent and implemented interfaces;
- empty child requirements;
- prefix;
- Account-only selector;
- absent form actions;
- locale completeness;
- prohibited-source scan;
- valid unpaired envelope drop;
- retained-envelope rejection;
- invalid-`DataID` rejection;
- outer size rejection;
- missing Account rejection;
- wrong-module Account rejection;
- valid Account stop before ingestion;
- absence of semantic input in debug output.

The focused runner now includes the Receiver test and PHPCS for its module
source.

## 10. Verification Result

Passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
composer test:navimow-rest-auth
composer test:navimow-pilot
php tests/Navimow/payload-mapper-fixtures.php
composer phpstan
make check
git diff --check
```

The distribution structure validator recognizes all four modules.

## 11. Official Module Validator

The official Symcon Module Validator was opened and supplied with the new
`module.json`.

The web tool did not produce a validation result. Its own page scripts failed
with:

```text
ReferenceError: $ is not defined
```

The error occurred in both `SetSchema` and `SetOutput`. No cookie dialog was
visible during this run.

Classification:

```text
official web validator:
UNAVAILABLE DUE TO EXTERNAL CLIENT-SIDE ERROR

module metadata:
LOCAL SAEF VALIDATION PASS
```

This is neither an official PASS nor a module FAIL. It does not justify
weakening any local gate.

## 12. Compatibility and Publication

No existing Account, Configurator or Device module file changed in this step.

Unchanged:

- existing module GUIDs;
- Account parent metadata;
- properties and configuration hashes of existing modules;
- variable Idents, profiles and actions;
- Archive Control configuration;
- REST polling and commands;
- standalone module repository;
- installed Symcon module.

The Receiver and parser delta remains only in the case-study distribution. It
has not been copied to `symcon-navimow`, committed there or installed.

No direct Symcon test is useful yet because the Receiver cannot hand off a
message and the standalone repository does not contain it.

## 13. Architecture Decisions

### AD-NAV-387: Use the official generated Receiver GUID

**Decision:** Use
`{1B9960A2-A30C-D846-DF55-800F583AA812}` from the Symcon GUID Generator.

**Rationale:** Module identity must follow the official SDK format and remain
auditable.

**Consequence:** The GUID is fixed for all later Receiver work.

### AD-NAV-388: Keep pairing logical, not physical

**Decision:** Use `AccountInstanceId` while retaining the native MQTT Client as
the physical parent.

**Rationale:** The Account owns semantics, but the Receiver must remain a
native MQTT child.

**Consequence:** Account metadata needs no MQTT parent requirement.

### AD-NAV-389: Make `WP-4` drop-only

**Decision:** Stop before Account ingestion even for a valid pairing.

**Rationale:** The Account method and symmetric pairing contract belong to
`WP-5` and must be reviewed together.

**Consequence:** The scaffold cannot affect shadow state or REST polling.

### AD-NAV-390: Reject retained input before pairing

**Decision:** Classify and reject retained envelopes at the Receiver boundary.

**Rationale:** A retained semantic snapshot may be stale and is not part of
the live evidence contract.

**Consequence:** Retained input never reaches the future Account parser.

### AD-NAV-391: Keep debug metadata non-semantic

**Decision:** Emit only a bounded result code and byte count.

**Rationale:** Receiver diagnostics do not need topic, payload, identity or
exception details.

**Consequence:** Debug mode cannot become an accidental payload store.

### AD-NAV-392: Record validator failure without substituting evidence

**Decision:** Classify the official web validator as unavailable.

**Rationale:** Its client-side JavaScript failed before producing a result.

**Consequence:** Local schema and runtime tests remain the current gate, and
official validation must be retried at a later publication checkpoint.

### AD-NAV-393: Keep publication closed

**Decision:** Do not publish or install the Receiver scaffold.

**Rationale:** A standalone module containing an unusable pairing surface would
provide no pilot value.

**Consequence:** Symcon remains on the known-good REST module at `2c32b86`.

## 14. Decision

**`WP-4` Receiver scaffold: COMPLETE.**

**Official GUID generation: PASS.**

**Native interface metadata: PASS.**

**Drop-only runtime: PASS.**

**Retained rejection: PASS.**

**No-publish invariant: PASS.**

**No-command invariant: PASS.**

**No variable, action, timer or attribute: PASS.**

**Official web validator: EXTERNALLY UNAVAILABLE.**

**Full repository gate: PASS.**

**Account ingestion: NOT IMPLEMENTED.**

**Standalone publication: NOT AUTHORIZED.**

**Symcon mutation: NONE.**

## 15. Recommended Next Step

Create:

```text
100-native-mqtt-account-pairing-and-ingestion.md
```

That step should execute `WP-5` only:

1. add disabled-by-default Account properties;
2. implement side-effect-free symmetric pairing validation;
3. implement the bounded Account ingestion entry point;
4. parse payloads into internal shadow candidates only;
5. register bounded internal diagnostics and shadow attributes;
6. clear semantic shadow and pending work on restart;
7. keep REST variables, timers and commands unchanged;
8. keep publication and live Symcon testing blocked.
