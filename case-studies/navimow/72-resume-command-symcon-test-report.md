# 72 Resume Command Symcon Test Report

**Case study:** Navimow native IP-Symcon module
**Status:** Published Resume passed direct supervised Symcon verification
**Date:** 2026-07-12
**Scope:** Verify update compatibility and one Paused-to-Running Resume transition

## 1. Purpose

This step executes the post-update and live-test gates defined in
`70-resume-command-publication-and-symcon-test-plan.md` after publication in
`71-resume-preupdate-baseline-and-publication.md`.

It verifies that the published Resume expansion:

- loads without replacing established Symcon objects;
- preserves operator-enabled archive logging and histories;
- retains healthy OAuth and read-only REST operation;
- sends exactly one Resume command from a fresh Paused state;
- reaches REST `Running`, terminal `Verified` and visible normal mowing;
- leaves cleanup to the official app.

No productive code or repository publication occurs in this step.

## 2. Tested Build

Repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Installed commit:

```text
64188f75527abcb49b0b27ce2b56ad2d34a403fd
feat: add bounded Resume command
```

The operator updated the existing module installation from the published
`main` branch before the test.

## 3. Post-Update Compatibility Gate

The current installation was compared privately with the repeatable pre-update
baseline retained by step 71.

| Area | Result |
| --- | --- |
| account, configurator and device instance identities | unchanged |
| all eight stable variable ObjectIDs | unchanged |
| variable types | unchanged |
| standard and custom profiles | unchanged |
| archive logging flags | unchanged |
| archive aggregation types | unchanged |
| logged variable count | unchanged at 5 |
| bounded history queryability | retained |
| Pause and Dock functions | retained |
| Resume function | newly present as expected |
| Stop and Start functions | still absent |
| active command verification | none |

Compatibility result: **PASS**.

No variable was recreated, migrated or repaired. Existing logging and archive
histories therefore remain attached to the same Symcon variable objects.

## 4. Read-Only Smoke Gate

Before physical preparation, the updated module passed:

- active account, configurator and device instances;
- healthy Connected state without reauthorization requirement;
- unchanged REST error baseline;
- one successful read-only device status refresh;
- valid vehicle state, online and battery values;
- unchanged command timestamp and command result;
- Pause, Resume and Dock availability;
- Stop and Start exclusion;
- no active command verification.

Smoke result: **PASS**.

## 5. Physical Preparation

The operator retained visual supervision and exclusive control. The official
app and physical stop remained available.

Setup was performed outside Symcon:

1. start normal mowing in the official app;
2. observe normal mowing;
3. pause through the official app;
4. confirm visible stationary behavior;
5. confirm supervision and a clear movement path.

A final read-only module refresh confirmed:

```text
VehicleState: Paused
Online: true
CommandActive: false
```

The subsequent `Resume()` invocation performed its own additional current
Paused read before transmission.

## 6. Single Resume Execution

Exactly one Resume invocation was executed through the updated Symcon module.

Observed immediate result:

```text
Resume command was accepted.
```

Safety invariants:

- one explicit invocation;
- one account command-send path;
- no command retry;
- no repeat after acceptance;
- no Symcon Pause, Dock, Stop or Start used for setup or cleanup.

## 7. Verification Result

The module's bounded read-only verification reached:

```text
LastCommand: Resume
VehicleState: Running
LastCommandResult: Verified
LastCommandError: empty
CommandActive: false
```

The command timestamp advanced from the private pre-command baseline. No
second timestamp advance or duplicate command was observed.

The operator independently confirmed that the mower visibly resumed normal
mowing. REST state, module result and physical behavior therefore agree.

Live Resume result: **PASS**.

## 8. Cleanup

After terminal verification, the operator sent the mower home using the
official Navimow app.

A final read-only Symcon check confirmed:

- an expected return-to-station state;
- last Symcon command still Resume;
- last Symcon command result still Verified;
- empty command error;
- no active command verification.

No cleanup command was sent through Symcon.

## 9. Public Evidence Boundary

This report intentionally excludes:

- Symcon ObjectIDs;
- private identity and archive hashes;
- exact timestamps and archive values;
- device and account identifiers;
- tokens, OAuth secrets and raw cloud responses;
- hostnames, garden data and movement details.

The detailed pre-update baseline remains under the ignored `private/`
boundary.

## 10. Residual Risks

- Resume evidence covers one mower and one direct Symcon transition.
- Cloud/API behavior remains unofficial and vendor-controlled.
- Resume timeout and failure paths are deterministic harness evidence, not
  deliberately induced productive failures.
- Physical movement always requires operator judgment and supervision.
- Stop and Start have independent evidence and safety gates and remain disabled.
- Public OAuth feasibility remains vendor-blocked.
- Symcon Store preparation remains planning-only until command integration is
  substantially complete.

## 11. Architecture Decisions

### AD-NAV-236: Treat archive equality as a hard update gate

**Decision:** Permit actuation only after exact variable identity and archive
configuration compatibility.

**Rationale:** Loader success alone cannot protect automations and historical
data.

**Consequence:** The operator-enabled logging on all five variables remains
attached to the original objects.

### AD-NAV-237: Separate setup, actuation and cleanup ownership

**Decision:** Use the official app for preparation and cleanup and Symcon only
for the single command under test.

**Rationale:** This isolates Resume evidence from Pause and Dock behavior.

**Consequence:** The observed transition is attributable to one Symcon Resume
write.

### AD-NAV-238: Require agreement across three evidence layers

**Decision:** Accept Resume only when cloud acceptance, later REST state and
visible mower behavior agree.

**Rationale:** HTTP acceptance alone does not prove movement or command
completion.

**Consequence:** The test establishes both protocol and physical transition
evidence.

### AD-NAV-239: Preserve the untagged publication state

**Decision:** Do not create a tag as part of the live test report.

**Rationale:** Tag and broader pilot decisions require a separate integration
review.

**Consequence:** Historical pilot tags remain immutable and correctly scoped.

## 12. Decision

**Post-update compatibility: PASS.**

**Read-only smoke test: PASS.**

**Direct supervised Resume transition: PASS.**

The published bounded Resume implementation is verified for continued private
pilot use. Existing variable identities, archive logging and histories are
preserved.

This result does not enable Stop, Start, Store submission or broad public
release.

## 13. Recommended Next Step

Create `73-resume-integration-review-and-stop-readiness.md` to consolidate
Pause and Resume evidence, decide whether the command-expanded pilot warrants
a new immutable tag, and evaluate Stop evidence readiness as an independent
fail-closed gate. Start remains separately gated and disabled.
