# SAEF Step 392: Continuous Receive-Only Publication Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Exact SAEF and standalone candidates are ready for separately
authorized publication gates; no commit, push, pull request, merge, standalone
mutation or Symcon access performed

**Date:** 2026-09-05

## 1. Purpose

Step 391 closed the complete offline validation and focused safety review of
the continuous receive-only MQTT candidate. This step refreshes both relevant
remote baselines, proves that the isolated workstream still starts from current
SAEF `main`, compares the deterministic module candidate with the current
standalone module and defines the reduced publication and live gate sequence.

The step performs no commit, push, pull request, merge, standalone mutation,
Symcon access, module update, MQTT activation, credential retrieval, OAuth
action, restart or mower command.

## 2. Fresh SAEF Baseline

The dedicated worktree remains the only source and build input:

```text
branch:       codex/navimow-continuous-receive-only
HEAD:         78f5c62f07d2df55ebeb788c3bbae07f57d41d43
origin/main:  78f5c62f07d2df55ebeb788c3bbae07f57d41d43
remote main:  78f5c62f07d2df55ebeb788c3bbae07f57d41d43
relationship: exact equality after fresh fetch
```

`HEAD...origin/main` is empty. No rebase, merge or candidate reconstruction is
required before local canonicalization. The primary checkout and unrelated
worktrees were not used as source input.

## 3. Exact SAEF Candidate Scope

Including this report, Gate P1 is limited to 39 paths. Every path belongs to
the Navimow case study, its deterministic standalone fileset or its Navimow
publication contracts.

### Documentation

```text
case-studies/navimow/README.md
case-studies/navimow/387-active-directional-map-marker-short-pilot.md
case-studies/navimow/388-continuous-receive-only-operating-decision.md
case-studies/navimow/389-continuous-receive-only-implementation-design.md
case-studies/navimow/390-continuous-receive-only-implementation.md
case-studies/navimow/391-continuous-receive-only-offline-validation-and-review.md
case-studies/navimow/392-continuous-receive-only-publication-readiness.md
```

### Canonical implementation, fixtures and checks

```text
case-studies/navimow/candidate/LocalMapSvgRenderer.php
case-studies/navimow/distribution/NavimowAccount/form.json
case-studies/navimow/distribution/NavimowAccount/locale.json
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/distribution/NavimowDevice/module.php
case-studies/navimow/distribution/libs/Navimow/LocalMapSvgRenderer.php
case-studies/navimow/distribution/libs/Navimow/MqttContinuousOperationReducer.php
case-studies/navimow/distribution/libs/Navimow/Profiles.php
case-studies/navimow/fixtures/mqtt/README.md
case-studies/navimow/fixtures/mqtt/bounded-diagnostics-shadow-active.json
case-studies/navimow/tests/local-map-evidence-contract.php
case-studies/navimow/tests/local-map-runtime-reducer.php
case-studies/navimow/tests/local-map-svg-renderer.php
case-studies/navimow/tests/mqtt-account-ingestion.php
case-studies/navimow/tests/mqtt-continuous-account.php
case-studies/navimow/tests/mqtt-continuous-operation.php
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
case-studies/navimow/tests/mqtt-shadow-diagnostics.php
case-studies/navimow/tests/mqtt-shadow-reconciliation.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
case-studies/navimow/tools/check-mqtt-shadow.sh
```

### Deterministic distribution and publication contracts

```text
deployments/symcon/navimow-module.fileset.json
deployments/symcon/navimow-publication.json
dist/symcon/symcon-navimow-module/NavimowAccount/form.json
dist/symcon/symcon-navimow-module/NavimowAccount/locale.json
dist/symcon/symcon-navimow-module/NavimowAccount/module.php
dist/symcon/symcon-navimow-module/NavimowDevice/module.php
dist/symcon/symcon-navimow-module/fileset.sha256
dist/symcon/symcon-navimow-module/fileset.sources.json
dist/symcon/symcon-navimow-module/libs/Navimow/LocalMapSvgRenderer.php
dist/symcon/symcon-navimow-module/libs/Navimow/MqttContinuousOperationReducer.php
dist/symcon/symcon-navimow-module/libs/Navimow/Profiles.php
```

No shared helper, framework standard, ADR, non-Navimow case study or private
evidence file is part of the candidate.

## 4. Standalone Baseline And Delta

A fresh remote read established the current standalone baseline:

```text
repository:   doctee/symcon-navimow
branch:       main
remote main:  af89eeb3b7360c7c8b3cf81db4b2f07bfc9370cb
files:        42
```

The generic manifest-driven publisher prepared the candidate locally without
remote mutation. It contains 43 files. A complete content comparison found:

```text
modified publication paths:  8
added publication paths:     1
deleted publication paths:   0
insertions:                2094
deletions:                   45
```

The seven semantic module paths are:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowAccount/module.php
NavimowDevice/module.php
libs/Navimow/LocalMapSvgRenderer.php
libs/Navimow/MqttContinuousOperationReducer.php   (new)
libs/Navimow/Profiles.php
```

The remaining two changed paths are deterministic generated metadata:

```text
fileset.sha256
fileset.sources.json
```

All other standalone paths are byte-identical to the fresh baseline. The
publication candidate identity is:

```text
file count:          43
fileset SHA-256:     852ae5939981f5a578305c9e9ac37b591b7e536c693bd3f4afea6bbaa94eebbb
publication SHA-256: d65f3c49d81e79cac393a222eb7c360c6384d8ab9208d7795a2de0885a08a9b5
```

Any source or generated byte change invalidates these hashes and returns the
candidate to complete offline validation.

## 5. Preserved Runtime Boundary

The publication candidate preserves these fixed contracts:

```text
public mower-state and command authority: REST
MQTT direction:                          receive-only
MQTT publish path:                       absent
MQTT mower-command path:                 absent
default master switch:                   disabled
legacy operating-mode default:           bounded pilot
continuous lease:                        renewable 72 hours
inner reconnect delays:                  60 / 300 / 900 seconds
half-open cooldowns:                     1800 / 7200 / 21600 / 86400 seconds
maximum half-open probes per lease:       4
credential-first terminal cleanup:       required
```

The existing bounded pilot remains available and retains its hard deadline.
Continuous operation is selected only by the explicit operating mode and
cannot be inferred from historical configuration or retained Core state.

## 6. Variable And Archive Contract

The candidate adds five Account variables:

```text
MqttOperatingState
MqttLastMessageAt
MqttLastPositionAt
MqttPositionFreshness
MqttLeaseExpiresAt
```

All existing Account and Device Idents, types, profiles and positions remain
unchanged. The module neither enables nor disables Archive Control logging and
does not change aggregation settings. Existing logged battery, mower-state and
zone-statistics histories therefore remain attached to their current
variables.

Archive logging for the five new operating variables may be enabled manually
by the installation owner after the variables exist. It is intentionally not
part of module publication, update or activation. A later live postflight must
compare variable identity plus Archive logging and aggregation before and after
the disabled update.

## 7. Verification Evidence

The fresh readiness checks established:

- SAEF `HEAD`, fetched `origin/main` and remote `main` are identical;
- the worktree contains only the exact 39-path candidate;
- `git diff --check` passes;
- the deterministic Navimow fileset is current;
- the generic publication check is mutation-free and passes;
- local preparation reproduces the exact 43-file publication identity;
- the complete standalone comparison contains no unclassified path or
  deletion;
- the standalone remote baseline is current and independently identified;
- the lock-identical Navimow functional, PHPCS, PHPStan and complete repository
  `composer check` pass again over the final step-392 documentation state;
- no credential, private topic, coordinate, device identity, ObjectID,
  hostname, local path or private evidence enters the publication payload.

The generic publication check reports `mutationAttempted=false`. Preparing a
local comparison tree is not a publication and changed no remote repository.
The complete Composer test suite also emits synthetic `published` and
`integrated` results against fake Git and GitHub executables. Those results are
publisher regression fixtures only and performed no real remote mutation.

## 8. Reduced Gate Sequence

Mechanically coupled operations are grouped, while repository integration,
standalone publication and live-system trust boundaries remain separate.

### Gate P1: SAEF candidate publication

One explicit authorization may cover:

1. one final fresh `origin/main` and exact-path preflight;
2. the complete focused and repository-wide checks;
3. staging only the 39 paths in section 3;
4. complete staged-diff and privacy review;
5. one Conventional Commit;
6. one branch push; and
7. one pull request against SAEF `main`.

Recommended commit:

```text
feat(navimow): add continuous receive-only mqtt mode
```

Gate P1 permits no merge, standalone mutation or Symcon access.

### Gate P2: SAEF review and merge

After terminal green checks, one separate authorization may cover exact PR
review, merge by merge commit and independent canonical `origin/main`
verification. It permits no standalone or live mutation.

### Gate S1: Standalone publication PR

After SAEF merge, one exact hash-bound invocation of the generic publisher may
create the deterministic standalone topic branch and pull request from the
canonical merged fileset. It permits no merge or Symcon access.

### Gate S2: Standalone integration and metadata conformance

After terminal green checks, one hash-bound generic integration invocation may
merge the exact standalone PR and verify the resulting remote tree. The same
gate may perform read-only metadata conformance on the exact merged commit.

### Gate L1: Disabled Symcon rollout

One live authorization may cover a bounded read-only preflight, exactly one
supported module update while MQTT is disabled and credential-free, plus
immediate and delayed read-only postflights. `MC_ReloadModule()` remains
prohibited. No MQTT activation belongs to this gate.

### Gate L2: Controlled continuous-mode validation

After renewed credential-retention acceptance, one separate authorization may
cover exactly one continuous receive-only activation, a 24-hour monitored
observation and mandatory credential-free cleanup. No restart or mower command
is implied. The first live test does not authorize ongoing operation.

### Gate O1: Ongoing private operation

Only a passing L2 result may open a separately reviewed ongoing private
operation. That decision must retain REST authority, receive-only MQTT,
freshness visibility, finite recovery, lease renewal and an operator kill
switch.

## 9. Stop Conditions

Every later gate stops before mutation if:

- SAEF or standalone remote state differs from its fresh expected commit;
- any path outside the applicable exact allowlist appears;
- fileset or publication hashes differ;
- a deletion or unclassified file appears;
- the functional, static, privacy or receive-only checks fail;
- REST authority, command scope, variable identity or Archive contracts drift;
- MQTT is active or Core credentials are present before the disabled rollout;
- a live result is truncated, transport-failed, execution-failed or ambiguous.

An ambiguous Git or live result is resolved only through read-back. It never
authorizes a blind repeated mutation.

## 10. Architecture Decisions

### AD-NAV-392-01: Publish the complete coherent workstream

**Decision:** Keep the mode reducer, Account lifecycle, diagnostics, freshness
presentation, tests, reports and generated fileset in one reviewable SAEF
candidate.

**Reason:** Splitting the lifecycle from its fail-closed UI, visibility and
regression contracts would create intermediate states that are harder to
review and unsafe to publish independently.

### AD-NAV-392-02: Use the generic manifest-driven publisher

**Decision:** Use `tools/publish-symcon-module.php` with the Navimow contract
for standalone preparation, publication and integration.

**Reason:** The accepted publisher already enforces exact hashes, inventory,
privacy, remote drift and independent post-merge verification without copying
module-specific publication logic.

### AD-NAV-392-03: Keep Archive policy installation-owned

**Decision:** Do not activate logging from module code or publication tooling.

**Reason:** Archive history and aggregation are installation policy. Additive
stable variables allow the owner to enable useful logging manually without an
update silently changing storage behavior.

### AD-NAV-392-04: Separate first continuous evidence from ongoing operation

**Decision:** Require a bounded 24-hour L2 observation before an O1 operating
decision.

**Reason:** Offline validation cannot prove real timer, Core, token-rotation,
lease and outage behavior over operational time.

## 11. Gate Result And Next Step

```text
fresh SAEF base:                    PASS
exact candidate scope:              FROZEN
standalone baseline:                PASS
standalone fileset comparison:      PASS
deterministic fileset check:         PASS
mutation-free publication check:    PASS
Gate P1 commit, push and PR:         CLOSED
Gate P2 review and merge:            CLOSED
Gate S1 standalone publication PR:  CLOSED
Gate S2 standalone integration:      CLOSED
Gate L1 disabled Symcon rollout:     CLOSED
Gate L2 live validation:             CLOSED
Gate O1 ongoing private operation:   CLOSED
```

The candidate is ready for Gate P1 only. No later gate inherits authority from
this readiness result.
