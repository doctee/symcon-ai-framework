# 25 Supervised Light Client Preflight Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Read-only physical-light and client-transport preflight
**Result:** DEVICE PASS; LIVE RUNTIME UPDATE REQUIRED
**Date:** 2026-07-16
**Live-system impact:** Temporary diagnostic script only; no MQTT publication or device command

## 1. Scope

The selected supervised tunable-white light and the dedicated site MQTT Client
were inspected before creating command adapters, events, retained messages or a
Home Assistant discovery entity.

The private mapping remains outside this public report. No hostname, topic,
ObjectID, device address, credential or site name is included here.

## 2. Device Contract Result

The pilot exposes exactly three capabilities:

- Boolean power;
- integer brightness from 0 to 100;
- integer color temperature in Kelvin.

All three variables have an explicit action and share the same reviewed control
script. The underlying Zigbee2MQTT device reports a color-temperature range of
250 to 454 mired. The pilot therefore uses the corresponding practical Kelvin
range of 2200 to 4000 instead of the much wider generic Symcon profile range.

The observed power, brightness and color-temperature values were type-correct
and within the selected ranges. No `RequestAction()` call was made.

## 3. MQTT Transport Result

The dedicated MQTT Client and its Client Socket were active and technically
connected. Their module types match the client-transport contract implemented
by the current repository candidate. A bidirectional broker round trip had
already passed before this preflight.

## 4. Live Runtime Drift

The live exporter class rejected the client configuration with the legacy
validation error that `mqtt.serverID` is required. This proves that the running
IP-Symcon process still contains the earlier server-only exporter class.

The repository candidate already accepts the intended contract:

```php
'mqtt' => [
    'transport' => 'client',
    'gatewayID' => $siteMqttClientID,
    'baseTopic' => 'saef',
    'discoveryPrefix' => 'homeassistant',
],
```

The generated fileset is current. Core, runtime, reconcile, execution,
dispatch, cleanup and fileset tests pass with the client transport.

## 5. Safety Decision

No fallback to the old MQTT Server contract is permitted. Doing so would
reverse the approved site-broker architecture and could couple the pilot to an
unrelated broker path.

No reconcile preparation or execution was attempted. The temporary diagnostic
script was deleted after read-back.

## 6. Next Gate

The next gate is a controlled replacement of the active exporter fileset,
followed by a clean IP-Symcon restart and runtime verification that the loaded
core accepts `transport = client` with `gatewayID`.

Only after that verification may the private single-light configuration enter
reconcile preparation. Retained discovery publication and each physical device
command remain separate authorization points.
