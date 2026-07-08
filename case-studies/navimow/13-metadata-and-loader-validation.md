# 13 Metadata and Loader Validation

**Case study:** Navimow native IP-Symcon module  
**Status:** Static metadata review completed; direct Symcon loader test pending  
**Date:** 2026-07-08  
**Build boundary:** This step validates scaffold metadata and defines the first
direct IP-Symcon test. It does not add live OAuth, REST polling, MQTT/WSS or
mower commands.

## 1. Purpose

This step validates whether the scaffold created in
`12-rest-mvp-scaffold.md` is ready for a direct IP-Symcon loader smoke test.

The intent is deliberately narrow:

- catch metadata and module-role mistakes early;
- confirm JSON and PHP files remain syntactically valid;
- define what should be tested directly in Symcon now;
- keep live cloud behavior out of scope until loader behavior is known.

## 2. Static Validation Result

Static validation found one loader-relevant issue from the first scaffold:

| Finding | Impact | Decision |
| --- | --- | --- |
| Module type values did not match the intended roles. | Symcon could classify modules incorrectly or reject the configurator. | Correct metadata before direct loader test. |

Corrected module role mapping:

| Module | Role | Type value used after correction |
| --- | --- | --- |
| `NavimowAccount` | Splitter-style account parent | `2` |
| `NavimowDevice` | Device child | `3` |
| `NavimowConfigurator` | Configurator | `4` |

The parent-child interface GUID remains:

```text
{54620029-127D-470D-97C7-44265496FAA0}
```

It is currently used as the account-to-child interface for device and
configurator instances.

## 3. Files Reviewed

Reviewed scaffold metadata:

```text
library.json
modules/NavimowAccount/module.json
modules/NavimowDevice/module.json
modules/NavimowConfigurator/module.json
modules/NavimowAccount/form.json
modules/NavimowDevice/form.json
modules/NavimowConfigurator/form.json
modules/NavimowAccount/locale.json
modules/NavimowDevice/locale.json
modules/NavimowConfigurator/locale.json
```

Reviewed scaffold PHP:

```text
modules/NavimowAccount/module.php
modules/NavimowDevice/module.php
modules/NavimowConfigurator/module.php
library/Navimow/ApiClient.php
library/Navimow/PayloadMapper.php
library/Navimow/Profiles.php
tests/Navimow/payload-mapper-fixtures.php
```

## 4. Local Verification

Local verification should pass before the direct Symcon test:

```text
php -l modules/NavimowAccount/module.php
php -l modules/NavimowDevice/module.php
php -l modules/NavimowConfigurator/module.php
php -l library/Navimow/ApiClient.php
php -l library/Navimow/PayloadMapper.php
php -l library/Navimow/Profiles.php
php tests/Navimow/payload-mapper-fixtures.php
```

Expected result:

- all PHP files report no syntax errors;
- JSON metadata files parse successfully;
- fixture mapper checks pass;
- no live network request is made.

## 5. When a Direct Symcon Test Is Useful

A direct IP-Symcon test is useful now, but only as a loader smoke test.

This is the right moment because:

- module type values, `prefix`, `module.json`, `form.json` and parent/child
  metadata cannot be fully validated by PHP CLI;
- profile and variable registration can only be validated against Symcon's
  module runtime;
- early loader failures are cheaper to fix before OAuth and REST behavior are
  added.

This is not yet the right moment for:

- real OAuth login;
- REST polling;
- device command execution;
- MQTT/WSS validation;
- map or location behavior.

## 6. First Direct Symcon Test Scope

The first direct Symcon test should be a no-credential, no-cloud smoke test:

1. Install or link the module repository in a test IP-Symcon environment.
2. Confirm the library appears with the expected module names.
3. Create one `Navimow Account` instance.
4. Open and save the account configuration without credentials.
5. Confirm account variables are created with stable Idents.
6. Create one `Navimow Device` instance below or connected to the account if
   Symcon offers the parent selection.
7. Confirm device variables are created with stable Idents.
8. Create or open the `Navimow Configurator` instance.
9. Confirm forms render and do not produce PHP errors.
10. Check the Symcon log for loader, metadata, profile or signature errors.

Success criteria:

- all three modules can be instantiated;
- forms open without errors;
- `ApplyChanges()` completes for account, device and configurator;
- `NAVIMOW.*` profiles are created;
- account and device variables match `03-variable-and-action-contract.md`;
- no live HTTP request is attempted;
- no token, authorization code or private device ID is required.

## 7. What to Capture From the Symcon Test

The useful return data is small and should not include private installation
details.

Capture:

- IP-Symcon version;
- whether the module library loads;
- whether each module instance can be created;
- any Symcon log error text, with local paths or object IDs removed if needed;
- screenshots only if they do not reveal private object tree details.

Do not capture:

- credentials;
- OAuth tokens;
- private ObjectIDs unless rewritten as placeholders;
- local hostnames;
- real device IDs.

## 8. Decision

**Decision:** Proceed to a direct Symcon loader smoke test before implementing
live OAuth or REST polling.

The loader test should be performed against the scaffold after the metadata
type correction in this step.

## 9. Next SAEF Step

Recommended next step:

```text
case-studies/navimow/14-symcon-loader-test-report.md
```

That step should record the result of the direct IP-Symcon smoke test and list
any metadata or lifecycle fixes required before implementing OAuth and
read-only REST status polling.
