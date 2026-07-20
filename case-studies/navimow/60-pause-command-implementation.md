# 60 Pause Command Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Local implementation and deterministic validation passed; publication pending
**Date:** 2026-07-12
**Scope:** Implement the fixture-backed bounded Pause command slice

## 1. Purpose

This step implements the conditional GO from
`59-pause-command-fixture-validation-and-implementation-readiness.md`.

The implementation adds Pause to the canonical case-study distribution while
preserving the existing Dock behavior, public variable identity and archive
configuration.

No module repository publication and no live mower command occur in this step.

## 2. Changed Components

| Component | Change |
| --- | --- |
| `libs/Navimow/CommandContract.php` | allowlist Pause and build the exact boolean-false request |
| `NavimowDevice/module.php` | add action, fresh-state gate and command-specific verification |
| account and device forms | expose and describe Pause without enabling other commands |
| locale files | add German Pause labels and updated scope text |
| distribution README | document Pause safety and one-write behavior |
| REST/auth test | verify the exact Pause request envelope |
| payload fixture test | verify accepted Pause and Paused mapping |
| deterministic pilot harness | cover eligibility, timing, timeout, restart and no-retry behavior |

The account module transport did not require a new endpoint or method. It
continues to own authentication, serialization and
`/openapi/smarthome/sendCommands`.

## 3. Request Contract

The account command allowlist now accepts exactly:

- `Dock`;
- `Pause`.

Pause produces:

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
        "command": "action.devices.commands.PauseUnpause",
        "params": {
          "on": false
        }
      }
    }
  ]
}
```

The `on` value is a JSON boolean, not a string or integer. Start, Stop and
Resume remain rejected by the allowlist.

## 4. Runtime Eligibility

`NavimowDevice::Pause()` applies these gates in order:

1. reject while another command remains active;
2. reject an empty device configuration;
3. perform one current read-only status request;
4. reject when that read fails;
5. reject unless the mapped current state is Running;
6. only then send exactly one symbolic Pause command.

The immediate pre-read replaces an arbitrary age threshold with stronger
evidence: the command is permitted only after a successful status response in
the same action execution.

A rejected eligibility check does not update command diagnostics and does not
call the command endpoint.

## 5. Command Lifecycle

Pause reuses the existing command lifecycle:

```text
Requested
  -> Accepted
  -> Pending Verification
  -> Verified | Failed | Verification Timeout
```

Cloud acceptance never changes `VehicleState`. Only the normal status mapper
may apply Paused after a successful read.

The existing diagnostic variables remain the only public command diagnostics:

- `LastCommand` uses stable profile value `4` for Pause;
- `LastCommandAt` records dispatch time;
- `LastCommandResult` records lifecycle result;
- `LastCommandError` contains a bounded sanitized message.

No Pause-specific public variable was added.

## 6. Persistent Internal State

One private module attribute extends the existing recovery state:

| Attribute | Purpose |
| --- | --- |
| `CommandKind` | distinguish Pause verification from Dock verification |

The command kind is written before transport and cleared at terminal
completion. On module restart, `ApplyChanges()` reconstructs only the timer;
it never resends the command.

For backward compatibility, an active command created by an older module build
without `CommandKind` is treated as Dock. This preserves an in-flight Dock
verification during update.

## 7. Pause Verification

Pause has a separate schedule from Dock:

```text
2s -> 5s -> 10s -> 20s -> 30s -> 60s
```

Rules:

- Paused after a successful read completes as Verified;
- Running remains pending until the next bounded read;
- a failed read remains pending while the deadline permits;
- an unexpected successfully read state fails closed;
- the 60-second deadline completes as Verification Timeout;
- no verification path sends a command.

The scheduler derives its next interval from persisted command start time and
the current clock. This avoids restarting the complete schedule after a service
restart.

Dock retains its five-second initial read, 60-second progress cadence and
15-minute deadline.

## 8. Variable and Archive Compatibility

No existing `RegisterVariable*()` call was removed, renamed or retyped.

The implementation preserves:

- every public Ident;
- every variable type;
- every profile name;
- all VehicleState and Command association numbers;
- the existing registration positions;
- user-configured Archive Control settings;
- existing variable ObjectIDs during `ApplyChanges()`.

Only internal attributes were added. IP-Symcon creates missing attributes
without recreating public variables.

## 9. Safety Properties

The productive slice maintains these invariants:

- one explicit action causes at most one command write;
- Pause cannot run against a stale cached Running value;
- command transport has no automatic retry;
- verification repeats reads only;
- concurrent commands are rejected;
- malformed or unknown cloud responses fail closed;
- Resume is not inferred from the inverse boolean;
- Restart resumes observation, not actuation;
- live recovery remains the official app or physical stop control.

## 10. Deterministic Test Evidence

### Contract and fixture tests

Passed checks include:

- exact Dock payload unchanged;
- exact Pause payload with boolean `false`;
- Start remains rejected;
- successful Pause fixture maps to Accepted;
- Paused fixture maps to the existing Paused association;
- existing auth, discovery, status and response-failure checks.

### Device harness

New passing cases:

| Case | Evidence |
| --- | --- |
| fresh Running and verify | read precedes one write; Paused at 2 seconds verifies |
| non-Running rejection | no command write |
| failed pre-read rejection | no command write |
| bounded schedule and timeout | reads at documented offsets; one write total |
| unexpected state | terminal fail-closed result; no retry |
| restart recovery | timer restored; Paused verifies without replay |

All 16 existing Dock/recovery/OAuth harness cases remain green. The harness now
passes 22 cases in total.

### Static validation

Passed:

- PHP syntax for changed productive and test files;
- all REST client/auth checks;
- payload mapper fixture checks;
- deterministic pilot observation harness;
- JSON parsing for distribution metadata, forms, locales and fixtures;
- local distribution structure validator;
- whitespace/error check.

The official browser-based IP-Symcon Module Validator and direct IP-Symcon
runtime test remain publication gates for the next step.

## 11. Architecture Decisions

### AD-NAV-175: Use an immediate status pre-read for Pause

**Decision:** Require a successful Running read in the same Pause action.

**Rationale:** This is stronger and easier to audit than selecting an arbitrary
cache-freshness threshold.

**Consequence:** Pause performs one additional safe read before its write and
fails without actuation when status is unavailable.

### AD-NAV-176: Persist command kind across restart

**Decision:** Store the active command profile value as an internal attribute.

**Rationale:** Verification semantics and deadlines differ between Dock and
Pause and must survive service restart.

**Consequence:** Restart can reconstruct the correct timer without replaying
the command.

### AD-NAV-177: Keep command policy explicit in the device module

**Decision:** Share transport and lifecycle mechanics while keeping Pause and
Dock eligibility, target states and timing explicit.

**Rationale:** Two commands demonstrate common mechanics but have materially
different physical behavior.

**Consequence:** Resume, Stop and Start cannot become enabled through a generic
configuration shortcut.

### AD-NAV-178: Preserve all public variable identity

**Decision:** Add no public Pause state or diagnostics variable.

**Rationale:** Existing variables already represent state and command outcome,
and their ObjectIDs own user archive history.

**Consequence:** Updating the module does not require archive migration.

## 12. Decision

The bounded Pause implementation is **locally passed**.

Publication and live use remain **No-Go in this step**. They require a separate
release action, official metadata validation and one supervised IP-Symcon
Running-to-Paused test.

Resume, Stop and Start remain disabled.

## 13. Recommended Next Step

Create SAEF step `61-pause-command-publication-and-symcon-test-plan.md` to:

1. run the official Module Validator gate;
2. publish the reviewed distribution to `symcon-navimow` without tagging;
3. update the module on the Win11 IP-Symcon host;
4. verify existing variable ObjectIDs and archive settings before actuation;
5. execute one supervised Pause action from current Running;
6. verify Accepted, Pending Verification and Verified diagnostics;
7. confirm that only one Pause write occurred;
8. document the result before any Resume work begins.
