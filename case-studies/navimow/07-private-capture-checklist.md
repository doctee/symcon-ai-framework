# 07 Private Capture Checklist

**Case study:** Navimow native IP-Symcon module  
**Status:** Private capture checklist draft  
**Date:** 2026-07-08  
**Build boundary:** This document defines a manual/private capture workflow only. It contains no credentials, no real payloads and no productive PHP code.

## 1. Purpose

This checklist describes how a human operator can collect the minimum real
Navimow API evidence needed for the REST MVP without committing private data to
the repository.

It is intended as the operational frame for the user: Codex defines what to
capture and how to sanitize it; the user performs the real API interaction in a
private environment and returns only sanitized payloads.

## 2. Meaning of Manual Capture

"Manual capture" means:

1. The user logs in to Navimow in a private browser or tool.
2. The user calls a small set of Navimow API endpoints in a private
   environment.
3. Raw responses are saved only outside this repository.
4. The user removes or replaces private values.
5. Only sanitized JSON payloads are shared back for inclusion as fixtures.

Codex should not receive raw tokens, authorization codes, real device IDs,
account IDs, MQTT credentials, exact garden coordinates or map data.

## 3. When to Capture

Capture should happen after:

- `06-structure-discovery-plan.md` is reviewed;
- `fixtures/README.md` is accepted as the fixture policy;
- the user is ready to handle secrets locally;
- physical mower command tests can be supervised.

Capture should happen before:

- productive module PHP files are created;
- public variable mapping is treated as final;
- command behavior is considered verified.

## 4. Private Working Location

Raw captures must live outside the public repository.

Acceptable private locations:

- a local temporary directory outside this repository;
- a private notes file that will not be committed;
- a password manager secure note for transient token values;
- a private ignored path if explicitly configured.

Not acceptable:

- `case-studies/navimow/fixtures/` before sanitization;
- any committed repository path;
- chat messages containing raw tokens or real device IDs;
- screenshots that expose tokens, private names or coordinates.

## 5. Minimum Capture Set

The minimum REST MVP capture set is:

| Output fixture | Capture purpose | Required before MVP code |
| --- | --- | --- |
| `rest/auth-list-success.json` | Device discovery shape. | yes |
| `rest/vehicle-status-docked.json` | Baseline status mapping. | yes |
| `rest/command-start-success.json` or equivalent safe command success | Command response shape. | yes |
| `rest/auth-expired-token.json` or `rest/api-temporary-failure.json` | Failure behavior. | yes |

Strongly recommended:

| Output fixture | Capture purpose |
| --- | --- |
| `rest/command-dock-already-in-state.json` | Verify `alreadyInState` behavior. |
| `rest/vehicle-status-mowing.json` | Verify active state and battery fields while mowing. |
| `rest/auth-refresh-failure.json` | Verify reauthentication path. |

Phase 2 only:

| Output fixture | Capture purpose |
| --- | --- |
| `mqtt/mqtt-user-info.json` | MQTT credential response shape after redaction. |
| `mqtt/location-sample.json` | Location payload shape after coordinate generalization. |

## 6. Capture Sequence

### Step 1: Login and Token Exchange

Goal:

- obtain an access token and refresh token privately.

Capture:

- do not share raw token response;
- optionally create a sanitized `auth-token-success.json` only if token schema
  becomes important later.

Sanitize:

- replace `access_token` with `REDACTED_ACCESS_TOKEN`;
- replace `refresh_token` with `REDACTED_REFRESH_TOKEN`;
- preserve `expires_in` if present.

### Step 2: Discovery

Request:

```text
GET /openapi/smarthome/authList
```

Goal:

- determine discovery response shape and available device metadata.

Sanitized output:

```text
case-studies/navimow/fixtures/rest/auth-list-success.json
```

Redact:

- device IDs;
- account IDs;
- serial numbers if present;
- private mower names;
- user identifiers.

Preserve:

- response envelope;
- `data.payload.devices` nesting;
- model/capability fields if non-private;
- JSON value types.

### Step 3: Docked Status

Request:

```text
POST /openapi/smarthome/getVehicleStatus
```

Request shape:

```json
{
  "devices": [
    { "id": "DEVICE_001" }
  ]
}
```

Goal:

- verify `vehicleState`, battery and online fields for a safe baseline.

Sanitized output:

```text
case-studies/navimow/fixtures/rest/vehicle-status-docked.json
```

Preserve:

- full response envelope;
- device status object shape;
- `vehicleState`;
- battery-related fields such as `battery` or `capacityRemaining`;
- online/connectivity field if present.

Redact:

- real device IDs;
- location fields if they reveal garden layout;
- map/base64 data;
- private names.

### Step 4: Safe Command Success

Goal:

- capture one successful `sendCommands` response.

Preferred low-risk options:

- `Dock` if the mower is already docked and the API returns success or
  `alreadyInState`;
- `Refresh` is local only and does not test `sendCommands`;
- avoid `Start` unless the area is physically safe and supervised.

Request:

```text
POST /openapi/smarthome/sendCommands
```

Sanitized output:

```text
case-studies/navimow/fixtures/rest/command-start-success.json
```

or, if a safer command is used:

```text
case-studies/navimow/fixtures/rest/command-dock-success.json
```

Preserve:

- response envelope;
- command result fields;
- per-device result nesting if present;
- result code or message.

Redact:

- device IDs;
- request IDs if traceable;
- account identifiers.

### Step 5: `alreadyInState` Response

Goal:

- verify whether the API returns `alreadyInState` as success, warning or error.

Suggested attempt:

- call `Dock` while the mower is already docked.

Sanitized output:

```text
case-studies/navimow/fixtures/rest/command-dock-already-in-state.json
```

If the API does not return `alreadyInState`, document the observed response.

### Step 6: Failure or Auth Error

Goal:

- capture a safe failure shape without damaging token state.

Options:

- call with an intentionally invalid placeholder token in a private tool;
- wait until token expiry if practical;
- use a harmless malformed request that does not include private payload.

Sanitized output:

```text
case-studies/navimow/fixtures/rest/auth-expired-token.json
```

or:

```text
case-studies/navimow/fixtures/rest/api-temporary-failure.json
```

Preserve:

- HTTP status if available;
- response envelope;
- error code;
- sanitized error message.

Do not preserve:

- raw token;
- full request headers;
- trace identifiers if private.

### Step 7: Optional Active Status

Goal:

- verify active mower state and active-mode fields.

Only perform this when the mower can be supervised.

Sanitized output:

```text
case-studies/navimow/fixtures/rest/vehicle-status-mowing.json
```

Redact or generalize:

- coordinates;
- map data;
- precise timestamps if identifying;
- private local context.

## 7. Physical Safety Rules

Command captures can move a real mower.

Rules:

- do not run `Start` unless the mowing area is safe;
- supervise the mower during command tests;
- prefer `Dock` while already docked for low-risk command evidence;
- do not rely on repeated commands for testing;
- stop command testing if the mower state is unexpected;
- keep people, pets and obstacles away from the mower area during active tests.

## 8. Sanitization Checklist

Before returning a payload to Codex or committing it:

1. Replace real device IDs with `DEVICE_001`, `DEVICE_002`, etc.
2. Replace account IDs with `ACCOUNT_001`.
3. Replace request IDs with `REQUEST_001` or remove them.
4. Replace serial numbers with `SERIAL_001`.
5. Replace private mower names with `Navimow Test Mower`.
6. Replace user/email values with `USER_001`.
7. Replace access tokens with `REDACTED_ACCESS_TOKEN`.
8. Replace refresh tokens with `REDACTED_REFRESH_TOKEN`.
9. Replace authorization codes with `REDACTED_AUTHORIZATION_CODE`.
10. Replace MQTT passwords with `REDACTED_MQTT_PASSWORD`.
11. Remove exact coordinates, maps and Base64 images.
12. Remove private hostnames, IP addresses and local paths.
13. Validate that JSON remains valid.
14. Confirm value types are preserved.

## 9. What to Return to Codex

Return only sanitized JSON payloads or paste them as sanitized code blocks.

Good:

```json
{
  "code": 1,
  "data": {
    "payload": {
      "devices": [
        {
          "id": "DEVICE_001",
          "name": "Navimow Test Mower"
        }
      ]
    }
  }
}
```

Not acceptable:

- raw token response;
- real device IDs;
- screenshots with visible auth data;
- payloads containing map images or coordinates;
- request headers containing bearer tokens.

## 10. How Codex Will Use Returned Payloads

After sanitized payloads are provided, Codex should:

1. validate that they are JSON;
2. run a redaction review;
3. save them under `case-studies/navimow/fixtures/rest/`;
4. update fixture notes if needed;
5. compare them against `03-variable-and-action-contract.md`;
6. update open questions and contract decisions;
7. only then consider the REST MVP fixture gate.

## 11. If Captures Are Not Available

If real captures cannot be collected, implementation can still proceed only as
a clearly marked prototype or spike.

Consequences:

- payload parsing must remain defensive;
- `Online` should remain open or derived conservatively;
- command response handling must not assume a complete schema;
- public module contract should remain draft;
- MVP completion cannot claim fixture-backed validation.

## 12. Next Step

The next practical step is for the user to perform Step 2 and Step 3 privately:

1. capture and sanitize `authList`;
2. capture and sanitize `getVehicleStatus` while docked;
3. return only the sanitized JSON payloads.

Those two fixtures are enough to start validating discovery and read-only
status mapping before any command test is performed.
