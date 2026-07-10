# Navimow Fixture Workspace

**Status:** Fixture workspace prepared  
**Scope:** Sanitized API payload fixtures for the Navimow case study  
**Boundary:** No raw captures, secrets or private installation data may be stored here.

## Purpose

This directory is reserved for sanitized Navimow API fixtures used to validate
the native IP-Symcon module design before productive PHP module code is created.

Fixtures in this directory are engineering evidence. They should preserve API
shape, key names, nesting and JSON value types while removing private data.

## Source Plan

Fixture collection and sanitization rules are defined in:

- `../05-fixture-plan.md`
- `../03-variable-and-action-contract.md`
- `../04-implementation-plan.md`

If fixture practice changes, update the plan first or document why the change
is local to this directory.

## Intended Structure

Expected structure after sanitized fixtures are collected:

```text
fixtures/
  README.md
  rest/
    auth-list-success.json
    vehicle-status-docked.json
    vehicle-status-docking.json
    vehicle-status-mowing.json
    command-dock-success.json
    command-dock-already-in-state.json
    auth-expired-token.json
    api-temporary-failure.json
  mqtt/
    README.md
```

The `mqtt/` area is reserved for the later MQTT/WSS phase. It should not
contain MQTT payload fixtures until the MQTT technical spike starts.

## Required REST Fixtures

The REST MVP needs these sanitized fixtures before implementation moves beyond
the first parser and contract validation work:

| Fixture | Purpose |
| --- | --- |
| `rest/auth-token-success.json` | Validate token response shape after redaction. |
| `rest/auth-list-success.json` | Validate discovery response shape and device list structure. |
| `rest/vehicle-status-docked.json` | Validate safe baseline status mapping. |
| `rest/vehicle-status-mowing.json` | Validate active state mapping if available. |
| `rest/vehicle-status-docking.json` | Validate the observed return-to-dock transition state. |
| `rest/command-dock-success.json` | Validate the real successful Dock command response shape. |
| `rest/command-dock-already-in-state.json` | Validate non-error `alreadyInState` handling. |
| `rest/auth-expired-token.json` | Validate auth failure and reauth behavior. |
| `rest/auth-invalid-token.json` | Validate invalid-token API error shape. |
| `rest/api-temporary-failure.json` | Validate temporary API/cloud failure handling. |

If `vehicle-status-mowing.json` cannot be collected immediately, document the
gap and proceed only with an explicit note in the contract or implementation
plan.

## Sanitization Rules

Sanitized fixtures must:

- preserve JSON syntax;
- preserve key names;
- preserve object and array nesting;
- preserve value types;
- replace private identifiers with deterministic placeholders;
- replace secrets with obvious non-secret placeholders;
- remove exact garden coordinates, maps and local installation details.

Sanitized fixtures must not contain:

- access tokens;
- refresh tokens;
- authorization codes;
- MQTT usernames or passwords;
- real device IDs;
- real account IDs;
- private mower names;
- exact garden coordinates;
- map images or Base64 map data;
- private hostnames or IP addresses;
- local filesystem paths.

## Placeholder Conventions

Use these placeholders consistently:

| Original kind | Placeholder |
| --- | --- |
| Device ID | `DEVICE_001` |
| Additional device ID | `DEVICE_002` |
| Account ID | `ACCOUNT_001` |
| Request ID | `REQUEST_001` |
| Command number | `COMMAND_001` |
| Serial number | `SERIAL_001` |
| Private mower name | `Navimow Test Mower` |
| Username or email | `USER_001` |
| Access token | `REDACTED_ACCESS_TOKEN` |
| Refresh token | `REDACTED_REFRESH_TOKEN` |
| Authorization code | `REDACTED_AUTHORIZATION_CODE` |
| MQTT password | `REDACTED_MQTT_PASSWORD` |
| Client secret | `REDACTED_CLIENT_SECRET` |

## Redaction Checklist

Before committing a fixture:

1. Validate that the file is valid JSON.
2. Confirm no token or authorization code remains.
3. Confirm no MQTT credential remains.
4. Confirm every device ID is a placeholder.
5. Confirm account and user identifiers are placeholders.
6. Confirm private names are replaced.
7. Confirm request IDs are removed or placeholders.
8. Confirm no exact location, boundary or map data remains.
9. Confirm no private hostname, IP address or local path remains.
10. Confirm JSON value types were not changed by sanitization.
11. Confirm the fixture is small enough for review.
12. Confirm its purpose is listed in this README or a nearby note.

## Review Flow

Use this sequence:

1. Capture raw payload outside this repository.
2. Create a sanitized copy.
3. Review the sanitized copy against the checklist.
4. Compare the fixture with the variable/action contract.
5. Commit only sanitized fixtures.

If a fixture reveals that the contract is wrong, update
`../03-variable-and-action-contract.md` before productive module code is
written.

## Completeness Gate

Fixture collection is sufficient for the REST MVP when:

- one discovery fixture exists;
- at least one status fixture exists;
- state, battery and online fields are understood or explicitly marked open;
- one successful command response fixture exists;
- one failure or auth-error fixture exists;
- all fixtures pass the redaction checklist;
- unresolved fields are recorded in the case study.

Until this gate is satisfied or explicitly waived, productive module PHP files
should not be created.
