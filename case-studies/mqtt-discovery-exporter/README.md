# IP-Symcon MQTT Discovery Exporter Case Study

**Status:** Completed SAEF engineering case study
**Scope:** Bidirectional export of IP-Symcon variables to Home Assistant through MQTT Discovery
**Implementation state:** G5/G6 passed; two-entity supervised pilot active and independently verified

## Purpose

This case study evaluates a tested private release candidate that exposes
IP-Symcon variables as Home Assistant MQTT `light` and `switch` entities.
Home Assistant may then expose those entities to additional user interfaces,
including Apple Home.

The original use case involved a Matter-connected light group. Matter is not
part of the exporter protocol or implementation boundary: IP-Symcon remains the
source of device state and actions, while MQTT Discovery is the integration
contract with Home Assistant. The reusable engineering subject is therefore a
generic MQTT Discovery exporter, not a Matter-specific lighting script.

The case study records requirements, accepted design decisions, gaps in the
private V4.1-RC2 baseline and the gates that must be passed before any code can
become an SAEF reference implementation.

## Case Study Files

| File | Purpose |
| --- | --- |
| `01-requirements.md` | Sanitized requirements, system boundaries, data roles and evidence limits. |
| `02-rc2-architecture-review.md` | SAEF review of the private V4.1-RC2 design and implementation. |
| `03-adoption-plan-and-gates.md` | Ordered adoption work packages and release gates. |
| `04-private-baseline-capture-report.md` | Sanitized G2 evidence, hashes, syntax result and provenance boundary. |
| `05-triggered-event-helper-report.md` | G3 event-helper contract, cyclic-event correction and test evidence. |
| `06-helper-first-exporter-design.md` | G4 implementation boundary, responsibilities, run modes and deployment gate. |
| `07-core-implementation-report.md` | G4 pure-core boundary, deterministic tests and verification result. |
| `08-runtime-diagnostics-report.md` | G4 helper-composed diagnostics structure, Registry contract and offline verification. |
| `09-reconcile-preparation-report.md` | G4 live-contract validation, deterministic adapters/events, publication planning and cleanup-disabled gate. |
| `10-outbound-mqtt-execution-report.md` | G4 outbound transport decision, retained execution, channel commits and retry-safe failure semantics. |
| `11-command-state-dispatch-report.md` | G4 Registry-indexed dispatch, bounded command confirmation and affected-entity state publication. |
| `12-ownership-exact-cleanup-report.md` | G4 exact cleanup preflight, retained-topic tombstones, retry markers and offline verification. |
| `13-deterministic-offline-verification-report.md` | G5 consolidated requirements trace, fixture evidence, external-contract recheck and gate decision. |
| `14-supervised-integration-and-rollback-plan.md` | G6 non-executing deployment, isolation, scenario, evidence and rollback procedure. |
| `15-filesystem-deployment-adapter-report.md` | Deterministic G6 filesystem fileset, provenance, conflict preflight and offline load verification. |
| `16-private-activation-inventory-report.md` | Sanitized read-only inventory of the active minimal bundle, bootstrap dependency, migrated caller and namespace transition. |
| `17-private-backup-and-staging-report.md` | Sanitized result of the recoverable private backup and exact inactive target-filesystem staging. |
| `18-activation-attempt-and-rollback-report.md` | Sanitized failed activation attempt, timeout-driven rollback and required restart-design correction. |
| `19-rollback-recovery-verification-report.md` | Verified clean-runtime restoration of the original minimal SAEF namespace, caller and bootstrap after manual service recovery. |
| `20-state-based-windows-restart-coordinator-report.md` | External state-based Windows restart and rollback coordinator, deterministic trace evidence and remaining live gate. |
| `21-pre-activation-drift-revalidation-report.md` | Immediate read-only verification of the active minimal runtime, migrated caller and complete inactive staged fileset. |
| `22-fileset-activation-and-runtime-verification-report.md` | Supervised clean-process fileset activation, exact runtime namespace verification and unchanged migrated-caller evidence. |
| `23-live-diagnostics-idempotency-report.md` | Live two-run diagnostics initialization, exact object reuse, empty publication state and independent MCP read-back. |
| `24-site-broker-and-mqtt-client-transport-report.md` | Site-local broker architecture, transport-neutral adapter contract, migration cleanup rule and offline verification. |
| `25-supervised-light-client-preflight-report.md` | Read-only tunable-white light mapping, client-transport verification and detection of the still active legacy server-only runtime. |
| `26-client-fileset-activation-and-runtime-verification-report.md` | Controlled client-fileset replacement, clean restart and independent runtime contract verification. |
| `27-device-oriented-object-tree-report.md` | Device-oriented Commands/Publishers categories, user-editable presentation and leaf-first category cleanup. |
| `28-device-tree-fileset-activation-and-runtime-verification-report.md` | Controlled device-tree fileset replacement, clean restart, exact runtime identity and unchanged pre-reconcile state. |
| `29-live-device-tree-preparation-and-parentid-correction-report.md` | First live device-tree preparation, repeated-run ParentID defect evidence, correction and reactivation gate. |
| `30-parentid-fix-activation-and-live-repeatability-report.md` | Corrected fileset activation, exact runtime verification and duplicate-free repeated live preparation. |
| `31-shared-wait-helper-load-path-analysis.md` | Read-only global load-path inventory, minimal fileset delta and supervised migration gates for the corrected shared wait helper. |
| `32-shared-wait-helper-inactive-staging-report.md` | Private Gate-A package, bounded chunk transfer, atomic inactive staging and independent non-selection readback. |
| `33-shared-wait-helper-maintenance-preflight-report.md` | Fresh byte-exact rollback, topology/diagnostics snapshot and the remaining external Windows service-preflight boundary. |
| `34-shared-wait-helper-activation-and-runtime-verification-report.md` | Atomic owner-fileset selection, supervised restart, effective Reflection identity and post-restart regression evidence. |
| `35-second-state-only-light-pilot-and-recovery-report.md` | Cleanup-disabled second light publication, rejected-command observability during an external transport outage and successful post-reconnect state restoration. |
| `36-client-subscription-coverage-and-runtime-namespace-report.md` | Client-subscription coverage validation, controlled runtime-namespace correction and complete Home Assistant/Apple Home functional evidence. |
| `37-owner-decoupling-and-subscription-runtime-activation-report.md` | Physical owner-path decoupling, corrected subscription-runtime activation, idempotent three-entity reconciliation and alarm-bounded live evidence. |
| `38-latest-command-wins-architecture-review.md` | V05-001 impact inventory, bounded generation arbitration, failure classification, deterministic test contract and separate implementation/live gates. |
| `39-latest-command-wins-live-inventory.md` | Fresh read-only owner, consumer, event, runtime, diagnostics and timing inventory admitting bounded offline implementation without live mutation. |
| `candidate/MqttDiscoveryExporterCore.php` | Side-effect-free normalization, payload, parsing, hashing and cleanup-planning core. |
| `candidate/MqttDiscoveryExporterRuntime.php` | Runtime adapter for diagnostics, reconcile, MQTT execution, indexed dispatch and exact cleanup. |
| `../../tests/mqtt-discovery-exporter/fixtures/discovery-capabilities.json` | Sanitized deterministic discovery fixtures for every supported capability combination. |
| `../../deployments/symcon/mqtt-discovery-exporter.fileset.json` | Reviewed manifest for the portable filesystem deployment closure. |
| `../../deployments/symcon/windows/Invoke-SaefSymconRestart.ps1` | External state-based G6 restart and rollback coordinator for Windows. |

## Engineering Boundary

The case study may describe the shape and behavior of the private baseline, but
must not contain:

- installation ObjectIDs;
- private device, room or site names;
- private MQTT topics;
- hostnames, IP addresses or credentials;
- installation-specific legacy cleanup lists;
- unreviewed production code copied from the private baseline.

The original handover and any live configuration remain private input evidence.
Only sanitized, reviewed and independently verified material may be promoted
into public SAEF artifacts.

## Current Decision

The reviewed candidate is accepted as case-study implementation and engineering
evidence. It remains intentionally separate from `references/`: its MQTT
transport, reconciliation, ownership and deployment workflow solve a concrete
integration project rather than define a generic SAEF reference pattern.

The completed implementation demonstrates:

- helper-first object and diagnostics management;
- removal of installation-specific migration cleanup from the generic core;
- strict configuration and MQTT payload validation;
- observable command failure semantics;
- deterministic cleanup of every owned object and retained topic;
- bounded state confirmation instead of a fixed sleep;
- reconciliation-time MQTT Client subscription coverage without mutating the
  shared gateway;
- repeatable offline tests and supervised live integration evidence.

Post-v0.4 architecture work additionally defines a bounded latest-command-wins
model for rapid commands. Runtime code, generated filesets and live owners
remain unchanged at this documentation stage.

The fresh read-only inventory in report 39 confirms the complete two-owner
consumer set and repository-identical active runtime. It admits deterministic
offline implementation with an explicit 15-second confirmation cap and
20-second maximum lock wait. Generated fileset activation and every functional
command remain separate live gates.

The current supervised client-transport pilot manages two light entities. The
second state-only entity has additionally demonstrated fail-closed handling
during a temporary target-transport outage and successful operation after
automatic transport recovery.

An additional state-only migration has demonstrated namespace-fail-closed
diagnosis, authoritative manual-on projection and complete Home Assistant plus
Apple Home command operation. The repository candidate can retain owned
adapter/event identities across a runtime-topic namespace change; its live
activation remains a separate retention-maintenance gate.

## Related SAEF Artifacts

- `principles/ENGINEERING_PRINCIPLES.md`
- `adr/ADR-0001-use-requestaction.md`
- `adr/ADR-0002-use-ident-over-object-id.md`
- `adr/ADR-0003-private-overlay.md`
- `adr/ADR-0004-introduce-architectural-patterns.md`
- `standards/SYMCON_STANDARDS.md`
- `standards/PHP_STANDARDS.md`
- `standards/TESTING_STANDARDS.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `knowledge/EK-006-runtime-diagnostics.md`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
