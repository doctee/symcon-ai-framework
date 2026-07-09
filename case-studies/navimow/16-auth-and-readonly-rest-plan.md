# 16 Authentication and Read-Only REST Plan

**Case study:** Navimow native IP-Symcon module  
**Status:** Implementation plan after successful loader gate  
**Date:** 2026-07-09  
**Build boundary:** This step plans OAuth, discovery and read-only status
polling. It adds no live API implementation, MQTT/WSS or mower commands.

## 1. Purpose

This document defines the next implementation slice after the dedicated module
distribution passed its direct IP-Symcon loader test.

The approved slice is deliberately read-only:

1. establish authenticated REST transport;
2. exchange and refresh OAuth tokens;
3. discover account devices;
4. read mower status;
5. update the existing account and device variables defensively.

The plan turns the fixture-backed API knowledge into ordered work packages,
test gates and explicit failure behavior before live cloud code is added.

## 2. Entry Conditions

The implementation may start because:

- the dedicated distribution repository loads in IP-Symcon;
- account, device and configurator instances pass lifecycle checks;
- all configuration forms return valid JSON;
- profiles and contracted variables are created;
- sanitized fixtures cover token success, discovery, docked status, mowing
  status and invalid-token behavior;
- the pure payload mapper passes its current fixture checks.

The authoritative loader result is recorded in
`15-loader-fix-report.md`.

## 3. Scope

### In scope

- form-encoded OAuth authorization-code exchange;
- refresh-token exchange;
- persistent account-owned token state;
- token expiry and refresh scheduling;
- authenticated JSON REST requests;
- device discovery via `/openapi/smarthome/authList`;
- status reads via `/openapi/smarthome/getVehicleStatus`;
- synchronous parent/child messages for discovery and status;
- fixture tests and transport-level tests without live credentials;
- one supervised private live test after all local gates pass.

### Out of scope

- `/openapi/smarthome/sendCommands`;
- all mower actions except local read-only refresh;
- command diagnostics and verification timers;
- MQTT/WSS and MQTT credential retrieval;
- maps, location and high-frequency telemetry;
- automatic retries that could amplify cloud traffic;
- unattended live API tests;
- public release or Module Store submission.

## 4. Confirmed REST Contract

| Method | Endpoint | Request | Success evidence |
| --- | --- | --- | --- |
| `POST` | `/openapi/oauth/getAccessToken` | form encoded OAuth fields | token fixture confirms access token, refresh token, token type and expiry |
| `GET` | `/openapi/smarthome/authList` | bearer token and `requestId` header | fixture confirms `data.payload.devices[]` |
| `POST` | `/openapi/smarthome/getVehicleStatus` | JSON `{"devices":[{"id":"..."}]}` | docked and mowing fixtures confirm status shape |

Authenticated REST headers:

```text
Authorization: Bearer <access token>
Content-Type: application/json
requestId: <new request identifier>
```

The token endpoint uses:

```text
Content-Type: application/x-www-form-urlencoded
```

HTTP status alone is not sufficient. A response with HTTP `200` and API code
`4005` is an authentication failure.

## 5. Architecture Decisions

### AD-NAV-023: Separate transport, protocol and Symcon state

**Decision:** Keep HTTP transport in `ApiClient`, payload interpretation in
`PayloadMapper` and variable/state transitions in the module classes.

**Rationale:** Fixture mapping can remain deterministic, transport failures can
be tested independently, and the HTTP client does not gain ownership of
Symcon variables.

**Consequence:** `ApiClient` returns typed result envelopes or throws
classified transport/protocol exceptions. It never calls `SetValue()`.

### AD-NAV-024: Account owns all authentication material

**Decision:** Access token, refresh token, token expiry and OAuth transaction
state remain persistent internal attributes of `NavimowAccount`.

The device and configurator receive only sanitized discovery, status or error
results. Tokens never cross the parent/child interface.

**Rationale:** Authentication is account state shared by all mowers.

**Consequence:** `NavimowDevice` and `NavimowConfigurator` must not construct
authorization headers or invoke `ApiClient` directly.

### AD-NAV-025: First login uses a supervised manual code handoff

**Decision:** The first implementation mirrors the proven private capture
workflow:

1. generate and show the Navimow login URL;
2. open it in the user's browser;
3. accept the resulting redirect URL or authorization code through a temporary
   password-style form field;
4. exchange the code only after an explicit user action;
5. clear the temporary code immediately after the exchange attempt.

No Symcon webhook or public callback endpoint is introduced in this slice.

**Rationale:** The manual handoff is already proven and avoids opening a new
HTTP endpoint before authentication behavior is stable.

**Consequence:** Login is supervised and not fully automatic. Callback state
handling must be validated in the first private live test.

### AD-NAV-026: No automatic REST retry in the first read-only slice

**Decision:** Each scheduled operation performs at most one HTTP request.
Token refresh may be attempted once before an authenticated read only when the
access token is already expired or inside the refresh margin.

**Rationale:** The undocumented cloud API has no confirmed rate-limit or
idempotency contract. Explicit timer-driven attempts are easier to diagnose.

**Consequence:** Temporary failures remain visible until the next scheduled or
manual refresh. Later bounded retry behavior requires separate evidence.

## 6. Authentication Configuration

The account form needs these fields:

| Field | Storage | Rule |
| --- | --- | --- |
| `BaseUrl` | property | HTTPS required; default remains the confirmed FRA API host |
| `ClientId` | property | default may be `homeassistant` if retained from confirmed upstream behavior |
| `ClientSecret` | password-style property | empty by default; never committed or logged |
| `RedirectUri` | property | must exactly match the URI used to obtain the authorization code |
| `PollInterval` | property | minimum 60 seconds; recommended default 300 seconds |
| temporary authorization input | form action state | not a public variable and not retained after exchange |

Security clarification:

- password-style controls reduce accidental display but do not prove encrypted
  storage;
- module attributes and properties are persistent internal state, not a
  hardware-backed secret vault;
- backups and administrative Symcon access must therefore be treated as
  security-sensitive;
- no secret value may appear in debug output, exception text or form captions.

The public repository must not contain a real client secret. Private test
configuration remains on the Symcon installation.

## 7. Authentication State Machine

| Event | Preconditions | Success transition | Failure transition |
| --- | --- | --- | --- |
| Apply configuration | valid HTTPS URL, client fields present | `Authorization Pending` or token evaluation | `Configuration Error` |
| Exchange code | explicit user action and non-empty code | store tokens, schedule refresh, `Connected` | clear code, `Reauth Required` |
| Refresh token | refresh token present | replace returned tokens atomically, reschedule | retain no unusable access state, `Reauth Required` |
| Authenticated read | usable access token | preserve `Connected` after API success | classified warning, offline or reauth state |
| API code `4005` | any authenticated request | none | clear unusable access token, set `ReauthRequired=true` |
| Logout/reset | explicit user action | clear all token state, disable timers | `Unconfigured` |

Required public state updates:

| Condition | `ConnectionState` | `ReauthRequired` | `TokenExpiresAt` |
| --- | --- | --- | --- |
| configuration incomplete | `Unconfigured` or `Configuration Error` | `true` | `0` |
| waiting for code | `Authorization Pending` | `true` | `0` |
| exchange/refresh active | `Authenticating` | unchanged | unchanged |
| authenticated API success | `Connected` | `false` | calculated expiry |
| invalid OAuth information | `Reauth Required` | `true` | `0` if token is unusable |
| transport unavailable | `Offline` | preserve | preserve |
| non-auth API failure | `API Warning` | preserve | preserve |

## 8. Token Storage and Refresh

Internal account attributes:

```text
AccessToken
RefreshToken
TokenExpiresAtInternal
OAuthState
DiscoveryCache
```

Rules:

- write access and refresh tokens only after the complete token response is
  validated;
- update token attributes as one logical operation;
- preserve the previous refresh token when a valid refresh response omits a
  replacement;
- calculate expiry from local receipt time plus `expires_in`;
- publish only the calculated timestamp through `TokenExpiresAt`;
- schedule refresh with a five-minute safety margin where token lifetime
  permits;
- never schedule a negative or immediate tight-loop interval;
- disable the refresh timer when no usable refresh token exists;
- serialize exchange, refresh and authenticated requests with an
  instance-scoped semaphore;
- release the semaphore in `finally`.

For the observed `expires_in=3600`, the target refresh time is approximately
55 minutes after receipt.

## 9. API Client Contract

`ApiClient` should expose narrow methods or one internal request primitive
supporting:

```text
exchangeAuthorizationCode(...)
refreshAccessToken(...)
getAuthorizedDevices(...)
getVehicleStatus(...)
```

Required transport behavior:

- HTTPS URL validation;
- explicit connect and total timeouts;
- TLS verification enabled;
- form encoding for OAuth;
- JSON encoding for status;
- fresh `requestId` per authenticated request;
- bounded response size where supported;
- capture HTTP status and response body separately;
- reject malformed JSON;
- classify DNS, connection, timeout, TLS, HTTP and API-code failures;
- redact secrets before diagnostics are returned or logged.

Initial timeout targets:

| Timeout | Target |
| --- | --- |
| connect | 10 seconds |
| complete request | 30 seconds |

These are starting values for supervised testing, not an API guarantee.

## 10. API Response Classification

The response pipeline must evaluate in this order:

1. transport completed;
2. HTTP status is acceptable;
3. body is valid JSON;
4. top-level API `code` indicates success;
5. expected payload shape exists;
6. mapper accepts required fields.

Error classes:

| Class | Example | State effect |
| --- | --- | --- |
| configuration | invalid URL or missing client data | `Configuration Error` |
| transport | DNS, timeout, TLS, connection failure | `Offline` |
| HTTP | non-success HTTP status | `API Warning` or `Offline` by class |
| authentication | API `4005` / `CODE_OAUTH_INFO_ILLEGAL` | `Reauth Required` |
| API | known HTTP success but API code not successful | `API Warning` |
| payload | malformed JSON or missing required structure | `API Warning` |

Sanitized diagnostics may include:

- operation name;
- HTTP status;
- API code;
- API description after length limiting;
- exception class;
- request identifier;
- timestamp.

They must not include request headers, form bodies, authorization codes,
tokens, client secrets or complete raw error payloads.

## 11. Parent/Child Read Contract

The existing interface GUID remains unchanged.

Child-to-account messages:

| Function | Caller | Payload | Account result |
| --- | --- | --- | --- |
| `GetDiscovery` | configurator | none | sanitized device list |
| `GetStatus` | device | `deviceId` | sanitized mapped status result |
| `Refresh` | device | `deviceId` | same as `GetStatus` |

Every message envelope must include the interface `DataID`, function name and
schema version.

Rules:

- reject unknown functions;
- validate `deviceId` as a non-empty bounded string;
- do not return token or request-header data;
- return explicit success/error envelopes;
- keep command-related message names rejected in this slice.

The account poll timer should trigger connected device children to request
their status. It must not inspect child properties through hidden ObjectIDs.

## 12. Discovery Behavior

After successful authentication:

1. call `GET /openapi/smarthome/authList`;
2. require top-level API success;
3. map `data.payload.devices[]`;
4. require a non-empty string `id` per retained device;
5. retain only `id`, `name`, `model` and `firmware`;
6. store the sanitized result as bounded account-owned discovery cache;
7. update `LastDiscovery` and `LastRestSuccess`;
8. make the cache available to the configurator.

Discovery must not automatically create device instances. The configurator
presents candidates and lets the user create or match instances.

An empty successful list is valid and must not reuse stale devices as though
they were current. A failed discovery preserves the last known cache only as
stale diagnostic data.

## 13. Read-Only Status Behavior

For each configured device:

1. validate the configured device ID;
2. request status for that device;
3. require top-level API success;
4. match the returned device by `id` or the documented fallback ID;
5. map the payload through `PayloadMapper`;
6. update only fields that passed validation;
7. set `LastStatusUpdate` after a valid device payload was applied;
8. update account `LastRestSuccess`;
9. optionally store bounded sanitized JSON only when debug mode is enabled.

Variable rules:

- `VehicleState`: map known strings; unknown values become `Unknown`;
- `BatteryLevel`: update only with an integer from 0 through 100;
- `Online`: set true after a valid fresh status response, false for confirmed
  `Offline`; transient request failure alone does not immediately prove the
  mower is offline;
- missing fields preserve their previous valid values;
- invalid whole payload updates no device domain variable.

Freshness policy:

- mark `Online=false` when no valid status has been received for more than two
  configured poll intervals;
- use a minimum stale threshold of five minutes;
- document that `Online` represents REST status freshness until a dedicated
  connectivity field is fixture-backed.

## 14. Timers and Concurrency

### `RefreshToken`

- enabled only with a usable refresh token and expiry;
- recalculated after every successful token response;
- disabled while configuration is invalid or reauthentication is required.

### `PollStatus`

- enabled only when authentication is usable;
- interval derives from `PollInterval`;
- minimum 60 seconds;
- disabled when account configuration is invalid;
- one timer tick must not overlap another.

Concurrency rules:

- one account-scoped semaphore protects token exchange, refresh and REST use;
- re-check token validity after acquiring the semaphore;
- never wait indefinitely for a lock;
- a lock timeout becomes a sanitized warning, not another HTTP request;
- timer handlers must terminate cleanly after failure.

## 15. Implementation Work Packages

### WP16.1: Pure protocol and mapper hardening

- add API success-envelope validation;
- add strict token value extraction, not only boolean token presence;
- add discovery and status shape errors;
- add sanitization helpers local to the implementation;
- add fixture tests for malformed and missing fields.

**Gate:** All tests run without IP-Symcon and without network access.

### WP16.2: HTTP transport

- implement OAuth form POST;
- implement authenticated GET and JSON POST;
- add timeout, TLS and response classification;
- inject or wrap transport so deterministic tests do not call the internet.

**Gate:** Transport tests prove headers, encoding, timeout classification and
secret redaction with a fake transport.

### WP16.3: Account authentication

- add account form fields and explicit login/reset actions;
- implement manual authorization-code parsing;
- implement token exchange and internal storage;
- implement refresh scheduling and auth state transitions.

**Gate:** Fixture/fake-transport tests prove successful exchange, refresh,
missing refresh token behavior, API `4005`, reset and timer calculation.

### WP16.4: Discovery

- implement `authList`;
- store bounded sanitized cache;
- return `GetDiscovery` through the parent interface;
- populate the configurator without automatic instance creation.

**Gate:** Fixture-backed discovery displays only sanitized device metadata.

### WP16.5: Read-only device status

- implement `GetStatus` and manual `Refresh`;
- apply mapped status in `NavimowDevice`;
- implement status freshness semantics;
- activate conservative account polling.

**Gate:** Docked and mowing fixtures produce the contracted values; malformed
payloads preserve previous domain state.

### WP16.6: Supervised private live test

- configure private client data only in IP-Symcon;
- perform one authorization-code exchange;
- verify discovery;
- verify one docked status read;
- verify token refresh or a safely accelerated refresh schedule;
- review sanitized Symcon logs;
- remove any temporary authorization code.

**Gate:** No token, secret, real device ID or raw private payload enters Git or
the case-study documents.

## 16. Verification Matrix

| Test | Local fixture/fake | Direct Symcon | Live cloud |
| --- | --- | --- | --- |
| token response parsing | required | required | supervised |
| refresh scheduling | required | required | supervised |
| invalid token API `4005` | required | required | optional; fixture preferred |
| discovery mapping | required | required | supervised |
| docked status mapping | required | required | supervised |
| mowing status mapping | required | optional | only when operationally safe |
| transport timeout | required fake | optional | do not induce deliberately |
| secret redaction | required | required log review | required log review |
| commands absent | static check | required | required |
| MQTT absent | static check | required | required |

## 17. Failure Safety

The implementation must:

- never clear valid device state because one request failed;
- never retry a mower command because commands do not exist in this slice;
- never send a request without a fresh request identifier;
- never loop token refresh immediately after failure;
- disable polling when reauthentication is required;
- avoid logging raw request or response bodies by default;
- keep debug payload storage disabled by default and size-bounded when enabled;
- preserve deterministic cleanup after exceptions and semaphore failures.

## 18. Definition of Done

This implementation slice is complete when:

- authorization code exchange works through an explicit supervised action;
- access and refresh tokens remain account-owned internal state;
- token refresh survives ApplyChanges and runtime restart;
- API `4005` transitions to `Reauth Required`;
- discovery populates the configurator from sanitized metadata;
- a device can refresh docked and mowing status read-only;
- account and device diagnostics follow the existing contract;
- timers are bounded and non-overlapping;
- local tests need no credentials or network;
- direct Symcon and supervised live tests pass;
- no command, MQTT/WSS, map or location behavior is present;
- the dedicated distribution repository is updated from the canonical
  case-study distribution and passes the structure validator.

## 19. Risks and Open Questions

| Item | Impact | Required resolution |
| --- | --- | --- |
| OAuth client secret distribution | Blocks a public release decision | Keep private for MVP; define lawful and supportable distribution before release |
| Redirect URI acceptance | Can block login UX | Verify exact URI and callback-state behavior in supervised test |
| Token attributes are not proven encrypted | Backup/admin exposure | Document security boundary; never expose through variables/logs |
| Refresh response may omit refresh token | Could break future refresh | Preserve prior valid refresh token when omission is confirmed acceptable |
| Cloud rate limits are unknown | Polling could be throttled | Keep 300-second default, no immediate retries |
| Dedicated online field remains unknown | `Online` is inferred | Label semantics as REST freshness |
| Multi-device response behavior | Could misroute status | Match by device ID and test before batching |
| API stability is undocumented | Future schema changes | Strict mapper, unknown fallback and sanitized diagnostics |

## 20. Recommendation and Next Step

**Recommendation:** Proceed with WP16.1 and WP16.2 only in the next
implementation step.

This first code change should harden pure protocol parsing and implement an
injectable HTTP transport with fake-transport tests. It should not yet require
private credentials or make a live Navimow request.

Recommended next SAEF artifact:

```text
case-studies/navimow/17-rest-client-and-auth-implementation.md
```

That step may implement WP16.1 through WP16.3 only after the transport tests
pass. Discovery and status polling remain separately gated.
