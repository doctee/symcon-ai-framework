# 70 Resume Command Publication and Symcon Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Pre-update baseline, publication and supervised Resume test planned
**Date:** 2026-07-12
**Scope:** Gate exact publication and one direct Paused-to-Running Symcon test

## 1. Purpose

This step defines how the locally validated Resume implementation from
`69-resume-command-implementation.md` may be published and tested on the
private-pilot IP-Symcon installation.

It corrects the evidence limitation from step 63 by requiring a compatibility
baseline before the user updates the module.

Ordered gates:

1. pre-update Symcon identity and archive baseline;
2. canonical and official schema validation;
3. exact publication to `symcon-navimow/main`;
4. user-controlled Symcon update;
5. post-update compatibility equality before actuation;
6. read-only module, authentication and status smoke test;
7. one supervised Resume action;
8. read-only verification and official-app cleanup.

No baseline capture, push, update or mower command occurs in this planning
step.

## 2. Authorized Boundary

Conditionally authorized after each prior gate passes:

- read-only inspection of the existing Symcon installation;
- private retention of a pre-update compatibility baseline;
- publication of the exact canonical distribution;
- manual update from the remotely verified commit;
- private pre/post identity and archive comparison;
- manual Start and Pause preparation through the official app;
- exactly one Resume through the updated Symcon module;
- read-only observation and official-app recovery.

Not authorized:

- Symcon Pause as setup;
- Stop, Start or Dock command testing;
- a second Resume after ambiguity or timeout;
- unattended operation;
- tag creation or movement;
- Store submission or broad release claims;
- MQTT/WSS work;
- unrelated OAuth changes.

## 3. Current Publication State

Repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Current `main` baseline:

```text
e82f73f752c4b588f13a5fb5331413279d2b77f7
feat: add bounded Pause command
```

The established publish clone is clean and aligned with `origin/main`.
Historical pilot tags remain immutable.

## 4. Mandatory Pre-Update Baseline

Capture while Symcon still runs `e82f73f`, before Resume publication or update.

Private file:

```text
private/navimow-symcon-baseline/resume-pre-update.json
```

The root `/private/` rule keeps it outside Git.

Capture privately:

- account, configurator, device and Archive Control instance identities;
- active status of all Navimow instances;
- current function availability;
- account connection, reauthorization and REST error state;
- command timestamp/result baseline;
- all variable metadata from section 5;
- deterministic identity and archive hashes;
- bounded history-query success for every logged variable.

Do not store tokens, secrets, device identifiers, raw API payloads or private
hostnames.

## 5. Stable Variable Baseline

Required Idents:

```text
VehicleState
Online
BatteryLevel
LastStatusUpdate
LastCommand
LastCommandAt
LastCommandResult
LastCommandError
```

For each capture:

- numeric ObjectID;
- variable type;
- standard profile;
- custom profile;
- effective profile;
- Archive Control logging flag;
- aggregation type;
- bounded history queryability.

Expected pre-update functions:

```text
present: NAVDV_Pause, NAVDV_Dock
absent:  NAVDV_Resume, NAVDV_Stop, NAVDV_Start
```

## 6. Private Baseline Format

```json
{
  "schemaVersion": 1,
  "purpose": "resume-pre-update-compatibility",
  "capturedAt": "PRIVATE_TIMESTAMP",
  "instances": {},
  "variables": {},
  "identityHash": "PRIVATE_SHA256",
  "archiveHash": "PRIVATE_SHA256",
  "historyQueryable": true
}
```

Hash deterministic sorted JSON:

- identity hash: instance identities plus Ident, ObjectID, type and effective
  profile;
- archive hash: Ident, logging flag and aggregation type.

Full records remain private for diagnosis. Public reports expose equality
booleans and counts only, never IDs or hash values.

## 7. Baseline Acceptance

Baseline capture passes only when:

- all expected instances exist and are active;
- every stable Ident resolves once;
- metadata and archive settings are readable;
- every logged history is queryable;
- authentication is healthy;
- the expected pre-Resume function set is observed;
- both deterministic hashes exist;
- private file creation succeeds;
- an immediate second capture yields equal hashes.

Any mismatch blocks publication.

## 8. Canonical Validation Gate

After baseline capture, rerun:

- PHP syntax for every productive distribution file;
- JSON parsing for all metadata, forms and locales;
- REST/auth/fixture tests;
- all 29 deterministic harness cases;
- distribution structure validator;
- Dock and Pause regression suite;
- seven Resume harness cases;
- Stop and Start rejection;
- one symbolic device command-send path scan;
- zero command retry paths;
- public variable registration invariance;
- privacy and whitespace scans.

Every check must pass.

## 9. Official Module Validator

Validate the exact candidate:

- `library.json`;
- three `module.json` files;
- three `locale.json` files;
- three `form.json` files.

Use the official browser validator when functional. If the established
`$ is not defined` defect remains, record it and execute the current four
official schemas with exact AJV `6.10.2`. Require ten PASS results.

Temporary schema dependencies are not published. The custom distribution
validator alone is insufficient.

## 10. Expected Publication Boundary

Canonical source:

```text
case-studies/navimow/distribution/
```

Expected seven-file delta:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowDevice/form.json
NavimowDevice/locale.json
NavimowDevice/module.php
README.md
libs/Navimow/CommandContract.php
```

Any other productive difference stops publication pending review.

Do not publish SAEF documents, fixtures, tests, private baselines/captures,
validator dependencies, credentials or local metadata.

## 11. Publication Procedure

1. prove the private baseline passed;
2. verify publish clone clean state;
3. fetch and fast-forward `origin/main`;
4. record current commit and tag references;
5. require exactly the expected delta;
6. synchronize the seven canonical files;
7. prove byte parity excluding repository metadata;
8. rerun PHP, JSON, schema, privacy and whitespace checks;
9. inspect the complete diff;
10. create one commit;
11. push `main` by fast-forward only;
12. verify remote branch and changed blobs independently;
13. assert remote Resume method, payload and form action;
14. verify clean clone and unchanged historical tags;
15. stop before Symcon update.

Suggested subject:

```text
feat: add bounded Resume command
```

No tag is created.

## 12. Manual Update Preconditions

Before the user presses Update:

- private baseline file exists and parses;
- purpose and schema version match;
- publication commit is remotely verified;
- mower is docked or under official-app control;
- no command verification or capture is active;
- enough time remains for comparison and supervised testing.

The user updates manually from `main` only after explicit instruction.

## 13. Post-Update Compatibility Gate

Before any mower action, capture the same structure and compare it with the
private baseline.

Required equality:

| Area | Result |
| --- | --- |
| account/configurator/device instance IDs | unchanged |
| all eight variable ObjectIDs | unchanged |
| variable types and profiles | unchanged |
| archive logging and aggregation | unchanged |
| history queryability | retained |
| identity hash | equal |
| archive hash | equal |

Expected intentional function change:

```text
NAVDV_Resume: absent -> present
```

Still absent:

```text
NAVDV_Stop
NAVDV_Start
```

Any unexpected mismatch blocks all mower commands. Do not recreate or delete
variables as a repair attempt.

## 14. Read-Only Smoke Gate

After compatibility equality:

1. confirm all module types and instances active;
2. require account Connected and reauthorization false;
3. record REST error and command diagnostic baseline;
4. perform one device status refresh;
5. require valid VehicleState, Online and BatteryLevel;
6. require REST errors unchanged;
7. require command timestamp/result unchanged;
8. confirm Pause, Resume and Dock available;
9. confirm Stop and Start absent;
10. confirm no secret-bearing output.

Only then may physical preparation start.

## 15. Physical Safety Gate

Resume can initiate movement and cutting immediately.

Require explicit operator confirmation that:

- mower and likely initial route remain visible;
- nobody is beside or in front of the mower;
- people, animals and obstacles are clear;
- weather and ground permit mowing;
- official app and physical stop are immediately available;
- schedules cannot interfere;
- one operator has exclusive control;
- no other command/capture is active;
- Paused belongs to the currently supervised mowing task;
- official-app cleanup is possible.

Loss of any condition aborts before transmission.

## 16. Paused Preparation

Do not use a Symcon command for setup:

1. start normal mowing in the official app;
2. observe expected mowing;
3. pause in the official app;
4. confirm visible stationary state;
5. clear the movement path again;
6. refresh Symcon status read-only;
7. require Paused and Online.

`Resume()` performs another current Paused read in the same invocation.

## 17. Single Resume Procedure

Before dispatch, capture command diagnostics privately and explicitly confirm
that one action may start movement and cutting.

Then:

1. invoke `NAVDV_Resume()` exactly once;
2. record the bounded return message;
3. never invoke it again under any outcome;
4. replace temporary action code with read-only observer content;
5. observe normal module variables and timer-driven status only.

Expected lifecycle:

```text
Paused
  -> one Resume
  -> Accepted
  -> Pending Verification
  -> Running
  -> Verified
```

Acceptance requires:

- `LastCommand == Resume`;
- one advanced command timestamp;
- later current Running;
- terminal Verified;
- empty command error;
- visibly normal expected mowing;
- no second command timestamp.

Pending may be too brief to observe. Current Running plus Verified and one-write
evidence are sufficient.

## 18. Abort Rules

| Observation | Response |
| --- | --- |
| baseline mismatch | stop before command |
| auth/status smoke failure | stop before preparation |
| current state not Paused | do not send Resume |
| immediate command error | no retry; status reads only |
| unsupported already-in-state | Failed; no retry |
| movement without REST Running | unresolved; recover safely |
| REST Running without expected movement | unresolved; intervene |
| still Paused at 60 seconds | timeout; no retry |
| unexpected state | fail closed; official-app recovery |
| unsafe movement | physical stop immediately |
| Symcon instability | end test; official controls only |

HTTP or cloud acceptance alone is insufficient.

## 19. Cleanup

After terminal evidence:

1. continue supervision;
2. choose Pause or return to station in the official app;
3. send no Symcon cleanup command;
4. optionally refresh status read-only;
5. confirm no verification remains active;
6. delete temporary scripts;
7. leave baseline and public variables unchanged.

## 20. Public Evidence Boundary

Public reports may include:

- publication commit;
- ten validator results;
- module/function markers;
- identity/archive equality booleans;
- stable/logged variable counts;
- symbolic command/state/result;
- relative timing;
- physical observation and cleanup category.

Never publish private hashes, ObjectIDs, tokens, device/account IDs, hostnames,
raw responses, mower/garden information or archive values.

## 21. Result Classification

| Result | Meaning |
| --- | --- |
| `BASELINE PASS` | repeatable pre-update snapshot permits publication |
| `PUBLICATION PASS` | remote parity permits manual update |
| `COMPATIBILITY PASS` | pre/post equality permits smoke test |
| `SMOKE PASS` | healthy read-only runtime permits physical setup |
| `LIVE PASS` | one Resume, Running, Verified and expected motion |
| `LIVE PARTIAL` | physical or REST evidence incomplete; no retry |
| `LIVE FAIL` | rejection, unsafe result or regression |
| `BLOCKED` | prior gate unavailable; no command |

No outcome authorizes Stop or Start.

## 22. Architecture Decisions

### AD-NAV-225: Capture baseline before publication

**Decision:** Step 71 begins with current Symcon baseline capture.

**Rationale:** The operator cannot update before evidence exists when the remote
update has not yet been published.

**Consequence:** Publication is blocked by baseline completeness.

### AD-NAV-226: Store details privately and publish equality only

**Decision:** Retain full diagnostic records and hashes under `private/`.

**Rationale:** Diagnosis needs exact IDs; public SAEF does not.

**Consequence:** Compatibility proof is strong and privacy-safe.

### AD-NAV-227: Make equality an actuation gate

**Decision:** Loader success cannot override identity/archive mismatch.

**Rationale:** Command expansion must preserve automation and history.

**Consequence:** Any mismatch blocks Resume testing.

### AD-NAV-228: Prepare Paused through the official app

**Decision:** Direct Symcon evidence contains one module write only.

**Rationale:** Symcon Pause setup would couple two command paths.

**Consequence:** Resume attribution remains unambiguous.

### AD-NAV-229: Publish without tag

**Decision:** Defer tagging until direct Resume evidence and review pass.

**Rationale:** Tags identify runtime-verified scope.

**Consequence:** Historical tags remain unchanged.

### AD-NAV-230: Keep cleanup outside Symcon

**Decision:** Use official app or physical stop after the test.

**Rationale:** Cleanup must not consume a second module write.

**Consequence:** One-write evidence remains intact.

### AD-NAV-231: Keep Store and remaining commands independent

**Decision:** Resume success does not release OAuth, Stop, Start or Store gates.

**Rationale:** They are separate evidence and product boundaries.

**Consequence:** Store work remains planning-only.

## 23. Gate Decision

**GO for pre-update baseline capture and controlled publication execution.**

**Conditional GO for one supervised direct Symcon Resume test only after
compatibility equality and read-only smoke pass.**

No mower command is authorized before those conditions are evidenced.

## 24. Recommended Next Steps

Execute `71-resume-preupdate-baseline-and-publication.md` to capture the private
baseline, validate, publish and remotely verify without a tag, then stop before
the Symcon update.

After explicit user update confirmation, execute
`72-resume-command-symcon-test-report.md`, beginning with mandatory baseline
comparison and read-only smoke testing.
