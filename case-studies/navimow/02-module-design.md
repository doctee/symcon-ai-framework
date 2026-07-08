# 02 Module Design

**Case study:** Navimow native IP-Symcon module  
**Status:** Design draft before implementation  
**Date:** 2026-07-08  
**Build boundary:** This document defines module shape and design decisions only. No productive PHP code is introduced.

## 1. Purpose

This document turns the initial source analysis from `01-requirements.md` into
a SAEF-aligned module design for a native IP-Symcon Navimow integration.

The design is intentionally conservative. It defines ownership, configuration,
state, diagnostics, actions and verification before implementation starts. The
goal is to make the future PHP module reviewable and safe to evolve.

## 2. Design Goals

The module should:

- integrate Segway Navimow mowers through the Navimow cloud API;
- avoid private installation data in public artifacts;
- separate account/session concerns from device state and device actions;
- expose stable, curated Symcon variables instead of mirroring arbitrary JSON;
- use action semantics for controllable operations;
- keep cloud, token, REST, MQTT and command failures diagnosable;
- support multiple mowers under one Navimow account;
- allow a REST-first MVP before MQTT/WSS is added.

## 3. Non-Goals for the First Implementation

The first implementation should not:

- implement map rendering;
- create garden-specific visualization helpers;
- archive every received API field by default;
- expose tokens in visible variables, logs or documentation;
- implement unbounded retries for remote commands;
- depend on hardcoded IP-Symcon ObjectIDs;
- re-create the dynamic ioBroker object tree one-to-one;
- introduce reusable SAEF helpers before repeated need is proven.

## 4. Proposed Module Structure

The recommended structure is a small native module family:

| Module | Responsibility | MVP status |
| --- | --- | --- |
| `NavimowAccount` | OAuth2, token refresh, REST client, device discovery, shared diagnostics. | Required |
| `NavimowConfigurator` | Discovers account devices and creates/links `NavimowDevice` instances. | Recommended for MVP if practical |
| `NavimowDevice` | One mower instance with status variables and actions. | Required |
| `NavimowMqttBridge` | MQTT/WSS connection, subscriptions and real-time payload routing. | Later phase |

### SAEF-Entscheidung AD-NAV-006: Account and device responsibilities stay separate

**Entscheidung:** The design separates account/session responsibilities from
per-device state and actions. The account instance owns OAuth2, tokens, REST
headers and discovery. Device instances own mower state, commands and
device-level diagnostics.

**Rationale:** OAuth tokens and API sessions are shared across devices, while
state variables and commands belong to one mower. A combined all-in-one module
would be simpler initially, but it would make multi-device support,
diagnostics, token refresh and later MQTT routing harder to review.

**Consequence:** The first PHP implementation needs a clear parent/child
communication contract before code is written.

### Alternative: Single instance module

A single instance with one configured device ID would be easier to implement.
It is rejected for the target design because Navimow accounts can expose
multiple devices and the API provides account-level discovery and shared MQTT
credentials.

It can still be used as a temporary spike outside production if a prototype is
needed to validate REST behavior.

## 5. Instance Ownership

### `NavimowAccount`

Owns:

- base URL and region configuration;
- OAuth2 authorization state;
- access and refresh token storage;
- token expiry metadata without token disclosure;
- device discovery results;
- REST request execution;
- shared REST diagnostics;
- optional shared MQTT setup in a later phase.

Does not own:

- user-facing mower status variables;
- mower actions;
- archive settings for mower state.

### `NavimowConfigurator`

Owns:

- discovery presentation for devices returned by `authList`;
- creation or matching of `NavimowDevice` instances by stable device ID;
- no persistent domain state beyond normal Symcon configurator state.

### `NavimowDevice`

Owns:

- one configured Navimow device ID;
- curated public status variables;
- action variables or module actions for remote commands;
- command state and command diagnostics;
- device-level REST freshness;
- device-level MQTT freshness in a later phase.

Does not own:

- OAuth tokens;
- account-wide discovery;
- unrelated mower instances.

## 6. Configuration Model

### Account Configuration

| Field | Type | Required | Private | Notes |
| --- | --- | --- | --- | --- |
| `Region` | enum/string | yes | no | Default design value: `fra`; future-proof for other regions. |
| `BaseUrl` | string | derived/advanced | no | Derived from region unless advanced override is enabled. |
| `AuthorizationCode` | string | only during login | yes | Temporary input, cleared after token exchange. |
| `PollingIntervalMinutes` | integer | yes | no | Conservative default: 5 minutes. `0` disables periodic polling only if explicitly supported. |
| `EnableDebugPayload` | boolean | no | no | Enables bounded raw payload diagnostic variable. |
| `EnableMqtt` | boolean | no | no | Default `false` for MVP; reserved for phase 2. |

Token values are not normal public configuration. They are protected account
state.

### Device Configuration

| Field | Type | Required | Private | Notes |
| --- | --- | --- | --- | --- |
| `DeviceId` | string | yes | yes-ish | Stable cloud device identifier; should not be published in examples. |
| `DisplayName` | string | no | no | User-facing instance name may be changed. |
| `ArchiveBattery` | boolean | no | no | Default `true` if archive strategy is accepted. |
| `ArchiveVehicleState` | boolean | no | no | Default `true`; stores state changes only. |
| `EnableLocation` | boolean | no | no | Default `false` for MVP. |

The device ID is not a credential, but it is installation-specific. Case-study
documents and examples should use placeholders.

## 7. Variable Model

The MVP should expose a small set of stable variables. Unknown or unstable API
fields remain internal or diagnostic until verified across real devices.

### Account Variables

| Ident | Type | Role | Write | Archive | Purpose |
| --- | --- | --- | --- | --- | --- |
| `ConnectionState` | integer/string | status | no | no | Account-level state such as unauthenticated, connected, token error, API error. |
| `LastDiscovery` | integer | timestamp | no | no | Last successful device discovery. |
| `LastRestSuccess` | integer | timestamp | no | no | Last successful account-level REST call. |
| `RestErrorCount` | integer | counter | no | no | Account REST error counter. |
| `TokenExpiresAt` | integer | timestamp | no | no | Diagnostic expiry timestamp, no token content. |
| `ReauthRequired` | boolean | status | no | no | True when refresh failed or no usable token exists. |

### Device Status Variables

| Ident | Type | Role | Write | Archive | Purpose |
| --- | --- | --- | --- | --- | --- |
| `VehicleState` | string or enum | status | no | yes | Main mower state from REST, mapped to known values. |
| `Online` | boolean | status | no | yes | Whether the device appears reachable/online. |
| `BatteryLevel` | integer | status | no | yes | Battery percentage if provided by API. |
| `LastStatusUpdate` | integer | timestamp | no | no | Last successful status update for this mower. |
| `LastCommand` | string | diagnostic | no | no | Last submitted command name. |
| `LastCommandAt` | integer | timestamp | no | no | Timestamp of last command submission. |
| `LastCommandResult` | string | diagnostic | no | no | accepted, alreadyInState, failed, pendingVerification, verified. |
| `LastCommandError` | string | diagnostic | no | no | Sanitized latest command error. |
| `RawStatusJson` | string | diagnostic | no | no | Optional bounded JSON payload for debug mode only. |

### Device Action Interfaces

The preferred design is to expose actions through Symcon action semantics, not
through writable status variables.

| Action | API command | Safety rule |
| --- | --- | --- |
| `Refresh` | local status refresh | Always allowed when account is authenticated. |
| `Start` | `action.devices.commands.StartStop` with `on: true` | No automatic retry; verify by later status. |
| `Stop` | `action.devices.commands.StartStop` with `on: false` | No automatic retry; verify by later status. |
| `Pause` | `action.devices.commands.PauseUnpause` with `on: false` | Allowed only when command is plausible for current state. |
| `Resume` | `action.devices.commands.PauseUnpause` with `on: true` | Allowed only when paused or API explicitly accepts. |
| `Dock` | `action.devices.commands.Dock` | No automatic repeat; status verification required. |

### MQTT/Location Variables for Phase 2

These variables are reserved until MQTT/WSS support is verified:

| Ident | Type | Role | Write | Archive | Purpose |
| --- | --- | --- | --- | --- | --- |
| `MqttConnected` | boolean | diagnostic | no | no | Device or bridge MQTT freshness. |
| `LastMqttMessage` | integer | timestamp | no | no | Last MQTT payload for the mower. |
| `LocationStale` | boolean | diagnostic | no | no | True when mower is active but location is stale. |
| `PostureX` | float | status | no | optional | Relative X position in meters. |
| `PostureY` | float | status | no | optional | Relative Y position in meters. |
| `PostureTheta` | float | status | no | optional | Orientation in radians. |
| `MowingPercentage` | integer | status | no | yes | Mowing progress if stable. |

### SAEF-Entscheidung AD-NAV-007: Public variables are curated and typed

**Entscheidung:** Public Symcon variables are created only for stable, useful
fields with documented roles. Raw payloads remain optional diagnostics.

**Rationale:** IP-Symcon variables carry UI, action and archive semantics. A
dynamic JSON-to-variable mirror would obscure ownership and make migration
risky.

**Consequence:** The implementation must maintain a field mapping table and a
payload review process for newly discovered fields.

## 8. Variable Profiles and Archive Policy

Expected profiles:

| Variable | Profile decision |
| --- | --- |
| `VehicleState` | Custom association profile after real state list is verified. |
| `BatteryLevel` | Standard percentage profile where available. |
| timestamps | Standard date/time presentation. |
| command result | Custom association profile or string diagnostic. |
| location floats | No custom profile initially; units documented as meters/radians. |

Archive defaults:

- Archive `VehicleState` and `Online` changes because they are useful for
  operational history.
- Archive `BatteryLevel` if polling interval is conservative.
- Do not archive raw JSON, error strings, command errors, token status or
  high-frequency location values by default.
- Archive `MowingPercentage` later only after MQTT update frequency is known.

### SAEF-Entscheidung AD-NAV-008: Archive only low-risk domain state by default

**Entscheidung:** The MVP archives only bounded, low-frequency domain state by
default.

**Rationale:** EK-003 warns against uncontrolled archive growth. MQTT location
could become high-frequency and should not be archived without rate and value
stability checks.

**Consequence:** Archive defaults must be documented in the module README and
configurable where user needs differ.

## 9. Token and Authentication State Machine

The account instance should model authentication explicitly:

```text
Unconfigured
    -> AuthorizationCodePending
    -> TokenExchange
    -> Authenticated
    -> RefreshScheduled
    -> Refreshing
    -> Authenticated

Failure transitions:
TokenExchange -> ReauthRequired
Refreshing -> ReauthRequired
Authenticated -> ApiAuthError -> Refreshing or ReauthRequired
```

State meanings:

| State | Meaning |
| --- | --- |
| `Unconfigured` | No token and no authorization code. |
| `AuthorizationCodePending` | User supplied a code; exchange not complete. |
| `TokenExchange` | Authorization code is being exchanged. |
| `Authenticated` | Access token is available. |
| `RefreshScheduled` | Refresh is planned before expiry. |
| `Refreshing` | Refresh token exchange is active. |
| `ReauthRequired` | Token exchange or refresh failed; user action needed. |
| `ApiAuthError` | API rejected current token. |

Engineering rules:

- Do not log token values.
- Clear temporary authorization code after successful exchange.
- Store only token metadata in public diagnostics.
- Treat refresh failure as recoverable only if a still-valid access token can
  be verified.
- Reconnect MQTT after token refresh in the later MQTT phase.

## 10. REST Polling State Machine

REST polling should be explicit and non-overlapping:

```text
Idle
    -> Polling
    -> ApplyingStatus
    -> Idle

Failure transitions:
Polling -> RetryWaiting -> Polling
Polling -> PollFailed -> Idle
ApplyingStatus -> PayloadRejected -> Idle
```

Rules:

- Only one poll run per account should be active at a time.
- Polling reads all enabled devices or one requested device depending on
  caller context.
- Poll retries are allowed only for temporary transport or server failures.
- Configuration and authentication failures should not be retried blindly.
- Polling should update `LastRestSuccess` only after a valid response has been
  parsed.
- Device variables should be updated only from validated fields.

Recommended initial timing:

- Default interval: 5 minutes.
- Manual refresh: allowed, but should not start a second poll if one is active.
- Post-command verification refresh: approximately 5 seconds after command
  acceptance, then normal polling resumes.

## 11. Command State Machine

Device commands must be safe to operate from visualizations, scripts and
automations through `RequestAction()` semantics.

```text
Idle
    -> CommandRequested
    -> SendingCommand
    -> PendingVerification
    -> Verified
    -> Idle

Failure transitions:
SendingCommand -> CommandRejected -> Idle
PendingVerification -> VerificationTimeout -> Idle
```

Rules:

- Commands are not retried automatically.
- `alreadyInState` is treated as a successful terminal response with explicit
  diagnostic result.
- A command HTTP success means "accepted by cloud", not "mower state already
  changed".
- Verification should compare later `VehicleState` against expected outcomes
  where this is reliable.
- The command result must remain visible for diagnosis without exposing request
  payloads containing private identifiers.

Expected command outcomes:

| Command | Expected state signal | Notes |
| --- | --- | --- |
| `Start` | `isRunning` or accepted active mowing state | May depend on schedule, lock state or mower readiness. |
| `Stop` | `isIdle`, `isPaused` or non-running state | Exact result requires device validation. |
| `Pause` | `isPaused` | If already paused, record `alreadyInState`. |
| `Resume` | `isRunning` or active mowing state | May transition through self-checking. |
| `Dock` | `isDocking` then `isDocked` | Verification may need longer timeout. |

## 12. MQTT/WSS Phase 2 Design

MQTT/WSS should be added only after REST MVP behavior is verified.

Responsibilities:

- account or bridge instance obtains MQTT connection info;
- bridge connects via WSS if supported by Symcon/PHP stack;
- bridge subscribes to per-device topics;
- bridge routes messages to device instances by `DeviceId`;
- device instances parse only verified channel payloads;
- diagnostics track connection, last message, error count and staleness.

Open design point:

- If Symcon's built-in MQTT components cannot send the required WSS
  `Authorization` header, the module may need an internal MQTT-over-WebSocket
  client. This must be decided by a technical spike before implementation.

### SAEF-Entscheidung AD-NAV-009: MQTT is not part of the REST MVP

**Entscheidung:** MQTT/WSS is designed as phase 2 and not required for the
first MVP.

**Rationale:** REST discovery, token handling and command verification are the
minimum stable base. MQTT adds separate connection, credential, topic routing
and staleness problems.

**Consequence:** The MVP must keep interfaces extensible enough for later MQTT
without blocking on it.

## 13. Diagnostics Model

Diagnostics should follow SAEF responsibilities. Module internals may implement
these as IP-Symcon instance buffers, attributes or variables depending on the
final module architecture, but the responsibility split should remain stable.

### Registry

Small metadata:

- module schema version;
- selected region;
- normalized configuration hash;
- authentication state;
- last known API compatibility marker;
- migration marker.

The registry must not store token content, complete discovery payloads or large
raw responses.

### Statistics

Counters and timestamps:

- REST request count;
- REST error count;
- token refresh count;
- token refresh error count;
- command count;
- command error count;
- MQTT message count in phase 2;
- last successful discovery;
- last successful status update.

### Error Ring Buffer

Bounded recent errors:

- token exchange failure class;
- refresh failure class;
- REST transport failure;
- API response failure;
- payload validation failure;
- command rejection;
- MQTT connect or subscribe failure in phase 2.

Entries should contain concise sanitized context only. Device IDs should be
omitted or shortened in public diagnostics unless the user explicitly enables
technical debug mode.

### Configuration Hash

The normalized hash should include:

- region;
- polling interval;
- enabled device IDs as placeholders or hashed identifiers;
- feature flags such as MQTT and debug payload mode.

It should exclude:

- tokens;
- authorization code;
- timestamps;
- last errors;
- raw payloads.

## 14. Security and Privacy

Security rules for the future implementation:

- Never write access tokens or refresh tokens into normal visible variables.
- Never log full tokens, authorization codes or MQTT passwords.
- Avoid logging full request/response bodies by default.
- Sanitize raw payload diagnostics.
- Treat device IDs as installation-specific identifiers in documentation.
- Keep private credentials out of public examples and tests.
- Do not include garden coordinates or map screenshots in public artifacts.

## 15. Error Handling and Retry Policy

| Operation | Retry policy | Reason |
| --- | --- | --- |
| Token exchange | No blind retry | Bad or expired code requires user action. |
| Token refresh | One bounded retry for temporary failures | Avoid unnecessary reauth after transient cloud issue. |
| Device discovery | Bounded retry for transport/server errors | Read-only operation is safe to repeat. |
| Status polling | Bounded retry or next scheduled poll | Read-only operation is safe, but cloud load should stay low. |
| Remote command | No automatic retry | Repeating actuator commands can be unsafe. |
| MQTT reconnect | Bounded/backoff reconnect in phase 2 | Persistent connection needs recovery but must not spin. |

Final failures should update diagnostics and, where appropriate, set connection
or reauthentication status.

## 16. Verification Plan

Before productive module code is considered complete, verify:

1. OAuth2 login succeeds with a test account and no token appears in logs.
2. Authorization code is cleared or invalidated after successful token exchange.
3. Token refresh works after restart.
4. `authList` discovers at least one mower and exposes stable IDs and names.
5. `getVehicleStatus` maps known fields into curated variables.
6. Unknown fields do not create public variables automatically.
7. Manual `Refresh` updates status without overlapping an active poll.
8. Each command sends the expected API command and records command diagnostics.
9. Command success is verified by later status where practical.
10. `alreadyInState` is visible as a non-error diagnostic result.
11. Cloud offline or invalid token states are distinguishable in variables.
12. Archive defaults do not include raw JSON or high-frequency data.
13. No private device IDs, tokens, hostnames or garden data are present in
    committed examples, tests or docs.

MQTT phase verification:

1. MQTT credentials can be obtained after authentication.
2. WSS connection works with required headers or a documented alternative.
3. Subscriptions receive `state`, `event`, `attributes` and `location` topics.
4. Topic routing updates only the matching device instance.
5. Location staleness is detected during active mower states.
6. MQTT reconnect does not duplicate subscriptions or messages.

## 17. Open Design Questions

1. Which IP-Symcon module lifecycle hooks are best suited for token refresh
   scheduling?
2. Should `NavimowAccount` act as a Splitter instance, or should account/device
   communication use a simpler parent-child module contract?
3. Can Symcon's existing MQTT infrastructure satisfy the Navimow WSS header
   requirement?
4. Should `VehicleState` be a string initially or an integer profile backed by
   an internal mapping?
5. How should model-specific fields be represented without fragmenting the
   public variable model?
6. Which fields from `getVehicleStatus` are stable enough for MVP variables
   after real-device payload collection?
7. Should command availability be strict by known state, or permissive with
   cloud-side rejection handling?
8. What is the minimum acceptable test setup: one mower, multiple models, or
   recorded sanitized payload fixtures?

## 18. Next SAEF Step

The next case-study artifact should be:

```text
case-studies/navimow/03-variable-and-action-contract.md
```

That document should define the exact MVP variable list, profiles, action
names, expected values, archive defaults and payload-to-variable mapping before
any productive PHP module files are created.
