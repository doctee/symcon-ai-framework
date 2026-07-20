# 01 Requirements and Evidence Boundary

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** Initial analysis
**Date:** 2026-07-15
**Source under analysis:** Sanitized handover of private HA Exporter V4.1-RC2
**Implementation boundary:** Analysis only; no productive PHP code is introduced

## 1. Objective

Create a reusable and reviewable IP-Symcon integration that exports selected
IP-Symcon variables to Home Assistant through MQTT Discovery.

The integration must support two directions:

1. Home Assistant command -> MQTT -> IP-Symcon variable action -> physical or
   virtual device.
2. IP-Symcon state change -> MQTT runtime state -> Home Assistant entity.

IP-Symcon remains the owner of device actions and the authoritative state
source. Home Assistant is an additional integration and presentation layer.

## 2. System Boundary

```text
Additional UI
    |
    v
Home Assistant
    |
    | MQTT Discovery, commands and retained runtime state
    v
IP-Symcon MQTT Server
    |
    v
Exporter-owned command adapters and events
    |
    | RequestAction()
    v
Configured IP-Symcon action variables
    |
    v
Device-owning IP-Symcon integration
```

The exporter does not directly communicate with Matter, Zigbee, Homematic,
Tasmota or another physical device protocol. Those responsibilities remain
with the configured IP-Symcon instances and variable actions.

## 3. Functional Requirements

### FR-01 Entity classes

The initial release scope supports Home Assistant MQTT `light` and `switch`
entities only.

### FR-02 Light capabilities

The initial light scope may expose:

- on/off;
- brightness from 0 to 100;
- RGB color;
- color temperature in Kelvin.

Every optional capability must be enabled by explicit configuration. Partial
state/action pairs are invalid.

### FR-03 Separate state and action variables

An entity may use one IP-Symcon variable for both state and action or separate
variables for those responsibilities.

The normalized contract must always contain a complete pair:

- one readable state variable;
- one controllable action variable.

### FR-04 Device actions

Incoming commands must use `RequestAction()` on the configured action variable.
The exporter must never use `SetValue()` to control device- or instance-owned
variables.

### FR-05 State publication

The exporter must publish the observed IP-Symcon state, not merely echo an
incoming command payload.

### FR-06 Event semantics

- MQTT command variables use an update trigger because an identical command is
  valid more than once.
- IP-Symcon state variables use a change trigger to avoid unnecessary state
  synchronization runs.

Automatically created events must have deterministic Idents, explicit
ownership and the IP-Symcon 6.0+ Run Automation action binding.

### FR-07 MQTT Discovery

Discovery payloads must use stable unique identifiers, deterministic topics and
an explicit device context. Discovery messages are retained.

The initial implementation may use single-component discovery. Device
discovery remains an evaluated alternative for devices with multiple entities.

### FR-08 Runtime state

Runtime state messages are retained so Home Assistant can reconstruct state
after restart or resubscription. A controlled force-refresh path must exist.

### FR-09 Desired and managed state

Configuration describes desired entities. Small owned registry metadata records
the managed entity identity and the data required for safe reconciliation.

Removing an entity from configuration must remove all exporter-owned command
objects, events, discovery messages and runtime topics belonging to that entity.

### FR-10 Validation before external effects

The complete desired configuration must be validated before MQTT publication,
device commands or cleanup begins.

Validation includes:

- configuration shape and required fields;
- unique device, entity and topic identities;
- existing variable IDs and compatible types;
- complete state/action pairs;
- action availability on every action variable;
- safe MQTT topic paths;
- capability-specific ranges and units.

### FR-11 Input validation

MQTT payloads are untrusted external input. Invalid booleans, numbers, RGB
values and color temperatures must be rejected explicitly. PHP scalar casts
must not silently turn malformed input into a valid command.

### FR-12 Command outcome

A failed command must remain observable as a failed exporter execution or an
explicit failed command result. A command failure must not be followed by a
successful run status for the same execution.

### FR-13 State confirmation

Command confirmation must be bounded and observable. The exporter should wait
for the relevant state variable to reach or update toward the expected value,
subject to a configured timeout. A fixed sleep alone is not confirmation.

### FR-14 Diagnostics

Runtime diagnostics must compose the existing SAEF responsibilities:

- ConfigurationHash for deterministic configuration fingerprints;
- Registry for small reconciliation metadata;
- Statistics for counters and timestamps;
- ErrorRingBuffer for bounded recent failure context.

Normal runtime logging must avoid one informational log entry for every
unchanged state or successful routine execution.

### FR-15 Migration and cleanup

Generic reconciliation may remove only objects and topics whose ownership is
proved by the current exporter registry and deterministic identity contract.

Installation-specific legacy cleanup is a separate, private and explicitly
activated migration operation. It must not be enabled by default in the
generic exporter.

## 4. Non-Functional Requirements

### NFR-01 Idempotency

Repeated setup or reconciliation with the same configuration must not create
duplicates or alter unrelated objects.

### NFR-02 Helper-first implementation

Existing SAEF helpers must be composed before local infrastructure logic is
introduced. A missing recurring event-creation capability should be added to
the canonical event helper rather than copied into this implementation.

### NFR-03 Explicit ownership

Every created category, variable, instance and event must be below or clearly
associated with the owning exporter script and addressable through a stable
Ident.

### NFR-04 Bounded operation

Waits, semaphores, error history and retries must have explicit limits.

### NFR-05 Restart behavior

The exporter must reconstruct its managed state after IP-Symcon, MQTT broker or
Home Assistant restart without relying on hidden in-memory state.

### NFR-06 Testability

Payload construction, normalization, validation, topic derivation and cleanup
planning must be testable without a live private IP-Symcon installation.

## 5. Private Data Boundary

The following values are installation-specific configuration and must not be
committed to public artifacts:

- IP-Symcon ObjectIDs;
- site, device and room names;
- private MQTT base topics and entity topic slugs;
- hostnames, IP addresses and configuration URLs;
- legacy topic and object names from the private installation.

Public examples use neutral placeholders. Real values belong in `private/` or
another ignored local configuration file.

## 6. Initial Out of Scope

- Home Assistant entity domains other than `light` and `switch`;
- availability and heartbeat design;
- transitions, effects, scenes, RGBW/RGBWW and white channels;
- a persistent general-purpose retry queue;
- conversion into a native IP-Symcon module;
- automated broker-wide cleanup;
- automatic control of a live device during offline tests.

## 7. Evidence Available

The handover reports successful private V4.1-RC2 testing for:

- Home Assistant or Apple Home commands reaching IP-Symcon;
- direct IP-Symcon changes reaching Home Assistant;
- on/off, brightness and color-temperature behavior in the original setup;
- retained discovery and runtime publication;
- the need for explicit MQTT light color-mode state.

## 8. Evidence Gaps

The case study does not yet contain independently repeatable evidence for:

- the exact V4.1-RC2 source deployed in the live test;
- syntax and static analysis of extracted standalone PHP files;
- invalid MQTT payload behavior;
- action-variable validation;
- slow or missing device feedback;
- restart and Home Assistant birth-message behavior;
- entity removal and complete owned-object cleanup;
- simultaneous or rapidly repeated state and command events;
- publisher failure and recovery;
- long-running operational behavior;
- final V4.1.0 release equivalence.

These gaps are release gates, not assumptions to fill with generated behavior.

## 9. External Contracts

The design is based on the official contracts below. They must be rechecked at
implementation and release time because Home Assistant behavior evolves.

- Home Assistant MQTT Discovery: <https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery>
- Home Assistant MQTT Light: <https://www.home-assistant.io/integrations/light.mqtt>
- IP-Symcon `IPS_SetEventTrigger()`: <https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ereignisverwaltung/ips-seteventtrigger/>
- IP-Symcon `IPS_SetEventAction()`: <https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ereignisverwaltung/ips-seteventaction/>
- IP-Symcon variable access and `RequestAction()`: <https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenzugriff/>
