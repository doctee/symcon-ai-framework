# 43 Pilot Recovery Hardening Symcon Smoke Test Report

**Case study:** Navimow native IP-Symcon module
**Status:** Direct read-only Symcon smoke test passed
**Date:** 2026-07-10
**Scope:** Updated module loading, authentication state and read-only status

## 1. Purpose

This step verifies the published recovery hardening from
`42-pilot-recovery-hardening-publication.md` after the user updated the module
in the private-pilot IP-Symcon installation.

The test is deliberately read-only with respect to the mower. It verifies:

- all Navimow module types remain installed;
- existing account and device instances remain active;
- account authentication remains healthy;
- a real status refresh succeeds;
- public status values remain valid;
- command diagnostics are not modified;
- no REST error is added;
- temporary test objects are removed.

No Dock or other mower command is sent.

## 2. Tested Publication

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Branch and commit:

```text
main
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

The user manually updated the module in Symcon before this test.

## 3. Test Method

The established sanitized MCP test pattern was used:

1. create one temporary Symcon PHP script;
2. discover module instances by module GUID;
3. perform assertions inside `try/catch`;
4. call exactly one `NAVDV_RefreshStatus()` operation;
5. write only a bounded PASS/FAIL marker into the script name;
6. read the marker through MCP;
7. delete the script;
8. verify that the script no longer exists.

The result channel contained no ObjectID, token, device identifier, account
name, hostname or raw API payload.

## 4. Sanitized Result

Observed marker:

```text
Navimow Hardening Smoke PASS M111 A3 R0 S2 O1 B95 C1 E0
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `M111` | account, configurator and device module types all have an instance |
| `A3` | account connection state is `Connected` |
| `R0` | reauthorization is not required |
| `S2` | mower state is `Docked` |
| `O1` | mower is online |
| `B95` | battery level is 95 percent |
| `C1` | command timestamp and result remained unchanged |
| `E0` | REST error count did not increase |

## 5. Module and Instance Checks

Assertions:

| Check | Result |
| --- | --- |
| Navimow Account module available | passed |
| Navimow Configurator module available | passed |
| Navimow Device module available | passed |
| at least one existing instance of each type | passed |
| selected account instance status is active | passed |
| selected device instance status is active | passed |
| account refresh function available | passed |
| account poll function available | passed |
| device refresh function available | passed |
| device verification function available | passed |

The updated module loaded without requiring instance recreation or OAuth
reauthorization.

## 6. Authentication State

Before the status read:

- `ConnectionState` was `Connected`;
- `ReauthRequired` was false;
- `TokenExpiresAt` contained a future timestamp;
- `RestErrorCount` was captured as a baseline.

After the status read:

- account remained `Connected`;
- reauthorization remained false;
- REST error count was unchanged.

The internal `RefreshToken` timer interval and private retry attribute are not
directly exposed by the available MCP interface. This test therefore proves
healthy token validity and successful authenticated use after update, but does
not inspect internal timer values directly.

Deterministic timer and retry behavior remains covered by the 16 green harness
cases from step 41.

## 7. Read-Only Status Refresh

Exactly one explicit device status refresh was executed.

Observed module result:

```text
Status refresh succeeded.
```

Post-refresh assertions:

| Variable | Result |
| --- | --- |
| `VehicleState` | valid contract value, observed `Docked` |
| `Online` | boolean, observed true |
| `BatteryLevel` | within `0..100`, observed 95 percent |
| `LastStatusUpdate` | valid and not moved backwards |
| `ConnectionState` | remained `Connected` |
| `ReauthRequired` | remained false |
| `RestErrorCount` | unchanged |

This confirms that the private structured status-result refactoring did not
change the public `RefreshStatus()` success contract.

## 8. Command Invariance

Before the read-only refresh, the test recorded:

- `LastCommandAt`;
- `LastCommandResult`.

Both values were identical after the refresh.

Confirmed:

- no Dock action was invoked;
- no command endpoint was requested by the test;
- no command timestamp changed;
- no command result changed;
- no verification timer was started by the test.

## 9. Error and Privacy Review

The available MCP interface does not expose the complete Symcon log stream.

The test instead confirmed:

- the module returned a successful status result;
- `RestErrorCount` did not increase;
- no reauthorization flag was raised;
- no exception text entered the sanitized result channel;
- no credentials, IDs or raw response data were read or returned;
- no debug payload variable was required.

No error path was triggered during the smoke test.

## 10. Cleanup

The temporary verification script was deleted after reading the result.

A subsequent object lookup confirmed that the temporary script no longer
exists.

No test category, event, variable or retained script was created.

## 11. Gate Decisions

### Direct Symcon smoke gate

**Decision: PASS.**

The hardening build loads, retains existing instances and authentication, and
performs a successful real read-only status request.

### Command safety gate

**Decision: PASS for this read-only scope.**

Command state remained unchanged and no mower command was sent.

### Supervised restart observation gate

**Decision: REOPENED for one controlled `OBS-02` live test.**

The prerequisites now exist:

- deterministic restart and deadline tests pass;
- published hardening content is remotely verified;
- updated Symcon module loads successfully;
- authenticated read-only status succeeds;
- no error counter increase is observed.

This decision authorizes planning and executing one supervised restart
transition under the safety rules from step 37. It does not authorize repeated
or unattended mower testing.

## 12. Architecture Decisions

### AD-NAV-101: Test lifecycle changes read-only first

**Decision:** Validate module loading, existing state and status transport
before another physical transition.

**Rationale:** The hardening changes timers, attributes and restart behavior,
while a status read can test installation health without moving the mower.

**Consequence:** Physical risk was avoided during the first post-update test.

### AD-NAV-102: Use command invariance as a smoke assertion

**Decision:** Compare command timestamp and result before and after the read.

**Rationale:** A read-only smoke test should prove not only success, but also
absence of command-side mutation.

**Consequence:** The result explicitly supports the no-command claim.

### AD-NAV-103: Combine live state with deterministic timer evidence

**Decision:** Accept healthy authenticated runtime state while retaining the
local harness as the timer-internals evidence source.

**Rationale:** MCP cannot directly inspect private module attributes or timer
intervals, but can prove successful authenticated use after lifecycle update.

**Consequence:** The smoke gate passes without overstating timer observability.

### AD-NAV-104: Delete every temporary test object

**Decision:** Remove the verification script and confirm its absence.

**Rationale:** Direct integration tests must not leave operational clutter or
future execution paths in Symcon.

**Consequence:** The runtime returns to its pre-test object structure.

## 13. Recommended Next Step

Create and execute:

```text
44-pilot-restart-observation-live-test.md
```

That step should:

- confirm supervision and a safe mowing area;
- start mowing manually through the official app;
- confirm `Running` through a read-only refresh;
- send exactly one Dock command through Symcon;
- observe `Docking` and `Pending Verification`;
- restart the Symcon service once while Docking;
- send no command after restart;
- verify preserved command timestamp and final `Verified` state;
- stop immediately if physical behavior becomes unexpected.
