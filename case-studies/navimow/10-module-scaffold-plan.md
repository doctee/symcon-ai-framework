# 10 Module Scaffold Plan

**Case study:** Navimow native IP-Symcon module  
**Status:** Scaffold plan before module code  
**Date:** 2026-07-08  
**Build boundary:** This document defines the first module scaffold plan only. No productive PHP code is introduced.

## 1. Purpose

This document defines the concrete scaffold for the future REST MVP of a native
IP-Symcon Navimow module.

It is the final planning step before creating real module files. It translates
the readiness decision from `09-rest-mvp-readiness-review.md` into module
folders, instance responsibilities, communication contracts, timer
responsibilities, protected state decisions and fixture-based test targets.

## 2. Scaffold Decision

**Decision:** Create a native IP-Symcon module family with an account parent
instance, a device child instance and an optional configurator.

| Module | MVP scaffold | Reason |
| --- | --- | --- |
| `NavimowAccount` | yes | Owns OAuth2, tokens, REST client, discovery and shared diagnostics. |
| `NavimowDevice` | yes | Owns one mower's variables, actions and status mapping. |
| `NavimowConfigurator` | yes, if low overhead | Creates device instances from discovery results. |
| `NavimowMqttBridge` | no | MQTT/WSS is phase 2 and not fixture-backed. |

### SAEF-Entscheidung AD-NAV-016: REST MVP scaffold uses account-parent and device-child modules

**Entscheidung:** The REST MVP scaffold should use `NavimowAccount` as the
single cloud/account owner and `NavimowDevice` as per-mower child instances.

**Rationale:** Fixtures confirm account-level discovery and per-device status.
OAuth tokens are shared account state, while variables and actions belong to
one mower. Keeping these responsibilities separate avoids hidden token sharing
and prepares for multiple devices and later MQTT routing.

**Consequence:** The implementation must define explicit parent-child messages
instead of letting device instances own tokens directly.

## 3. Intended Repository Placement

The future module implementation should not live inside the case study.

Expected future structure:

```text
modules/
  NavimowAccount/
    module.php
    form.json
    locale.json
  NavimowConfigurator/
    module.php
    form.json
    locale.json
  NavimowDevice/
    module.php
    form.json
    locale.json
library/
  Navimow/
    ApiClient.php
    PayloadMapper.php
    Profiles.php
tests/
  fixtures/
    navimow/
      ...
  Navimow/
    ...
```

This layout is a planning target. The actual top-level module directory should
be confirmed against IP-Symcon module conventions before files are created.

Case-study fixtures may be copied or referenced for tests only after redaction
review. Fixture originals remain documented under:

```text
case-studies/navimow/fixtures/rest/
```

## 4. Module Responsibilities

### `NavimowAccount`

Owns:

- account configuration;
- OAuth authorization-code exchange;
- refresh token handling;
- access token lifetime;
- REST request wrapper;
- discovery via `/openapi/smarthome/authList`;
- status polling orchestration;
- command forwarding to `/openapi/smarthome/sendCommands`;
- account diagnostics.

Public variables:

- `ConnectionState`;
- `ReauthRequired`;
- `TokenExpiresAt`;
- `LastDiscovery`;
- `LastRestSuccess`;
- `RestErrorCount`.

Protected/private state:

- access token;
- refresh token;
- last raw token response if needed only as protected internal data;
- normalized account configuration hash.

### `NavimowConfigurator`

Owns:

- display of discovered devices;
- creation of `NavimowDevice` instances;
- matching by device ID placeholder in tests and real ID in runtime.

Does not own:

- tokens;
- device status;
- actions.

The configurator can be deferred if it slows first scaffold creation, but the
account/device architecture should still allow it.

### `NavimowDevice`

Owns:

- configured `DeviceId`;
- status variables;
- command actions;
- device-level command diagnostics;
- device-level status freshness;
- mapping from REST payload to variables.

Does not own:

- OAuth token values;
- account-wide discovery;
- direct HTTP client credentials.

## 5. Account-Device Communication Contract

The first implementation should use explicit message types between device and
account instances.

Device to account:

| Message | Purpose | Payload |
| --- | --- | --- |
| `GetStatus` | Request status for one device. | `{ "deviceId": "..." }` |
| `SendCommand` | Send command for one device. | `{ "deviceId": "...", "command": "...", "params": {...} }` |
| `GetDiscovery` | Return known devices for configurator/device validation. | `{}` |

Account to device:

| Message | Purpose | Payload |
| --- | --- | --- |
| `StatusResult` | Return parsed or raw status payload for one device. | sanitized runtime payload, not tokens |
| `CommandResult` | Return command response for command diagnostics. | command result envelope |
| `ErrorResult` | Return sanitized API or transport error. | error class, API code, desc |

Rules:

- messages must never include access or refresh tokens;
- device instances must not assemble OAuth headers;
- unknown message types should fail visibly and safely;
- command result messages must distinguish top-level API code from per-command
  status.

## 6. Profile Creation Order

The module should create or ensure profiles before registering variables that
use them.

Profile order:

1. `NAVIMOW.ConnectionState`
2. `NAVIMOW.VehicleState`
3. `NAVIMOW.Command`
4. `NAVIMOW.CommandResult`

Profile definitions must match `03-variable-and-action-contract.md`.

Migration rule:

- profile association changes must be versioned and documented;
- avoid reusing numeric values with different meanings.

## 7. Variable Creation Order

### Account Variables

1. `ConnectionState`
2. `ReauthRequired`
3. `TokenExpiresAt`
4. `LastDiscovery`
5. `LastRestSuccess`
6. `RestErrorCount`

### Device Variables

1. `VehicleState`
2. `Online`
3. `BatteryLevel`
4. `LastStatusUpdate`
5. `LastCommand`
6. `LastCommandAt`
7. `LastCommandResult`
8. `LastCommandError`
9. `RawStatusJson` only when debug payload mode is enabled

Rules:

- all variables use stable Idents;
- status variables are read-only;
- command actions use action semantics, not direct state writes;
- archive defaults follow `03-variable-and-action-contract.md`.

## 8. Protected Token State

The scaffold must treat token values as protected internal state.

Required behavior:

- access token is never exposed as a variable;
- refresh token is never exposed as a variable;
- token values are never logged;
- temporary authorization code is cleared after successful exchange;
- public diagnostics expose only `TokenExpiresAt`, `ReauthRequired` and
  account connection state.

Implementation decision still pending:

- whether token values are stored in IP-Symcon properties, attributes, buffers
  or another module-safe internal mechanism.

Scaffold gate:

- choose one protected state mechanism before writing `NavimowAccount` token
  code.

## 9. Timer Responsibilities

### Account Timers

| Timer | Owner | Purpose |
| --- | --- | --- |
| `PollStatus` | `NavimowAccount` | Periodic status refresh for enabled devices. |
| `RefreshToken` | `NavimowAccount` | Refresh token before expiry. |
| `DiscoveryRefresh` | `NavimowAccount` or configurator | Optional periodic discovery, can be manual in MVP. |

### Device Timers

| Timer | Owner | Purpose |
| --- | --- | --- |
| `VerifyCommand` | `NavimowDevice` or account-mediated | Delayed status check after a command. |

Rules:

- polling must not overlap;
- command verification must not retry commands;
- token refresh must not log token values;
- timers should be disabled when account configuration is invalid.

## 10. REST Client Responsibilities

The first REST client abstraction should support:

- base URL from account configuration;
- request ID creation;
- bearer header injection;
- JSON POST requests;
- form-encoded token exchange;
- API-code evaluation;
- transport error classification;
- sanitized diagnostics.

REST client must not:

- update Symcon variables directly;
- know device variable profiles;
- store raw payloads in public state;
- perform command retries.

## 11. Payload Mapper Responsibilities

`PayloadMapper` should be independently testable with fixture JSON.

Required mapper functions:

| Function | Input fixture | Expected output |
| --- | --- | --- |
| token response mapping | `auth-token-success.json` | token metadata, expiry seconds |
| discovery mapping | `auth-list-success.json` | list of devices with id, name, model, firmware |
| status mapping | `vehicle-status-docked.json` | `VehicleState=Docked`, battery `81` |
| status mapping | `vehicle-status-mowing.json` | `VehicleState=Running`, battery `92` |
| command mapping | `command-dock-already-in-state.json` | `CommandResult=Already In State` |
| auth error mapping | `auth-invalid-token.json` | auth error / reauth required |

Mapper rules:

- unknown vehicle states map to `Unknown`;
- invalid battery values are rejected;
- absent online field must not become proof of offline;
- API code `4005` maps to auth failure;
- command-level `alreadyInState` maps to non-fatal command result.

## 12. Fixture-Based Test Targets

Before REST MVP code is considered reviewable, tests or documented checks
should cover:

1. JSON fixtures are valid.
2. Discovery fixture produces one device.
3. Docked status maps to `Docked` and battery `81`.
4. Running status maps to `Running` and battery `92`.
5. Missing direct `battery` still maps from `capacityRemaining`.
6. `alreadyInState` maps to `Already In State`.
7. Invalid token maps to reauthentication/auth failure.
8. Unknown state maps to `Unknown`.
9. Raw JSON debug output is disabled by default.
10. Token values are not returned by mappers used for public variables.

## 13. Files Allowed in the First Code Scaffold

Once this plan is accepted, the first code scaffold may create:

```text
modules/NavimowAccount/
modules/NavimowConfigurator/
modules/NavimowDevice/
library/Navimow/
tests/Navimow/
```

Allowed first files:

- module metadata files required by IP-Symcon;
- empty or minimal `module.php` files with lifecycle stubs;
- profile definitions;
- fixture mapper tests;
- REST client skeleton without live credentials.

Not allowed in first scaffold:

- MQTT/WSS implementation;
- map rendering;
- location variables;
- hardcoded real device IDs;
- private tokens;
- real endpoint captures outside sanitized fixtures;
- automatic command execution during tests.

## 14. Open Scaffold Decisions

Before implementation starts, decide:

1. Exact IP-Symcon module folder and metadata conventions.
2. Whether `NavimowAccount` is implemented as a Splitter instance or as a
   parent IO-like module with explicit message handling.
3. Protected token storage mechanism.
4. Whether `NavimowConfigurator` is included in the first commit or second
   commit.
5. Test framework/tooling for fixture-based parser tests.
6. Whether `Online` should be renamed or semantically documented as freshness
   until a real online field is found.

## 15. Next SAEF Step

The next SAEF step is:

```text
case-studies/navimow/11-implementation-start-decision.md
```

That document should resolve the open scaffold decisions and explicitly approve
or reject creation of the first real module scaffold files.
