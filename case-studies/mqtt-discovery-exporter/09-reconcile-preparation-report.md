# 09 Non-Destructive Reconcile Preparation Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation sequence step 4a complete
**Date:** 2026-07-15
**Deployment status:** Repository candidate only; no live installation changes

## 1. Outcome

`MqttDiscoveryExporterRuntime::prepareReconcile()` now prepares a complete
desired exporter state without publishing or deleting anything.

The method:

1. initializes diagnostics;
2. validates the configured MQTT gateway and its matching MQTT Device module;
3. validates every state and action variable before adapter creation;
4. requires `HasAction()` for every action variable;
5. reads strictly typed observed state values;
6. constructs discovery and runtime publication plans;
7. records exact planned ownership before object creation;
8. ensures deterministic command adapters and command/state events;
9. writes exact resource identities and trigger indexes to the Registry.

## 2. Live Variable Contract

The initial runtime contract deliberately accepts only unambiguous Symcon
types:

| Capability | State type | Action type |
| --- | --- | --- |
| Power | Boolean | Boolean |
| Brightness | Integer | Integer |
| RGB | Integer color value | Integer color value |
| Color temperature | Integer Kelvin | Integer Kelvin |

Float brightness/Kelvin and string RGB values remain rejected until an
explicit conversion and formatting contract exists. This avoids relying on
PHP or `RequestAction()` scalar coercion.

`HasAction()` proves only that an action is present, not that its underlying
implementation is correct. That limitation remains part of the later
supervised integration gate, as stated by the
[official HasAction documentation](https://www.symcon.de/en/service/documentation/command-reference/access-variables/hasaction/).

## 3. Command Adapter Contract

Every configured command capability receives one deterministic MQTT Device
instance below the exporter script. Its module matches the selected client or
server transport:

- stable hashed Ident;
- exact command topic;
- string Value variable;
- retain disabled for incoming commands;
- connection to the configured MQTT gateway instance;
- one update-triggered event using the canonical
  `SAEF_EnsureTriggeredScriptEvent()` helper.

Repeated equal payloads therefore remain observable. State variables receive
change-triggered events.

The official MQTT Server documentation confirms that an MQTT Device owns its
topic and exposes a Value variable; publishing is performed with
`RequestAction()` on that variable. It also notes that receive-only devices
must be created manually because the configurator cannot discover them:
[IP-Symcon MQTT Server documentation](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-server/).

This report chooses deterministic per-topic instances for incoming command
subscriptions only. It does not yet choose or create the outbound publisher
transport.

## 4. Publication Plan

For each entity, the returned plan contains:

- changed/not-changed decisions for discovery and runtime state;
- canonical discovery JSON;
- exact discovery and runtime topics;
- payload strings;
- retain settings.

Payload bodies are returned to the caller but are not stored in the Registry.
The Registry stores only hashes, topics, identities, indexes and small
ownership metadata.

No `RequestAction()` call, MQTT publish, execution-success statistic or
published hash is written in this step.

## 5. Cleanup-Disabled Safety Gate

Before creating command resources, preparation rejects changes that would
require cleanup:

- removal of a managed entity;
- removal of a capability;
- replacement of discovery, runtime or command topics;
- replacement of previously managed instance or event Idents.

This prevents orphaned active events, command adapters and retained topics
while destructive reconciliation is disabled.

Exact planned ownership is written before object creation. If adapter creation
fails partway through, the Registry still contains the deterministic parent,
Ident and topic contract needed for review and recovery.

## 6. Offline Verification

`tests/mqtt-discovery-exporter/prepare-reconcile.php` verifies five scenario
groups:

1. complete validation, adapter creation, event binding and publication plan;
2. repeated preparation without duplicate instances or events;
3. rejection of action variables without actions before command resources;
4. rejection of incompatible variable types before command resources;
5. capability-contraction rejection while cleanup is disabled.

The integration test also verifies:

- unique command topics;
- MQTT adapter connection, type and retain configuration;
- four update triggers for commands;
- four change triggers for observed state;
- explicit Run Automation action binding on every event.

The complete `composer check` passes. Tests do not access a network, broker,
live IP-Symcon installation or physical device.

## 7. Next Step

G4 remains open. Step 4b must select and implement the outbound MQTT transport,
execute the prepared plan, update published hashes only after successful
publication and model execution success/failure accurately.

Cleanup remains disabled until the later ownership-exact cleanup step.
