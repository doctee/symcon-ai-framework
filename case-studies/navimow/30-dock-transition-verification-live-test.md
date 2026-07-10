# 30 Dock Transition Verification Live Test

**Case study:** Navimow native IP-Symcon module
**Status:** Supervised Running-to-Docked Symcon transition passed
**Date:** 2026-07-09
**Scope:** Live transition verification with the published long-running Dock
verification implementation

## 1. Purpose

This report records the first direct Symcon live transition test after the
long-running Dock verification implementation was published and installed.

The goal was to verify that a real `Running -> Docking -> Docked` transition
is handled by the native Symcon module without reporting the successful
intermediate `Docking` phase as a timeout.

## 2. Preconditions

The preceding step `29-dock-transition-verification-symcon-test-report.md`
confirmed:

- the updated module was published to `doctee/symcon-navimow`;
- the user updated the module in Symcon;
- the already-docked Dock path still passed;
- the device instance was reachable through Symcon.

Before this live transition test:

- the mower was supervised;
- the official app was available;
- the area was expected to be safe for a short supervised run;
- the mower was started manually through the official app;
- the module confirmed `Running` before the Dock command was sent.

No private ObjectIDs, tokens or raw payloads are included in this report.

## 3. Test Procedure

The procedure was:

1. perform a read-only status precheck;
2. user manually starts the mower in the official app;
3. confirm `Running` through the Symcon module;
4. send exactly one Dock command through the Symcon module;
5. perform only read-only observations afterward;
6. wait until the command result becomes terminal.

Temporary Symcon scripts were used only as MCP-safe probes. They wrote
sanitized PASS/FAIL summaries into their own object names and were deleted
after the test.

## 4. Read-Only Precheck

Initial read-only status before manual start:

| Check | Result |
| --- | --- |
| status refresh | succeeded |
| vehicle state | `Docked` |
| online | `true` |
| battery | `91%` |
| previous command result | `Already In State` |

The test then waited for the user to start the mower manually.

## 5. Running Confirmation

After the user confirmed that the mower had started, Symcon read-only status
confirmed:

| Check | Result |
| --- | --- |
| status refresh | succeeded |
| vehicle state | `Running` |
| online | `true` |
| battery | `92%` |

This satisfied the safety gate for sending one Dock command.

## 6. Dock Command

Exactly one Dock command was sent through the Symcon module.

Observed sanitized response:

```text
Dock command was accepted.
```

Immediate command result:

```text
Accepted
```

No command retry was issued by the test procedure.

## 7. Read-Only Observation

First read-only observation after command acceptance:

| Variable | Observed value |
| --- | --- |
| `LastCommandResult` | `Pending Verification` |
| `VehicleState` | `Docking` |
| `Online` | `true` |
| `LastCommandError` | empty |

This confirms the main design requirement: `Docking` is treated as valid
progress and does not produce a premature verification timeout.

Later read-only observation:

| Variable | Observed value |
| --- | --- |
| `LastCommandResult` | `Verified` |
| `VehicleState` | `Docked` |
| `Online` | `true` |
| `LastCommandError` | empty |

The transition reached the expected terminal success state.

## 8. Validation Result

The supervised live Symcon transition passed.

Confirmed:

- Symcon confirmed `Running` before the Dock command;
- exactly one Dock command was sent;
- the cloud command response was accepted;
- `Docking` mapped to `Pending Verification`, not failure;
- the final `Docked` state mapped to `Verified`;
- `LastCommandError` remained empty;
- no command retry was needed or performed.

Not covered:

- very long `Docking` duration near the 15-minute timeout;
- timeout behavior;
- restart or `ApplyChanges()` during active verification;
- cloud read failures during the verification window.

## 9. Architecture Decision

### AD-NAV-054: Long-running Dock verification is MVP-ready

**Decision:** Treat the Dock command verification path as MVP-ready for
supervised use after the successful Symcon live transition.

**Rationale:** The module handled the complete `Running -> Docking -> Docked`
sequence through Symcon with one command, read-only verification, correct
intermediate state handling and final `Verified` result.

**Consequence:** Dock remains the only enabled mower command, but it can now be
considered implemented for the REST MVP command path. Further commands remain
blocked until separate safety and evidence gates are completed.

## 10. Recommendation

The REST MVP now has evidence for:

- OAuth authentication;
- discovery;
- read-only status refresh;
- Dock command when already docked;
- Dock command from running state;
- long-running read-only command verification.

Recommended next SAEF step:

```text
31-rest-mvp-stabilization-and-release-check.md
```

That step should review the complete MVP for release readiness, including:

- public README/user instructions;
- known limitations;
- Symcon Store compatibility assumptions;
- remaining private-data checks;
- tag/version policy;
- whether additional diagnostics should be exposed before broader testing.
