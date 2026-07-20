# 12 Ownership-Exact Cleanup Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation sequence step 6 complete
**Date:** 2026-07-15
**Deployment status:** Repository candidate only; no live installation changes

## 1. Outcome

> **Evolution note (2026-07-16):** The original flat parent contract described
> below has been superseded by the device-oriented category contract in
> `27-device-oriented-object-tree-report.md`. Events remain below the exporter
> script; MQTT Device adapters are now verified below their recorded Commands
> or Publishers category.

`MqttDiscoveryExporterRuntime::executeReconcileWithCleanup()` now removes
obsolete exporter resources before reconciling the desired configuration.
Cleanup is driven exclusively by exact Registry ownership. It performs no
prefix search and contains no installation-specific legacy manifest.

The cleanup-enabled method is explicit. The existing
`executeReconcileWithoutCleanup()` remains available for non-destructive runs.

## 2. Cleanup Trigger

An existing entity is selected for cleanup when it is removed or when its
owned resource contract contracts or changes. Relevant contract fields are:

- discovery topic;
- runtime topics;
- command topics;
- command-instance Idents;
- command-event Idents;
- state-event Idents.

The old entity contract is removed as one unit and then recreated from the
desired configuration. Unaffected entities and their publishers remain in
place.

## 3. Ownership Preflight

Before the first side effect, the runtime validates every selected resource:

- object ID still exists or is already absent from an interrupted cleanup;
- object type is exactly event, instance or variable as recorded;
- parent is the exporter script or owned adapter;
- Ident matches the Registry;
- adapter module ID is the recorded, supported MQTT Client or Server Device module;
- the only adapter child is its recorded string `Value` variable.

Any mismatch aborts cleanup before events are disabled, tombstones are
published or objects are deleted.

## 4. Ordered Effects

Cleanup executes under the same bounded semaphore as reconcile publication:

1. complete ownership preflight;
2. disable all selected events;
3. publish an empty payload to every exact retained topic;
4. delete the selected events;
5. delete each adapter's owned `Value` variable;
6. delete the now childless adapter instance;
7. remove only the completed ownership entries and rebuild Registry indexes;
8. reconcile and publish desired resources.

IP-Symcon documents that event, variable and instance deletion can fail when
child objects remain. The implementation therefore verifies event children
and deletes the exact owned adapter variable before its instance:

- <https://www.symcon.de/en/service/documentation/command-reference/management-events/>
- <https://www.symcon.de/en/service/documentation/command-reference/management-variables/ips-deletevariable/>
- <https://www.symcon.de/en/service/documentation/command-reference/management-instances/ips-deleteinstance/>

## 5. Interrupted Cleanup

The Registry retains entity and publisher ownership until the complete cleanup
sequence succeeds. Each successful retained-topic tombstone additionally gets
a small `cleanupTombstones` marker. A retry can therefore distinguish an
already-cleared topic from one that still requires publication, including the
short interval after the publisher Value variable has been deleted.

Markers contain only the deterministic SHA-256 publisher key and Boolean
completion state. They contain no payload, topic, hostname or private device
data and are removed with the completed publisher ownership entry.

## 6. Offline Verification

`tests/mqtt-discovery-exporter/cleanup.php` verifies six scenario groups:

1. removal of one complete entity while an unrelated entity remains intact;
2. removal of the final entity and complete exporter-resource cleanup;
3. capability contraction through exact replacement and republishing;
4. refusal before side effects when an owned Ident no longer matches;
5. Registry preservation after a failed tombstone and successful retry;
6. an unchanged cleanup-enabled reconcile without cleanup or MQTT actions.

The fake runtime implements the documented childless delete behavior for
events, variables and instances. Tests access no network, broker, live
IP-Symcon installation or physical device.

## 7. Gate Decision

G4 implementation sequence step 6 is complete. The helper-first candidate now
covers core logic, diagnostics, reconciliation, retained MQTT publication,
indexed dispatch and ownership-exact cleanup.

The next step is G5: consolidate deterministic verification and prepare a
supervised live-integration and rollback plan. Live deployment remains
unauthorized and is not performed by the repository test suite.
