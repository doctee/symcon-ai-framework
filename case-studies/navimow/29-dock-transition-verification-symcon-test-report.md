# 29 Dock Transition Verification Symcon Test Report

**Case study:** Navimow native IP-Symcon module
**Status:** Already-docked Symcon retest passed; transition retest pending
**Date:** 2026-07-09
**Scope:** Published long-running Dock verification update and supervised
Symcon smoke test

## 1. Purpose

This report records the publication and first direct Symcon test after
implementing the long-running Dock verification design from
`28-dock-transition-verification-implementation.md`.

The test verifies that the updated module still handles the safe
already-docked command path correctly before another Running-to-Docked
transition test is attempted.

## 2. Publication Result

The dedicated public Symcon module repository was updated successfully:

```text
Repository: doctee/symcon-navimow
Branch: main
Commit: a6178dc feat: add long-running Dock verification
Previous commit: 43e248e
```

The first push attempt from the agent environment failed because DNS/network
access to GitHub was unavailable. The GitHub connector could read the
repository but had no write permission. The user then pushed the prepared
local commit successfully from the Mac terminal.

## 3. Symcon Update

The user manually updated the Navimow module in Symcon after publication.

MCP observations after the update:

- the Navimow library is installed;
- Symcon reports one Navimow Device instance;
- module functions `NAVDV_RefreshStatus`, `NAVDV_Dock` and
  `NAVDV_VerifyCommand` are available;
- direct file-location verification of the installed module source was not
  possible through the available MCP/PHP path scan.

Because the public Symcon module update API is not available in this Symcon
runtime, the update action itself was performed manually by the user.

## 4. Read-Only Precheck

Before sending any command, a read-only status refresh was executed through
the device instance.

Observed sanitized result:

| Check | Result |
| --- | --- |
| status refresh | succeeded |
| vehicle state | `Docked` |
| online | `true` |
| battery | `85%` |
| status timestamp | updated |

The test was therefore allowed to continue with the already-docked Dock path.

## 5. Safety Preconditions

The user confirmed before the command:

- the mower was still docked;
- the mower was supervised.

The test intentionally used the already-docked path. It did not start the
mower and did not perform a physical Running-to-Docked transition.

## 6. Command Test

Exactly one Dock command was triggered through the Symcon module function.

Observed sanitized command response:

```text
Dock command is already in state.
```

No repeated command was issued by the test procedure.

## 7. Post-Command Readback

After a short timer wait, the device variables were read back by Ident.

Observed sanitized result:

| Variable | Observed value |
| --- | --- |
| `LastCommand` | `Dock` |
| `LastCommandResult` | `Already In State` |
| `VehicleState` | `Docked` |
| `Online` | `true` |
| `LastCommandError` | empty |

This confirms that the already-docked path still behaves correctly after the
long-running verification implementation.

## 8. Validation Result

The supervised Symcon smoke test passed for the already-docked scenario.

Confirmed:

- published module can still be loaded by Symcon;
- Navimow Device instance remains usable;
- read-only status refresh still works;
- Dock command path still accepts the safe already-docked case;
- command result remains non-error;
- no private ObjectIDs or raw payloads are required for the public report.

Not yet confirmed in Symcon:

- `isDocking` remains `Pending Verification` during a live transition;
- 60-second read-only verification rescheduling;
- terminal transition from `Docking` to `Verified`;
- 15-minute timeout behavior.

## 9. Architecture Decision

### AD-NAV-053: Retest already-docked path before transition path

**Decision:** After changing the Dock verification state machine, first retest
the safe already-docked path in Symcon.

**Rationale:** The already-docked case validates that the published module
still loads, the device instance still communicates through the account
instance, and the command path still reports a non-error result before a
physical transition is triggered.

**Consequence:** A separate supervised Running-to-Docked test remains required
before claiming live transition verification support in Symcon.

## 10. Recommendation

Proceed to one supervised Running-to-Docked Symcon test with the updated
module.

Recommended next SAEF step:

```text
30-dock-transition-verification-live-test.md
```

The next test should:

- start the mower manually through the official app;
- confirm `Running` through the module;
- send exactly one Dock command through Symcon;
- observe `Pending Verification` while the mower is `Docking`;
- wait for `Verified` after `Docked` is observed;
- avoid any command retry.
