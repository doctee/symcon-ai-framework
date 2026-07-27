# 89 MQTT Active REST Comparison Capture Procedure

**Case study:** Navimow native IP-Symcon module
**Status:** Private receive-only comparison tool ready; live run pending
**Date:** 2026-07-27
**Scope:** Capture active MQTT messages, read-only REST status and operator phase markers on one relative timeline

## 1. Purpose

This step implements the active comparison recommended by
`88-mqtt-partial-payload-parser-design-and-implementation.md`.

It prepares one bounded private run that can establish:

- which MQTT channels emit while the mower is active;
- the numeric MQTT `vehicleState` values seen across physical phases;
- MQTT message cadence and partial-message behavior;
- relative timing between MQTT, REST and visible mower behavior;
- whether MQTT and REST agree during Running, Docking and final Docked;
- whether the first parser contract remains valid under active traffic.

The tool is observational. It cannot start, pause, resume, stop or dock the
mower.

## 2. Implementation Boundary

The private implementation consists of:

```text
private/navimow-capture/capture-mqtt-rest-comparison.sh
private/navimow-capture/capture-mqtt-readonly.sh
private/navimow-capture/capture_mqtt_readonly.py
```

The new wrapper enables a comparison mode in the previously proven
receive-only MQTT client.

All implementation and generated output remain below ignored `private/`.
Nothing from this tool is part of:

```text
case-studies/navimow/distribution/
```

No Symcon module, instance, variable, profile, action or archive setting is
changed.

## 3. Transport Contract

The MQTT side retains the step-86 contract:

- MQTT 3.1.1 over WSS;
- TLS certificate verification;
- port 443;
- Bearer token in the WebSocket upgrade;
- temporary MQTT username and password;
- exactly four per-device downlink topics;
- QoS 0;
- no wildcard;
- no publish function;
- no command endpoint.

The exact topics remain:

```text
/downlink/vehicle/{DEVICE_ID}/realtimeDate/state
/downlink/vehicle/{DEVICE_ID}/realtimeDate/event
/downlink/vehicle/{DEVICE_ID}/realtimeDate/attributes
/downlink/vehicle/{DEVICE_ID}/realtimeDate/location
```

## 4. REST Comparison Contract

After all four MQTT subscriptions are acknowledged, the tool performs:

```text
POST /openapi/smarthome/getVehicleStatus
```

The request body contains only the discovered mower ID.

Default interval:

```text
30 seconds
```

Allowed interval:

```text
15 through 300 seconds
```

The endpoint must use HTTPS on port 443. Each response is bounded to one MiB.
HTTP/JSON reception and Navimow business success are counted separately.

Only a JSON object with:

```text
code == 1
```

counts as a successful REST status observation. Business errors remain private
evidence and do not participate in state comparison.

There is no:

- REST command;
- REST retry burst;
- token-refresh loop;
- optimistic state mutation;
- write to Symcon.

## 5. Common Relative Timeline

Every MQTT message, REST response and operator marker records:

```text
elapsedMs
```

The value is measured from one monotonic process start.

Raw evidence also records an actual UTC receipt time. Sanitized candidates
replace that timestamp with:

```text
NORMALIZED_TIMESTAMP
```

The relative millisecond offset is retained because it is required to compare:

- visible phase transition;
- first corresponding MQTT message;
- first corresponding REST response.

Vendor payload timestamps remain evidence fields. They are not used as the
only correlation clock because their source and clock semantics are not yet
proven.

## 6. Operator Markers

The interactive tool accepts only:

```text
MARK DOCKED-INITIAL
MARK RUNNING
MARK DOCKING
MARK DOCKED-FINAL
STOP
```

Markers mean:

| Marker | Human observation |
| --- | --- |
| `DOCKED-INITIAL` | mower is visibly in the charging station before activity |
| `RUNNING` | mower is visibly mowing, not merely leaving the station |
| `DOCKING` | mower is visibly returning to the station |
| `DOCKED-FINAL` | mower is visibly back in the station |
| `STOP` | finish capture cleanly after sufficient evidence |

The tool ignores all other input and does not persist it. This prevents
free-text garden or installation details from entering evidence.

Markers record physical observation. They do not assert that REST or an MQTT
integer has the same meaning.

## 7. Session and Storage Bounds

Default duration:

```text
1800 seconds
```

Allowed duration:

```text
300 through 7200 seconds
```

Additional bounds:

| Boundary | Limit |
| --- | ---: |
| individual MQTT payload | 1 MiB |
| individual REST response | 1 MiB |
| accepted MQTT messages | 10,000 |
| combined persisted payload bytes | 64 MiB |
| disconnects before stop | 4 |

The payload-byte limit covers accepted MQTT payloads and persisted REST
response bodies. JSON envelope overhead and the separate sanitized copy add
bounded storage overhead beyond that counter.

Reaching an evidence bound ends the run without weakening filters.

## 8. Private Output

Each comparison run uses a new UTC session directory:

```text
private/navimow-capture/output/mqtt-comparison/<UTC-session-id>/
```

Raw evidence:

```text
raw/auth-token.json
raw/auth-list.json
raw/mqtt-user-info.json
raw/mqtt-messages.jsonl
raw/rest-status.jsonl
raw/operator-markers.jsonl
raw/mqtt-capture-report.json
```

Sanitized review candidates:

```text
sanitized/mqtt-credential-shape.json
sanitized/mqtt-messages.jsonl
sanitized/rest-status.jsonl
sanitized/operator-markers.jsonl
sanitized/mqtt-capture-report.json
```

Every session directory and generated evidence file is created under process
`umask 077`.

Raw files must never be shared or committed.

## 9. Sanitization Boundary

The existing sanitizer replaces or removes:

- OAuth and MQTT credentials;
- account, user, device, request and serial identifiers;
- WSS host and path;
- private mower names;
- real local and vendor absolute timestamps;
- real coordinates;
- map, path, point and boundary geometry;
- embedded Bearer tokens.

It preserves:

- JSON structure and value types;
- exact logical channel;
- placeholder device topic;
- relative receipt offset;
- synthetic vendor timestamps that retain only relative deltas and value type;
- state strings and numeric state values;
- battery and progress values when present.

Sanitized files are candidates, not automatically approved fixtures.

## 10. Static Validation

Run from the repository root:

```sh
NAVIMOW_CAPTURE_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-mqtt-rest-comparison.sh
```

The validation:

- checks Bash syntax separately;
- compiles the Python helper;
- validates exact topics;
- validates WSS and HTTPS endpoint restrictions;
- exercises sanitizer redaction;
- exercises REST state extraction;
- scans for an MQTT publish call;
- scans for mower command paths;
- performs no OAuth, REST or MQTT request.

Expected result:

```text
MQTT capture static topic, sanitizer and no-publish validation passed.
```

## 11. Live Preconditions

Before starting:

- the mower is initially docked;
- the mower, station and mowing area can be supervised as normally required;
- the official app remains available;
- the next activity is initiated only by the official schedule or app;
- at least 30 minutes are available, or the duration is deliberately adjusted;
- no other local comparison capture is active;
- raw output will remain private;
- no Symcon module update is required.

This is not a command safety test. The capture process cannot intervene if the
mower needs attention. Normal app and physical safety controls remain the
operator's responsibility.

## 12. Recommended Live Run

Start with the 30-minute default:

```sh
cd /Users/carsten/IT/Projekte/symcon-ai-framework
./private/navimow-capture/capture-mqtt-rest-comparison.sh
```

Then:

1. Open the printed Navimow login URL.
2. Complete login.
3. Copy the full localhost callback URL or authorization code.
4. Paste the OAuth client secret into the hidden prompt.
5. Paste the callback URL or authorization code into the hidden prompt.
6. Wait for WSS connection and four accepted subscriptions.
7. While the mower is visibly docked, enter `MARK DOCKED-INITIAL`.
8. Let the schedule start the mower or start it in the official app.
9. When normal mowing is visibly established, enter `MARK RUNNING`.
10. When return to station is visibly established, enter `MARK DOCKING`.
11. When the mower is visibly docked again, enter `MARK DOCKED-FINAL`.
12. Enter `STOP`.

Starting or returning the mower remains an independent operator action in the
official app or schedule. It is not caused by a marker.

If the normal cycle does not finish inside the selected duration, let the tool
end automatically. Do not extend the bound by modifying source during a run.
A later separately identified session may capture the return phase.

## 13. Optional Bounded Duration

For a known longer normal cycle:

```sh
NAVIMOW_MQTT_CAPTURE_SECONDS=7200 \
  ./private/navimow-capture/capture-mqtt-rest-comparison.sh
```

The two-hour maximum is an evidence bound, not a mower-operation limit.

The REST interval should normally remain 30 seconds. A different interval must
be explicitly selected before the run:

```sh
NAVIMOW_REST_COMPARE_INTERVAL=60 \
  ./private/navimow-capture/capture-mqtt-rest-comparison.sh
```

Intervals below 15 seconds are rejected to avoid aggressive polling.

## 14. Terminal Output

The terminal prints:

- connection and subscription progress;
- MQTT channel, channel sequence and payload byte count;
- REST state changes and relative time;
- accepted operator markers;
- final subscription, message and outcome counts.

It does not print:

- payload content;
- device ID;
- topic containing the real device ID;
- OAuth token;
- MQTT credentials;
- coordinates;
- mower name.

## 15. Pass Criteria

The comparison transport gate passes when:

- OAuth and discovery succeed;
- MQTT credentials are retrieved;
- TLS/WSS connection succeeds;
- all four exact topics are accepted;
- at least one successful REST status response is persisted;
- no MQTT publish is attempted;
- no mower command is attempted;
- the run ends within its bound;
- raw and sanitized evidence are separated.

An active-semantics gate additionally requires:

- a visible `RUNNING` marker;
- at least one active MQTT message;
- a contemporaneous successful REST Running observation;
- sufficient sanitized evidence to compare numeric and string states.

A full transition gate additionally requires:

- `DOCKING` and `DOCKED-FINAL` markers;
- corresponding REST observations;
- MQTT evidence spanning those phases.

The transport run may pass while either semantics gate remains incomplete.

## 16. Failure and Stop Rules

| Situation | Required handling |
| --- | --- |
| OAuth or discovery failure | stop before MQTT |
| WSS or TLS failure | stop; never weaken verification |
| subscription rejection | stop; never add a wildcard |
| REST status failure | record bounded error; keep MQTT receive-only |
| token expires during run | retain evidence; do not add an ad-hoc refresh loop |
| unknown MQTT channel | reject; do not broaden allowlist |
| invalid or oversized payload | reject and continue within bounds |
| evidence limit reached | stop cleanly |
| mower needs intervention | use normal app or physical controls; capture is secondary |
| insufficient transition evidence | retain partial session; plan another bounded run |

Never publish a probe, send a command from the capture tool or repeat a private
run by overwriting earlier evidence.

## 17. Comparison Method

The follow-up report must derive, per phase:

| Evidence | Required comparison |
| --- | --- |
| operator marker | physical phase and relative offset |
| MQTT message | channel, relative offset, numeric state, type and field set |
| REST response | relative offset, string state and battery shape |
| parser result | accepted, rejected, partial and out-of-order counts |

For each transition, calculate:

```text
MQTT latency = first relevant MQTT elapsedMs - operator marker elapsedMs
REST latency = first relevant REST elapsedMs - operator marker elapsedMs
```

Negative latency is not automatically invalid. The operator can observe and
enter a marker after the protocol transition. Such cases must be reported as
measurement uncertainty, not silently corrected.

No numeric MQTT state receives a semantic label from timing alone unless it is
repeated and unambiguous across the captured phase boundaries.

## 18. Architecture Decisions

### AD-NAV-319: Correlate all evidence on one monotonic timeline

**Decision:** Add `elapsedMs` to MQTT, REST and operator records.

**Rationale:** Sanitized absolute timestamps cannot support a reliable timing
comparison.

**Consequence:** Relative latency remains measurable without publishing the
real capture time.

### AD-NAV-320: Keep operator markers vocabulary-bound

**Decision:** Accept only four phase names and `STOP`.

**Rationale:** Free-text notes could disclose installation or garden details.

**Consequence:** Physical observations remain useful and privacy-bounded.

### AD-NAV-321: Poll REST only after exact subscriptions are active

**Decision:** Start comparison reads after all four MQTT subscriptions are
acknowledged.

**Rationale:** Every REST sample must belong to an active comparison window.

**Consequence:** A failed subscription creates no misleading REST-only run.

### AD-NAV-322: Preserve every private comparison session

**Decision:** Store each run under a new UTC session ID.

**Rationale:** Repeated evidence must not overwrite an earlier outcome.

**Consequence:** Closure and fixture promotion can identify one exact source
session.

### AD-NAV-323: Bound cumulative evidence volume

**Decision:** Stop after 64 MiB of accepted payloads or 10,000 MQTT messages.

**Rationale:** Per-message and duration limits alone do not bound a
high-frequency stream tightly enough.

**Consequence:** Active capture cannot grow without a deterministic ceiling.

### AD-NAV-324: Separate protocol reception from API business success

**Decision:** Count a REST comparison as successful only when HTTP/JSON parsing
succeeds and Navimow returns `code == 1`.

**Rationale:** HTTP 200 has already been observed for Navimow API error
semantics.

**Consequence:** Business failures cannot masquerade as valid state evidence.

## 19. Static Verification Result

The prepared private implementation passed:

| Check | Result |
| --- | --- |
| Bash syntax | PASS |
| Python compilation | PASS |
| no-network validation | PASS |
| exact MQTT topics | 4 |
| wildcard topics | 0 |
| MQTT publish path | none |
| mower command path | none |
| HTTPS comparison restriction | PASS |
| fixed marker vocabulary | PASS |
| unique session directory | PASS |
| private executable mode | `700` |
| private ignore coverage | PASS |
| productive distribution change | none |

No live OAuth, REST, MQTT, mower or Symcon action was performed by static
validation.

## 20. Decision

**Private active MQTT/REST comparison procedure: READY.**

**MQTT role: RECEIVE-ONLY.**

**REST role: READ-ONLY AUTHORITY AND COMPARISON.**

**Mower commands from capture tool: IMPOSSIBLE BY IMPLEMENTATION.**

**Productive MQTT integration: NO-GO pending live evidence.**

**Existing Symcon variables and archive logging: UNCHANGED.**

## 21. Recommended Next Step

Run one supervised normal activity comparison and return only the terminal
summary.

Then create:

```text
90-mqtt-active-rest-comparison-capture-report.md
```

The report must close private evidence, promote only minimal sanitized
fixtures, test the step-88 parser against active payloads and decide whether
numeric state mapping is sufficiently supported.
