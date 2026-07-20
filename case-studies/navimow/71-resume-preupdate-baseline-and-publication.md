# 71 Resume Pre-Update Baseline and Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Pre-update compatibility baseline and Resume publication passed
**Date:** 2026-07-12
**Scope:** Preserve the installed Pause baseline and publish bounded Resume without updating Symcon

## 1. Purpose

This step executes the first two gates from
`70-resume-command-publication-and-symcon-test-plan.md`:

1. capture a repeatable private compatibility baseline from the still-installed
   Pause build;
2. validate and publish the exact seven-file Resume candidate.

The Symcon module was not updated and no mower command was sent. Direct runtime
compatibility and supervised Resume evidence remain separate post-update gates.

## 2. Starting State

Before publication, the dedicated module repository and the installed Symcon
module were based on:

```text
e82f73f752c4b588f13a5fb5331413279d2b77f7
feat: add bounded Pause command
```

The publish clone was clean and aligned with `origin/main`. Historical tags
`pilot-0.1.0.1` and `pilot-0.1.0.2` existed and were not changed.

## 3. Private Baseline Capture

A temporary Symcon script captured the compatibility data defined in step 70.
The complete record is retained only under the ignored `private/` boundary.

Public result:

| Check | Result |
| --- | --- |
| account, configurator, device and Archive Control resolved | PASS |
| Navimow instances active | PASS |
| stable variable Idents resolved exactly once | PASS, 8 of 8 |
| logged variables | 5 |
| bounded history query for every logged variable | PASS |
| authentication and REST state healthy | PASS |
| Pause and Dock functions present | PASS |
| Resume, Stop and Start functions absent | PASS |
| deterministic identity fingerprint created | PASS |
| deterministic archive fingerprint created | PASS |
| immediate independent repeat capture | PASS |
| identity fingerprints equal | PASS |
| archive fingerprints equal | PASS |

The eight protected Idents are:

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

No token, secret, device/account identifier, hostname, raw payload, ObjectID,
hash value, archive value or garden information is published. All temporary
Symcon scripts used for the capture were deleted after verification.

## 4. Canonical Validation

The exact canonical candidate passed:

- PHP syntax for all eight productive PHP files;
- JSON parsing for all ten metadata, form and locale files;
- REST client, authentication and fixture checks;
- all 29 deterministic pilot-observation harness cases;
- all seven Resume cases, including rejection, timeout and restart behavior;
- Dock and Pause regression coverage;
- distribution structure validation;
- whitespace validation;
- unchanged public variable registration;
- one shared account `SendCommand` path;
- changed-file privacy checks.

Stop and Start remain rejected. No command retry path was introduced.

## 5. Official Schema Gate

The browser validator defect already reproduced and documented in step 62
remains an external frontend limitation. The equivalent local gate therefore
used the validator's four official schemas with its declared AJV `6.10.2`.

Results:

| Schema target | Passed |
| --- | ---: |
| `library.json` | 1 |
| `module.json` | 3 |
| `locale.json` | 3 |
| `form.json` | 3 |
| **Total** | **10** |

No validator dependency entered the publication repository.

## 6. Publication Boundary

Exactly these seven canonical files differed from the published Pause build:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowDevice/form.json
NavimowDevice/locale.json
NavimowDevice/module.php
README.md
libs/Navimow/CommandContract.php
```

They were synchronized byte-for-byte to the dedicated distribution repository.
No SAEF document, fixture, test, private baseline or temporary validator file
was copied.

## 7. Published Result

Repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Published commit:

```text
64188f75527abcb49b0b27ce2b56ad2d34a403fd
feat: add bounded Resume command
```

Independent remote verification confirmed:

- `origin/main` and the remote branch reference equal the published commit;
- the commit changes exactly the seven authorized files;
- remote content contains the Resume method, form action and command contract;
- the publish clone is clean;
- both historical pilot tags are unchanged;
- no new tag was created.

## 8. Compatibility Boundary

The publication does not prove that the installed Symcon objects survived an
update because no update occurred in this step. The private pre-update record
is now the mandatory comparison source for step 72.

Before any Resume actuation, step 72 must prove:

- all eight variable ObjectIDs are unchanged;
- variable types and effective profiles are unchanged;
- logging flags and aggregation types are unchanged;
- all five logged histories remain queryable;
- account, configurator and device instances remain healthy;
- Resume is newly present while Pause and Dock remain present;
- Stop and Start remain absent;
- no command verification is active.

Any mismatch blocks the live test.

## 9. Architecture Decisions

### AD-NAV-232: Require a repeated pre-update snapshot

**Decision:** Accept the baseline only after two independent captures produce
equal identity and archive fingerprints.

**Rationale:** A single transient read is insufficient evidence for later
ObjectID and archive compatibility.

**Consequence:** The post-update comparison has a stable private reference.

### AD-NAV-233: Protect archive continuity as a release gate

**Decision:** Preserve all existing variables and explicitly baseline the five
operator-enabled archive streams.

**Rationale:** Historical data and external automation must survive command
expansion.

**Consequence:** A loader-success result cannot override an archive or identity
mismatch.

### AD-NAV-234: Publish before the operator-controlled update

**Decision:** End this step after remote verification and before Symcon update.

**Rationale:** The installed pre-update state must remain available until the
publication itself is proven.

**Consequence:** The user controls the update boundary and step 72 starts with
comparison, not actuation.

### AD-NAV-235: Keep the publication untagged

**Decision:** Advance `main` without creating or moving a tag.

**Rationale:** Resume has private API evidence but not yet direct Symcon runtime
evidence.

**Consequence:** Existing pilot tags continue to describe their verified scope.

## 10. Decision

**Pre-update baseline: PASS.**

**Resume publication: PASS.**

**Symcon update and live Resume test: PENDING.**

The published commit is eligible for an operator-controlled module update. It
is not yet eligible for a new tag, broader pilot claim, Store submission, Stop
or Start enablement.

## 11. Next Step

Update the Navimow module in Symcon from `main`. After explicit update
confirmation, execute `72-resume-command-symcon-test-report.md` beginning with
the mandatory private pre/post identity and archive comparison. Only a complete
compatibility and read-only smoke PASS may unlock one supervised Resume action.
