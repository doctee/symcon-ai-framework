# 69 Resume Command Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Local Resume implementation and deterministic validation passed
**Date:** 2026-07-12
**Scope:** Implement bounded Resume without publication or live Symcon use

## 1. Purpose

This step implements the conditional GO from
`68-resume-command-fixture-validation-and-implementation-readiness.md`.

The canonical case-study distribution now adds Resume while preserving:

- exact Dock behavior;
- published Pause behavior;
- one-write command safety;
- restart-safe read-only verification;
- all public variable identity and archive contracts.

No module repository publication, Symcon update or mower command occurs in this
step.

## 2. Changed Components

| Component | Change |
| --- | --- |
| `libs/Navimow/CommandContract.php` | allowlist Resume and build Boolean-true payload |
| `NavimowDevice/module.php` | add Resume action, Paused eligibility and Running verification |
| account and device forms | expose Resume and retain Stop/Start exclusion |
| locale files | add movement-aware German Resume text |
| distribution README | document actual Pause/Resume/Dock scope and safety |
| REST/auth tests | verify exact payload and captured response fixture |
| deterministic harness | cover Resume lifecycle, failure, timeout and restart |

No API endpoint, public variable, profile association or account credential
path was added.

## 3. Command Contract

The account allowlist now accepts exactly:

```text
Dock
Pause
Resume
```

Resume request:

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
          "on": true
        }
      }
    }
  ]
}
```

Pause remains the same command string with boolean `false`. Dock retains its
empty parameter object. Stop and Start remain rejected.

## 4. Runtime Eligibility

`NavimowDevice::Resume()` applies these gates:

1. reject while another command remains active;
2. reject an empty device configuration;
3. perform a current read-only status request;
4. reject after read or mapping failure;
5. require exact current Paused state;
6. only then send one symbolic Resume command.

This same-invocation read prevents Resume from relying on stale Paused state.

Eligibility rejection does not change `LastCommand*` and does not call the
command endpoint.

## 5. Action Boundary

Resume is available through:

- `RequestAction('Resume', ...)`;
- public module method `NAVDV_Resume()`;
- the explicit configuration-form button.

The form confirmation states that movement and cutting may begin immediately.

No user-facing state variable is made writable. `VehicleState` remains owned by
status mapping only.

## 6. Command Lifecycle

Successful path:

```text
Requested
  -> Accepted
  -> Pending Verification
  -> Verified
```

Resume uses stable command profile value:

```text
5 = Resume
```

Existing diagnostics remain:

```text
LastCommand
LastCommandAt
LastCommandResult
LastCommandError
```

No new diagnostic or archive-enabled variable is introduced.

## 7. Response Policy

Nested `SUCCESS` becomes Accepted and starts read-only verification.

For Resume, `alreadyInState` is explicitly unsupported and becomes Failed:

```text
Resume already-in-state response is unsupported.
```

This differs intentionally from Dock. A fresh Paused precondition conflicts
with an unproven already-Running interpretation, so the implementation fails
closed rather than inheriting Dock semantics.

Malformed, rejected, transport and unknown responses also terminate without
command retry.

## 8. Verification Policy

Resume uses:

```text
2s -> 5s -> 10s -> 20s -> 30s -> 60s
```

Rules:

- current Running completes as Verified;
- Paused remains an allowed transient state;
- failed reads remain bounded while the deadline permits;
- any other successfully read state becomes Failed;
- 60 seconds without Running becomes Verification Timeout;
- timer and restart paths send no command.

Timeout message:

```text
Running state was not confirmed before the verification timeout.
```

## 9. Shared Short-Transition Scheduler

The prior Pause-specific constants were renamed to:

```text
SHORT_VERIFICATION_TIMEOUT_SECONDS
SHORT_VERIFICATION_SCHEDULE_SECONDS
```

Pause and Resume share only these captured timing mechanics.

They retain explicit differences:

| Policy | Pause | Resume |
| --- | --- | --- |
| fresh precondition | Running | Paused |
| request boolean | false | true |
| terminal state | Paused | Running |
| allowed transient | Running | Paused |
| already-in-state | existing generic behavior | fail closed |
| physical direction | stops movement | initiates movement |

Dock remains on its separate long-running schedule.

## 10. Persistent Restart State

Resume uses `CommandKind == 5` in the existing internal attribute.

`activeCommandKind()` now explicitly recognizes:

- Pause;
- Resume;
- Dock fallback for old active builds.

After restart:

- original command start and deadline remain persisted;
- Resume selects Running as its target;
- the next short-schedule read is reconstructed from elapsed time;
- no command is replayed.

## 11. Variable and Archive Compatibility

No existing `RegisterVariable*()` call changed.

Preserved:

- all eight stable device Idents;
- all types and profile names;
- VehicleState association values;
- Command association value `5` for Resume;
- registration positions;
- existing ObjectIDs under idempotent `ApplyChanges()`;
- user Archive Control logging and aggregation;
- accumulated histories.

Only productive methods, internal command policy, forms and documentation were
extended.

## 12. Deterministic Resume Evidence

Seven new harness cases pass:

| Case | Evidence |
| --- | --- |
| fresh Paused and verify | one pre-read, one write, later Running Verified |
| non-Paused rejection | no command write |
| failed pre-read rejection | no command write |
| bounded schedule and timeout | documented reads, one write total |
| unexpected state | fail closed without retry |
| already-in-state | Failed with no verification timer |
| restart recovery | read-only reconstruction and Running verification |

The harness now passes 29 cases in total.

## 13. Contract and Fixture Tests

Passed checks include:

- exact Dock payload unchanged;
- exact Pause boolean `false` unchanged;
- exact Resume boolean `true`;
- Stop and Start remain rejected;
- invalid device ID remains rejected;
- Resume success fixture maps to Accepted;
- existing command parser failure cases remain defensive;
- captured Running and Paused mapping remain unchanged;
- device source contains one symbolic command send path only.

## 14. Full Local Validation

Passed:

- PHP syntax for all productive distribution files;
- JSON parsing for metadata, forms and locales;
- REST/auth/fixture tests;
- all 29 deterministic harness cases;
- distribution structure validator;
- whitespace validation;
- no public variable registration diff;
- changed-file privacy scan;
- existing Dock and Pause regression cases.

The official Module Validator, publication and direct Symcon gates belong to a
later step and were not executed here.

## 15. Safety Invariants

The implementation preserves:

- one explicit action causes at most one Resume write;
- current Paused is required before transport;
- cloud acceptance never writes Running;
- only later status reads can verify Running;
- no transport or verification path retries Resume;
- another active command blocks Resume;
- restart resumes reads only;
- unsupported already-in-state fails closed;
- Stop and Start remain unavailable;
- operational recovery remains the official app or physical stop.

## 16. Architecture Decisions

### AD-NAV-220: Extend the allowlist by one fixture-backed command

**Decision:** Add Resume while keeping Stop and Start rejected.

**Rationale:** Resume now has exact request, response, state and physical
evidence.

**Consequence:** Command expansion remains incremental.

### AD-NAV-221: Share timing mechanics between inverse short transitions

**Decision:** Rename Pause timing constants and use them for Resume.

**Rationale:** Both real captures reached their terminal state at the first
two-second read and use the same bounded evidence schedule.

**Consequence:** Timing duplication is removed while state policy remains
explicit.

### AD-NAV-222: Persist Resume as an explicit command kind

**Decision:** Recognize command value `5` during active verification and
restart reconstruction.

**Rationale:** Treating unknown kinds as Dock would select an unsafe target and
deadline.

**Consequence:** Resume survives restart without replay or policy confusion.

### AD-NAV-223: Reject Resume already-in-state

**Decision:** Finish as Failed before scheduling verification.

**Rationale:** No real evidence defines this response and it contradicts the
fresh Paused gate.

**Consequence:** A future relaxation requires a dedicated capture.

### AD-NAV-224: Preserve existing public identity

**Decision:** Use the reserved profile association and existing diagnostics
without adding or recreating variables.

**Rationale:** Automations and archive histories depend on stable objects.

**Consequence:** The later update requires equality evidence, not migration.

## 17. Decision

**Local Resume implementation: PASS.**

**Publication and direct Symcon Resume test: NO-GO in this step.**

The implementation may advance to publication and supervised test planning
only after the pre-update compatibility baseline procedure is ready.

Stop and Start remain disabled. Broader release and Store work remain blocked.

## 18. Recommended Next Step

Create SAEF step `70-resume-command-publication-and-symcon-test-plan.md` to:

1. capture anonymized variable and archive baseline before the Symcon update;
2. run the official Module Validator gate;
3. publish the exact canonical distribution without a tag;
4. remotely verify content and immutable historical tags;
5. update Symcon only after the baseline exists;
6. compare ObjectID, type, profile and archive markers before actuation;
7. execute one supervised Resume action from current Paused;
8. require Running, Verified and visible normal mowing;
9. clean up through the official app;
10. retain Store and Start/Stop gates.
