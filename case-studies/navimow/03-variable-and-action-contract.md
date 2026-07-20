# 03 Variable and Action Contract

**Case study:** Navimow native IP-Symcon module  
**Status:** MVP contract draft before implementation  
**Date:** 2026-07-08  
**Build boundary:** This document defines the intended public contract only. No productive PHP code is introduced.

## 1. Purpose

This document defines the first public MVP contract for variables, actions,
profiles, archive defaults and payload mapping of a native IP-Symcon Navimow
module.

It refines the design decisions from `02-module-design.md` into stable names
that future implementation and tests can use. The contract is intentionally
small so the first module version remains reviewable and safe to validate with
real devices.

## 2. Contract Principles

The MVP contract follows these rules:

- Public variables are curated and typed.
- Status variables are read-only.
- User-controllable behavior is exposed through Symcon action semantics.
- Internal token values are never public variables.
- Raw payload diagnostics are optional, bounded and disabled by default.
- Unknown API fields do not create variables automatically.
- Archive defaults are conservative.
- MQTT and location fields are reserved for phase 2 and are not MVP
  requirements.

## 3. Naming Rules

Variable and action Idents use PascalCase because IP-Symcon Idents are stable
technical references and should be readable in the object tree.

Display names may be localized later. Idents must remain stable unless a
documented migration is provided.

| Kind | Rule | Example |
| --- | --- | --- |
| Account variable Ident | PascalCase, account-level meaning | `ConnectionState` |
| Device variable Ident | PascalCase, device-level meaning | `VehicleState` |
| Action Ident | PascalCase imperative command | `Start` |
| Diagnostic Ident | PascalCase and explicit scope | `LastCommandResult` |
| Profile name | Module-prefixed | `NAVIMOW.VehicleState` |

### SAEF-Entscheidung AD-NAV-010: Contract names are stable API

**Entscheidung:** Public variable Idents, action names and profile names are
treated as part of the module contract.

**Rationale:** Symcon automations, visualizations and user scripts may depend
on these names. Changing them casually would break installations.

**Consequence:** Contract changes after implementation need migration notes and
compatibility handling.

## 4. Account Instance Variables

The `NavimowAccount` instance exposes account/session health, not device state.

| Ident | Display name | Type | Profile | Role | Write | Archive | Required |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `ConnectionState` | Connection State | integer | `NAVIMOW.ConnectionState` | status | no | no | yes |
| `ReauthRequired` | Reauthentication Required | boolean | default boolean | status | no | no | yes |
| `TokenExpiresAt` | Token Expires At | integer | `~UnixTimestamp` | diagnostic | no | no | yes |
| `LastDiscovery` | Last Discovery | integer | `~UnixTimestamp` | diagnostic | no | no | yes |
| `LastRestSuccess` | Last REST Success | integer | `~UnixTimestamp` | diagnostic | no | no | yes |
| `RestErrorCount` | REST Error Count | integer | none | diagnostic counter | no | no | yes |

### Account Connection States

`NAVIMOW.ConnectionState` should represent operational state, not raw API
errors.

| Value | Association | Meaning |
| --- | --- | --- |
| `0` | Unconfigured | No usable login data exists. |
| `1` | Authorization Pending | Authorization code is present or required. |
| `2` | Authenticating | Token exchange or refresh is active. |
| `3` | Connected | Account is authenticated and last REST operation succeeded. |
| `4` | API Warning | Account is authenticated, but last API operation failed non-fatally. |
| `5` | Reauth Required | Token exchange or refresh failed; user action is required. |
| `6` | Offline | Cloud or network is unavailable. |
| `7` | Configuration Error | Required configuration is invalid. |

## 5. Device Instance Variables

The `NavimowDevice` instance exposes one mower. All status variables are
read-only from the user's perspective.

| Ident | Display name | Type | Profile | Role | Write | Archive | Required |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `VehicleState` | Vehicle State | integer | `NAVIMOW.VehicleState` | domain status | no | yes | yes |
| `Online` | Online | boolean | default boolean | domain status | no | yes | yes |
| `BatteryLevel` | Battery Level | integer | `~Intensity.100` or percent profile | domain status | no | yes | yes |
| `LastStatusUpdate` | Last Status Update | integer | `~UnixTimestamp` | diagnostic timestamp | no | no | yes |
| `LastCommand` | Last Command | integer | `NAVIMOW.Command` | command diagnostic | no | no | yes |
| `LastCommandAt` | Last Command At | integer | `~UnixTimestamp` | command diagnostic | no | no | yes |
| `LastCommandResult` | Last Command Result | integer | `NAVIMOW.CommandResult` | command diagnostic | no | no | yes |
| `LastCommandError` | Last Command Error | string | none | command diagnostic | no | no | yes |
| `RawStatusJson` | Raw Status JSON | string | none | optional debug diagnostic | no | no | optional |

### Device Variable Initialization

Before the first successful status update:

| Ident | Initial value rule |
| --- | --- |
| `VehicleState` | `0` (`Unknown`) |
| `Online` | `false` |
| `BatteryLevel` | `0`, with UI meaning "unknown until first payload" documented |
| `LastStatusUpdate` | `0` |
| `LastCommand` | `0` (`None`) |
| `LastCommandAt` | `0` |
| `LastCommandResult` | `0` (`None`) |
| `LastCommandError` | empty string |
| `RawStatusJson` | empty string |

### SAEF-Entscheidung AD-NAV-011: VehicleState is an integer profile in the MVP

**Entscheidung:** `VehicleState` is modeled as an integer variable with a
module-owned association profile, not as a free-form string.

**Rationale:** An integer profile gives stable visualization, easier automation
conditions and a controlled `Unknown` fallback for unmapped API values.

**Consequence:** The implementation needs a mapping layer from API strings to
integer associations and must preserve unmapped source values in diagnostics.

## 6. Device Profiles

### `NAVIMOW.VehicleState`

| Value | Association | Source values |
| --- | --- | --- |
| `0` | Unknown | missing or unmapped |
| `1` | Running | `isRunning` |
| `2` | Docked | `isDocked` |
| `3` | Idle | `isIdle` |
| `4` | Paused | `isPaused` |
| `5` | Docking | `isDocking` |
| `6` | Mapping | `isMapping` |
| `7` | Lifted | `isLifted` |
| `8` | Error | `Error` |
| `9` | Software Update | `inSoftwareUpdate` |
| `10` | Self-Checking | `Self-Checking` |
| `11` | Offline | `Offline` |

Unknown values must not fail parsing. They should map to `Unknown` and be
recorded in sanitized diagnostics.

Fixture evidence currently confirms `isDocked` and `isRunning`. Other listed
states are supported based on static source analysis and must remain defensive
until fixture-backed.

### `NAVIMOW.Command`

| Value | Association | Meaning |
| --- | --- | --- |
| `0` | None | No command submitted yet. |
| `1` | Refresh | Local status refresh. |
| `2` | Start | Start mowing command. |
| `3` | Stop | Stop mowing command. |
| `4` | Pause | Pause command. |
| `5` | Resume | Resume command. |
| `6` | Dock | Return to dock command. |

### `NAVIMOW.CommandResult`

| Value | Association | Meaning |
| --- | --- | --- |
| `0` | None | No command result yet. |
| `1` | Requested | Action accepted locally and is being prepared. |
| `2` | Accepted | Cloud accepted the command. |
| `3` | Already In State | Cloud reported no state change was needed, including command payloads with `status == "ERROR"` and `errorCode == "alreadyInState"`. |
| `4` | Pending Verification | Waiting for later status confirmation. |
| `5` | Verified | Later status confirms expected result. |
| `6` | Rejected | Cloud rejected the command. |
| `7` | Failed | Local, transport or parsing failure. |
| `8` | Verification Timeout | No confirming state was observed in time. |

### `NAVIMOW.ConnectionState`

Defined in section 4. The profile should be account-owned and reused only for
account-level connection state.

## 7. Action Contract

Actions are exposed through module action handling. Public status variables
must not be directly writable.

| Action Ident | Display name | API command | Params | Expected local behavior |
| --- | --- | --- | --- | --- |
| `Refresh` | Refresh | none | none | Trigger one status refresh for the device or account. |
| `Start` | Start | `action.devices.commands.StartStop` | `{ "on": true }` | Submit command once, then schedule verification. |
| `Stop` | Stop | `action.devices.commands.StartStop` | `{ "on": false }` | Submit command once, then schedule verification. |
| `Pause` | Pause | `action.devices.commands.PauseUnpause` | `{ "on": false }` | Submit command once, then schedule verification. |
| `Resume` | Resume | `action.devices.commands.PauseUnpause` | `{ "on": true }` | Submit command once, then schedule verification. |
| `Dock` | Dock | `action.devices.commands.Dock` | `{}` | Submit command once, then schedule verification. |

### Action Preconditions

| Action | Required preconditions |
| --- | --- |
| `Refresh` | Account configured and not already polling the same scope. |
| `Start` | Account authenticated; device configured; no command currently in `SendingCommand`. |
| `Stop` | Account authenticated; device configured; no command currently in `SendingCommand`. |
| `Pause` | Account authenticated; device configured; no command currently in `SendingCommand`. |
| `Resume` | Account authenticated; device configured; no command currently in `SendingCommand`. |
| `Dock` | Account authenticated; device configured; no command currently in `SendingCommand`. |

The MVP should prefer permissive command availability with explicit cloud-side
error handling over hiding commands based on incomplete local state knowledge.

### Action Postconditions

Every action attempt updates:

- `LastCommand`;
- `LastCommandAt`;
- `LastCommandResult`;
- `LastCommandError`.

Remote commands also schedule a delayed status refresh. HTTP command success
means cloud acceptance only. It must not directly overwrite `VehicleState`.
An API response with top-level `code == 1` can still contain a per-command
`status == "ERROR"` result. `errorCode == "alreadyInState"` maps to
`Already In State`, not `Failed`.

### SAEF-Entscheidung AD-NAV-012: Commands never set domain state directly

**Entscheidung:** Command actions update command diagnostics and trigger later
status verification, but they do not directly set `VehicleState`, `Online` or
`BatteryLevel`.

**Rationale:** Directly changing domain state would create a false local state
if the mower or cloud rejects or delays the command.

**Consequence:** Users see command progress separately from mower state.

## 8. Payload Mapping Contract

The MVP reads REST status payloads from `/openapi/smarthome/getVehicleStatus`.
The exact schema must be validated with real payload fixtures before coding is
finalized.

### Required Mapping Behavior

| Target variable | Source rule | Validation |
| --- | --- | --- |
| `VehicleState` | Read known `vehicleState`-like field from device status payload. | Map known strings through `NAVIMOW.VehicleState`; unknown -> `Unknown`. |
| `Online` | Read online/connectivity indication, or derive from `VehicleState == Offline` if no explicit field exists. | Boolean only; missing -> no change until verified. |
| `BatteryLevel` | Prefer `capacityRemaining[].rawValue` where `unit == "PERCENTAGE"`; fall back to direct `battery` only if present and valid. | Integer 0..100; out of range rejected. |
| `LastStatusUpdate` | Set after valid payload was applied. | Unix timestamp from local receipt time. |
| `RawStatusJson` | Store sanitized, size-bounded status JSON only when debug payload is enabled. | Must exclude credentials and be truncated by policy. |

### Missing Field Rules

| Condition | Behavior |
| --- | --- |
| Known field missing in first payload | Keep initialization value and add sanitized diagnostic warning. |
| Known field missing after previous success | Preserve previous domain value and update diagnostic warning. |
| Field type mismatch | Reject that field, preserve previous value, record payload validation error. |
| Unknown extra field | Ignore for public variables; optionally include in debug payload if enabled. |
| Whole payload invalid | Do not update domain variables; increment error diagnostics. |

### Raw JSON Policy

`RawStatusJson` is optional and disabled by default.

If enabled:

- store only the latest payload;
- apply a maximum length before writing;
- redact any token-like fields if present;
- do not archive it;
- document that it is a diagnostic aid, not an API contract.

## 9. Archive Contract

Default archive behavior:

| Variable | Archive default | Reason |
| --- | --- | --- |
| `VehicleState` | yes | Useful for operational history and state transitions. |
| `Online` | yes | Useful for cloud/device availability history. |
| `BatteryLevel` | yes | Useful at conservative polling intervals. |
| `LastStatusUpdate` | no | Frequent diagnostic timestamp, not domain history. |
| `LastCommand` | no | Diagnostic state, not domain state. |
| `LastCommandAt` | no | Diagnostic timestamp. |
| `LastCommandResult` | no | Diagnostic state. |
| `LastCommandError` | no | Error text should not be archived by default. |
| `RawStatusJson` | no | Large/unstructured diagnostic payload. |
| account diagnostics | no | Operational metadata, not domain history. |

Archive settings should be documented and user-adjustable where normal Symcon
module behavior allows it. The module should not perform archive correction or
history rewriting in the MVP.

### Archive ownership and update compatibility

Archive logging configured by the user is installation-owned state. Module
updates must preserve the existing Symcon variable objects for the stable
public Idents, especially:

- `VehicleState`;
- `Online`;
- `BatteryLevel`;
- `LastStatusUpdate`;
- `LastCommand`;
- `LastCommandAt`;
- `LastCommandResult`;
- `LastCommandError`.

Re-registering a variable with the same Ident and compatible type is the
normal idempotent update path. The module must not delete and recreate these
variables merely to change a display name, position or profile.

Any future Ident rename, variable-type change, split or removal requires a
documented migration that preserves object identity and archive history where
technically possible. If identity cannot be preserved, the change is breaking
and requires an explicit release decision and user migration procedure.

The module must not disable, reset or otherwise overwrite user-configured
archive logging during `Create()`, `ApplyChanges()` or migration.

### SAEF-Entscheidung AD-NAV-013: Public variable identity is persistent

**Entscheidung:** Treat the Symcon object identity behind every public stable
Ident as persistent installation state.

**Rationale:** Automations, visualizations and Archive Control logging can
depend on the existing variable ObjectID and its historical series.

**Consequence:** Future module updates must use idempotent registration and
explicit migrations instead of destructive variable recreation.

## 10. MQTT Phase 2 Reserved Contract

These names are reserved but not required for MVP implementation:

| Ident | Type | Profile | Archive default | Notes |
| --- | --- | --- | --- | --- |
| `MqttConnected` | boolean | default boolean | no | Bridge or device MQTT connection health. |
| `LastMqttMessage` | integer | `~UnixTimestamp` | no | Freshness diagnostic. |
| `LocationStale` | boolean | default boolean | no | Watchdog result. |
| `PostureX` | float | none | no | Relative coordinate in meters. |
| `PostureY` | float | none | no | Relative coordinate in meters. |
| `PostureTheta` | float | none | no | Orientation in radians. |
| `MowingPercentage` | integer | percent profile | no initially | Archive only after update frequency is known. |

MQTT fields should not be added to the MVP just because they exist in the
ioBroker adapter. The WSS client capability and payload stability must be
verified first.

## 11. Verification Contract

The implementation is contract-complete only when these checks pass:

1. All required account variables exist with stable Idents and expected types.
2. All required device variables exist with stable Idents and expected types.
3. Public status variables have no direct user write path.
4. Commands are reachable through action semantics.
5. `RequestAction()` on command surfaces delegates to module command handling.
6. Command actions do not directly set `VehicleState`.
7. Unknown vehicle states map to `Unknown`.
8. Invalid battery values do not overwrite the previous valid value.
9. `RawStatusJson` is absent or empty when debug payload is disabled.
10. Token content is not present in variables, logs, raw diagnostics or
    committed fixtures.
11. Archive defaults match section 9.
12. `alreadyInState` maps to `Already In State`, not a hard error.
13. Updating the module preserves existing public variable objects and does
    not alter user-configured archive logging.

## 12. Payload Fixture Requirements

Before implementation moves beyond a first local spike, collect sanitized
fixtures for:

- successful `authList` response with at least one device;
- successful `getVehicleStatus` response while docked;
- successful `getVehicleStatus` response while mowing if possible;
- command response for a successful command;
- command response with `alreadyInState`;
- authentication failure or expired token response;
- offline/cloud failure response if practical.

Fixtures must not contain tokens, private device IDs, private hostnames, exact
garden coordinates or other private installation data.

## 13. Open Contract Questions

1. Should `BatteryLevel` initialize to `0` or should the UI expose a separate
   `BatteryKnown` diagnostic to avoid confusing unknown with empty battery?
2. Which exact API field names carry online status and battery level across
   supported mower models?
3. Should command actions be individual button-like action variables, a single
   command selector, or module methods only?
4. Should `LastCommandError` be a visible variable or an internal diagnostics
   entry only?
5. What maximum length should `RawStatusJson` use in production?
6. Should `VehicleState` association values be kept sparse to allow stable
   insertion of future states?

## 14. Next SAEF Step

The next case-study artifact should be:

```text
case-studies/navimow/04-implementation-plan.md
```

That document should define the order of implementation work, test fixtures,
manual verification steps and the point at which creating real module PHP files
becomes justified.
