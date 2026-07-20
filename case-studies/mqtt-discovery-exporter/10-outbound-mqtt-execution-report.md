# 10 Outbound MQTT Execution Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation sequence step 4 complete
**Date:** 2026-07-15
**Deployment status:** Repository candidate only; no live installation changes

## 1. Outcome

`MqttDiscoveryExporterRuntime::executeReconcileWithoutCleanup()` now executes
the prepared discovery and runtime publication plan through transport-matched
IP-Symcon MQTT Device Value variables.

The method:

- records one execution and last-run timestamp;
- prepares and validates the non-destructive reconcile plan;
- ensures all exact publisher resources before the first publish;
- serializes publication and Registry hash commits with a bounded semaphore;
- invokes `RequestAction()` with string payloads;
- checks the Boolean action result;
- commits discovery and runtime hashes only after their complete channel
  succeeds;
- records publish, skip, success and failure diagnostics;
- rethrows failures without automatic retry.

Runtime retain is now mandatory in normalized configuration. Discovery and
runtime state are therefore always published through retained publisher
adapters.

## 2. Transport Decision

Two outbound designs were considered:

| Criterion | Shared reconfigured publisher | Deterministic publisher per topic |
| --- | --- | --- |
| Instance count | Low | Proportional to configured topics |
| Topic/retain configuration | Changed before messages | Stable after Ensure |
| ApplyChanges calls | Required during normal publishing | Required only for configuration changes |
| Concurrent configuration risk | Requires strict serialization around reconfiguration and action | No shared topic configuration |
| Ownership and cleanup | One instance plus indirect topic history | Exact instance per retained topic |
| Offline verification | More timing-dependent | Deterministic |

The candidate selects one deterministic MQTT Device publisher per outbound
topic. The module is selected from the configured client or server transport.
A full light with power, brightness, RGB and color temperature uses six
outbound publishers: one discovery topic and five runtime topics.

This costs more Symcon objects but provides the safer initial engineering
contract:

- no reconfiguration between messages;
- exact Registry ownership;
- idempotent instance reuse;
- straightforward retained-topic cleanup later;
- no possibility that one execution publishes through another execution's
  temporarily configured topic.

The official MQTT Server documentation defines the device topic and shows
publishing with `RequestAction()` on its Value variable:
[IP-Symcon MQTT Server](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-server/).

## 3. Publisher Ownership

Publisher metadata is stored in the Registry as a small map keyed by the full
SHA-256 topic hash. Each entry contains:

- deterministic instance Ident;
- exact topic;
- retain contract;
- planned/ready state;
- instance ID;
- Value variable ID.

The desired publisher contract is written before publisher creation. Existing
publisher entries not present in desired state cause an explicit failure while
cleanup is disabled.

Payload bodies are never stored in the Registry.

## 4. Concurrency Boundary

Publication and published-hash commits are protected by an exporter-specific
semaphore with a five-second maximum wait. A timeout is a failed execution and
is recorded with phase `reconcile_lock`.

IP-Symcon documents `IPS_SemaphoreEnter()` as the bounded mechanism for
exclusive access between simultaneously running scripts:
[IPS_SemaphoreEnter](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ablaufsteuerung/ips-semaphoreenter/).

The semaphore is released in a `finally` block. A failed release is logged.

## 5. Channel Commit Semantics

Discovery and runtime form separate commit channels for each entity:

- discovery hash is committed after its retained message succeeds;
- runtime hash is committed only after every runtime topic succeeds;
- a partial runtime publication leaves the runtime hash unpublished;
- the next execution republishes the complete runtime channel;
- an already committed discovery channel is skipped during that retry.

This provides idempotent recovery without introducing an automatic retry loop.
Only successful `RequestAction()` calls increment `PUBLISHES`.

`SUCCESSES` is the final state mutation of a successful execution. A failed
publication cannot subsequently be counted as successful.

## 6. Offline Verification

`tests/mqtt-discovery-exporter/execute-reconcile.php` verifies three scenario
groups:

1. six retained publications through six deterministic topic publishers;
2. unchanged discovery/runtime channels are skipped without another action;
3. a partial runtime failure commits discovery only, records failure and
   republishes the complete runtime channel on the next explicit execution.

The tests additionally verify:

- string payload types;
- six retained outbound and four non-retained command adapters;
- cumulative publish, skip, success and failure counters;
- failure-ring phase classification;
- no success count for a failed execution.

No test accesses a network, broker, live IP-Symcon installation or physical
device.

## 7. Gate Decision

G4 sequence step 4 is complete. Cleanup remains disabled.

The next step is exact command and state dispatch:

- resolve triggers only through Registry indexes;
- parse command payloads through the pure core;
- invoke device action variables with the exact Symcon type;
- confirm observed state through the bounded WaitForVariable helper;
- publish only the affected entity state;
- preserve explicit failure results.
