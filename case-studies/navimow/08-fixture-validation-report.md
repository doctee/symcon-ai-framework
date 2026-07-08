# 08 Fixture Validation Report

**Case study:** Navimow native IP-Symcon module  
**Status:** First REST fixture validation  
**Date:** 2026-07-08  
**Build boundary:** This document validates sanitized fixtures only. No productive PHP code is introduced.

## 1. Purpose

This report records the first validation results from sanitized Navimow REST
fixtures. It compares the fixture evidence against the static structure
discovery and the MVP variable/action contract.

## 2. Fixtures Reviewed

| Fixture | Status | Purpose |
| --- | --- | --- |
| `fixtures/rest/auth-token-success.json` | valid JSON, sanitized | Token response shape. |
| `fixtures/rest/auth-list-success.json` | valid JSON, sanitized | Discovery response shape. |
| `fixtures/rest/vehicle-status-docked.json` | valid JSON, sanitized | Docked status response shape. |
| `fixtures/rest/vehicle-status-mowing.json` | valid JSON, sanitized | Active mowing status response shape. |
| `fixtures/rest/command-dock-already-in-state.json` | valid JSON, sanitized | Dock command response while already docked. |
| `fixtures/rest/auth-invalid-token.json` | valid JSON, sanitized | Invalid-token API error shape. |

Redaction review:

- access token is replaced;
- refresh token is replaced;
- device ID is replaced with `DEVICE_001`;
- request ID is replaced with `REQUEST_001`;
- mower name is replaced with `Navimow Test Mower`;
- no raw JSON fixture contains PHP code, private hostnames, IP addresses or map
  data.

## 3. Confirmed REST Envelope

The first fixtures confirm the success envelope assumed in
`06-structure-discovery-plan.md`:

```text
code
desc
data
data.payload.devices[]
```

`requestId` appears under `data.requestId`, not at the top level.

## 4. Confirmed Token Fields

The token response fixture confirms:

| Field | Type | Notes |
| --- | --- | --- |
| `access_token` | string | Redacted in fixture. |
| `refresh_token` | string | Redacted in fixture. |
| `token_type` | string | Observed value: `Bearer`. |
| `expires_in` | integer | Observed value: `3600`. |

Design impact:

- `TokenExpiresAt` can be calculated from local receipt time plus
  `expires_in`.
- Token refresh should be scheduled before one hour elapses, matching the
  existing design.

## 5. Confirmed Discovery Fields

The discovery fixture confirms:

| Field path | Type | Example after sanitization | Contract impact |
| --- | --- | --- | --- |
| `data.payload.devices[]` | array | one device | Configurator source confirmed. |
| `data.payload.devices[].id` | string | `DEVICE_001` | Device identity confirmed. |
| `data.payload.devices[].name` | string | `Navimow Test Mower` | Display name source confirmed. |
| `data.payload.devices[].model` | string | `X450` | Candidate general information field. |
| `data.payload.devices[].firmware` | string | `004C` | Candidate general information field. |

Design impact:

- `model` and `firmware` can be documented as candidate read-only general
  metadata, but they should not enter the MVP status contract unless needed.
- No serial number appeared in this fixture.

## 6. Confirmed Docked Status Fields

The docked status fixture confirms:

| Field path | Type | Example after sanitization | Contract impact |
| --- | --- | --- | --- |
| `data.payload.devices[]` | array | one device | Status list path confirmed. |
| `data.payload.devices[].id` | string | `DEVICE_001` | Match key confirmed. |
| `data.payload.devices[].capacityRemaining` | array | one entry | Battery source confirmed. |
| `data.payload.devices[].capacityRemaining[].unit` | string | `PERCENTAGE` | Existing derivation rule confirmed. |
| `data.payload.devices[].capacityRemaining[].rawValue` | integer | `81` | Battery value source confirmed. |
| `data.payload.devices[].vehicleState` | string | `isDocked` | `VehicleState` mapping confirmed for docked state. |
| `data.payload.devices[].descriptiveCapacityRemaining` | string | `HIGH` | Candidate non-MVP descriptive field. |

Design impact:

- `BatteryLevel` should be mapped from
  `capacityRemaining[].rawValue` where `unit == "PERCENTAGE"`.
- Direct `battery` was not present in this fixture.
- `VehicleState` value `isDocked` maps to `NAVIMOW.VehicleState` value
  `Docked`.

## 7. Still Open

The first fixtures do not yet answer:

- exact online/connectivity field;
- command success response shape other than `alreadyInState`;
- temporary cloud failure shape;
- MQTT credential response shape;
- MQTT location payload shape.

## 8. Confirmed Command and Auth Error Fields

The Dock command while already docked confirms:

| Field path | Type | Example | Contract impact |
| --- | --- | --- | --- |
| `code` | integer | `1` | Top-level API success can still contain command-level error status. |
| `data.payload.commands[]` | array | one command result | Command results are nested below payload. |
| `data.payload.commands[].devices[]` | array | one device | Device-level command target is echoed. |
| `data.payload.commands[].devices[].cmdNum` | null | `null` | Present but not useful for MVP. |
| `data.payload.commands[].status` | string | `ERROR` | Must not automatically map to command failure. |
| `data.payload.commands[].errorCode` | string | `alreadyInState` | Maps to `Already In State`. |

The invalid-token fixture confirms:

| Field path | Type | Example | Contract impact |
| --- | --- | --- | --- |
| `code` | integer | `4005` | Auth failure can be represented as API code despite HTTP 200. |
| `desc` | string | `CODE_OAUTH_INFO_ILLEGAL` | Can map to auth error diagnostics. |
| `data` | null | `null` | No payload on invalid token. |

## 9. Confirmed Active Status Fields

The active status fixture confirms that the REST status payload keeps the same
shape while mowing:

| Field path | Type | Example after sanitization | Contract impact |
| --- | --- | --- | --- |
| `data.payload.devices[].capacityRemaining[].unit` | string | `PERCENTAGE` | Same battery source as docked status. |
| `data.payload.devices[].capacityRemaining[].rawValue` | integer | `92` | Same numeric battery value source. |
| `data.payload.devices[].vehicleState` | string | `isRunning` | Maps to `NAVIMOW.VehicleState` value `Running`. |
| `data.payload.devices[].descriptiveCapacityRemaining` | string | `HIGH` | Same candidate non-MVP descriptive field. |

No dedicated online/connectivity field is present in this fixture either.

## 10. Contract Updates Applied

Applied update to `03-variable-and-action-contract.md`:

- make `capacityRemaining[].rawValue` with `unit == "PERCENTAGE"` the primary
  documented `BatteryLevel` source for the MVP;
- keep direct `battery` as an optional fallback only;
- map `errorCode == "alreadyInState"` to `Already In State`, even when the
  command-level status is `ERROR`;
- treat API code `4005` / `CODE_OAUTH_INFO_ILLEGAL` as an authentication error.

Still open:

- keep `Online` open or derive it conservatively from known failure/offline
  state until a fixture proves a dedicated field.

## 11. Fixture Gate Status

REST MVP fixture gate is partially satisfied:

| Requirement | Status |
| --- | --- |
| Discovery fixture exists | satisfied |
| At least one status fixture exists | satisfied; docked and running fixtures exist |
| State field understood | satisfied for `isDocked` and `isRunning`; other states remain defensive |
| Battery field understood | satisfied for `capacityRemaining` percentage |
| Online field understood | open |
| Successful command response exists | partially satisfied by `alreadyInState` command response |
| Failure or auth-error fixture exists | satisfied for invalid-token API error |
| Fixtures pass redaction checklist | satisfied for current fixtures |

The remaining useful capture is a true successful command transition where the
mower state changes, but the REST read-only MVP can proceed defensively without
it.

## 12. Next Step

Create a REST MVP readiness review and decide whether the missing true command
success fixture is a blocker or an accepted residual risk.
