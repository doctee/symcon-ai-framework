# 22 Dock Command Implementation

**Case study:** Navimow native IP-Symcon module  
**Status:** Dock-first implementation complete; direct Symcon test pending  
**Date:** 2026-07-09  
**Build boundary:** This step implements only the Dock command path. It does
not execute a live mower command and does not enable Start, Stop, Pause,
Resume or MQTT/WSS.

## 1. Purpose

This step implements the conditional Go decision from
`21-command-implementation-readiness.md`.

The implementation adds:

- a Dock-only command allowlist;
- authenticated command transport through `NavimowAccount`;
- device-scoped command response parsing;
- command diagnostics in `NavimowDevice`;
- duplicate-command protection;
- a one-shot delayed read-only status verification;
- a confirmed Dock button in the device configuration form.

## 2. Entry Gate

Implementation started after:

- OAuth and refresh passed in IP-Symcon;
- discovery and status reads passed against one real mower;
- the sanitized Dock `alreadyInState` fixture was validated;
- the command-readiness review approved only a Dock-first slice;
- automatic retries and direct domain-state writes were explicitly prohibited.

No live command was sent during this implementation step.

## 3. Implemented Files

| File | Change |
| --- | --- |
| `distribution/libs/Navimow/CommandContract.php` | Dock-only allowlist and request envelope |
| `distribution/libs/Navimow/ApiClient.php` | authenticated `sendCommands()` transport |
| `distribution/libs/Navimow/PayloadMapper.php` | device-matched command result parser |
| `distribution/NavimowAccount/module.php` | `SendCommand` parent operation and account serialization |
| `distribution/NavimowDevice/module.php` | Dock action, diagnostics, guard and verification timer |
| `distribution/NavimowDevice/form.json` | confirmed Dock button |
| `distribution/NavimowDevice/locale.json` | German Dock captions |
| `distribution/NavimowAccount/form.json` | updated implemented-scope label |
| `distribution/NavimowAccount/locale.json` | translated scope label |
| `distribution/README.md` | Dock-first distribution scope |
| `tests/rest-client-auth.php` | fixture-backed command contract tests |

All changes remain inside the Navimow case study.

## 4. Architecture Decisions

### AD-NAV-035: The allowlist is independent from transport

**Decision:** `CommandContract` creates the command envelope and accepts only
the symbolic command `Dock`.

**Rationale:** A generic transport method must not accidentally make all
statically known mower commands active.

**Consequence:** `Start`, `Stop`, `Pause` and `Resume` fail before any HTTP
request can be created.

### AD-NAV-036: Command responses are matched by device ID

**Decision:** The mapper searches nested `commands[].devices[]` for the
requested device.

**Rationale:** Reading only the first command result would be unsafe for
multi-device or multi-command responses.

**Consequence:** A response without the requested device fails closed.

### AD-NAV-037: Verification requires a newer status sample

**Decision:** The device stores `LastStatusUpdate` as a private baseline before
submitting Dock. Verification succeeds only when a later read advances that
timestamp and reports `Docked`.

**Rationale:** A previously cached Docked value must not be mistaken for
post-command confirmation.

**Consequence:** Failed or stale status reads end in
`Verification Timeout`, even if the old visible state was Docked.

### AD-NAV-038: `alreadyInState` remains visible

**Decision:** A fixture-backed `alreadyInState` result remains
`Already In State` after the delayed status read confirms Docked.

**Rationale:** Replacing it with `Verified` would discard useful evidence about
the actual cloud response.

**Consequence:** The first supervised Dock test has an explicit expected
terminal diagnostic.

## 5. Command Data Flow

```text
Confirmed Dock button or module action
                |
                v
NavimowDevice::Dock()
                |
                | SendCommand / Dock / DeviceId
                v
NavimowAccount::ForwardData()
                |
                v
CommandContract::createPayload()
                |
                v
POST /openapi/smarthome/sendCommands
                |
                v
PayloadMapper::mapCommandResult()
                |
                v
Device command diagnostics
                |
                | one-shot timer, no write retry
                v
NavimowDevice::RefreshStatus()
                |
                v
new Docked status -> terminal result
```

Tokens and authorization headers never cross the account boundary.

## 6. Request Contract

The allowlisted Dock request is:

```json
{
  "commands": [
    {
      "devices": [
        {
          "id": "DEVICE_001"
        }
      ],
      "execution": {
        "command": "action.devices.commands.Dock",
        "params": {}
      }
    }
  ]
}
```

`DEVICE_001` is a public fixture placeholder. The configured device ID remains
private runtime configuration.

The endpoint is:

```text
POST /openapi/smarthome/sendCommands
```

The request uses the existing bearer token and generated request ID owned by
the account instance.

## 7. Device Action Behavior

`Dock()` performs:

1. reject while another command is active;
2. require a configured `DeviceId`;
3. snapshot the current status timestamp;
4. set `LastCommand` to Dock;
5. set `LastCommandAt`;
6. set `LastCommandResult` to Requested;
7. send one parent request;
8. accept only `Accepted` or `Already In State`;
9. schedule one verification timer after five seconds.

`RequestAction('Dock', ...)` delegates to the same method. The configuration
form exposes a button with an explicit confirmation prompt.

No command variable is directly writable.

## 8. Response and Diagnostic Behavior

| Condition | Result |
| --- | --- |
| Request initialized | `Requested` |
| Nested result has `errorCode == "alreadyInState"` | `Already In State` |
| Nested result has `status == "SUCCESS"` | `Accepted`, then pending verification |
| Missing command result | `Failed` |
| Requested device missing from response | `Failed` |
| Unknown status or error | `Failed` |
| New status confirms Docked after Accepted | `Verified` |
| New status confirms Docked after alreadyInState | `Already In State` |
| New Docked status is not obtained | `Verification Timeout` |

Errors are bounded and sanitized before entering `LastCommandError`.

## 9. Retry and Concurrency Behavior

The implementation contains no command retry.

- one user action creates one HTTP command request;
- transport uncertainty terminates as `Failed`;
- delayed verification repeats only the status read;
- the verification timer is disabled before it executes the read;
- the active-command attribute blocks another attempt until terminal cleanup;
- account HTTP serialization uses the existing account semaphore.

Normal account polling remains independent.

## 10. Domain-State Protection

The command path does not call `SetValue()` for:

- `VehicleState`;
- `Online`;
- `BatteryLevel`;
- `LastStatusUpdate`.

Only `RefreshStatus()` and its existing status mapper can update those
variables.

This preserves AD-NAV-012: cloud command acceptance is not mower state.

## 11. Local Verification

Executed checks:

```text
Navimow REST client and authentication checks passed.
Navimow payload mapper fixture checks passed.
Navimow distribution structure is valid.
All Navimow PHP files passed syntax checks.
```

The command tests verify:

- exact Dock request JSON;
- exact command endpoint;
- empty object encoding for `params`;
- Start is rejected by the allowlist;
- an empty device ID is rejected;
- captured `alreadyInState` maps correctly;
- explicit synthetic `SUCCESS` maps to `Accepted`;
- missing command results fail closed;
- unknown command status fails closed;
- a response for another device fails closed.

No local test contacts the Navimow cloud.

## 12. Official Symcon Schema Gate

The exact official schemas previously adopted for the Module Validator gate
were executed again with AJV `6.10.2`.

All ten metadata files passed:

```text
PASS library.json
PASS NavimowAccount/module.json
PASS NavimowConfigurator/module.json
PASS NavimowDevice/module.json
PASS NavimowAccount/locale.json
PASS NavimowConfigurator/locale.json
PASS NavimowDevice/locale.json
PASS NavimowAccount/form.json
PASS NavimowConfigurator/form.json
PASS NavimowDevice/form.json
```

## 13. Security and Privacy Review

Confirmed:

- no credential was added;
- no token is returned to a device instance;
- no private device ID is committed;
- only placeholder fixture identifiers are used in tests;
- no raw command response is logged;
- no automatic write retry exists;
- Start, Stop, Pause and Resume remain absent from the allowlist and form.

## 14. Direct Symcon Test Gate

The implementation is not operationally accepted until a supervised test:

1. publishes the updated distribution;
2. updates the module in IP-Symcon;
3. confirms the mower is docked and supervised;
4. refreshes status and confirms Docked;
5. presses Dock once and confirms the prompt;
6. observes `LastCommand == Dock`;
7. observes terminal `LastCommandResult == Already In State`;
8. confirms `LastCommandError` is empty;
9. confirms a newer `LastStatusUpdate`;
10. confirms `VehicleState` remains Docked;
11. confirms no second command was emitted;
12. records only sanitized PASS/FAIL evidence.

The live test must stop immediately if the mower is not docked, the area is not
supervised or the command result differs from the expected bounded behavior.

## 15. Definition of Done

This implementation step is complete because:

- the Dock-only implementation exists;
- local tests and syntax checks pass;
- official metadata schemas pass;
- no live command was sent;
- non-Dock commands remain disabled;
- the direct Symcon gate is explicitly pending.

## 16. Recommendation and Next Step

Publish the updated distribution and perform the supervised Dock test while
the mower is already docked.

Recommended next SAEF artifact:

```text
case-studies/navimow/23-dock-command-symcon-test-report.md
```

Start, Stop, Pause and Resume remain blocked until that report passes and a
separate transition-success evidence plan is approved.
