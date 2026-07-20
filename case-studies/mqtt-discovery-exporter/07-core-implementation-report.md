# 07 Pure Core Implementation Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** G4 implementation sequence step 2 complete
**Date:** 2026-07-15
**Deployment status:** Repository candidate only; no live installation changes

## 1. Outcome

The deterministic exporter core is implemented in
`candidate/MqttDiscoveryExporterCore.php`. It contains no IP-Symcon calls,
MQTT effects, SAEF helper copies or private installation data.

The implementation covers:

- strict configuration normalization;
- complete state/action capability pairs;
- stable entity identity and topic derivation;
- Home Assistant discovery payload construction;
- strict command parsing for power, brightness, RGB and Kelvin;
- runtime payload construction from supplied observed values;
- canonical payload hashing;
- exact managed-entry removal planning.

## 2. Boundary Decisions

### Strict raw input

Configuration values are accepted only with their declared scalar type.
String integers, integer booleans and partial capability pairs are rejected.
Disabled devices and entities require an explicit Boolean `export` value.

### Capability availability

The command parser accepts a command type only when the normalized entity
actually exposes that capability. It therefore cannot turn an unconfigured
brightness, RGB or color-temperature command into a device action.

### Observed state

Runtime payload construction consumes values supplied by the future runtime
adapter. It does not receive or echo the MQTT command payload. This preserves
the FR-05 boundary: published state must originate from the configured Symcon
state variable.

### Deterministic identity

Discovery identities use UUID v5 with one configured namespace and a stable
location/entity name. Associative payloads are recursively key-sorted before
hashing, while list order remains significant.

### Exact cleanup planning

The core only compares previous registry keys with desired entity keys. It
does not inspect Symcon objects or search topics by prefix. Ownership checks
and deletion effects remain a runtime responsibility.

## 3. Deterministic Test Coverage

The repository test `tests/mqtt-discovery-exporter/core.php` verifies eight
scenario groups:

1. combined aliases and separate state/action normalization;
2. incomplete and unsupported capability rejection;
3. duplicate topic and scalar-coercion rejection;
4. deterministic discovery identities and topics;
5. valid power, brightness, RGB and Kelvin parsing;
6. malformed and unavailable command rejection;
7. runtime payloads from strictly typed observed values;
8. exact removals and canonical hash stability.

The test is included in Composer `check`, syntax linting, PHPStan and PHPCS.

## 4. Verification Result

`composer check` passes completely, including:

- PHP syntax checks;
- generated bundle consistency;
- EnsureVariable bundle tests;
- six EnsureEvent tests;
- eight exporter core scenario groups;
- PHPStan for repository and generated bundle;
- PHPCS.

No test accesses a network, broker, private file or live IP-Symcon instance.

## 5. Gate Decision

G4 remains open. Sequence step 2 is complete and the next authorized step is
diagnostics initialization in `MqttDiscoveryExporterRuntime.php` by composing
the existing Registry, Statistics, ErrorRingBuffer and ConfigurationHash
helpers.

Reconcile, dispatch, cleanup and live deployment are not enabled by this
change.
