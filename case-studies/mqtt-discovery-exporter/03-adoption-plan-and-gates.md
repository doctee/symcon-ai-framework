# 03 SAEF Adoption Plan and Gates

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** Approved staged adoption plan
**Date:** 2026-07-15
**Current gate:** G6 - Supervised integration verification (planning only; live execution unauthorized)

## 1. Purpose

Define the smallest ordered steps that can transform the tested private RC2
baseline into reviewed SAEF artifacts without losing operational evidence or
promoting private migration logic into the public framework.

Passing one gate authorizes only the next gate. It does not imply final release
approval.

## 2. Gate Summary

| Gate | Outcome | Productive code allowed? |
| --- | --- | --- |
| G1 | Case-study requirements and architecture review | No |
| G2 | Private baseline and provenance boundary | No public implementation |
| G3 | Canonical triggered-event helper | Helper code and tests only |
| G4 | Helper-first exporter implementation | Candidate implementation |
| G5 | Deterministic offline verification | No live commands |
| G6 | Supervised Symcon/HA integration verification | Controlled live test only |
| G7 | Reference implementation release decision | Only after evidence review |

## 3. G1 - Case-Study Foundation

**Status:** Complete

### Scope

- record the generic integration objective;
- separate the Matter use case from the reusable MQTT exporter boundary;
- define functional and non-functional requirements;
- classify private RC2 decisions against SAEF;
- record evidence gaps and adoption gates.

### Exit Criteria

- `README.md`, `01-requirements.md`, `02-rc2-architecture-review.md` and this
  plan exist;
- no private identifiers or topics are present;
- no RC2 production code is promoted;
- the decision remains No-Go for verbatim reference publication.

## 4. G2 - Private Baseline and Provenance Boundary

**Status:** Complete

### Scope

- preserve the exact supplied handover as local private evidence;
- extract the two RC2 PHP files without changing them;
- record source date, reported version, source hash and sanitization status;
- keep installation configuration and legacy cleanup manifests private;
- verify that no third-party code has been copied without compatible
  provenance and licensing information.

### Proposed Private Layout

```text
private/mqtt-discovery-exporter/
|-- README.md
|-- source/
|   |-- matter-led-handover.private.md
|   |-- HAExportV41_Config.private.php
|   `-- HAExportV41_Exporter.private.php
`-- migration/
    `-- legacy-cleanup-manifest.private.php
```

The private source is evidence, not a canonical SAEF implementation.

### Exit Criteria

- exact source hashes are recorded;
- extracted PHP syntax can be checked independently;
- private and public boundaries are explicit;
- no private file is staged for publication.

## 5. G3 - Canonical Triggered-Event Helper

**Status:** Complete

### Scope

Extend `helpers/object/EnsureEvent.php` with one general helper for
variable-triggered script events.

### Required Behavior

- support trigger types update and change initially;
- validate parent, target script, trigger variable and Ident;
- reuse compatible events and reject incompatible object or event types;
- set trigger, target execution contract, action binding, active state,
  position and visibility deterministically;
- preserve unrelated events;
- document the narrow warning-suppression rule for Ident lookup.

### Verification

- missing-event creation;
- compatible-event update;
- incompatible object rejection;
- incompatible event-type rejection;
- invalid trigger variable and type rejection;
- repeat execution without duplicates;
- explicit action binding verification.

### Exit Criteria

- helper tests pass;
- PHP syntax and repository checks pass;
- no MQTT-specific behavior exists in the helper API.

## 6. G4 - Helper-First Exporter Candidate

### Scope

Implement a candidate exporter with separated responsibilities:

```text
Configuration and normalization
    |
    +-- validation
    +-- reconciliation plan
    +-- discovery payload construction
    +-- runtime payload construction
    +-- command parsing and dispatch
    +-- state confirmation
    `-- exact owned-resource cleanup
```

### Required Changes from RC2

- compose existing object and diagnostics helpers;
- use the G3 triggered-event helper;
- remove all installation-specific legacy cleanup;
- strictly parse MQTT command payloads;
- validate `HasAction()` for action variables;
- normalize complete state/action capability pairs;
- propagate or explicitly model command failure;
- use bounded variable confirmation instead of a fixed sleep;
- avoid reapplying all MQTT command instances during state events;
- track state and command events for exact cleanup;
- keep routine diagnostics within established SAEF responsibilities.

### Exit Criteria

- no duplicated canonical helper implementation remains;
- no private ObjectID, topic or hostname exists;
- no destructive migration runs by default;
- implementation review maps every persistent object to one owner.

## 7. G5 - Deterministic Offline Verification

### Pure Test Scope

- entity alias normalization;
- complete and incomplete capability contracts;
- unique ID, Ident, object ID and topic derivation;
- Home Assistant discovery payloads for all supported capability combinations;
- runtime payload construction;
- RGB and Kelvin parsing;
- malformed payload rejection;
- configuration hash stability;
- managed-state reconciliation and exact removal planning;
- command failure result propagation;
- repeated setup without duplicates through the Symcon test stubs.

### Static Verification

- PHP syntax;
- repository linting and coding standards;
- static analysis at the repository-supported level;
- private-data scan;
- deterministic fixture comparison.

### Exit Criteria

- all offline checks pass;
- no test performs network access or live device actions;
- expected Home Assistant discovery fixtures are sanitized and reviewable.

## 8. G6 - Supervised Integration Verification

### Preconditions

- G5 is complete;
- test entities and topics are isolated from unrelated production entities;
- a rollback and retained-topic cleanup procedure exists;
- every device-affecting command is individually supervised.

### Required Scenarios

1. Initial reconciliation and repeated reconciliation.
2. Home Assistant discovery and entity identity stability.
3. Home Assistant on/off command to IP-Symcon.
4. Brightness and color-temperature commands where supported.
5. Direct IP-Symcon state change to Home Assistant.
6. Repeated identical MQTT command.
7. Invalid MQTT payload with no device action.
8. Slow feedback and confirmation timeout.
9. Removed entity and complete owned-resource cleanup.
10. IP-Symcon, MQTT broker and Home Assistant restart behavior.
11. MQTT publish failure and later controlled refresh.

### Exit Criteria

- results are recorded in a sanitized integration report;
- observed behavior matches the documented contracts;
- failures do not report successful execution;
- no unrelated object, topic or device is modified.

## 9. G7 - Reference Implementation Decision

### Decision Options

- **Go:** publish a complete generic reference implementation.
- **Conditional Go:** retain the candidate in the case study while closing
  bounded evidence gaps.
- **No-Go:** retain the learning artifacts but do not publish reusable code.

### Go Criteria

- G1 through G6 are complete;
- the implementation is no longer an RC2 transcription;
- helper usage and deployment assumptions are documented;
- the reference contains complete files and a review checklist;
- known limitations and recovery behavior are explicit;
- external contract links and verification date are recorded;
- private baseline and installation migration remain excluded.

## 10. Deferred Decisions

The following decisions remain outside the initial adoption path:

- conversion to a native IP-Symcon module;
- Home Assistant device discovery instead of single-component discovery;
- availability and heartbeat strategy;
- general MQTT publish retry with backoff;
- additional entity domains and light capabilities;
- promotion of desired/managed-state reconciliation into an architectural
  pattern.

They require separate evidence and must not expand the initial reference scope.
