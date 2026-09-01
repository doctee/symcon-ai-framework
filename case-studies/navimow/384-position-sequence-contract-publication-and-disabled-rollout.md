# SAEF Step 384: Position Sequence Contract Publication and Disabled Rollout

## Status

Complete. The position-session contract correction is published and installed
in IP-Symcon with native MQTT disabled and credential-free. A receive-only
functional activation remains a separate gate.

## Published Revisions

| Artifact | Revision |
|---|---|
| SAEF merge | `e0634bd22f4091dd369fffe48948055bed7584ff` |
| Standalone module merge | `81c9ca07329ba6d0f7d73874e139b5137d2652b5` |
| Module fileset | `4f73a968a261f38cfb53fb885771a7ec8c6c94adcab6603dd7c177d950cbc8ad` |

SAEF pull request 92 and standalone module pull request 7 were merged through
their established review paths. The generic manifest publisher independently
verified all 42 standalone files against the SAEF distribution before the
standalone merge.

## Metadata Gate

The established offline fallback for the official IP-Symcon Module Validator
validated 13 metadata inputs with the pinned official schemas and AJV 6.10.2:

- one library descriptor;
- four module descriptors;
- four configuration forms;
- four locale descriptors.

All inputs passed with empty error arrays. No metadata file changed as part of
the position-session correction.

## Disabled Live Preflight

The bounded structured Symcon MCP probe verified before mutation:

- installed standalone commit `3926bbdf` on clean and valid `main`;
- Account, Configurator, Device and Receiver status `102`;
- MQTT Client and WebSocket Client status `104`;
- MQTT and position diagnostics disabled;
- empty MQTT username, password and WebSocket headers;
- Local Map and zone statistics configured;
- 29 existing variable contracts and their archive settings fingerprinted.

No ObjectIDs, variable values, coordinates or credentials were retained in
the public evidence.

## Controlled Update

Exactly one supported `MC_UpdateModule()` call updated the standalone module
to `81c9ca07`. The structured execution result was unambiguous:

- transport error: none;
- PHP execution error: none;
- output truncation: false;
- update result: true;
- repository branch: `main`;
- repository clean and valid: true.

No `MC_ReloadModule()`, instance `ApplyChanges()`, restart, OAuth action, MQTT
credential request, MQTT activation or mower command was executed.

## Immediate and Delayed Verification

Immediate and approximately 60-second delayed read-only probes both passed:

- exact target commit observed;
- all four Navimow module instances remained status `102`;
- MQTT and WebSocket remained status `104`;
- transport remained disabled and credential-free;
- all four reusable instance-configuration hashes remained unchanged;
- all 29 variable metadata contracts remained unchanged;
- all archive logging and aggregation settings remained unchanged;
- Local Map and zone statistics remained available.

`StatisticsState` remained `No Data`, which is the expected disabled-transport
state. This rollout proves installation compatibility, not fresh MQTT path
processing.

## Architecture Decisions

### AD-NAV-384-01: Separate installation proof from transport proof

The disabled rollout proves module and persistence compatibility without
opening a network session. Fresh corrected path segmentation requires a later
bounded receive-only activation and cannot be inferred from a successful
module update.

### AD-NAV-384-02: Preserve user logging by fingerprint

Variable identities and archive configuration are compared before and after
the update. This protects user-enabled logging, including battery and state
history, from silent module-update regressions.

## Next Gate

Run one bounded receive-only MQTT and position observation during a supervised
mowing interval. Success requires at least two fresh position samples in one
transport session, a non-invalid statistics state, preserved path continuity
and mandatory credential cleanup afterward. REST remains authoritative and no
MQTT device command is permitted.
