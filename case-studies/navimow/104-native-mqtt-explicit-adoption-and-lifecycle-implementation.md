# 104 Native MQTT Explicit Adoption and Lifecycle Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation complete; publication and live Symcon
mutation remain blocked
**Date:** 2026-07-28
**Scope:** First implementation increment of `WP-8` from step 103

## 1. Purpose

This step implements the first bounded native MQTT transport lifecycle:

- read-only candidate validation;
- explicit adoption of one manually prepared dedicated chain;
- redacted ownership evidence;
- one stable local MQTT client identity;
- one explicit connection attempt;
- ownership-checked disconnect and credential cleanup;
- lifecycle observation without automatic reconnect.

The implementation does not create, delete or search for replacement core
instances. It was exercised only against the offline fake runtime.

## 2. Preserved Boundaries

- REST remains the only source of public Device state.
- MQTT remains receive-only and cannot send mower commands.
- `EnableMqttShadow` still defaults to `false`.
- Existing public variables, Idents, profiles and action contracts are
  unchanged.
- No Archive Control configuration is changed.
- No private endpoint, topic, device identity, username, password, token or
  client ID is written to an Account attribute, report, fixture or debug
  message.
- No automatic connection or recovery runs from `ApplyChanges()`.
- `MC_ReloadModule()` is not used.
- No publication, Symcon update or live core mutation occurred in this step.

## 3. Implemented Contract

### 3.1 Candidate validation

`ValidateMqttAdoptionCandidate()` verifies:

- the feature is explicitly enabled;
- Receiver-to-Account pairing;
- Receiver, MQTT Client and WebSocket Client GUIDs and parent order;
- a non-empty bounded discovery cache;
- four exact QoS-0 topics per discovered mower;
- absence of wildcard topics;
- inactive WebSocket;
- empty WebSocket header, MQTT username and MQTT password properties.

The action is read-only and returns only bounded status text and the configured
Receiver ObjectID.

### 3.2 Explicit adoption

`AdoptMqttShadowChain()`:

1. acquires the dedicated lifecycle semaphore;
2. reruns candidate validation;
3. creates one 128-bit random local identity when none exists;
4. stores only its SHA-256 hash in the ownership registry;
5. stores redacted subscription and transport shape hashes;
6. moves lifecycle state to `Ready`;
7. performs no core mutation and no credential request.

Repeated adoption of the same owned chain is idempotent. Existing but invalid
ownership fails closed.

### 3.3 Explicit connect

`ConnectMqttShadow()` requires valid ownership and a usable OAuth access token.
It then executes exactly one bounded attempt:

1. set WebSocket `Active = false` and apply;
2. retrieve fresh MQTT credentials through the implemented REST endpoint;
3. configure MQTT username, password, stable client ID, keepalive and exact
   subscriptions, then apply;
4. configure WSS URL, complete `Bearer <token>` Authorization header, binary
   mode, certificate verification and inactive state, then apply;
5. read back and validate the complete inactive transport shape;
6. update redacted ownership evidence;
7. set WebSocket `Active = true` and apply exactly once;
8. record `Connecting`.

No retry is scheduled. A normal OAuth token refresh does not reconnect or
rewrite the native transport.

### 3.4 Accepted receive evidence

The first successfully reduced non-retained MQTT message changes lifecycle
state from the current state to `ShadowActive`. This is stronger evidence than
a core status code alone while keeping REST authoritative for public values.

### 3.5 Disconnect, disable and reset

`DisconnectMqttShadow()` first revalidates ownership, then:

1. deactivates and applies the WebSocket;
2. clears WebSocket headers;
3. clears MQTT username and password;
4. applies both core configurations;
5. refreshes only redacted ownership evidence;
6. clears ephemeral shadow and reconciliation state.

Disabling the feature through `ApplyChanges()` and resetting authentication use
the same fail-closed cleanup path when valid ownership exists. Instance
deletion, subscription deletion and client-identity rotation do not occur.

## 4. Persistence and Ownership

`MqttOwnershipRegistry` now uses format version 2. It contains:

- the three adopted instance IDs and module GUIDs;
- connection order and Account binding;
- redacted subscription and transport shape hashes;
- local client-identity hash;
- adoption timestamp.

The old format-1 raw-configuration hashes are rejected. The runtime compares
actual exact topics transiently against `DiscoveryCache`, but persists only
device count, fixed channel set, QoS and topic count in the shape hash.

`MqttClientIdentity` contains 32 lowercase hexadecimal characters. The native
client ID is deterministically derived as:

```text
symcon_navimow_<first 24 identity characters>
```

The client ID itself is not returned by diagnostics or written to ownership
metadata.

## 5. Lifecycle State

The bounded internal state vocabulary is:

```text
Disabled
WaitingForAuthentication
WaitingForPairing
Ready
Configuring
Connecting
ShadowActive
Disconnected
ReauthenticationRequired
ConfigurationError
```

`MqttLifecycle` remains a disabled observation timer. Its handler can record
the native MQTT Client status but cannot reconnect, create or replace an
instance.

## 6. Files Changed

- `distribution/NavimowAccount/module.php`
- `distribution/NavimowAccount/form.json`
- `distribution/NavimowAccount/locale.json`
- `distribution/libs/Navimow/MqttTransportConfiguration.php`
- `tests/mqtt-account-ingestion.php`
- `tests/mqtt-shadow-reconciliation.php`
- `tests/mqtt-transport-lifecycle.php`
- `tools/check-mqtt-shadow.sh`

## 7. Offline Evidence

The lifecycle fake runtime verifies:

- disabled `ApplyChanges()` performs no core mutation;
- candidate validation is read-only;
- adoption performs no core mutation;
- repeated adoption preserves identity and ownership;
- ownership contains no synthetic device ID or secret;
- the credential endpoint is called once per explicit connect;
- inactive-first ordering;
- exactly one `Active = true` mutation;
- credentials exist only in the fake native core configuration;
- routine OAuth refresh performs no MQTT mutation;
- disconnect deactivates and clears credentials;
- configuration drift prevents every disconnect mutation;
- no create, delete or module-reload call exists.

The complete MQTT shadow check passes, including fixture tests, strict
distribution validation, PHPCS and PHPStan.

## 8. Gate Decision

| Gate | Result |
|---|---|
| Explicit inactive-chain adoption | PASS offline |
| Redacted ownership and stable identity | PASS offline |
| One-attempt connect ordering | PASS offline |
| Rollback and credential cleanup | PASS offline |
| Ownership-drift fail-closed behavior | PASS offline |
| No automatic lifecycle mutation | PASS offline |
| Publication | NOT PERFORMED |
| Supervised Symcon candidate validation | PENDING |
| Supervised Symcon adoption/connect/receive/disconnect | PENDING |
| Restart and update preservation | PENDING |

The implementation is ready for a publication and supervised-test plan, not
yet for productive activation.

## 9. Next Step

Create:

```text
105-native-mqtt-lifecycle-publication-and-symcon-test-plan.md
```

That step should define:

1. a pre-publication byte and metadata gate;
2. read-only validation of the already prepared inactive dedicated chain;
3. explicit adoption;
4. one supervised connect attempt;
5. accepted-message and REST-authority evidence;
6. explicit disconnect with credential-cleanup readback;
7. restart and module-update preservation;
8. rollback criteria before any automatic recovery is considered.
