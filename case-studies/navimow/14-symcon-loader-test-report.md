# 14 Symcon Loader Test Report

**Case study:** Navimow native IP-Symcon module  
**Status:** Initial loader failed; corrected retest passed in step 15
**Date:** 2026-07-08  
**Build boundary:** This report records loader-readiness and direct Symcon smoke
test results only. It does not add OAuth, REST polling, MQTT/WSS or mower
commands.

## 1. Purpose

This document records the result of the first loader validation step for the
Navimow REST MVP scaffold.

It follows `13-metadata-and-loader-validation.md` and separates:

- local preflight checks that can be executed without IP-Symcon;
- the direct IP-Symcon loader smoke test that must be executed in a real
  Symcon environment;
- fixes required before live OAuth or REST polling may be implemented.

## 2. Scope

The smoke test scope is intentionally limited to loader and lifecycle behavior.

In scope:

- module library loading;
- module metadata acceptance;
- instance creation for account, device and configurator;
- form rendering;
- `ApplyChanges()` execution;
- profile creation;
- variable creation with stable Idents;
- Symcon log review for loader, metadata, profile or lifecycle errors.

Out of scope:

- OAuth authorization-code exchange;
- token refresh;
- live REST requests;
- status polling;
- command execution;
- MQTT/WSS;
- map or location behavior.

## 3. Current Local Preflight Result

Local preflight was executed after the metadata type correction from
`13-metadata-and-loader-validation.md`.

| Check | Result | Notes |
| --- | --- | --- |
| JSON metadata parse | passed | `library.json`, all `module.json`, `form.json` and `locale.json` files parse successfully. |
| PHP syntax | passed | Module files, shared library files and fixture test runner report no syntax errors. |
| Fixture mapper test | passed | `php tests/Navimow/payload-mapper-fixtures.php` reports success. |
| Secret pattern scan | passed with expected documentation hits | Hits are limited to placeholder names and documented field names in case-study fixture guidance. |
| Live network behavior | not present | The scaffold test performs no HTTP requests. |

Fixture mapper result:

```text
Navimow payload mapper fixture checks passed.
```

## 4. Direct Symcon Test Status

| Item | Status |
| --- | --- |
| Private Git module source prepared | passed |
| IP-Symcon version captured | pending |
| Module library loads from private Git source | pending |
| `Navimow Account` instance can be created | pending |
| `Navimow Account` form opens and saves without credentials | pending |
| Account variables are created | pending |
| `Navimow Device` instance can be created | pending |
| Device variables are created | pending |
| `Navimow Configurator` instance can be created or opened | pending |
| `NAVIMOW.*` profiles are created | pending |
| Symcon log checked | pending |

### Recorded direct result

The Git transport and `library.json` discovery succeeded after publishing the
scaffold to `main`. The direct loader then rejected the SAEF root directory
`adr/` because it did not contain `module.json`.

The correction and revised distribution boundary are documented in:

```text
case-studies/navimow/15-loader-fix-report.md
```

The corrected dedicated-repository retest passed. Step 15 is the authoritative
final result for this loader gate.

## 5. Manual Test Procedure

Run this test in a non-critical IP-Symcon environment.

The Symcon runtime is on a separate Win11 PC. It cannot load the Mac-local path
`/Users/carsten/IT/Projekte/symcon-ai-framework` directly. Therefore this case
study uses a private Git repository as the standard module transfer path for
direct Symcon tests.

1. Commit and push the current scaffold branch to the private Git remote.
2. On the Win11 Symcon PC, open the IP-Symcon Console.
3. Open the module management area.
4. Add the dedicated private module repository URL as module source:

   ```text
   https://github.com/doctee/symcon-navimow.git
   ```

   The SAEF framework repository itself is not a valid Symcon module source.
5. Reload the module list.
6. Confirm the `Navimow` library appears.
7. Confirm these modules are listed:
   - `Navimow Account`;
   - `Navimow Device`;
   - `Navimow Configurator`.
8. Create one `Navimow Account` instance.
9. Open the account form.
10. Save the account form without credentials.
11. Confirm these account variables exist:
   - `ConnectionState`;
   - `ReauthRequired`;
   - `TokenExpiresAt`;
   - `LastDiscovery`;
   - `LastRestSuccess`;
   - `RestErrorCount`.
12. Create one `Navimow Device` instance.
13. Leave `DeviceId` empty or use the sanitized placeholder `DEVICE_001` only
    if a value is required by the form.
14. Save the device form.
15. Confirm these device variables exist:
    - `VehicleState`;
    - `Online`;
    - `BatteryLevel`;
    - `LastStatusUpdate`;
    - `LastCommand`;
    - `LastCommandAt`;
    - `LastCommandResult`;
    - `LastCommandError`.
16. Open or create one `Navimow Configurator` instance.
17. Confirm its form opens without errors.
18. Check whether these profiles exist:
    - `NAVIMOW.ConnectionState`;
    - `NAVIMOW.VehicleState`;
    - `NAVIMOW.Command`;
    - `NAVIMOW.CommandResult`.
19. Check the Symcon log for errors related to:
    - module loading;
    - metadata;
    - form JSON;
    - missing classes;
    - method signatures;
    - profile creation;
    - variable registration;
    - parent/child interface compatibility.

## 6. Safe Return Format

After running the test, report only sanitized information.

Use this format:

```text
IP-Symcon version:
Private Git source added: yes/no
Branch used:
Library loads from Git source: yes/no
Account instance: ok/error
Device instance: ok/error
Configurator instance: ok/error
Forms open: ok/error
Profiles created: ok/error
Variables created: ok/error
Symcon log findings:
- ...
Private details removed: yes/no
```

Do not include:

- credentials;
- OAuth tokens;
- authorization codes;
- real device IDs;
- private ObjectIDs unless rewritten as placeholders;
- local hostnames;
- screenshots that reveal private object tree details.

## 7. Transfer Decision for Future Symcon Tests

**Decision:** Direct Symcon tests for this case study use the private Git
repository as the standard transfer path from Mac development workspace to the
Win11 IP-Symcon host.

**Rationale:** The IP-Symcon runtime cannot access the Mac-local repository
path directly. A private Git source matches normal module loading workflows,
keeps updates reproducible and avoids fragile SMB or manual ZIP transfer steps.

**Consequence:** Before each direct Symcon test, the relevant scaffold branch
must be committed and pushed. The Win11 Symcon host should update the module
from that private Git source.

## 8. Expected Findings to Watch

The most likely first-run findings are loader-facing, not domain-facing:

| Possible finding | Meaning | Expected action |
| --- | --- | --- |
| Library or module not listed | Metadata shape or directory structure is wrong. | Fix `library.json` or `module.json` before adding behavior. |
| Configurator not creatable | Type or form metadata still needs adjustment. | Fix metadata only. |
| Device cannot connect to account | Parent/child requirement GUID or implemented interface is wrong. | Correct interface metadata before REST work. |
| Form fails to render | Unsupported form field or invalid form JSON. | Simplify form and retest. |
| `ApplyChanges()` fatal error | Runtime signature or unavailable function issue. | Fix lifecycle code before OAuth. |
| Profiles missing | Profile helper did not execute or profile API usage needs adjustment. | Fix `Profiles.php` before variable behavior. |

## 9. Decision Gate

Live OAuth and read-only REST polling remain blocked until the direct Symcon
loader smoke test passes or its findings are resolved.

Proceed only when:

- account, device and configurator instances can be created;
- forms render;
- profiles and variables are created;
- no live HTTP request is attempted;
- the Symcon log contains no unresolved loader or lifecycle errors.

## 10. Next SAEF Step

If the direct Symcon loader smoke test passes, the next SAEF step should be:

```text
case-studies/navimow/15-auth-and-readonly-rest-plan.md
```

If the smoke test finds loader issues, the next SAEF step should instead be a
focused correction report:

```text
case-studies/navimow/15-loader-fix-report.md
```
