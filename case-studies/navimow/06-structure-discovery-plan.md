# 06 Structure Discovery Plan

**Case study:** Navimow native IP-Symcon module  
**Status:** Static structure discovery draft  
**Date:** 2026-07-08  
**Build boundary:** This document contains reverse-engineering analysis and discovery planning only. No productive PHP code or real payload fixture is introduced.

## 1. Purpose

This document defines how the Navimow API payload structure can be discovered
without currently having sanitized real-device fixtures.

It also records a static field and payload matrix derived from
`TA2k/ioBroker.navimow` `main.js`. The matrix is not an official API
specification. It is an engineering hypothesis that must be validated by
sanitized fixtures before productive module implementation.

## 2. When to Test the Real API

The real API should be tested after static structure discovery and before
productive PHP module files are created.

The recommended point is:

1. Finish static matrix from `main.js`.
2. Mark known, inferred and open fields.
3. Define the minimum private capture checklist.
4. Capture raw API responses outside the repository.
5. Sanitize responses into fixtures.
6. Validate `03-variable-and-action-contract.md` against those fixtures.
7. Start module PHP implementation only after the REST MVP fixture gate is met
   or explicitly waived.

### SAEF-Entscheidung AD-NAV-015: Real API capture is a pre-code gate

**Entscheidung:** Real API testing is required before the REST MVP becomes
productive module code, but not before static structure discovery is complete.

**Rationale:** Testing too early creates ad-hoc captures without a clear
redaction target. Testing too late risks hardcoding unverified payload
assumptions into public variables and actions.

**Consequence:** This document and `05-fixture-plan.md` are the immediate
preparation for real API testing.

## 3. Source Basis

Static discovery is based on:

- `https://github.com/TA2k/ioBroker.navimow`
- `main.js`
- repository README

Known limitation:

- the repository documents endpoints and adapter state behavior, but does not
  provide complete sanitized REST response fixtures.

## 4. Confidence Levels

The matrix uses these confidence labels:

| Label | Meaning |
| --- | --- |
| Known | Explicitly read, written or documented in the source. |
| Inferred | Implied by code structure, but exact payload shape may vary. |
| Open | Needed for the Symcon module, but not sufficiently proven by static analysis. |

## 5. REST Endpoint Matrix

| Endpoint | Method | Request shape | Response shape used by adapter | Confidence |
| --- | --- | --- | --- | --- |
| `/openapi/oauth/getAccessToken` | `POST` | form data with `grant_type`, `code` or `refresh_token`, `client_id`, `client_secret`, `redirect_uri` for auth-code flow | expects token object with at least `access_token`; may include `refresh_token`, `expires_in` | Known |
| `/openapi/smarthome/authList` | `GET` | bearer auth headers | expects `res.data.code === 1`; reads `res.data.data?.payload?.devices || []` | Known |
| `/openapi/smarthome/getVehicleStatus` | `POST` | JSON `{ "devices": [{ "id": "<deviceId>" }] }` | expects `res.data.code === 1`; reads `res.data.data?.payload?.devices || []` | Known |
| `/openapi/smarthome/sendCommands` | `POST` | JSON command envelope with `commands[].devices[]` and `commands[].execution` | command response is evaluated for API success and `alreadyInState` behavior | Inferred |
| `/openapi/mqtt/userInfo/get/v2` | `GET` | bearer auth headers | expects `res.data.code === 1`; reads `res.data.data` as MQTT info | Known |

## 6. Common REST Envelope

Observed source behavior implies a common success envelope:

```text
res.data.code === 1
res.data.data.payload.devices
```

First sanitized fixtures additionally show `requestId` under:

```text
res.data.data.requestId
```

For failures, the adapter uses:

```text
res.data.desc
JSON.stringify(res.data)
error.response.status
error.response.data
```

The exact error schema remains open.

## 7. Authentication Payload Matrix

| Field | Direction | Used for | Confidence | Notes |
| --- | --- | --- | --- | --- |
| `grant_type` | request | `authorization_code` or `refresh_token` | Known | Form encoded. |
| `code` | request | Authorization code exchange | Known | Must never be committed. |
| `refresh_token` | request/response | Token refresh | Known | Must never be public. |
| `client_id` | request | OAuth client | Known | Observed value: `homeassistant`. |
| `client_secret` | request | OAuth client secret | Known | Public upstream code contains a value; SAEF docs must not rely on exposing secrets. |
| `redirect_uri` | request | Authorization-code flow | Known | Observed value: `http://localhost:1/callback`. |
| `access_token` | response | Bearer auth | Known | Required by adapter. |
| `expires_in` | response | refresh scheduling | Known | Used if present. |

Open questions:

- complete token response schema;
- token lifetime behavior across regions and accounts;
- refresh failure response schema.

## 8. Discovery Payload Matrix

Endpoint:

```text
GET /openapi/smarthome/authList
```

Adapter path:

```text
res.data.data?.payload?.devices || []
```

| Field | Path | Adapter use | Confidence | Symcon relevance |
| --- | --- | --- | --- | --- |
| device list | `data.payload.devices[]` | Iterates devices | Known | Configurator source. |
| device ID | `data.payload.devices[].id` | Required; stored in `deviceArray`; used as object root | Known | Device instance key. |
| device name | `data.payload.devices[].name` | Display name fallback to ID | Known | Instance display name. |
| other device fields | `data.payload.devices[]` | Parsed into `.general` by `json2iob` | Inferred | Field inventory needed before Symcon variables. |

Open questions:

- exact model, firmware and serial fields;
- whether serial number is present and should be treated as private;
- whether discovery exposes capabilities useful for command availability.

## 9. Vehicle Status Payload Matrix

Endpoint:

```text
POST /openapi/smarthome/getVehicleStatus
```

Request body:

```json
{
  "devices": [
    { "id": "DEVICE_001" }
  ]
}
```

Adapter path:

```text
res.data.data?.payload?.devices || []
```

| Field | Path | Adapter use | Confidence | Symcon contract mapping |
| --- | --- | --- | --- | --- |
| device list | `data.payload.devices[]` | Iterates status payloads | Known | Multi-device status update. |
| device ID | `devices[].id` | Preferred ID | Known | Match to `NavimowDevice`. |
| alternate device ID | `devices[].device_id` | Fallback ID | Known | Match to `NavimowDevice`. |
| raw status | entire `deviceData` | Stored in `.status.json` | Known | Optional bounded `RawStatusJson`. |
| `vehicleState` | `devices[].vehicleState` | Used for watchdog and state mapping | Known | `VehicleState`. |
| `battery` | `devices[].battery` | Used if present | Known | `BatteryLevel`. |
| `capacityRemaining` | `devices[].capacityRemaining[]` | Used to derive `battery` when missing | Known | `BatteryLevel` fallback source. |
| capacity unit | `capacityRemaining[].unit` | Prefer `PERCENTAGE` | Known | Validation. |
| capacity raw value | `capacityRemaining[].rawValue` | Parsed as number | Known | Battery value. |
| online/connectivity | unknown | Not clearly identified by source | Open | `Online` requires fixture validation. |
| position/signal fields | status payload | README mentions position and signal | Inferred | Not MVP unless validated. |

Battery derivation rule from source:

1. If `deviceData.battery` is missing and `capacityRemaining` is an array,
   inspect entries.
2. Prefer the first entry where `unit` uppercased equals `PERCENTAGE`.
3. Parse `rawValue` as number.
4. If no percentage entry exists, fall back to the first entry's `rawValue`.
5. Assign derived value to `deviceData.battery`.

Open questions:

- whether `battery` is ever delivered directly;
- whether `capacityRemaining.rawValue` is string or number;
- exact online/offline field;
- whether `vehicleState == Offline` is sufficient for `Online == false`;
- status schema differences between mower models.

## 10. Command Payload Matrix

Endpoint:

```text
POST /openapi/smarthome/sendCommands
```

Inferred request envelope:

```json
{
  "commands": [
    {
      "devices": [
        { "id": "DEVICE_001" }
      ],
      "execution": {
        "command": "action.devices.commands.StartStop",
        "params": {}
      }
    }
  ]
}
```

Command mapping:

| Action | API command | Params | Confidence |
| --- | --- | --- | --- |
| `Start` | `action.devices.commands.StartStop` | `{ "on": true }` | Known |
| `Stop` | `action.devices.commands.StartStop` | `{ "on": false }` | Known |
| `Pause` | `action.devices.commands.PauseUnpause` | `{ "on": false }` | Known |
| `Resume` | `action.devices.commands.PauseUnpause` | `{ "on": true }` | Known |
| `Dock` | `action.devices.commands.Dock` | no params / empty params | Known |

Open questions:

- exact success response envelope;
- exact rejection response envelope;
- whether command response includes per-device result array;
- whether command success always has `code === 1`.

First sanitized command fixture confirms that `alreadyInState` appears as:

```text
code == 1
data.payload.commands[].status == "ERROR"
data.payload.commands[].errorCode == "alreadyInState"
```

The per-command `status == "ERROR"` must therefore be interpreted together
with `errorCode`.

## 11. MQTT Info Payload Matrix

Endpoint:

```text
GET /openapi/mqtt/userInfo/get/v2
```

Adapter path:

```text
res.data.data
```

| Field | Path | Adapter use | Confidence |
| --- | --- | --- | --- |
| `mqttUrl` | `data.mqttUrl` | Preferred WebSocket URL | Known |
| `mqttHost` | `data.mqttHost` | Host fallback; may include scheme | Known |
| `userName` | `data.userName` | MQTT username if present | Known |
| `pwdInfo` | `data.pwdInfo` | MQTT password if present | Known |

Connection behavior:

- prefer `mqttUrl` when present;
- parse `wss` or `ws`;
- include `Authorization: Bearer <access_token>` as WebSocket header;
- use username/password only if both are present;
- fall back to `mqtt://<host>:1883` if no `mqttUrl` exists.

Open questions:

- whether Symcon can provide the required WSS Authorization header via built-in
  MQTT infrastructure;
- MQTT credential lifetime;
- exact failure schema for this endpoint.

## 12. MQTT Topic and Payload Matrix

Subscribed topics per device:

```text
/downlink/vehicle/{deviceId}/realtimeDate/state
/downlink/vehicle/{deviceId}/realtimeDate/event
/downlink/vehicle/{deviceId}/realtimeDate/attributes
/downlink/vehicle/{deviceId}/realtimeDate/location
/downlink/vehicle/{deviceId}/#
```

Topic parser:

```text
downlink/vehicle/{deviceId}/.../{channel}
```

| Topic channel | Adapter target | Behavior | Confidence |
| --- | --- | --- | --- |
| `state` | `{deviceId}.status` | Also writes raw JSON to `.status.json` | Known |
| `event` | `{deviceId}.event` or channel-derived folder | Parsed dynamically | Known |
| `attributes` | `{deviceId}.attributes` | Parsed dynamically | Known |
| `location` | `{deviceId}.location` | Updates location diagnostics, map history and parsed fields | Known |
| wildcard others | channel-derived folder | Parsed dynamically | Inferred |

Payload behavior:

- payload is parsed as JSON;
- arrays are reduced to the last entry for dynamic parsing;
- `location` may arrive as a single object or array;
- `location` points with `postureX` and `postureY` are used for map history;
- if a location point has `mowingPercentage == 0`, map history resets.

## 13. Known MQTT Location Fields

| Field | Source | Adapter use | Confidence | MVP status |
| --- | --- | --- | --- | --- |
| `postureX` | location payload | map and position | Known | Phase 2 |
| `postureY` | location payload | map and position | Known | Phase 2 |
| `postureTheta` | README/documented location | parsed dynamically | Known | Phase 2 |
| `vehicleState` | README/documented location | parsed dynamically | Known | Phase 2 |
| `time` | README/documented location | parsed dynamically | Known | Phase 2 |
| `mowingPercentage` | location payload | map reset and progress | Known | Phase 2 |

## 14. Remote State Mapping from Vehicle State

The adapter maps vehicle state strings to active remote booleans:

| Vehicle state | Active remote |
| --- | --- |
| `isRunning` | `start` |
| `mowing` | `start` |
| `isPaused` | `pause` |
| `paused` | `pause` |
| `isDocking` | `dock` |
| `returning` | `dock` |
| `isDocked` | `dock` |
| `docked` | `dock` |
| `charging` | `dock` |
| `isIdle` | `stop` |
| `isIdel` | `stop` |
| `idle` | `stop` |

For Symcon, this mapping is useful as diagnostic knowledge, but it should not
turn command variables into state mirrors. Commands remain actions.

## 15. Derived Symcon MVP Mapping

| Symcon contract field | Static source basis | Confidence | Needs real fixture |
| --- | --- | --- | --- |
| `VehicleState` | `deviceData.vehicleState` | Known | yes, to verify values |
| `BatteryLevel` | `deviceData.battery` or derived from `capacityRemaining[].rawValue` | Known | yes, to verify schema |
| `Online` | not clearly available; may derive from `vehicleState == Offline` | Open | yes |
| `LastStatusUpdate` | local receipt time after valid status | Design decision | no payload field required |
| `RawStatusJson` | entire `deviceData` | Known | yes, for redaction policy |
| `LastCommand*` diagnostics | command action flow | Design decision | command response fixture needed |

## 16. Minimum Real Capture Set

The smallest useful private capture set is:

1. `authList` success response.
2. `getVehicleStatus` response while docked.
3. one command success response for a supervised safe command.
4. one auth failure or expired-token response.

Optional but valuable:

- `getVehicleStatus` while mowing; captured and validated for `isRunning`;
- `alreadyInState` command response;
- MQTT info response with credentials redacted;
- MQTT `location` sample for phase 2.

## 17. Capture Safety Rules

Real capture must:

- happen outside this repository;
- avoid committing raw responses;
- immediately produce sanitized copies if data is needed for the case study;
- preserve JSON types and nesting;
- remove tokens, authorization codes, device IDs, account IDs, private names,
  MQTT credentials, garden coordinates and map data.

These rules are inherited from `05-fixture-plan.md` and
`fixtures/README.md`.

## 18. Contract Impact

Expected updates after real capture:

- exact payload paths for battery and online state;
- exact error schema for auth and temporary failures;
- exact command response shape;
- decision on whether `BatteryLevel == 0` remains acceptable as unknown;
- decision on whether `Online` should be explicit or derived;
- possible refinement of `NAVIMOW.VehicleState` associations.

## 19. Next Step

The next SAEF step is to create a private capture checklist or conduct the
minimum real capture set, then sanitize the responses into fixtures.

No productive PHP module files should be created until the REST MVP fixture
gate from `05-fixture-plan.md` is satisfied or explicitly waived.
