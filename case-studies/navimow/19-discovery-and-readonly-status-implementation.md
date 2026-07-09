# 19 Discovery and Read-Only Status Implementation

**Case study:** Navimow native IP-Symcon module  
**Status:** Distribution published; direct Symcon and live read test pending  
**Date:** 2026-07-09  
**Build boundary:** This step implements account discovery, configurator
population and read-only device status. It does not implement mower commands,
MQTT/WSS, maps or location.

## 1. Purpose

This step implements WP16.4 and WP16.5 after the authentication gate passed in
`18-auth-symcon-test-report.md`.

The implementation adds:

- authenticated account discovery;
- sanitized discovery caching;
- a dynamic Symcon Configurator;
- explicit account-child messages;
- per-device read-only status refresh;
- conservative status freshness behavior;
- periodic account-driven polling triggers.

## 2. Entry Gate

Implementation started only after:

- the public module distribution installed successfully;
- metadata passed the official Symcon schemas;
- authorization-code exchange passed;
- authentication survived `ApplyChanges()`;
- one real refresh-token exchange passed;
- no token or secret value entered public artifacts.

## 3. Architecture Decisions

### AD-NAV-027: Account returns mapped read results

**Decision:** `NavimowAccount` performs authenticated HTTP calls and returns
mapped, bounded results to child instances.

It does not return:

- access or refresh tokens;
- authorization headers;
- OAuth configuration;
- full transport request data.

**Rationale:** The account remains the only owner of cloud authentication and
transport.

**Consequence:** Device and configurator modules cannot call the cloud
directly.

### AD-NAV-028: Status is matched by device ID

**Decision:** Status mapping selects the requested device by `id` or the
documented `device_id` fallback.

**Rationale:** Selecting the first payload entry would misroute data when an
account has multiple mowers.

**Consequence:** A response without the requested device is rejected and does
not update domain variables.

### AD-NAV-029: Online means REST freshness

**Decision:** Until a dedicated online field is fixture-backed, `Online`
represents a recent valid REST status.

- valid status sets `Online=true`;
- explicit `Offline` state sets it false;
- one failed request preserves the previous value;
- stale status eventually sets it false.

**Rationale:** Transport failure alone does not prove the mower is offline.

**Consequence:** The semantics must remain documented until MQTT or a dedicated
cloud field provides stronger evidence.

## 4. Account Read Contract

The existing data interface GUID and schema version `1` are used.

Child requests:

| Function | Required input | Result |
| --- | --- | --- |
| `GetDiscovery` | none | sanitized devices and receipt timestamp |
| `GetStatus` | bounded non-empty `DeviceId` | mapped status and receipt timestamp |

Parent polling message:

| Function | Receiver | Effect |
| --- | --- | --- |
| `PollStatus` | connected device children | each configured device requests its own status |

Unknown functions and schema versions return explicit sanitized errors.

## 5. Discovery Implementation

`NavimowAccount` now:

1. verifies a usable access token;
2. serializes the operation with the account semaphore;
3. calls `/openapi/smarthome/authList`;
4. evaluates HTTP, JSON and API-level success;
5. maps only `id`, `name`, `model` and `firmware`;
6. limits the encoded discovery cache to 64 KiB;
7. stores the sanitized cache as account-owned internal state;
8. updates `LastDiscovery` and `LastRestSuccess`;
9. returns the sanitized list to the configurator.

An empty successful list replaces the cache with an empty list. A failed
request does not claim stale entries as current.

## 6. Dynamic Configurator

`NavimowConfigurator::GetConfigurationForm()` requests discovery from its
connected account and renders a native Symcon `Configurator` element.

Visible columns:

- name;
- model;
- firmware.

The cloud device ID is required in the creation configuration but is not shown
as a list column.

For each discovered mower, the configurator:

- matches an existing `NavimowDevice` by configured `DeviceId`;
- otherwise offers creation of one device instance;
- initializes `DeviceId`, `DisplayName` and disabled debug payloads;
- lets Symcon connect the created device to the configurator's account parent;
- allows deletion through the standard configurator control.

The discovery interval hint is 60 seconds.

## 7. Read-Only Device Status

`NavimowDevice::RefreshStatus()`:

1. validates configured `DeviceId`;
2. requests `GetStatus` from the account;
3. accepts only an explicit success envelope;
4. validates mapped vehicle state, battery and receipt timestamp;
5. updates only valid fields;
6. preserves the previous battery when battery data is absent;
7. updates `LastStatusUpdate` only after a valid status result;
8. stores bounded mapped debug JSON only when explicitly enabled.

Public manual action:

```text
NAVDV_RefreshStatus(...)
```

The device form exposes this as `Refresh Status`.

No command action is enabled.

## 8. Status Mapping

Fixture-backed mappings remain:

| API value | Contract value |
| --- | --- |
| `isDocked` | `Docked` |
| `isRunning` | `Running` |
| percentage `capacityRemaining[].rawValue` | `BatteryLevel` |

Defensive behavior:

- unknown vehicle states map to `Unknown`;
- invalid battery values are ignored;
- a response that omits the requested device is rejected;
- extra fields do not create variables;
- missing direct online data does not imply false.

The mapper now has an explicit multi-device selection test.

## 9. Freshness and Failure Behavior

The account returns a stale threshold equal to:

```text
max(300 seconds, 2 * PollInterval)
```

On read failure, a device:

- leaves `VehicleState` and `BatteryLevel` unchanged;
- keeps `Online` unchanged while the last valid status is fresh;
- sets `Online=false` only after the stale threshold;
- records only a bounded debug message.

Account failures:

| Failure | Account state |
| --- | --- |
| transport | `Offline` |
| OAuth rejection | `Reauth Required`, polling disabled |
| API/payload | `API Warning` |
| invalid configuration | `Configuration Error` |

## 10. Timers and Concurrency

After usable authentication:

- `RefreshToken` remains scheduled from token expiry;
- `PollStatus` uses the configured interval with a minimum of 60 seconds;
- account requests use the existing instance-scoped semaphore;
- one account operation waits at most five seconds for the semaphore;
- polling is disabled when no usable token exists;
- no immediate retry loop exists.

The account timer sends only a polling trigger to children. Each device owns
application of its own status result.

## 11. Metadata and Data Flow

Device and configurator metadata now declare the shared data interface in
`implemented`.

This aligns metadata with:

- device `ReceiveData()` for poll triggers;
- device `SendDataToParent()` for status;
- configurator `SendDataToParent()` for discovery;
- account `ForwardData()` for both child request types.

The interface GUID remains stable.

## 12. Local Verification

Passed:

```text
official Symcon library schema
official Symcon module schemas
official Symcon locale schemas
official Symcon form schemas
SAEF distribution validator
PHP syntax checks
REST client and authentication checks
payload mapper fixture checks
```

Added regression coverage:

- requested device is selected from a multi-device status response;
- selected device battery is used;
- non-success API code is rejected.

No local test calls the Navimow cloud.

## 13. Security Review

Confirmed:

- tokens stay in `NavimowAccount`;
- child messages contain no authorization data;
- discovery cache is bounded and sanitized;
- debug status contains mapped data only;
- raw cloud payloads are not persisted by default;
- device IDs remain runtime configuration;
- commands and MQTT/WSS remain absent;
- no private fixture or installation value was added.

## 14. Direct Symcon Test Gate

The next test must:

1. update the public module distribution;
2. verify account authentication remains connected;
3. create or open one configurator connected to the account;
4. confirm live discovery returns the expected device count;
5. create or match one device instance through the configurator;
6. run one manual read-only status refresh;
7. verify state, battery, online freshness and timestamp;
8. allow one bounded timer poll;
9. inspect public diagnostics without reading tokens;
10. confirm no command or MQTT request occurred.

Use an explicit PASS/FAIL result channel for MCP assertions.

## 15. Published Distribution

The canonical distribution was synchronized to:

```text
Repository: doctee/symcon-navimow
Branch: main
Commit: 49fca9b34856f874646b0b5ceea2ce71800f3fb0
```

## 16. Definition of Done

This step is complete locally when:

- discovery and status code passes all local gates;
- metadata declares the real data flow;
- no command path is active;
- the canonical distribution is synchronized to the public repository.

The live read-only gate remains pending until the updated module is installed
and verified against the authenticated account.

## 17. Recommendation and Next Step

Publish and update the module, then execute the supervised live read-only test.

Recommended next SAEF artifact after that test:

```text
case-studies/navimow/20-discovery-and-status-symcon-test-report.md
```

Commands remain blocked until that report passes.
