# 12 REST MVP Scaffold

**Case study:** Navimow native IP-Symcon module  
**Status:** First scaffold created  
**Date:** 2026-07-08  
**Build boundary:** This step creates structural scaffold code and fixture-based
mapping checks only. It does not implement live OAuth, REST polling, MQTT/WSS
or mower commands.

## 1. Purpose

This step executes the conditional implementation start decision from
`11-implementation-start-decision.md`.

The goal is to create a reviewable first module skeleton that can load as a
native IP-Symcon module family after metadata validation, while keeping all
live cloud behavior disabled until later SAEF steps.

## 2. Generated Scaffold

The following public scaffold was created:

```text
library.json
modules/NavimowAccount/
  form.json
  locale.json
  module.json
  module.php
modules/NavimowConfigurator/
  form.json
  locale.json
  module.json
  module.php
modules/NavimowDevice/
  form.json
  locale.json
  module.json
  module.php
library/Navimow/
  ApiClient.php
  PayloadMapper.php
  Profiles.php
tests/Navimow/
  payload-mapper-fixtures.php
tests/fixtures/navimow/
  README.md
```

## 3. Scaffold Decisions Applied

| Decision | Applied result |
| --- | --- |
| Account parent | `NavimowAccount` is a splitter-style parent scaffold with explicit child interface metadata. |
| Device child | `NavimowDevice` owns device variables and has no token storage. |
| Configurator | `NavimowConfigurator` is included as a minimal discovery-ready stub. |
| Token state | Access and refresh token placeholders are internal account attributes only. |
| Live REST | `ApiClient` is a skeleton and throws until live behavior is intentionally implemented. |
| Mapper tests | `PayloadMapper` is pure PHP and verified against sanitized fixtures. |
| Online semantics | Missing online fields map to `null`, not false. |

## 4. Generated Module Boundary

### `NavimowAccount`

Created as the account owner for:

- account configuration placeholders;
- protected token attribute placeholders;
- account diagnostics variables;
- future account-to-device message handling.

The current `ForwardData()` response is explicitly `not_implemented`.

### `NavimowDevice`

Created as the per-mower owner for:

- `VehicleState`;
- `Online`;
- `BatteryLevel`;
- `LastStatusUpdate`;
- `LastCommand`;
- `LastCommandAt`;
- `LastCommandResult`;
- `LastCommandError`.

Command actions intentionally throw until a later command implementation step.

### `NavimowConfigurator`

Created as a minimal configurator stub. It does not yet render discovered
devices because discovery transport is not implemented in this scaffold step.

## 5. Shared Library Boundary

### `PayloadMapper`

The mapper is intentionally pure PHP and has no IP-Symcon dependency. It maps:

- token response metadata;
- discovery device lists;
- status state and battery values;
- command result envelopes;
- API auth failures.

### `Profiles`

The profile helper centralizes the module-owned integer profiles documented in
`03-variable-and-action-contract.md`.

### `ApiClient`

The REST client exists only as a boundary placeholder. It does not perform
network requests in this step.

## 6. Verification Scope

The first fixture verification covers:

- token placeholders and expiry;
- one discovered device;
- docked state and battery `81`;
- running state and battery `92`;
- missing online field does not imply offline;
- `alreadyInState` command mapping;
- invalid OAuth info maps to reauthentication required;
- unknown vehicle state maps to `Unknown`.

The check was executed successfully during this step.

Run:

```text
php tests/Navimow/payload-mapper-fixtures.php
```

## 7. Metadata Review Note

The scaffold uses the common IP-Symcon module metadata shape with a root
`library.json` and one `module.json` per module.

Because this repository did not contain an existing IP-Symcon module example,
the next implementation review must validate the numeric module type values
and parent/child interface metadata in a real IP-Symcon development
installation before treating the scaffold as install-ready.

## 8. Explicitly Not Implemented

This step does not implement:

- OAuth authorization-code exchange;
- token refresh;
- live REST requests;
- discovery UI population;
- status polling;
- command forwarding;
- command verification;
- MQTT/WSS;
- map rendering;
- location variables.

## 9. Next SAEF Step

Recommended next step:

```text
case-studies/navimow/13-metadata-and-loader-validation.md
```

That step should validate whether the generated module metadata is accepted by
IP-Symcon and adjust only the loader-facing scaffold details before adding live
REST behavior.
