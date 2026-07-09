# 18 Authentication Symcon Test Report

**Case study:** Navimow native IP-Symcon module  
**Status:** Authentication gate passed  
**Date:** 2026-07-09  
**Build boundary:** This report covers metadata validation, supervised OAuth
authentication, persistence and token refresh only. It does not test discovery,
device status, mower commands or MQTT/WSS.

## 1. Purpose

This report closes the authentication gate defined in
`16-auth-and-readonly-rest-plan.md` and implemented in
`17-rest-client-and-auth-implementation.md`.

It records:

- official Symcon metadata-schema validation;
- public module installation;
- credential-free lifecycle validation;
- supervised authorization-code exchange;
- authentication persistence across `ApplyChanges()`;
- one explicit refresh-token exchange;
- private-data handling and cleanup.

## 2. Test Environment

| Item | Value |
| --- | --- |
| IP-Symcon version | `9.0` |
| Runtime host | Separate Win11 system |
| Module source | `https://github.com/doctee/symcon-navimow.git` |
| Module branch | `main` |
| Validated distribution commit | `a78db303f83f828ea55a7b936a03e169f60d03ee` |
| SAEF workspace | Mac development workspace |
| MCP role | Assertions and sanitized result read-back |

No private hostname, ObjectID, account name, device ID or token value is
recorded.

## 3. Official Module Validator Gate

The Symcon Module Validator webpage was selected as a mandatory gate.

Its page-side validation was unavailable during the test because the page
raised:

```text
ReferenceError: $ is not defined
```

The exact official schemas referenced by the page were therefore downloaded
and executed locally with AJV `6.10.2`, matching the validator implementation.

Initial findings:

- `library.json` required a non-empty URL;
- `library.json` required `compatibility`;
- all three `module.json` files required `url`;
- all form and locale files already passed.

After correction, all ten files passed:

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

The distribution now declares minimum compatibility with Symcon `6.2`.

## 4. Installation and Credential-Free Test

The dedicated distribution repository was changed to public because Module
Control could not authenticate against its initial private HTTPS source.

Manual installation then succeeded. Module Control displayed:

- Navimow Account;
- Navimow Configurator;
- Navimow Device.

The credential-free MCP smoke test used an explicit result channel:

1. create a temporary test script;
2. run assertions in `try/catch/finally`;
3. write `PASS` or a sanitized failure reason to the script name;
4. read the script object back through MCP;
5. delete temporary instances and the script.

Read-back result:

```text
Navimow SAEF Smoke Test PASS
```

## 5. Supervised OAuth Procedure

The user performed these private steps in the Symcon account form:

1. configured the confirmed FRA API base URL;
2. retained client ID `homeassistant`;
3. entered the OAuth client secret locally;
4. retained redirect URI `http://localhost:1/callback`;
5. saved the instance configuration;
6. opened the generated Navimow login URL;
7. authenticated with Navimow;
8. copied the resulting redirect URL locally;
9. pasted it into the non-persistent authorization action field;
10. explicitly started authorization-code exchange.

The authorization input, client secret and redirect result were never sent
through this conversation or stored in the case study.

Observed form result:

```text
Authentication succeeded.
```

## 6. Post-Exchange Assertions

The MCP verification inspected public state only.

| Assertion | Result |
| --- | --- |
| Exactly one account instance exists | passed |
| `ConnectionState == Connected` | passed |
| `ReauthRequired == false` | passed |
| `TokenExpiresAt` is sufficiently in the future | passed |
| No token value was read | passed |

The test did not inspect internal access or refresh token contents.

## 7. Persistence Test

`IPS_ApplyChanges()` was executed once on the authenticated account instance.

Assertions:

| Assertion | Result |
| --- | --- |
| Account remains `Connected` | passed |
| `ReauthRequired` remains false | passed |
| Published token expiry remains unchanged | passed |
| No network request is triggered by `ApplyChanges()` | confirmed by implementation boundary |

This verifies instance-internal token state survives a configuration apply.
A full service restart remains a later operational persistence check.

## 8. Refresh-Token Test

Exactly one supervised refresh was invoked through:

```text
NAVAC_RefreshAuthentication(...)
```

The verification did not receive or log token values.

Assertions:

| Assertion | Result |
| --- | --- |
| Module method reports refresh success | passed |
| Account returns to `Connected` | passed |
| `ReauthRequired == false` | passed |
| Refreshed expiry is sufficiently in the future | passed |
| `LastRestSuccess` is set | passed |

Explicit result-channel read-back:

```text
Navimow OAuth Verification PASS
```

## 9. Security and Privacy Review

Confirmed:

- client secret remained in the private installation path;
- authorization code and redirect URL were not recorded;
- access and refresh tokens were not read through MCP;
- token values were not exposed as Symcon variables;
- test output contained only state assertions;
- no private ObjectIDs were added to public files;
- no raw payload was captured;
- no mower command was executed.

Residual security boundary:

- Symcon properties and module attributes are not claimed to be encrypted
  secret storage;
- administrative access and Symcon backups remain security-sensitive;
- supported public distribution of the OAuth client secret remains unresolved.

## 10. Cleanup

Removed:

- credential-free smoke-test script;
- OAuth verification script;
- version-capture script;
- temporary account, device and configurator instances from the smoke test.

Retained intentionally:

- installed Navimow module library;
- one authenticated Navimow Account instance;
- module-owned variable profiles;
- persistent internal token state required for subsequent read-only tests.

## 11. Gate Decision

**Decision:** Authentication gate passed.

WP16.3 is accepted because:

- metadata passes the official Symcon schemas;
- the module installs and loads;
- authorization-code exchange succeeds;
- public authentication state is correct;
- state survives `ApplyChanges()`;
- one real refresh-token exchange succeeds;
- no secret or token value entered public artifacts.

This decision does not approve commands, MQTT/WSS or public release.

## 12. Recommendation and Next Step

Proceed with WP16.4 and WP16.5:

- account-owned discovery through `/openapi/smarthome/authList`;
- configurator population from sanitized metadata;
- read-only device status through
  `/openapi/smarthome/getVehicleStatus`;
- conservative REST freshness semantics for `Online`.

Recommended next SAEF artifact:

```text
case-studies/navimow/19-discovery-and-readonly-status-implementation.md
```

Commands remain blocked until read-only discovery and status pass their own
fixture, direct Symcon and supervised live gates.
