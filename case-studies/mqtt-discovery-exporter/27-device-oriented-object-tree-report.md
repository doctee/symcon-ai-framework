# 27 Device-Oriented Object Tree Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** Candidate implementation and offline verification complete
**Date:** 2026-07-16
**Deployment status:** Fileset activation and independent runtime verification passed

## 1. Decision

The exporter no longer places every MQTT Device directly below its owner
script. Visible transport resources are grouped by configured device:

```text
Exporter owner script
|-- Diagnostics
`-- Devices
    `-- Device display name
        |-- Commands
        `-- Publishers
```

Command and state-trigger events remain hidden direct children of the owner
script. The canonical event helper binds the Run Automation action to that
script and therefore requires this parent relationship.

The MQTT protocol contract is unchanged. Every command, discovery and state
topic still has its own MQTT Device instance. Combining several values in one
JSON topic is a separate future protocol change.

## 2. Stable Ownership and User Presentation

The exporter manages:

- stable Idents;
- exact parent relationships;
- object types and MQTT module identities;
- gateway connection, topic, value type and retain behavior;
- Registry ownership metadata.

Display names, positions and icons are creation defaults. Reconcile does not
overwrite later user changes to those presentation properties. This applies to
the exporter categories, MQTT Device instances, hidden events and diagnostics
objects created through the updated helper composition.

## 3. Registry Contract

The Registry now records a bounded `resourceTree` map containing the Devices
root and each device's category identities. Managed entity entries record their
device key and exact Commands and Publishers parent IDs. Publisher entries also
record their exact parent category.

This metadata is structural ownership information only. It contains no MQTT
payloads, credentials or network endpoints.

## 4. Cleanup

Cleanup remains leaf-first:

1. validate every event, adapter, Value variable and category relationship;
2. reject unmanaged children before any mutation;
3. disable and delete events;
4. clear retained topics;
5. delete adapter Value variables and adapter instances;
6. delete empty Commands and Publishers categories;
7. delete the empty device category;
8. delete the Devices root only when no managed device remains;
9. commit the reduced Registry.

Removing one entity does not delete a shared device category while another
entity of the same configured device remains.

## 5. Verification

Offline tests verify:

- Commands and Publishers are children of the configured device category;
- hidden events remain direct children of the owner script;
- repeated reconcile creates no duplicates;
- user-edited names and positions survive reconcile;
- publishers are created below the device Publishers category;
- final cleanup removes the complete resource-category branch leaf-first;
- unmanaged category children block cleanup before effects.

The fileset was rebuilt, activated in a clean IP-Symcon process and verified
independently. The private pilot owner script has not yet run
`prepareReconcile`; that remains the next separate state-changing gate.
