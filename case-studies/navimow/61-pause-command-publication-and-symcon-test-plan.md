# 61 Pause Command Publication and Symcon Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication and supervised Symcon test planned; execution pending
**Date:** 2026-07-12
**Scope:** Gate publication and one direct Running-to-Paused Symcon test

## 1. Purpose

This step defines how the locally validated Pause implementation from
`60-pause-command-implementation.md` may be published and tested on the private
pilot IP-Symcon installation.

The plan separates:

1. canonical validation;
2. publication to the dedicated module repository;
3. read-only post-update smoke testing;
4. preservation checks for existing variables and archive logging;
5. one supervised Pause command;
6. sanitized evidence and cleanup.

No repository push, Symcon update or mower command is performed in this step.

## 2. Test Boundary

Authorized by this plan after every preceding gate passes:

- publish the reviewed canonical distribution to `symcon-navimow/main`;
- update the existing module in the private pilot installation;
- perform read-only module, authentication and status checks;
- start mowing manually through the official Navimow app;
- invoke exactly one Pause action through the updated Symcon module;
- observe command diagnostics and the later current device state;
- recover manually through the official app or physical control if required.

Not authorized:

- Resume, Stop, Start or Dock command testing;
- automatic or manual command repetition after an ambiguous result;
- unattended mower operation;
- creation or movement of a release tag;
- Store submission or broader release claims;
- MQTT/WSS work;
- OAuth credential changes unless the read-only smoke test proves they are
  independently required.

## 3. Roles and Systems

| Role or system | Responsibility |
| --- | --- |
| SAEF workspace on Mac | canonical source, tests, publication evidence and public report |
| dedicated publish clone | clean synchronization and commit to module repository |
| `symcon-navimow/main` | update source for the private pilot installation |
| Win11 IP-Symcon host | module update, existing instances and action execution |
| official Navimow app | manual Start, operational observation and recovery |
| operator | continuous physical supervision and final safety authority |
| MCP, when available | sanitized read-only assertions and one explicitly approved Pause invocation |

MCP must not receive or return OAuth secrets, tokens, real device identifiers,
private hostnames or ObjectIDs. Manual Symcon operation remains the fallback
when the required MCP capability is unavailable.

## 4. Publication Source and Target

Canonical source:

```text
case-studies/navimow/distribution/
```

Dedicated repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Target branch:

```text
main
```

No tag is created. The existing immutable pilot tags must remain unchanged.

The publication commit should use a Conventional Commit message such as:

```text
feat: add bounded Pause command
```

The exact commit hash must be recorded only after successful commit and push.

## 5. Publication File Boundary

The dedicated repository must become byte-equivalent to the canonical
distribution, excluding repository metadata and ignored local OS files.

Expected productive changes include:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowDevice/form.json
NavimowDevice/locale.json
NavimowDevice/module.php
README.md
libs/Navimow/CommandContract.php
```

If synchronization reveals another changed productive file, stop and explain
the difference before committing.

Do not publish:

- SAEF case-study documents;
- fixture files;
- harness or test files;
- private captures;
- local publication scripts;
- credentials or installation-specific data;
- `.DS_Store` or editor metadata.

## 6. Pre-Publication Validation Gate

Run the complete local validation again immediately before synchronization:

```text
PHP syntax: all distribution PHP files
JSON parse: all distribution metadata, forms and locales
REST/auth tests
22-case deterministic observation harness
distribution structure validator
Git whitespace check
private-data scan
```

Required results:

| Check | Acceptance |
| --- | --- |
| productive PHP syntax | all passed |
| distribution JSON | all passed |
| REST/auth contract tests | passed |
| deterministic harness | 22 of 22 passed |
| distribution validator | passed |
| Start, Stop and Resume allowlist tests | rejected as expected |
| command retry scan | no Pause retry path |
| variable registration diff | no existing Ident, type or profile change |
| privacy scan | no finding |

Any failure blocks publication.

## 7. Official Module Validator Gate

Validate the exact publish candidate with the official IP-Symcon Module
Validator or the previously adopted equivalent official schemas.

Required files:

- `library.json`;
- all three `module.json` files;
- all three `form.json` files;
- all three `locale.json` files.

All files must pass. If the browser validator is unavailable again, the report
must record the failure and use the same locally executed official schemas as
the established fallback. A custom distribution validator alone is not a
substitute for this gate.

## 8. Publication Procedure

1. verify that the dedicated publish clone is clean;
2. fetch and fast-forward it to current `origin/main`;
3. record the pre-publication commit;
4. synchronize only the canonical distribution into the repository root;
5. compare all files and confirm canonical parity;
6. inspect the complete diff;
7. rerun PHP, JSON, distribution and privacy checks in the publish clone;
8. create one commit with the approved scope;
9. push `main` as a fast-forward update;
10. verify remote `main` identifies the new commit;
11. read the changed remote files independently;
12. confirm the publish clone is clean;
13. confirm existing pilot tags still identify their original commits.

Do not proceed to Symcon while local, remote or canonical hashes disagree.

## 9. Symcon Update Preconditions

Before updating the module:

- no mower command verification may be active;
- mower should be safely docked or otherwise under normal official-app control;
- current account authentication should be healthy;
- the operator must have access to the Symcon module management UI;
- existing device instance and archive logging must be available for baseline
  inspection;
- enough time must remain for a supervised live test and manual recovery.

If a command remains active, wait for its terminal result or resolve it safely
before updating. Do not update during a physical Dock transition.

## 10. Pre-Update Compatibility Baseline

Before the Symcon module update, capture privately for the existing device
instance:

- module instance status;
- ObjectID behind every stable variable Ident;
- variable type and profile;
- Archive Control logging enabled/disabled state;
- archive aggregation type when logging is enabled;
- current `VehicleState`, `Online` and `BatteryLevel`;
- current `LastCommandAt` and `LastCommandResult`;
- account `ConnectionState`, `ReauthRequired` and `RestErrorCount`.

Stable Idents to compare:

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

Actual ObjectIDs remain private. The public report may record only equality
markers such as `O11111111` and archive-preservation markers, never the numeric
IDs.

## 11. Module Update and Read-Only Smoke Gate

After publication, the user updates the Navimow module from `main` in Symcon.

Before any mower action, verify:

1. all three Navimow module types remain available;
2. existing account, configurator and device instances still load;
3. no instance was recreated;
4. account remains Connected;
5. reauthorization is not required;
6. one explicit device status refresh succeeds;
7. vehicle state, online and battery values remain valid;
8. REST error count does not increase;
9. command timestamp and result remain unchanged by the read;
10. the Pause button or `NAVDV_Pause()` function is available;
11. Resume, Stop and Start are not exposed;
12. no secret-bearing error or debug output appears.

Then compare the compatibility baseline:

| Check | Required result |
| --- | --- |
| variable ObjectIDs | unchanged for every stable Ident |
| variable types | unchanged |
| profiles | unchanged |
| archive logging flags | unchanged |
| archive aggregation | unchanged |
| accumulated archive data | still queryable |

Any failed compatibility check blocks the live Pause test and requires a
rollback or implementation review without deleting existing variables.

## 12. Physical Safety Gate

The live test may start only when the operator confirms:

- mower and operating area remain continuously visible;
- people, animals and obstacles are clear;
- weather and ground conditions permit normal operation;
- automatic schedules cannot interfere;
- official Navimow app is connected;
- physical stop control is immediately reachable;
- one operator has exclusive control;
- no other Symcon or capture command test is active;
- the operator understands that Pause may leave the mower stationary in the
  lawn;
- manual recovery can be performed without testing Resume through Symcon.

If any condition changes, stop the test and use the official app or physical
control as appropriate.

## 13. Supervised Pause Procedure

### Phase A: establish Running

1. start mowing manually through the official Navimow app;
2. keep the mower supervised;
3. confirm visibly that normal mowing has begun;
4. perform a read-only Symcon status refresh;
5. require current `VehicleState == Running` and `Online == true`.

If Running is not confirmed, do not invoke Pause.

### Phase B: single command

1. record the current command diagnostic baseline privately;
2. explicitly confirm that the next operation sends one Pause command;
3. invoke Pause once through the device module;
4. do not click again after timeout, error or ambiguous feedback;
5. record only the bounded returned message.

The module itself performs another current Running read before its one command
write. A state change between Phase A and dispatch therefore fails safely.

### Phase C: read-only observation

Observe without sending another command:

- `LastCommand == Pause`;
- `LastCommandAt` advances once;
- `LastCommandResult` progresses through Accepted or Pending Verification;
- `VehicleState` becomes Paused through status polling;
- terminal result becomes Verified;
- `LastCommandError` remains empty;
- no second command timestamp is created.

The implementation may read at 2, 5, 10, 20, 30 and 60 seconds and stops early
after Paused. The operator does not need to manually trigger these reads.

## 14. Expected Result

Successful sequence:

```text
Running
  -> one Pause action
  -> Accepted
  -> Pending Verification
  -> Paused
  -> Verified
```

Because Paused may be observed at the first timer read, `Pending Verification`
may be too brief for manual UI observation. This does not fail the test when
the final diagnostics, status timestamp and one-command evidence prove the
correct lifecycle.

Acceptance criteria:

- exactly one explicit Pause action;
- one current Running pre-read immediately before transport;
- accepted nested command result;
- later current Paused status;
- terminal Verified result within 60 seconds;
- empty command error;
- no command retry;
- stable variable ObjectIDs and archive settings;
- existing Dock behavior unchanged by static and deterministic tests.

## 15. Abort and Failure Rules

| Observation | Required response |
| --- | --- |
| pre-read fails or state is not Running | do not send Pause |
| Pause returns an immediate error | do not retry; refresh read-only status |
| mower visibly stops but REST is not Paused | retain unresolved evidence; do not retry |
| mower continues beyond 60 seconds | use official app or physical stop; classify timeout |
| unexpected Docking, Docked or error state | recover manually; classify fail-closed result |
| authentication fails | stop command test; handle OAuth separately |
| variable ObjectID or archive setting changes | stop before actuation; investigate compatibility regression |
| Symcon or module becomes unstable | use official controls; end test |

HTTP success, command acceptance or visible stopping alone is insufficient for
Verified. The current REST Paused state is required.

## 16. Cleanup

After terminal evidence is captured:

1. confirm the mower remains physically safe;
2. use the official Navimow app to choose the desired operational state;
3. do not use Symcon Resume in this step;
4. perform a final read-only Symcon status refresh if required;
5. confirm no command remains active;
6. delete every temporary Symcon test script or object;
7. leave existing device variables and archive logging untouched.

The cleanup action is operational context and must be recorded in the private
test notes without exposing installation details.

## 17. Sanitized Evidence Contract

The public test report may include:

- published commit hash and subject;
- validator PASS/FAIL results;
- sanitized module availability markers;
- equality markers for variable ObjectIDs;
- archive-setting preservation markers;
- symbolic states and command-result associations;
- relative timing such as Paused within two seconds;
- bounded error classification;
- confirmation that one command was invoked.

It must not include:

- credentials, tokens or callback URLs;
- real device, account or command identifiers;
- private hostnames, IP addresses or filesystem paths;
- numeric Symcon ObjectIDs;
- mower name or garden information;
- raw API payloads or headers;
- archive data values beyond the minimum compatibility assertion.

## 18. Execution Result Classification

| Result | Conditions | Next action |
| --- | --- | --- |
| `PUBLICATION PASS` | remote commit and canonical parity verified | permit Symcon update |
| `SMOKE PASS` | loader, auth, read and compatibility checks pass | permit live Pause |
| `LIVE PASS` | one accepted Pause followed by current Paused and Verified | create test report |
| `LIVE PARTIAL` | mower pauses but API verification remains incomplete | no retry; analyze evidence |
| `LIVE FAIL` | rejected, unexpected state, unsafe behavior or compatibility regression | recover and reopen implementation |
| `BLOCKED` | validator, publication, auth or supervision gate unavailable | stop without command |

No classification authorizes Resume automatically.

## 19. Architecture Decisions

### AD-NAV-179: Publish before live Symcon testing

**Decision:** Test the exact remotely published module rather than a local or
manually copied build.

**Rationale:** The private pilot installation must exercise the same auditable
artifact that future updates receive.

**Consequence:** Remote parity is a blocking precondition for runtime testing.

### AD-NAV-180: Verify object and archive identity before actuation

**Decision:** Compare all stable variable ObjectIDs and archive settings before
the first Pause command.

**Rationale:** User logging history is persistent installation state and must
not be risked by command expansion.

**Consequence:** A compatibility regression blocks physical testing even when
the module otherwise loads.

### AD-NAV-181: Separate read-only smoke from physical Pause

**Decision:** Require healthy loading, authentication and status transport
before starting the mower.

**Rationale:** Deployment failures can be detected without physical movement.

**Consequence:** The command gate opens only after a lower-risk runtime check.

### AD-NAV-182: Treat one explicit action as the complete write budget

**Decision:** Do not repeat Pause after timeout, ambiguity or state mismatch.

**Rationale:** Missing feedback does not prove that the cloud failed to deliver
the first command.

**Consequence:** Recovery and diagnosis use reads and official controls only.

### AD-NAV-183: Keep publication untagged until live evidence passes

**Decision:** Update `main` without creating or moving a pilot tag.

**Rationale:** An immutable release marker should identify a runtime-verified
state, not merely a locally tested implementation.

**Consequence:** A later release review may create a new tag after successful
Pause observation and consolidation.

## 20. Gate Decision

**GO for controlled publication preparation and execution.**

**Conditional GO for one supervised Symcon Pause test only after publication,
read-only smoke and variable/archive compatibility gates pass.**

No mower command is authorized before those conditions are evidenced.

## 21. Recommended Next Steps

Execute SAEF step `62-pause-command-publication.md` to validate, synchronize,
commit, push and remotely verify the canonical distribution without a tag.

After the user updates the module and the read-only compatibility gate passes,
execute `63-pause-command-symcon-test-report.md` for the single supervised live
test and its sanitized result.
