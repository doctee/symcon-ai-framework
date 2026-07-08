# 05 Fixture Plan

**Case study:** Navimow native IP-Symcon module  
**Status:** Fixture planning draft  
**Date:** 2026-07-08  
**Build boundary:** This document defines fixture collection and sanitization only. No productive PHP code or real payload fixture is introduced.

## 1. Purpose

This document defines how real Navimow API payloads should be captured,
sanitized, reviewed and used before the native IP-Symcon module implementation
starts.

The fixture plan exists because the Navimow API behavior is inferred from a
community implementation. Before public module variables, profiles and actions
are implemented, the MVP contract must be checked against real but sanitized
payload examples.

## 2. Fixture Goals

Fixtures should help answer:

- which exact field names represent mower state, battery and online status;
- how command success, command failure and `alreadyInState` are represented;
- how authentication failures differ from temporary cloud failures;
- whether known `vehicleState` values match the contract;
- which fields are model-specific or unstable;
- whether raw payload diagnostics can be safely bounded and redacted.

Fixtures are engineering evidence. They are not private backups, API dumps or
long-term telemetry storage.

## 3. Proposed Fixture Location

Sanitized fixtures should later live under the case study:

```text
case-studies/navimow/fixtures/
```

Recommended structure:

```text
fixtures/
  README.md
  rest/
    auth-list-success.json
    vehicle-status-docked.json
    vehicle-status-mowing.json
    command-start-success.json
    command-dock-already-in-state.json
    auth-expired-token.json
    api-temporary-failure.json
  mqtt/
    README.md
```

`mqtt/` is reserved for phase 2. It should remain empty or contain only a README
until the MQTT/WSS spike is explicitly started.

Raw unsanitized captures must not be stored in this repository. If raw captures
are needed temporarily, they belong outside the repository or in a private
ignored location.

### SAEF-Entscheidung AD-NAV-014: Only sanitized fixtures may enter the case study

**Entscheidung:** The public case study may contain only sanitized payload
fixtures. Raw captures, tokens, authorization codes, private device IDs, exact
garden coordinates and account identifiers stay outside the repository.

**Rationale:** SAEF requires strict separation between public engineering
artifacts and private installation data. Fixtures are useful only if they can
be reviewed and reused safely.

**Consequence:** Fixture capture and fixture sanitization are separate steps.
A fixture is not commit-ready until it passes the redaction checklist.

## 4. Required REST Fixtures

### `auth-list-success.json`

Purpose:

- verify discovery response shape;
- identify stable device fields;
- support configurator design.

Must preserve:

- response envelope shape;
- device array nesting;
- non-private model or capability fields if present;
- placeholder device ID;
- placeholder device name.

Must redact:

- real device ID;
- account identifiers;
- user-facing private device name if sensitive;
- tokens or auth metadata if present.

### `vehicle-status-docked.json`

Purpose:

- verify read-only status mapping for a safe baseline state.

Must preserve:

- response envelope shape;
- state field and value;
- battery field and value;
- online/connectivity field if present;
- firmware/model fields if they are non-sensitive and useful.

Must redact:

- real device ID;
- location fields, exact coordinates or map data;
- private names.

### `vehicle-status-mowing.json`

Purpose:

- verify active-state mapping and identify fields that differ during mowing.

Must preserve:

- active state value;
- battery value;
- online/connectivity indicator;
- mowing-related fields if present and non-private after sanitization.

Must redact or generalize:

- relative location coordinates if they reveal garden layout;
- map data;
- precise timestamps if they are installation-identifying.

### `command-start-success.json`

Purpose:

- verify successful command response shape.

Must preserve:

- response envelope;
- command result fields;
- success indicator;
- any returned status or error code.

Must redact:

- device ID;
- request IDs if they are traceable;
- account identifiers.

### `command-dock-already-in-state.json`

Purpose:

- verify `alreadyInState` handling and command diagnostics.

Must preserve:

- exact non-private result code or message shape;
- command response nesting.

Must redact:

- device ID;
- request IDs if traceable.

### `auth-expired-token.json`

Purpose:

- verify account state transition to token refresh or reauth.

Must preserve:

- HTTP status if captured;
- API error code;
- sanitized error message;
- response envelope shape.

Must redact:

- expired token;
- authorization code;
- refresh token;
- account identifiers.

### `api-temporary-failure.json`

Purpose:

- distinguish temporary transport/cloud failures from authentication failures.

Must preserve:

- HTTP status or error class;
- retry-relevant response shape;
- sanitized message.

Must redact:

- request IDs if traceable;
- server-specific private routing data if present.

## 5. Optional REST Fixtures

Optional fixtures are useful after the MVP contract is stable:

| Fixture | Purpose |
| --- | --- |
| `vehicle-status-error.json` | Verify `Error` and diagnostic mapping. |
| `vehicle-status-paused.json` | Verify pause/resume command expectations. |
| `vehicle-status-self-checking.json` | Verify transient states. |
| `command-rejected.json` | Verify cloud-side command rejection. |
| `auth-refresh-success.json` | Verify refresh response shape without storing tokens. |
| `auth-refresh-failure.json` | Verify `ReauthRequired` transition. |

Refresh fixtures must never contain real access or refresh tokens. Token fields
must be replaced with obvious placeholders.

## 6. Phase 2 MQTT Fixtures

MQTT fixtures are not required for the REST MVP.

When phase 2 starts, capture sanitized examples for:

- `state`;
- `event`;
- `attributes`;
- `location`;
- stale or missing location behavior if observable.

MQTT fixture rules:

- preserve topic structure with placeholder device ID;
- preserve channel name;
- preserve payload shape;
- redact exact coordinates if they reveal garden layout;
- redact MQTT usernames, passwords, tokens and account IDs;
- avoid committing high-frequency location streams.

## 7. Sanitization Rules

### Replace Private Identifiers

Use deterministic placeholders:

| Original kind | Placeholder |
| --- | --- |
| Device ID | `DEVICE_001` |
| Account ID | `ACCOUNT_001` |
| Request ID | `REQUEST_001` |
| Serial number | `SERIAL_001` |
| Private mower name | `Navimow Test Mower` |
| Username/email | `USER_001` |

If multiple devices are present, use `DEVICE_001`, `DEVICE_002` and so on.

### Replace Secrets

Use obvious non-secret placeholders:

| Secret kind | Placeholder |
| --- | --- |
| Access token | `REDACTED_ACCESS_TOKEN` |
| Refresh token | `REDACTED_REFRESH_TOKEN` |
| Authorization code | `REDACTED_AUTHORIZATION_CODE` |
| MQTT password | `REDACTED_MQTT_PASSWORD` |
| Client secret | `REDACTED_CLIENT_SECRET` |

### Generalize Time and Location

| Data kind | Rule |
| --- | --- |
| Absolute timestamps | Keep only if needed for schema; otherwise normalize. |
| Relative mower coordinates | Remove or replace with small non-identifying values. |
| Map images/Base64 | Do not commit. |
| Garden boundaries | Do not commit. |
| Private timezone context | Avoid unless relevant to API behavior. |

### Preserve Types

Sanitization must preserve JSON types. A number should stay a number, a boolean
should stay a boolean and an object should stay an object. This keeps fixtures
useful for parser tests.

## 8. Redaction Checklist

Before a fixture can be committed:

1. Confirm it contains no access token.
2. Confirm it contains no refresh token.
3. Confirm it contains no authorization code.
4. Confirm it contains no MQTT password or username.
5. Confirm all device IDs are placeholders.
6. Confirm account IDs and user identifiers are placeholders.
7. Confirm private mower names are replaced.
8. Confirm request IDs are removed or placeholders.
9. Confirm exact garden coordinates and map data are absent.
10. Confirm no private hostname, IP address or local path is present.
11. Confirm JSON remains valid.
12. Confirm key names and value types are preserved.
13. Confirm the fixture is small enough for review.
14. Confirm the fixture purpose is documented.

## 9. Fixture README Requirements

The future `fixtures/README.md` should document:

- fixture purpose;
- sanitization rules;
- placeholder conventions;
- how fixtures were reviewed;
- which fixtures are required for REST MVP;
- which fixtures are optional;
- warning that raw captures must not be committed.

The README should not include private account descriptions, device IDs or local
site information.

## 10. Review Process

Recommended review flow:

1. Capture raw payload outside the repository.
2. Create a sanitized copy.
3. Validate JSON syntax.
4. Run the redaction checklist.
5. Compare sanitized fixture against the contract mapping needs.
6. Add or update fixture notes.
7. Commit only the sanitized fixture and fixture README.

If a fixture reveals that the contract is wrong, update
`03-variable-and-action-contract.md` before implementation code is written.

## 11. Contract Validation Matrix

| Contract area | Required fixture evidence |
| --- | --- |
| `VehicleState` mapping | `vehicle-status-docked.json`, `vehicle-status-mowing.json` if available |
| `BatteryLevel` mapping | Any valid vehicle status fixture with battery field |
| `Online` mapping | Vehicle status fixture with online/offline field or offline fixture |
| `LastStatusUpdate` behavior | Any valid status fixture; timestamp is local receipt time |
| `alreadyInState` handling | `command-dock-already-in-state.json` or equivalent |
| Command success handling | `command-start-success.json` or another safe command success |
| Reauth transition | `auth-expired-token.json` or refresh failure fixture |
| Temporary API failure | `api-temporary-failure.json` |
| Raw JSON redaction | Any fixture containing fields that need placeholder replacement |

## 12. Fixture Completeness Gate

Fixture collection is sufficient for the REST MVP when:

- one discovery fixture exists;
- at least one status fixture exists;
- state, battery and online fields are understood or explicitly marked open;
- one successful command response fixture exists;
- one failure or auth-error fixture exists;
- all fixtures pass the redaction checklist;
- unresolved fields are recorded in the contract or implementation plan.

If mowing-state fixture collection is not immediately possible, the MVP may
still proceed if docked-state and command fixtures are available and the gap is
documented.

## 13. Tooling Notes

Potential future tooling may include:

- a fixture validator that checks JSON syntax and required redactions;
- a schema inventory script that lists top-level and nested keys;
- parser tests that use sanitized fixtures;
- a diff review helper for fixture updates.

These tools should not be introduced until fixture structure is accepted. If
created later, they should live outside the case study unless they become
general SAEF tooling.

## 14. Risks

| Risk | Mitigation |
| --- | --- |
| Sanitized fixture changes behavior | Preserve key names, nesting and JSON types. |
| Secrets leak through nested fields | Use checklist and text search before commit. |
| Fixture is too model-specific | Capture model notes and add more fixtures later. |
| Private garden data leaks through location | Exclude or generalize location and map data. |
| Contract freezes too early | Treat fixtures as a gate before PHP implementation. |

## 15. Next SAEF Step

After this plan is accepted, the next work should be either:

- collect and sanitize the first REST fixtures according to this plan; or
- create `case-studies/navimow/fixtures/README.md` before adding any fixture
  files.

Productive module code should still wait until the Fixture Completeness Gate in
section 12 is satisfied or explicitly waived with a documented reason.
