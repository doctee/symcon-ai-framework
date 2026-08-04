# 102 Native MQTT Credential Endpoint Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Read-only MQTT credential retrieval and validation complete
offline; persistence, transport lifecycle, publication and live testing remain
blocked
**Date:** 2026-07-28
**Scope:** Execute `WP-7` from the approved MQTT shadow implementation plan

## 1. Purpose

This step implements the smallest credential boundary required by the future
owned native MQTT transport.

It adds:

- one authenticated read-only REST method;
- one separate MQTT credential mapper;
- strict WSS endpoint composition and validation;
- synthetic success and failure fixtures;
- exhaustive secret-redaction tests.

It does not:

- expose an Account action;
- store a credential in an attribute;
- write a credential to debug output;
- create a WebSocket Client;
- create an MQTT Client;
- configure or connect a transport;
- subscribe to a topic;
- publish MQTT;
- update a public variable;
- send a mower command.

## 2. REST Contract

Added to `ApiClient`:

```text
getMqttUserInfo(string accessToken): array
```

The exact request is:

```text
method:
GET

path:
/openapi/mqtt/userInfo/get/v2

headers:
Accept: application/json
Authorization: Bearer <access token>
requestId: <generated UUID>

body:
none
```

The method reuses the existing bounded authorized-request implementation:

- HTTPS-only base URL;
- certificate verification;
- 10-second connect timeout;
- 30-second request timeout;
- one MiB response limit;
- no redirect;
- no internal retry;
- generic body-free transport errors.

## 3. Evidence-backed Response Shape

Private structural inspection confirmed these response fields:

```text
code
desc
data.mqttHost
data.mqttUrl
data.userName
data.pwdInfo
```

It also confirmed that `mqttUrl` may contain only a relative path and query.
In that case, `mqttHost` supplies the WSS origin.

No private value, length, hostname, credential or query was copied into this
case-study artifact.

## 4. Separate Mapper

Added:

```text
distribution/libs/Navimow/MqttCredentialMapper.php
```

The mapper is separate from `PayloadMapper` because:

- its result contains credentials;
- it owns endpoint composition;
- it has security-specific limits;
- its output must never become Device payload data.

Result:

```text
wssUrl
mqttUsername
mqttPassword
```

No client ID is accepted from the cloud response. A future transport lifecycle
must generate it locally.

## 5. Endpoint Composition

The mapper accepts both evidenced forms.

Absolute:

```text
mqttUrl:
wss://mqtt.example.test/mqtt?ticket=SYNTHETIC
```

Relative:

```text
mqttHost:
wss://mqtt.example.test

mqttUrl:
/mqtt?ticket=SYNTHETIC
```

The relative form is combined only after validating that `mqttHost` is a
credential-free WSS origin.

## 6. Validation

Required:

- API `code` exactly `1`;
- response `data` object;
- non-empty string `mqttHost`;
- non-empty string `mqttUrl`;
- non-empty string `userName`;
- non-empty string `pwdInfo`;
- no control character in any required value;
- final scheme exactly `wss`;
- host present and at most 253 bytes;
- DNS-style host characters only;
- effective port exactly `443`;
- path beginning with `/`;
- path at most 1,024 bytes;
- query at most 2,048 bytes;
- complete endpoint at most 4,096 bytes;
- no URL username or password;
- no fragment;
- MQTT username at most 512 bytes;
- MQTT password at most 2,048 bytes.

Rejected:

```text
ws://
port other than 443
protocol-relative URL
host containing path or query
URL credentials
fragment
control character
missing field
empty field
oversized value
business error
```

## 7. Error Redaction

Mapper exceptions use fixed messages only.

They never include:

- response `desc`;
- host;
- path or query;
- username;
- password;
- bearer token;
- response body.

`ApiClient` already omits request headers and response bodies from malformed
JSON, HTTP and transport exceptions. WP-7 adds endpoint-specific regression
tests for that invariant.

## 8. Synthetic Fixtures

Added:

```text
fixtures/mqtt/mqtt-credential-success.json
fixtures/mqtt/mqtt-credential-business-error.json
```

The success fixture uses:

- reserved `example.test` host;
- explicit synthetic username;
- explicit synthetic password;
- explicit synthetic WSS ticket.

The failure fixture deliberately places a synthetic secret marker in `desc`.
Tests prove that marker cannot reach an exception message.

These files contain no real account, endpoint, token or credential.

## 9. Tests

Extended:

```text
tests/rest-client-auth.php
tools/check-mqtt-shadow.sh
```

The tests prove:

- exact GET method;
- exact endpoint path;
- absent request body;
- Accept header;
- Bearer header;
- generated request ID;
- relative host/path composition;
- absolute WSS URL support;
- username mapping;
- password mapping;
- rejection when each required field is absent;
- rejection of an empty required field;
- plain WebSocket rejection;
- unsupported port rejection;
- fragment rejection;
- control-character rejection;
- business-error redaction;
- malformed-JSON body redaction;
- HTTP-error body redaction;
- bearer-token redaction.

The focused runner includes the new mapper and `ApiClient` in PHPCS and
PHPStan.

## 10. Credential Lifetime

The values exist only:

1. in the immediate `ApiClient` response;
2. in the immediate mapper input;
3. in the returned internal credential array held by the caller.

This step adds no caller that persists or applies them.

Therefore:

- no credential is written to an IP-Symcon attribute;
- no credential is written to a variable;
- no credential is written to MQTT ownership metadata;
- no credential is written to a public fixture;
- no credential is written to debug output.

The future lifecycle implementation must retrieve fresh credentials only when
it has an authorized, validated transport operation to perform.

## 11. Compatibility

Unchanged:

- Account form and actions;
- Account variables;
- Device variables;
- Configurator behavior;
- REST polling;
- MQTT shadow queue behavior;
- command transport;
- Archive Control configuration;
- installed Symcon module;
- standalone published module.

The new method is dormant because no production Account path invokes it yet.

## 12. Architecture Decisions

### AD-NAV-408: Keep credential mapping separate

**Decision:** Add `MqttCredentialMapper` instead of extending
`PayloadMapper`.

**Rationale:** Credential-bearing transport configuration has different
security and validation responsibilities from device state.

**Consequence:** Device and Configurator data cannot accidentally receive MQTT
credentials through the normal mapper.

### AD-NAV-409: Support the evidenced split URL

**Decision:** Compose relative `mqttUrl` from validated `mqttHost`.

**Rationale:** Private structural evidence shows that the endpoint may return
host and WebSocket path separately.

**Consequence:** No undocumented requirement for an absolute `mqttUrl` is
introduced.

### AD-NAV-410: Permit WSS on port 443 only

**Decision:** Reject plain WebSocket and every non-443 port.

**Rationale:** The native pilot proved secure WSS transport with certificate
verification.

**Consequence:** TCP MQTT and insecure WebSocket fallback remain excluded.

### AD-NAV-411: Treat URL query as credential material

**Decision:** Return the validated query only as part of the internal WSS URL
and never include it in diagnostics.

**Rationale:** The query may carry private connection information.

**Consequence:** Errors use fixed classifications rather than endpoint text.

### AD-NAV-412: Add no credential persistence

**Decision:** Stop at the mapper return value.

**Rationale:** Storage and application require ownership, lifecycle and
rollback guarantees from `WP-8`.

**Consequence:** WP-7 cannot create a reusable secret cache.

### AD-NAV-413: Keep publication closed

**Decision:** Do not publish or install the credential implementation.

**Rationale:** No owned transport lifecycle exists to consume the result.

**Consequence:** Productive Symcon remains on the known-good REST release.

## 13. Verification Result

Passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
make check
git diff --check
```

This includes:

```text
REST and authentication regression
credential endpoint and mapper tests
secret-redaction tests
MQTT envelope and parser checks
Account pairing and ingestion checks
targeted REST reconciliation checks
distribution validation
PHPCS
PHPStan
```

The complete SAEF repository gate, including repository-wide PHPStan, passed.

## 14. Decision

**`WP-7` credential endpoint: COMPLETE OFFLINE.**

**Exact authenticated GET contract: PASS.**

**Split host/path composition: PASS.**

**WSS-only validation: PASS.**

**Port-443 restriction: PASS.**

**Required credential fields: PASS.**

**Secret-redaction gate: PASS.**

**Full repository gate: PASS.**

**Credential persistence: NONE.**

**Core-instance mutation: NONE.**

**Transport connection: NONE.**

**Owned transport lifecycle: NOT IMPLEMENTED.**

**Standalone publication: NOT AUTHORIZED.**

**Live Symcon mutation: NONE.**

## 15. Recommended Next Step

Create:

```text
103-native-mqtt-owned-transport-lifecycle-design.md
```

That step should design `WP-8` before implementation:

1. explicit create-versus-adopt workflow;
2. deterministic local client ID;
3. secret application without persistence outside core properties;
4. exact subscription generation from discovery;
5. idempotent connection ordering;
6. bounded reconnect state machine;
7. ownership drift and rollback behavior;
8. disabled-update and restart invariants;
9. no automatic deletion;
10. staged Symcon test gates.
