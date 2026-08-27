# 355 Private Map Capture Security And Procedure Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Offline procedure design complete; implementation and live login
remain separately gated

**Date:** 2026-08-27

## 1. Objective And Boundary

This step designs one bounded private map capture against the undocumented
Navimow mobile-app cloud. It defines the future executable, transport policy,
credential lifecycle, evidence separation, sanitizer and cleanup checks.

This step performs no account login, region lookup, device registration,
token refresh, private API request, Symcon action, MQTT activation, mower
command, payload capture, dependency installation or publication. It creates
no executable client.

The future capture is **mower-command-free**, but it is not server-state-free:
the private `user/user/login` call registers an app device identity and creates
or changes vendor-side session state. Calling the procedure simply
"read-only" without this qualification would be misleading.

## 2. Inputs And Fixed Evidence

The design uses:

- the private-overlay rule from ADR-0003;
- the raw/sanitized separation and hidden-input conventions from steps 7, 57
  and 86;
- the exact source revision and private-cloud endpoint analysis from steps 353
  and 354;
- `candidate/MapGeometryReducer.php` for bounded geometry validation;
- the synthetic geometry fixture and test from step 353;
- upstream `ilguala/navimow_pro` revision
  `f25f418224681f67e2ad68693cded6c17b11dbe6` as attributed structural evidence.

No upstream runtime dependency or private protocol implementation is added by
this plan.

## 3. Threat Model

### 3.1 Protected Assets

- dedicated-account email and password;
- Passport access token, refresh token, UUID and region;
- private-cloud UID and persistent app device identity;
- mower serial number and private device metadata;
- vendor hosts and request metadata retained with a private session;
- exact home-relative zone polygons, obstacles, dock and local positions;
- map identifiers, area values and boundary attributes;
- the primary mobile-app session and shared-mower relationship.

### 3.2 Threats

| Threat | Required control |
|---|---|
| Secret enters shell history | Hidden interactive input; no credential CLI arguments |
| Secret enters process listing | No password or token arguments or environment export to child processes |
| Arbitrary endpoint or SSRF | Fixed HTTPS host, port and path allowlists; no live URL override |
| Command is sent accidentally | Transport denies every non-allowlisted method/path before network I/O |
| Retry registers another identity | Identity created once and persisted before login; no automatic regeneration |
| Primary app session is disturbed | Dedicated shared account is mandatory |
| Raw geometry reaches Git or chat | Ignored private output, mode `700/600`, explicit sharing warning |
| Sanitizer preserves garden shape | Public projection contains booleans and contract results, never vertices or areas |
| Error text leaks identifiers | Error classes are allowlisted; raw vendor descriptions remain private |
| Cleanup overclaims logout | Local cleanup and server-session limitation are reported separately |
| Upstream drift changes behavior | Exact source revision and local source hash recorded before implementation |
| Dependency supply-chain drift | Isolated private environment with pinned versions and recorded hashes |
| Unbounded response exhausts resources | Header, body, JSON depth, list and geometry limits enforced before persistence |

### 3.3 Explicit Non-Goals

The first capture does not:

- poll continuously;
- call MQTT or WSS;
- read trail, schedule, settings or maintenance endpoints;
- use compressed map detail;
- send Start, Stop, Pause, Resume, Dock or zone commands;
- create a productive private-cloud client;
- import geometry into Symcon;
- render or publish the private map;
- prove contractual or vendor support for the protocol.

## 4. Account And Consent Preconditions

Implementation may begin offline before these statements exist. Live execution
may not begin until the user confirms both statements in the same gate:

1. **Dedicated account:** The credentials belong to a dedicated second Navimow
   account to which the mower is shared; they are not the primary app account.
2. **Private-protocol acceptance:** The user accepts one bounded private-cloud
   authentication and map-read experiment despite the undocumented protocol,
   possible vendor-side session retention and absence of a proven logout or
   revocation endpoint.

The confirmation authorizes one attempt only. It does not authorize retries,
continuous operation, productive integration or publication.

Before the terminal confirmation, the procedure displays:

```text
This run registers one persistent app device identity for a dedicated shared
account and performs only the fixed authentication and map-read calls.
No mower command exists. Local cleanup cannot prove server-side logout.
Type PRIVATE MAP ONCE to continue:
```

Any other input exits before credentials are requested or identity is created.

## 5. Planned Private Files

Future implementation is confined to the ignored private overlay:

```text
private/navimow-capture/capture-private-map-readonly.sh
private/navimow-capture/capture_private_map_readonly.py
private/navimow-capture/reduce-private-map.php
private/navimow-capture/private-map-third-party-notice.md
private/navimow-capture/.venv-private-map/
private/navimow-capture/state/private-map-device-identity.json
private/navimow-capture/output/private-map/<SESSION_ID>/
```

The public case study receives only procedure documentation, synthetic tests
and a structure-only report after separate review. Real map geometry is never a
public fixture.

### 5.1 Reuse Before Extend

The future wrapper reuses the established private conventions:

- `set -euo pipefail` and `umask 077`;
- hidden input without command-line secrets;
- mode-`700` directories and mode-`600` files;
- one exclusive lock and an immutable session identifier;
- a validation-only path that exits before network access;
- raw, private projection and sanitized report separation;
- machine-readable request and command-attempt counters;
- explicit no-share warnings for raw output.

`MapGeometryReducer` performs geometry bounds and polygon validation through a
small private PHP wrapper. The transport helper must not duplicate its polygon
algorithm.

The existing MQTT sanitizer cannot be reused for map evidence because it
intentionally replaces every geometry field with `REDACTED_GEOMETRY`. A small
private map-specific structure reporter is justified, but it remains
implementation-local until another source demonstrates reuse.

## 6. Narrow Client And Provenance Contract

The future private helper must not execute the complete `navimow_pro` client,
because that source also contains mower command methods. Instead it implements
only the fixed authentication/envelope primitives and endpoint operations
listed in this plan.

Any implementation derived from the reviewed MIT source must:

- remain under the ignored private overlay;
- retain the MIT notice and source repository URL;
- record the exact reviewed commit;
- identify every adapted source file in
  `private-map-third-party-notice.md`;
- avoid copying Home Assistant integration, entities, UI or command code;
- pass a source scan proving no `/vehicle/set/` path or mower command token is
  present;
- use an operation enum mapped internally to exact request methods and paths;
- reject arbitrary path, host, scheme and port input.

The cryptography dependency is installed only after a separate implementation
gate into `.venv-private-map/`, pinned to an exact version and recorded hash.
No system Python or SAEF dependency is modified.

This design does not conclude whether implementing or running the private
protocol is permitted by vendor terms. That acceptance remains a live-gate
input rather than an engineering inference from the upstream MIT license.

## 7. Transport Policy

### 7.1 Region And Host Selection

The user selects the known account region from a fixed list before entering
credentials. Automatic region discovery is excluded from the first capture so
the account email is not sent to several regional services.

Each supported region maps to exact Passport and mower-cloud hosts compiled
into the private helper from the reviewed source revision. Live environment
variables cannot override these hosts. Unknown regions abort.

Every request requires:

- `https`;
- TCP port `443`;
- system CA verification and hostname verification;
- an exact allowlisted host;
- an exact method/path pair;
- no cross-host redirect;
- a 20-second per-request timeout;
- a 180-second absolute process deadline.

HTTP response headers are bounded to 32 KiB and are not persisted. A response
body is rejected before JSON decoding when it exceeds the endpoint-specific
limit.

### 7.2 Exact Operation Allowlist

| Operation | Method and path | Max attempts | Body limit |
|---|---|---:|---:|
| Passport login | `POST /v3/user/login` | 1 | 256 KiB |
| App device registration | `POST /user/user/login` | 1 | 256 KiB |
| Vehicle discovery | `POST /vehicle/vehicle/auth-list` | 1 | 1 MiB |
| Current location and map ids | `POST /vehicle/vehicle/get-location` | 1 | 1 MiB |
| Map list fallback | `POST /map/index/map-list` | 0 or 1 | 1 MiB |
| Plain map detail | `POST /map/index/map-detail` | 1 | 4 MiB |

`map-list` is attempted only when `get-location` lacks both required map
identifiers. The absolute maximum is six private-cloud requests. No endpoint
is retried after timeout, transport ambiguity, authentication error, business
error or malformed response.

Token refresh, region discovery, compressed map fallback and station-map are
not allowed. Every path containing `/vehicle/set/` is rejected before request
serialization.

### 7.3 Request Accounting

The attempt counter is incremented and durably written before every network
request. The final private report records:

```json
{
  "passportLoginAttempts": 0,
  "deviceRegistrationAttempts": 0,
  "discoveryAttempts": 0,
  "locationAttempts": 0,
  "mapListAttempts": 0,
  "mapDetailAttempts": 0,
  "tokenRefreshAttempts": 0,
  "mqttAttempts": 0,
  "writeEndpointAttempts": 0,
  "mowerCommandAttempts": 0
}
```

Only the six read/authentication counters may become `1`. The last four must
remain `0`.

## 8. Credential And Identity Lifecycle

### 8.1 Input

- email is read interactively and treated as private;
- password is read with terminal echo disabled;
- neither value is accepted from command-line arguments;
- password is not exported to child-process environment;
- the helper keeps it in process memory only until Passport login completes;
- buffers and shell variables are cleared on the normal path and trap path as
  far as the runtime permits.

No claim of perfect memory erasure is made for Python or shell strings.

### 8.2 Persistent Device Identity

After typed confirmation and before Passport login, the wrapper:

1. acquires the exclusive lock;
2. creates or reads one mode-`600` identity file;
3. validates one UUID-shaped private device identity;
4. records its hash, not its value, in the capture report;
5. refuses to replace it automatically.

If an identity already exists, the wrapper reports reuse without displaying
the value. Corrupt or ambiguous identity state aborts before login.

### 8.3 Tokens And UID

Passport tokens, account UUID and private-cloud UID remain in the single Python
process. Login responses are not written as raw JSON. A crash therefore leaves
the stable identity and attempt report but no intentionally persisted token
bundle.

If later evidence proves that a multi-process retry requires token persistence,
that is a new design and consent gate. It is not part of this procedure.

### 8.4 Local And Server-Side Cleanup

The trap always:

- clears temporary request files;
- removes any transient credential file;
- closes the HTTPS connection;
- writes a bounded termination class;
- releases the lock only after the report is flushed.

The persistent private device identity is retained after the attempt to avoid
silently creating a second vendor-side identity. Removing it requires a
separate retention decision after the case study is closed.

Local cleanup cannot prove server-side token revocation, logout or device
deregistration. The final report therefore has separate fields:

```json
{
  "localCredentialArtifactsPresent": false,
  "stableDeviceIdentityRetained": true,
  "serverSessionClosureProven": false
}
```

## 9. Output And Retention Layout

```text
output/private-map/<SESSION_ID>/
  attempt-state.json
  raw/
    auth-list.json
    get-location.json
    map-list.json                 optional
    map-detail.json
  private/
    map-geometry-projection.json
    validation-report.json
  sanitized/
    map-structure-report.json
    capture-report.json
```

All directories are mode `700`; files are mode `600`. `raw/` and `private/`
must never be shared. Sanitized files remain review candidates and are not
automatically public.

The attempt report is created before credentials are requested. Raw files are
written atomically through a same-directory temporary file, bounded before
rename and never overwritten by a later session.

Retention decisions are separate:

- raw map payload: delete after private reducer verification unless a bounded
  retention gate explicitly preserves it;
- private reduced projection: retain only as installation geometry evidence;
- structure-only sanitized report: retain for public review after scan;
- stable device identity: retain privately until server-session and retry
  decisions are closed.

## 10. Geometry Validation Pipeline

The map response is never sent directly to a renderer. The future sequence is:

1. validate response size, UTF-8 and JSON depth;
2. extract the `map_detail` object or JSON string without logging it;
3. pass it to the existing `MapGeometryReducer` through the private PHP wrapper;
4. store the full reduced projection only under `private/`;
5. compare the optional charging-station point with the single docked location
   observation privately;
6. produce a structure-only sanitized report;
7. scan sanitized files against collected secret and identifier values;
8. require an independent human review before any public promotion.

The first live attempt requires the mower to be docked according to the
official app. It sends no command and does not wait for or induce movement. A
single docked location is correlation evidence only; it does not by itself
prove metric scale or long-term frame stability.

The reducer must reject the capture when zone, element, point, coordinate,
ring, self-intersection or total-size limits from step 353 fail. A rejected
geometry remains private failure evidence and cannot be partially promoted.

## 11. Sanitized Structure Report

Real garden shape remains identifying after translation, rotation or uniform
scaling. Consequently, sanitization does not transform real vertices into a
public polygon. The public candidate contains only allowlisted booleans,
contract versions and bounded result classes.

Proposed form:

```json
{
  "schemaVersion": 1,
  "source": "private-app-cloud-map-detail",
  "sourceRevisionPinned": true,
  "captureOutcome": "completed",
  "mowerCommandAttempts": 0,
  "writeEndpointAttempts": 0,
  "mapDetailPresent": true,
  "mapDetailWithinByteLimit": true,
  "zonesPresent": true,
  "allZoneIdsValid": true,
  "allBoundariesValid": true,
  "reportedAreaPresent": true,
  "stationPresent": true,
  "obstaclesFieldPresent": true,
  "visionOffAreasFieldPresent": true,
  "coordinateFrame": "navimow-local-map-candidate",
  "privateProjectionCreated": true,
  "privateValuesRetained": false
}
```

The report does not include:

- exact zone, obstacle or point counts;
- zone ids, names or area values;
- map ids, serials or device identities;
- coordinates, extents, orientation or polygon hashes;
- timestamps more precise than a normalized capture date;
- hosts, account region or vendor error descriptions;
- access, refresh or session metadata.

Errors use fixed classes such as `transport_error`, `authentication_error`,
`business_error`, `invalid_envelope`, `geometry_rejected` and
`cleanup_incomplete`. Raw messages remain private.

## 12. Static Validation Contract

The future implementation must support:

```sh
NAVIMOW_PRIVATE_MAP_VALIDATE_ONLY=1 \
  ./private/navimow-capture/capture-private-map-readonly.sh
```

Validation-only mode performs no DNS lookup, network access, dependency
installation or identity creation. It must prove:

1. every allowed operation maps to the exact method/path table;
2. arbitrary paths, hosts, schemes, ports and redirects are rejected;
3. `/vehicle/set/`, MQTT and command operations cannot be dispatched;
4. the maximum attempt count is six;
5. attempt accounting happens before synthetic dispatch;
6. malformed, oversized, deeply nested and non-object responses are rejected;
7. hidden-input values do not enter arguments, reports or logs;
8. the existing synthetic map fixture passes the reducer;
9. self-intersecting and over-limit fixtures fail closed;
10. the structure reporter contains no coordinates, ids, names, areas, hosts,
    exact counts or secret-shaped values;
11. local cleanup reports separately from unproven server-session closure;
12. no public or productive file is mutated.

Source-level checks additionally require:

```text
/vehicle/set/ occurrences in the private client: 0
MQTT library imports: 0
publish/send-command methods: 0
arbitrary live endpoint overrides: 0
```

Synthetic tests may contain forbidden strings only as rejection probes and
must verify that they do not survive output.

## 13. Live Procedure State Machine

```text
Prepared
  -> Confirmed
  -> IdentityReady
  -> PassportAuthenticated
  -> DeviceRegistered
  -> MowerSelected
  -> LocationRead
  -> MapResolved
  -> MapCaptured
  -> GeometryValidated
  -> Sanitized
  -> LocallyCleaned
  -> Completed
```

Any failure moves directly to `LocallyCleaning`, then either
`FailedCleanly` or `CleanupIncomplete`. There is no transition back to an
earlier network state and no automatic retry.

The attempt is consumed once `Passport login` dispatch is durably recorded,
even if no response arrives. Ambiguous transport results must not be repeated.

## 14. Live Pass And Failure Criteria

### 14.1 Pass

The first capture passes only when:

- the exact dedicated-account and risk statements were recorded;
- one stable private identity was used;
- each required operation was attempted no more than once;
- exactly one intended mower was selected without publishing its identity;
- plain map detail was received within 4 MiB;
- `MapGeometryReducer` accepted the complete geometry;
- private projection and structure-only report were written atomically;
- command, write-endpoint, MQTT and token-refresh counts are zero;
- local temporary credentials are absent after cleanup;
- sanitized output passes automated and human privacy review;
- the report states that server-side session closure is unproven.

### 14.2 Non-Pass Outcomes

- no map ids or plain map detail: `map_unavailable`;
- compressed-only response: `plain_map_unavailable`, with no fallback;
- auth/session error: `authentication_error`, with no refresh;
- timeout after dispatch: `ambiguous_transport`, with no retry;
- malformed or over-limit payload: `invalid_or_oversized_payload`;
- reducer rejection: `geometry_rejected`;
- residual temporary credential artifact: `cleanup_incomplete`.

No non-pass outcome authorizes another attempt.

## 15. Architecture Decisions

### AD-NAV-355-01: Name authentication mutation explicitly

**Decision:** Classify the future run as command-free and map-read-only after a
vendor-side app-device registration, not as mutation-free.

**Reason:** Accurate risk language is required for informed authorization.

### AD-NAV-355-02: Deny by transport policy, not caller discipline

**Decision:** The transport maps a closed operation enum to exact endpoints and
rejects all other requests before network I/O.

**Reason:** A procedural promise not to call a command is weaker than making a
command path unrepresentable.

### AD-NAV-355-03: Keep tokens process-local

**Decision:** The first capture persists the stable device identity but not
tokens, UID or password.

**Reason:** One process can complete the bounded sequence without introducing a
token-at-rest lifecycle.

### AD-NAV-355-04: Never sanitize real shape into a public polygon

**Decision:** Public evidence is structure-only. Real geometry remains private
even after geometric normalization.

**Reason:** Garden outlines can identify a property independently of absolute
coordinates.

### AD-NAV-355-05: Reuse the existing reducer

**Decision:** Invoke the step-353 reducer through a private wrapper rather than
creating a second polygon validator.

**Reason:** Bounds and geometry semantics require one testable owner.

## 16. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Security and procedure design | **PASS offline** |
| Private client implementation | **NO-GO pending separate gate** |
| Dependency installation | **NO-GO** |
| Dedicated account | **Unconfirmed** |
| Private-protocol risk acceptance | **Unconfirmed** |
| Live Passport login | **NO-GO** |
| Vendor-side device registration | **NO-GO** |
| Live map capture | **NO-GO** |
| Productive or Symcon integration | **NO-GO** |
| Public geometry fixture | **REJECTED** |

The recommended next step is
`356-private-map-capture-tool-implementation.md`. With a fresh offline-only
implementation gate it may create the ignored private executable, pinned
dependency closure and synthetic validation suite. It must still stop before
dependency installation, identity creation or network access.

Only after that implementation and static review may the user separately
confirm the dedicated shared account and accept one vendor-side registration
and capture attempt.
