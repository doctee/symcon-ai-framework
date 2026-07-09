# 17 REST Client and Authentication Implementation

**Case study:** Navimow native IP-Symcon module  
**Status:** Local and credential-free Symcon tests passed
**Date:** 2026-07-09  
**Build boundary:** This step implements REST transport and account
authentication. It does not activate discovery, status polling, commands or
MQTT/WSS.

## 1. Purpose

This step implements work packages WP16.1 through WP16.3 from
`16-auth-and-readonly-rest-plan.md`.

The implementation provides:

- strict OAuth and REST response handling;
- injectable transport for network-free tests;
- authorization URL and callback parsing;
- authorization-code exchange;
- refresh-token exchange and scheduling;
- account-owned persistent token state;
- explicit authentication reset;
- a non-persistent authorization input in the account form.

## 2. Implemented Files

| File | Change |
| --- | --- |
| `distribution/libs/Navimow/ApiClient.php` | HTTPS transport, OAuth form POST, authenticated REST requests and classified exceptions |
| `distribution/libs/Navimow/OAuthHelper.php` | authorization URL, random state and callback/code parsing |
| `distribution/libs/Navimow/PayloadMapper.php` | strict internal token parsing and API success validation |
| `distribution/NavimowAccount/module.php` | token ownership, auth state, exchange, refresh, reset and timers |
| `distribution/NavimowAccount/form.json` | private configuration and supervised login actions |
| `tests/rest-client-auth.php` | fake-transport and authentication protocol checks |

## 3. REST Client Boundary

`ApiClient` now supports:

```text
exchangeAuthorizationCode(...)
refreshAccessToken(...)
getAuthorizedDevices(...)
getVehicleStatus(...)
```

Discovery and status methods exist at the transport boundary only. The account
module does not call them yet.

Transport guarantees:

- credential-free HTTPS base URL;
- TLS peer and host verification;
- 10-second connection timeout;
- 30-second total timeout;
- no redirects;
- HTTPS-only cURL protocol;
- one-megabyte decoded response limit;
- separate HTTP and JSON validation;
- no response body in exception messages;
- a new UUID-style `requestId` for authenticated requests.

## 4. Testability Decision

The client accepts an optional transport callable.

Production instances use cURL. Tests provide a deterministic fake transport
that receives the complete request envelope and returns a controlled HTTP
response.

This permits verification of:

- form encoding;
- bearer and request-ID headers;
- JSON request bodies;
- HTTP classification;
- malformed JSON behavior;
- token redaction;
- OAuth state handling;

without credentials or network access.

## 5. Authentication Form

Persistent account properties:

```text
BaseUrl
ClientId
ClientSecret
RedirectUri
PollInterval
DebugPayloads
```

The authorization code is not a property. It is entered into a
`PasswordTextBox` in the form's `actions` area. Symcon does not persist action
area test inputs as instance properties.

Actions:

| Action | Module method |
| --- | --- |
| Open Navimow Login | `GetAuthorizationUrl()` |
| Exchange Authorization Code | `ExchangeAuthorizationCode()` |
| Refresh Token | `RefreshAuthentication()` |
| Reset Authentication | `ResetAuthentication()` |

The login button returns the generated HTTPS URL, which Symcon opens in the
user's browser.

## 6. Persistent Internal State

`NavimowAccount` owns:

```text
AccessToken
RefreshToken
TokenExpiresAtInternal
OAuthState
DiscoveryCache
```

The access and refresh tokens:

- are attributes, not variables;
- are never returned to child modules;
- are never included in debug messages;
- are replaced only after strict token validation;
- survive `ApplyChanges()` and runtime restart.

The refresh token is preserved when a valid refresh response omits a
replacement. The initial authorization response must contain one.

## 7. Refresh Scheduling

The implementation:

- calculates expiry from receipt time plus `expires_in`;
- publishes only the resulting timestamp;
- uses a five-minute margin for normal token lifetimes;
- uses half the remaining lifetime for short-lived tokens;
- enforces a minimum 60-second timer interval;
- disables refresh without valid configuration or token state;
- serializes exchange and refresh with an instance-scoped semaphore.

No immediate retry loop exists.

## 8. ApplyChanges Safety

`ApplyChanges()` performs no network call.

It only:

- ensures profiles and variables;
- validates account configuration;
- evaluates existing internal token state;
- schedules or disables the refresh timer;
- publishes the corresponding authentication state.

This preserves deterministic module loading and configuration editing.

## 9. Explicitly Gated Behavior

The following remains disabled:

- discovery;
- configurator population;
- automatic status polling;
- device refresh;
- all mower commands;
- MQTT/WSS;
- map and location behavior.

`PollReadOnlyStatus()` disables its timer and records a diagnostic warning if
invoked before the next implementation gate.

## 10. Local Verification

Executed checks:

```text
php case-studies/navimow/tools/validate-distribution.php
php case-studies/navimow/tests/rest-client-auth.php
php tests/Navimow/payload-mapper-fixtures.php
```

Results:

```text
Navimow distribution structure is valid.
Navimow REST client and authentication checks passed.
Navimow payload mapper fixture checks passed.
```

All distribution and test PHP files pass syntax validation.

## 11. Official Symcon Module Validator Gate

The official Symcon Module Validator is now a mandatory distribution gate.

It covers:

```text
library.json
module.json
locale.json
form.json
```

The validator webpage could not execute its result function on 2026-07-09
because its own JavaScript dependency was missing:

```text
ReferenceError: $ is not defined
```

To avoid treating a broken UI as a successful check, the exact official
schemas referenced by that page were downloaded from:

```text
https://www.symcon.de/assets/files/validation/librarySchema.json
https://www.symcon.de/assets/files/validation/moduleSchema.json
https://www.symcon.de/assets/files/validation/localeSchema.json
https://www.symcon.de/assets/files/validation/formSchema.json
```

They were executed locally with AJV `6.10.2`, matching the validator page.

### Initial findings

| File scope | Finding |
| --- | --- |
| `library.json` | URL was empty and `compatibility` was missing |
| three `module.json` files | required `url` field was missing |
| three `locale.json` files | passed |
| three `form.json` files | passed |

Corrections:

- library URL set to the public distribution repository;
- minimum compatibility set to Symcon `6.2`;
- module URL added to all three module metadata files.

Final official-schema result:

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

This gate must be repeated after every metadata or form change and before each
distribution publication.

## 12. Security Review

Confirmed:

- no production client secret was added;
- no token or authorization code fixture was added;
- fake test values are clearly synthetic;
- authorization input is non-persistent form action state;
- HTTP exceptions do not include response bodies;
- bearer values are not logged;
- only sanitized exception class and message reach module debug output.

Residual boundary:

- password controls and module attributes are not asserted to be encrypted;
- Symcon backups and administrative access remain security-sensitive;
- client-secret distribution is unresolved and blocks a supported module
  release even though the sanitized source repository is publicly readable.

## 13. Direct Symcon Test Gate

The next direct test has two stages.

### Stage A: No-credential loader and form test

- update the dedicated module repository;
- update the installed Symcon module;
- run account lifecycle without credentials;
- verify configuration form JSON;
- verify no network request occurs;
- verify state becomes `Configuration Error` or `Authorization Pending`
  according to saved configuration.

### Stage B: Supervised private authentication test

- enter the private client secret in Symcon only;
- save configuration;
- open the generated login URL;
- paste the redirect URL or code into the action field;
- exchange the code;
- verify `Connected`, `ReauthRequired=false` and `TokenExpiresAt`;
- restart or reapply the instance and verify token persistence;
- trigger one supervised refresh;
- inspect logs for secret leakage.

Stage B requires the user at the browser login step.

## 14. Published Distribution

The canonical distribution was synchronized to:

```text
Repository: doctee/symcon-navimow
Branch: main
Commit: eab2e6e5a0f97829e6ad938c54610161f3c2b2ea
```

The published commit is ready for manual installation through Module Control.

## 15. Credential-Free Symcon Result

Stage A passed after manual installation of the public distribution.

The test used an explicit result channel instead of relying on the MCP
script-execution acknowledgement:

1. create a temporary Symcon script;
2. run all assertions inside `try/catch/finally`;
3. write `PASS` or the sanitized failure reason to the test script name;
4. read the script object back through MCP;
5. delete all temporary instances and the test script.

Verified:

| Check | Result |
| --- | --- |
| Account, device and configurator module GUIDs | passed |
| Four public account authentication methods | passed |
| All three temporary instance lifecycles | passed |
| Device and configurator parent connections | passed |
| All three configuration forms parse | passed |
| Account and device variable contracts | passed |
| Missing credentials produce `Configuration Error` | passed |
| `ReauthRequired` is true without credentials | passed |
| `TokenExpiresAt` remains zero | passed |
| Live Navimow request | not performed |

The read-back result was:

```text
Navimow SAEF Smoke Test PASS
```

## 16. Exit Decision

WP16.1, WP16.2 and the implementation portion of WP16.3 are complete locally.

The credential-free portion of WP16.3 is accepted. Full WP16.3 acceptance
still requires the supervised private OAuth exchange and refresh test.

After that gate, the next SAEF artifact should be:

```text
case-studies/navimow/18-auth-symcon-test-report.md
```

Only after a successful authentication report may discovery and read-only
status work begin.
