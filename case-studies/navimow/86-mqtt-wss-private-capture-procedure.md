# 86 MQTT/WSS Private Capture Procedure

**Case study:** Navimow native IP-Symcon module
**Status:** Receive-only private capture tool implemented; first docked run pending
**Date:** 2026-07-27
**Scope:** Prepare bounded private MQTT/WSS evidence without publishing or changing Symcon

## 1. Purpose

This step implements the first work package approved by
`85-mqtt-wss-track-reprioritization-and-evidence-plan.md`.

It provides a directly executable private Mac terminal tool that:

- authenticates through the existing Smart Home OAuth flow;
- discovers exactly one selected mower;
- retrieves temporary MQTT/WSS credentials;
- establishes one TLS-verified WSS MQTT connection;
- subscribes to four exact per-device downlink topics;
- captures messages for a bounded interval;
- stores raw and sanitized evidence separately;
- contains no MQTT publish or mower-command path.

No productive PHP file, Symcon instance, module metadata, public fixture or
mower state is changed while preparing and validating the tool.

## 2. Private Executables

The private implementation consists of:

```text
private/navimow-capture/capture-mqtt-readonly.sh
private/navimow-capture/capture_mqtt_readonly.py
```

Both files and all generated output are covered by the repository's
`/private/` ignore rule.

The shell wrapper owns:

- hidden OAuth input;
- REST token, discovery and credential requests;
- bounded duration;
- single-process locking;
- isolated dependency setup;
- private path and permission setup.

The Python helper owns:

- WSS endpoint validation;
- MQTT connection and subscription;
- exact-topic allowlisting;
- message bounds and JSON validation;
- raw and sanitized message persistence;
- machine-readable capture reporting.

## 3. Dependency Isolation

The live capture uses:

```text
paho-mqtt==2.1.0
```

The wrapper installs it only into:

```text
private/navimow-capture/.venv-mqtt/
```

No repository dependency, productive module package or system Python package
is modified.

Static validation does not require this dependency and performs no network
access.

## 4. Authentication and Credential Handling

The wrapper contains no client-secret value.

It accepts the OAuth client secret through:

```text
NAVIMOW_CLIENT_SECRET
```

or the hidden prompt:

```text
Paste OAuth client secret for this private test (input hidden):
```

The authorization code or callback URL is also entered through a hidden
prompt. The temporary form body is written with mode `600`, used once and
deleted immediately.

The resulting token, discovery response and MQTT credential response remain
only under the private raw directory.

The Python process reads secrets from private JSON files. Tokens, MQTT
credentials and the private WSS endpoint are not passed as Python command-line
arguments. REST requests read the Bearer header from a temporary mode-`600`
file, which is deleted before MQTT starts.

## 5. Exact Receive Contract

For the one selected device, the tool subscribes only to:

```text
/downlink/vehicle/{DEVICE_ID}/realtimeDate/state
/downlink/vehicle/{DEVICE_ID}/realtimeDate/event
/downlink/vehicle/{DEVICE_ID}/realtimeDate/attributes
/downlink/vehicle/{DEVICE_ID}/realtimeDate/location
```

The topic builder rejects device identifiers containing:

```text
/
#
+
```

The tool contains no wildcard subscription.

The tool contains no:

- MQTT publish call;
- `sendCommands` REST request;
- Start, Stop, Pause, Resume or Dock command;
- command retry;
- automatic mower action after a message.

Its capture report always records:

```json
{
  "publishAttemptCount": 0,
  "commandAttemptCount": 0
}
```

## 6. WSS Security Contract

The tool accepts only:

```text
wss://
port 443
```

It configures:

- MQTT 3.1.1;
- WebSocket transport;
- system CA certificate verification;
- `Authorization: Bearer ...` during the WebSocket upgrade;
- MQTT username and password during MQTT CONNECT;
- keepalive of 2400 seconds;
- reconnect delays bounded from 5 through 60 seconds.

Plain WebSocket, raw TCP MQTT, disabled certificate verification and unexpected
ports fail closed.

## 7. Output Structure

Private output root:

```text
private/navimow-capture/output/mqtt/
```

Raw files:

```text
raw/auth-token.json
raw/auth-list.json
raw/mqtt-user-info.json
raw/mqtt-messages.jsonl
raw/mqtt-capture-report.json
```

Sanitized candidates:

```text
sanitized/mqtt-credential-shape.json
sanitized/mqtt-messages.jsonl
sanitized/mqtt-capture-report.json
```

The message files use one JSON object per line. Each record preserves:

- normalized receipt time in the sanitized copy;
- placeholder topic;
- channel;
- payload shape and JSON value types.

## 8. Sanitization

The sanitizer removes or replaces:

- access and refresh tokens;
- MQTT username and password;
- MQTT host, URL and WebSocket path;
- device, account, request and serial identifiers;
- mower names;
- Bearer headers;
- position coordinates;
- point, path, map and boundary geometry.

Coordinates are replaced with small synthetic values only to preserve their
numeric types. Geometry arrays are removed.

Sanitized output is a review candidate, not automatically approved public
fixture material.

## 9. Message and Session Bounds

The default capture duration is:

```text
180 seconds
```

Allowed range:

```text
30 through 1800 seconds
```

Each MQTT payload is limited to:

```text
1 MiB
```

Only valid UTF-8 JSON from an exact allowed topic is persisted. Unknown topics,
invalid JSON and oversized payloads do not update evidence files.

A private lock directory prevents concurrent runs. Four disconnect events end
the capture rather than allowing an unbounded recovery loop.

## 10. Static Validation

From the repository root:

```sh
NAVIMOW_CAPTURE_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-mqtt-readonly.sh
```

Validation mode:

- compiles the Python helper;
- builds all four synthetic exact topics;
- proves there are no wildcard topics;
- tests exact-topic rejection;
- tests token, credential, endpoint, device and coordinate sanitization;
- accepts a synthetic WSS port-443 endpoint;
- rejects a plain WebSocket endpoint;
- checks the Python source for a publish call;
- checks the Python source for mower-command paths;
- exits before OAuth, REST, dependency installation or MQTT.

Expected result:

```text
MQTT capture static topic, sanitizer and no-publish validation passed.
```

## 11. First Docked Live Procedure

Prerequisites:

- mower is docked;
- normal app access remains available;
- no other local capture process is running;
- no raw file will be shared;
- three minutes are available for the default observation;
- network access from the Mac is available.

Start from the SAEF repository root:

```sh
./private/navimow-capture/capture-mqtt-readonly.sh
```

Then:

1. open the displayed Navimow login URL;
2. complete login;
3. copy the full failing callback URL or short-lived code;
4. paste the OAuth client secret into the hidden prompt;
5. paste the callback URL or code into the hidden prompt;
6. allow the one-time private dependency installation if required;
7. leave the mower docked;
8. wait for the bounded capture to finish.

The tool prints only channel, sequence number and byte count for received
messages. It does not print payloads, topics, credentials or device IDs.

## 12. Pass Criteria

The first docked capture passes its transport gate when:

- OAuth token exchange succeeds;
- exactly one intended mower is selected;
- the MQTT credential response has all four required fields;
- TLS/WSS connection succeeds;
- all four exact subscriptions are acknowledged;
- no publish or command is attempted;
- capture ends within the configured bound;
- a private machine-readable report is created;
- sanitized output contains no private values.

Receiving zero mower messages while docked does not fail the transport gate.
It means payload evidence remains pending for a natural activity run.

## 13. Failure Rules

| Failure | Required response |
| --- | --- |
| OAuth failure | stop before MQTT |
| ambiguous device selection | set private `NAVIMOW_DEVICE_ID` or stop |
| credential business error | stop; retain private response |
| non-WSS endpoint | stop |
| certificate failure | stop; never disable verification |
| subscription rejection | stop; do not broaden wildcard scope |
| no docked messages | retain connection evidence; plan passive activity run |
| repeated disconnect | stop after bounded count |
| sanitizer uncertainty | share nothing and review private files locally |

Do not retry by weakening TLS, adding `#`, publishing a probe or sending a mower
command.

## 14. Static Verification Result

The implementation passed:

| Check | Result |
| --- | --- |
| Bash syntax | PASS |
| Python compilation | PASS |
| no-network validation | PASS |
| pinned `paho-mqtt` version | `2.1.0` |
| offline WSS client construction | PASS |
| private ignore coverage | PASS |
| executable permissions | PASS |
| no publish source path | PASS |
| no command endpoint | PASS |
| exact topic count | 4 |
| wildcard topic count | 0 |
| productive distribution diff | none |
| complete repository gate | PASS |

The complete gate includes the focused REST/auth tests, all 33 deterministic
pilot harness cases, distribution validation, PHP syntax, PHPStan, PHPCS and
the remaining repository tests.

No OAuth, REST, MQTT or mower request was made by these validations.

## 15. Architecture Decisions

### AD-NAV-304: Use a private external capture client before Symcon transport work

**Decision:** Prove the vendor WSS contract from the Mac before creating an
IP-Symcon transport module.

**Rationale:** This isolates cloud behavior from Symcon topology questions.

**Consequence:** A connection failure does not create module metadata churn.

### AD-NAV-305: Keep the first live run docked and passive

**Decision:** Do not combine initial connection proof with a mower transition.

**Rationale:** Transport and payload evidence are independently reviewable
gates.

**Consequence:** Zero docked messages is an acceptable transport result.

### AD-NAV-306: Reject publish structurally

**Decision:** Provide no publish function or command request in the private
capture code.

**Rationale:** A policy flag is weaker than absence of the capability.

**Consequence:** MQTT cannot actuate the mower through this tool.

### AD-NAV-307: Keep raw evidence private and bounded

**Decision:** Store full payloads only below the ignored private root and limit
time, payload size and reconnect count.

**Rationale:** MQTT may expose identifiers and garden geometry at high
frequency.

**Consequence:** Public fixtures require a separate review and promotion step.

## 16. Decision

**Receive-only capture implementation: GO.**

**First docked private run: READY AFTER STATIC VALIDATION.**

**Natural activity capture: NOT YET STARTED.**

**Productive Symcon MQTT implementation: NO-GO.**

**MQTT publish and mower commands: OUT OF SCOPE.**

## 17. Recommended Next Step

Run the static validation. If it passes, perform one docked three-minute
receive-only capture and return only the terminal summary. Then create
`87-mqtt-wss-private-capture-report.md`.
