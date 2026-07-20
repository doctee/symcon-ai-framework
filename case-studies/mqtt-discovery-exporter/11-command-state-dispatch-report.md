# 11 Command and State Dispatch Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation sequence step 5 complete
**Date:** 2026-07-15
**Deployment status:** Repository candidate only; no live installation changes

## 1. Outcome

`MqttDiscoveryExporterRuntime::dispatchTriggeredVariable()` now processes one
registered command or state trigger without executing full reconciliation.

Dispatch:

- loads the existing diagnostics objects without creating or ensuring them;
- requires both prepared and published configuration hashes to match the
  supplied normalized configuration;
- resolves the trigger only through exact Registry indexes;
- rejects ambiguous and unknown triggers;
- serializes dispatch with a bounded exporter-specific semaphore;
- returns an explicit command or state result.

There is no unknown-trigger fallback to Reconcile.

## 2. Registry Index Contract

Command indexes map one MQTT Value variable ID to exactly:

- entity key;
- command capability.

State indexes map one state variable ID to exactly one entity and capability.
Reconcile validation now rejects reuse of one state variable by multiple
entity/capability contracts, so state dispatch cannot be ambiguous by design.

Dispatch also requires:

- `preparedConfigurationHash` for the successfully ensured resource contract;
- `publishedConfigurationHash` for the successfully initialized outbound
  publication contract.

A configuration change must therefore pass Reconcile before events may use it.

## 3. Command Flow

The command path performs:

1. exact Registry resolution;
2. string payload read from the indexed MQTT command variable;
3. strict parsing through `MqttDiscoveryExporterCore::parseCommand()`;
4. capability and action-variable validation;
5. one typed `RequestAction()` call;
6. bounded observed-state confirmation through
   `SAEF_WaitForVariable()` in update mode;
7. forced publication of only the affected entity's observed runtime state;
8. runtime-hash commit only after the complete channel succeeds.

The confirmation timeout and polling interval come from the normalized entity
configuration. A short lookback handles synchronous or sub-second updates with
IP-Symcon's second-based variable metadata.

No command retry is performed.

## 4. Command Result Contract

Implemented command statuses are:

- `confirmed`;
- `invalid_payload`;
- `action_failed`;
- `confirmation_timeout`;
- `publish_failed`.

`accepted_unconfirmed` is not implemented because every current capability
contract has an observable state variable. Only `confirmed` increments the
command and success counters.

Classified command failures return an explicit result, increment failures and
do not increment success. Infrastructure or unknown-trigger failures are
re-thrown after diagnostic recording.

## 5. State Flow

The state path:

1. resolves exactly one entity from the state index;
2. reads the complete observed state of that entity;
3. constructs its current runtime payloads;
4. compares the runtime hash;
5. publishes only that entity when changed;
6. skips unchanged state without MQTT actions.

State dispatch never reconfigures command adapters, publishers or unrelated
entities.

## 6. Diagnostic Privacy

The ErrorRingBuffer stores a generic phase-classified message and exception
type. It does not store MQTT payloads, topics or configuration contents.
Detailed exception text remains available in the Symcon log.

## 7. Offline Verification

`tests/mqtt-discovery-exporter/dispatch.php` verifies eight scenario groups:

1. confirmed typed brightness command and affected-entity publication;
2. malformed payload rejection without a device action;
3. bounded confirmation timeout without state publication;
4. action rejection as `action_failed`;
5. partial runtime publication as `publish_failed` without hash commit;
6. changed state publication followed by unchanged-state skip;
7. unknown-trigger rejection without Reconcile side effects;
8. rejection of configuration not matching the published Registry.

The tests use separate state/action variables and simulated device feedback.
No test accesses a network, broker, live IP-Symcon installation or physical
device.

## 8. Gate Decision

G4 sequence step 5 is complete. The next step is ownership-exact cleanup behind
offline tests:

- disable and remove only Registry-proven events and instances;
- clear only exact retained discovery/runtime topics;
- refuse deletion on parent, Ident, object type or module mismatch;
- commit desired Registry state only after successful cleanup.

Cleanup remains disabled in the current candidate.
