# 20 Discovery and Status Symcon Test Report

**Case study:** Navimow native IP-Symcon module  
**Status:** Read-only discovery and status gate passed  
**Date:** 2026-07-09  
**Build boundary:** This report covers live account discovery, configurator
behavior and read-only mower status. It does not approve mower commands,
MQTT/WSS, maps or location.

## 1. Purpose

This report verifies the implementation from
`19-discovery-and-readonly-status-implementation.md` against the authenticated
IP-Symcon runtime and the real Navimow cloud account.

The test validates:

- account-owned discovery;
- dynamic configurator output;
- one device instance;
- explicit account/device connection;
- manual read-only status;
- one account polling trigger;
- mapped public state without token access.

## 2. Test Environment

| Item | Value |
| --- | --- |
| IP-Symcon version | `9.0` |
| Runtime host | Separate Win11 system |
| Module source | `https://github.com/doctee/symcon-navimow.git` |
| Module branch | `main` |
| Distribution commit | `49fca9b34856f874646b0b5ceea2ce71800f3fb0` |
| Authentication | Existing account from passed OAuth gate |
| MCP role | Sanitized assertions and PASS/FAIL read-back |

No ObjectID, device ID, token, account name or private hostname is recorded.

## 3. Preconditions

Confirmed before the test:

- all official Symcon metadata schemas passed;
- public module distribution was updated;
- exactly one authenticated Navimow Account existed;
- account state was `Connected`;
- commands and MQTT/WSS remained unimplemented.

## 4. Result Channel

The test did not rely on the generic MCP execution acknowledgement.

It used the established explicit result pattern:

1. create a temporary Symcon verification script;
2. execute assertions inside `try/catch`;
3. store only PASS or a bounded sanitized error in the script name;
4. read the script object through MCP;
5. delete the verification script.

Read-back:

```text
Navimow Read Only Verification PASS D1 S2 B100 O1
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `D1` | one discovered device |
| `S2` | `VehicleState=Docked` |
| `B100` | `BatteryLevel=100` |
| `O1` | `Online=true` from fresh valid REST status |

## 5. Discovery Test

The test opened the dynamic configurator form. This triggered:

```text
GET /openapi/smarthome/authList
```

Assertions:

| Assertion | Result |
| --- | --- |
| Account remained authenticated | passed |
| Dynamic form returned valid JSON | passed |
| Native `Configurator` element exists | passed |
| Discovery returned at least one valid entry | passed |
| Observed device count | `1` |
| Device ID was not emitted in test output | passed |

The configurator received only sanitized device metadata:

- name;
- model;
- firmware;
- internal creation configuration.

## 6. Device Instance Test

The first discovered entry was matched to an existing device instance or used
to create one.

Assertions:

| Assertion | Result |
| --- | --- |
| Device configuration contained a non-empty cloud ID | passed internally |
| Device instance exists | passed |
| Device is connected to the authenticated account | passed |
| Debug payload mode remains disabled | passed |
| Device ID was not recorded publicly | passed |

The configured device instance is retained intentionally for continued
read-only operation.

## 7. Manual Status Test

Exactly one explicit device refresh invoked:

```text
POST /openapi/smarthome/getVehicleStatus
```

Assertions:

| Assertion | Result |
| --- | --- |
| Module returned `Status refresh succeeded.` | passed |
| Requested device was present in response | passed |
| `VehicleState` is within contract | passed |
| `BatteryLevel` is within `0..100` | passed |
| `LastStatusUpdate` was set | passed |
| Account remained `Connected` | passed |

Observed public state:

| Variable | Value |
| --- | --- |
| `VehicleState` | `Docked` |
| `BatteryLevel` | `100 %` |
| `Online` | `true` |

## 8. Poll Trigger Test

One bounded account poll trigger was invoked:

```text
NAVAC_PollReadOnlyStatus(...)
```

The account sent a `PollStatus` message to connected child devices. The device
then requested its own status through the account.

Assertions:

| Assertion | Result |
| --- | --- |
| Poll trigger completed | passed |
| `LastStatusUpdate` did not move backwards | passed |
| Account remained `Connected` | passed |
| No retry loop was triggered | passed |

## 9. Safety Review

Confirmed:

- no mower command was sent;
- no command endpoint was called;
- no MQTT/WSS endpoint was called;
- no map or location data was requested;
- token values were not read through MCP;
- device ID was kept inside runtime configuration;
- raw cloud payload was not persisted;
- debug payload mode remained disabled;
- test output contained only curated public state.

## 10. Cleanup and Retained State

Removed:

- temporary read-only verification script.

Retained intentionally:

- installed public Navimow library;
- authenticated Navimow Account instance;
- Navimow Configurator instance;
- one configured Navimow Device instance;
- module-owned variables, profiles, timers and internal token state.

## 11. Gate Decision

**Decision:** Read-only discovery and status gate passed.

WP16.4 and WP16.5 are accepted because:

- live discovery succeeded;
- the configurator produced valid native creation data;
- one device instance was connected correctly;
- manual and account-triggered status reads succeeded;
- mapped state, battery and freshness followed the public contract;
- no private authentication material crossed the module boundary;
- no command or MQTT behavior occurred.

## 12. Residual Risks

| Risk | Current treatment |
| --- | --- |
| `Online` has no dedicated fixture-backed field | remains REST freshness |
| Only one real mower was tested | multi-device routing is covered locally, not live |
| Unknown vehicle states may occur | map to `Unknown` |
| Cloud rate limits remain unknown | 300-second default, no immediate retries |
| Full service restart persistence | still not directly tested |
| Commands remain only partially fixture-backed | commands stay blocked |

## 13. Recommendation and Next Step

The REST read-only MVP foundation is now operational:

- authentication;
- refresh;
- discovery;
- configurator;
- status;
- polling.

Before implementing mower commands, perform a focused command-readiness review
against the current fixtures and the established status-verification contract.

Recommended next SAEF artifact:

```text
case-studies/navimow/21-command-implementation-readiness.md
```

MQTT/WSS remains a separate later phase.
