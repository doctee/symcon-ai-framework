# SAEF Step 391: Continuous Receive-Only Offline Validation And Review

**Case study:** Navimow native IP-Symcon module

**Status:** Complete offline validation and focused review pass; publication
and all Symcon gates remain closed

**Date:** 2026-09-05

## 1. Purpose

Step 390 implemented the productive continuous receive-only candidate but left
the lock-identical PHPCS, PHPStan and complete Composer gates open. This step:

- resolves the exact local toolchain without changing dependencies;
- corrects the findings produced by that toolchain;
- expands the highest-risk Account regression evidence;
- reruns the complete Navimow and repository checks; and
- performs the focused architecture and safety review required before any
  publication decision.

It performs no commit, push, pull request, merge, standalone publication,
Symcon access, module update, MQTT activation, credential retrieval, OAuth
action, restart or mower command.

## 2. Toolchain Resolution

The isolated worktree's `composer.lock` SHA-256 is:

```text
b108c9f037ca0e575cd827914baf355131205825752b474c1799dfd14f07547c
```

The primary checkout vendor is not compatible because it contains a different
lock and PHP_CodeSniffer version. It was not used.

A network-disabled cache-only install was attempted first. Required archives
were absent, network access remained disabled and no temporary toolchain files
remained. A pre-existing isolated vendor tree was then verified with the exact
same lock hash and used read-only through `COMPOSER_VENDOR_DIR`.

The effective tools are:

```text
PHPStan:          2.2.5
PHP_CodeSniffer: 3.13.5
```

No dependency was copied, downloaded, upgraded, downgraded or written into the
Navimow worktree.

## 3. Static Findings And Corrections

### Formatting

The exact PHPCS version found 59 automatically correctable formatting issues:

| File | Corrected |
|---|---:|
| `NavimowAccount/module.php` | 47 |
| `MqttContinuousOperationReducer.php` | 10 |
| distribution Local Map renderer | 1 |
| prototype Local Map renderer | 1 |

The same PHPCBF version corrected only whitespace and control-structure
formatting. The generated distribution was rebuilt afterward.

### Productive PHPStan finding

PHPStan found one unnecessary null-coalescing expression on the reducer's
guaranteed `effect` result. The Account now reads the required key directly.
No behavior or failure policy changed.

### Integration-test analysis boundary

The synthetic Account scenario depends on the reusable Fake-Symcon runtime.
Loading that runtime into the same PHPStan process changes the inferred base
class for every productive module and turns intentional cross-version
`is_callable()` and constant checks into false positives.

The Account scenario therefore remains:

- executed in every Navimow MQTT functional run;
- checked by PHPCS; and
- covered by PHP syntax validation.

The pure continuous reducer and every changed productive module remain in the
PHPStan scope. No ignore comment, baseline entry, weakened type or inline type
override was introduced.

## 4. Expanded Account Evidence

The synthetic-clock Account scenario now additionally proves:

1. `ApplyChanges()` during Active preserves the current lease, schedules no
   second credential retrieval and retains the lease timer;
2. `ApplyChanges()` during credential-free CircuitOpen preserves the exact
   next-probe deadline, consumes no probe and retrieves no credentials; and
3. `ApplyChanges()` at exact lease expiry enters `Stopping` with first reason
   `lease-expired`, completes credential-first cleanup, preserves the enabled
   policy and ends in explicit `Suspended` state.

These checks close the most important update/restart-style boundaries without
claiming live Symcon restart evidence.

## 5. Focused Review Results

### Mode And Migration

**PASS.** The existing master switch still defaults to false. The new mode
defaults to BoundedPilot. No legacy pilot, Local Map setting, retained Core
credential or historical registry can select continuous mode implicitly.
Unknown mode values fail closed and disable the master only after cleanup.

### Lease

**PASS.** Start and resume create one 72-hour lease. Renewal becomes eligible
after 48 hours and requires Active state, a matching configuration hash, 900
seconds of sustained Core health, current credentials, no rotation, usable
authentication and REST success within 900 seconds. Rechecks cannot cross the
persisted expiry.

### Recovery

**PASS.** Existing `60/300/900`-second reconnects remain the inner episode.
Continuous exhaustion enters credential-free CircuitOpen. Four half-open
probes use the exact `1800/7200/21600/86400`-second sequence, require a fresh
1200-second token horizon and stop in Suspended after exhaustion. No infinite
connect loop exists.

### Authentication And Configuration

**PASS.** Authentication and configuration failures never enter transport
retry. Token rotation is limited to Starting, Active or Degraded continuous
states. CircuitOpen recovery requires fresh prerequisites before consuming a
probe.

### Cleanup And Ownership

**PASS.** Terminal transition remains ordered as:

```text
Stopping -> Core credentials absent -> CredentialsCleared -> Suspended/Stopped
```

No public stop result is finalized after a credential cleanup failure. Core
mutation remains behind exact ownership and topology validation.

### State And Data Authority

**PASS.** MQTT writes no REST-owned public mower state and exposes no publish
or command method. REST remains authoritative. Position freshness changes only
the current map marker; retained path and zone evidence remain independent.

### Variables And Archive Stability

**PASS.** Five Account variables are additive. Existing Account and Device
Idents, positions, profiles, values, Archive logging and aggregation contracts
remain unchanged. Existing logged battery and mower variables are not recreated
or renamed by this candidate.

### Privacy

**PASS.** The bounded diagnostics and public artifacts contain no credential,
Authorization header, endpoint, private topic, coordinate, device identifier,
ObjectID, hostname or installation path.

## 6. Complete Validation

| Check | Result |
|---|---|
| Navimow MQTT functional suite | PASS |
| PHPCS with lock-identical 3.13.5 | PASS |
| PHPStan with lock-identical 2.2.5 | PASS |
| Distribution validator | PASS |
| Deterministic Navimow fileset check | PASS |
| Mutation-disabled Navimow publication check | PASS |
| `composer navimow:fileset-check` | PASS |
| `composer navimow:publication-check` | PASS |
| Complete repository `composer check` | PASS |
| `git diff --check` | PASS |

The generic publication tests inside `composer check` emit synthetic
`published` and `integrated` outcomes against fake Git/GitHub executables.
Those lines are fixture evidence only. No real repository, branch or pull
request was changed.

The final local publication candidate contains 43 files:

```text
filesetSha256:      852ae5939981f5a578305c9e9ac37b591b7e536c693bd3f4afea6bbaa94eebbb
publicationSha256: d65f3c49d81e79cac393a222eb7c360c6384d8ab9208d7795a2de0885a08a9b5
```

These hashes authorize no publication.

## 7. Remaining Risks

- The candidate has no evidence from the real Symcon timer and Core lifecycle.
- A disabled update has not yet proven migration and variable identity on the
  installation.
- Continuous restart, lease renewal, outage recovery and 24-hour operation
  remain live evidence gates.
- The vendor protocol remains undocumented. REST authority and strict
  receive-only MQTT remain mandatory.
- The branch was based on the locally known `origin/main` at implementation
  start. A fresh remote comparison and conflict-free integration are required
  before publication.

No blocking offline code finding remains.

## 8. Architecture Decisions

### AD-NAV-391-01: Resolve tools by lock identity, not proximity

An adjacent or primary-checkout vendor is valid only when its lock hash and
package versions match. This preserves deterministic review across worktrees.

### AD-NAV-391-02: Keep the executable Fake-Symcon scenario outside the
productive PHPStan process

Mixing its concrete compatibility runtime into productive analysis produces a
different type environment from IP-Symcon. Functional execution, PHPCS and
syntax validation retain meaningful coverage without suppressing findings.

### AD-NAV-391-03: Treat ApplyChanges as a lifecycle boundary

Active, CircuitOpen and expired states now have explicit Account-level tests.
This verifies persisted deadlines and prevents update-triggered duplicate
connections.

### AD-NAV-391-04: Require one fresh publication-base gate

Offline validation proves the exact local candidate, not current remote state.
Publication preparation must fetch and reconcile `origin/main` before creating
an immutable commit or PR.

## 9. Gate Result

| Gate | Scope | Status |
|---|---|---|
| I1 productive implementation | isolated local candidate | PASS |
| I2 complete offline validation | lock-identical full toolchain | PASS |
| R1 focused code review | exact candidate | PASS |
| P1 SAEF commit, push and pull request | Git publication | CLOSED |
| P2 standalone publication | external publication | CLOSED |
| M1 metadata conformance | published tree | CLOSED |
| S1 disabled Symcon update | exact module update | CLOSED |
| S2 inactive migration postflight | read-only live | CLOSED |
| L1 credential-retention acceptance | operator statement | NOT REQUESTED |
| L2 24-hour continuous activation | one controlled start | CLOSED |
| L3 evidence and cleanup | bounded live observation | CLOSED |
| O1 ongoing private operation | separate operating activation | CLOSED |

No publication or live authority is inherited from the step-391 approval.

## 10. Next Step

Proceed with:

```text
392-continuous-receive-only-publication-readiness.md
```

That step should obtain a fresh remote baseline, review the exact integration
delta and prepare one combined SAEF P1 gate for commit, push and pull-request
creation. Standalone publication and all Symcon actions remain separate.
