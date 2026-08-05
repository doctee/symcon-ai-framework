# 270 Navimow Account Status Correction Integration Review

**Case study:** Navimow native IP-Symcon module

**Status:** Account status correction integrated and verified; local SAEF
documentation canonicalization remains a separate gate

**Date:** 2026-08-05

**Scope:** Consolidate steps 267 through 269, verify workstream isolation and
toolchain provenance, and decide the next documentation gate without changing
Symcon or publishing SAEF

## 1. Result

The integration review passed.

```text
standalone commit:       eda494513826fa43ccc1b28634b06354356f49a4
installed commit:        eda494513826fa43ccc1b28634b06354356f49a4
Account status:          102
REST:                    operational
MQTT feature:            disabled
MQTT credentials:        absent
variable contracts:      14 / 14
Archive contracts:       5 / 5
workstream isolation:    PASS
toolchain provenance:    PASS
integration decision:    PASS
```

The stale Account status is corrected in source, standalone publication and
the authorized Symcon installation. No functional follow-up repair is needed.

## 2. Reviewed Evidence Chain

| Step | Gate | Result |
|---|---|---|
| 267 | exact one-file standalone publication | PASS |
| 268 | exact published metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| 269 | one supported disabled Symcon update | PASS |
| current review | workstream and toolchain isolation | PASS |

The productive path is continuous:

```text
canonical SAEF candidate d473467
  -> standalone commit eda4945
  -> installed Symcon commit eda4945
  -> stable Account status 102
```

## 3. Workstream Isolation

The active SAEF workstream is:

```text
branch:       codex/navimow-standalone-readiness
base:         origin/main 2ef7a22
branch head:  d473467
ahead:        4 commits
public scope: case-studies/navimow only
```

The base contains the merged ControlLight closure and SAEF workstream
governance. Commit `7358fa5` is an ancestor of the Navimow branch.

Read-only inventory proved:

- no committed path outside `case-studies/navimow/` differs from
  `origin/main`;
- no uncommitted path outside `case-studies/navimow/` belongs to this
  workstream;
- ControlLight, Open-Meteo, shared helpers, deployment artifacts and generated
  distributions have no Navimow workstream delta;
- historical dirty ControlLight and Statistics reconstruction worktrees were
  not used as source, build input or publication input;
- the older local `main` checkout was not used as a source baseline.

The workstream therefore satisfies one branch, one clean base and one stated
functional scope.

## 4. Source, Evidence and Toolchain Boundaries

Three deliberately separate locations participated:

| Layer | Ownership | Role |
|---|---|---|
| clean Navimow Git worktree | public SAEF candidate | source and reports |
| ignored private overlay | private Navimow evidence | probes and sanitized machine-readable results |
| lock-identical Composer installation | local tool provider | executable validation dependencies only |

The private overlay is outside the Git worktree because `/private/` is ignored
at repository level. Task-specific evidence is namespaced below the Account
status-correction capture directory and is not public source.

The external Composer installation supplied executables only. Both source and
tool-provider checkouts have byte-identical `composer.lock` files:

```text
b108c9f037ca0e575cd827914baf355131205825752b474c1799dfd14f07547c
```

Composer verified that nothing needed installation, update or removal. All
working directories, PHPStan configurations and analyzed files belonged to
the clean Navimow worktree.

## 5. Validation Clarification

A literal `make check` run in the isolated worktree did not complete because
the worktree intentionally had no local `vendor/bin/phpstan`. All Open-Meteo
fixture tests before that lookup passed.

The equivalent remaining checks then passed with the lock-identical external
toolchain:

```text
Composer check:                         PASS
repository PHPStan:                     PASS
repository PHPCS:                       PASS
Open-Meteo PHPStan:                     PASS
Open-Meteo PHPCS:                       PASS
Navimow REST and authentication:        PASS
Navimow pilot and lifecycle harnesses:  PASS
Navimow distribution validation:        PASS
```

Open-Meteo was tested only as an unchanged repository-wide regression target.
No Open-Meteo source entered the Navimow candidate.

Future isolated worktree reports must distinguish:

```text
literal worktree-local make check
```

from:

```text
equivalent complete gate with an explicitly named lock-identical toolchain
```

## 6. Probe Compatibility Review

The installed pre-update commit `79686e52` did not expose
`GetMqttPilotSummary()`. That method first appears in standalone commit
`a8481c9`.

The recovery therefore derived its private read-only projection from the
older compatible disabled-update probe. The derivation added only:

- kernel runlevel;
- sanitized role-based instance statuses;
- fail-closed acceptance of Account status `101` or `102` for the bounded
  recovery observation;
- required healthy statuses for all surrounding instances.

No ObjectID was returned in public evidence. The probe performed no mutation,
MQTT activation, credential request, OAuth action or mower command.

## 7. Current Read-Only Confirmation

A later read-only MCP confirmation after step 269 returned:

```text
transportError:  null
executionError:  null
truncated:       false
repository:      main / eda49451 / clean / valid
kernel:          ready
Account:         102
Configurator:    102
Device:          102
Receiver:        102
MQTT/WebSocket:  104 / 104
REST:            operational
MQTT feature:    disabled
MQTT credentials absent: true
```

All configuration, identity, archive, command-evidence and subscription hashes
equal the accepted step-269 postflight. The five existing logged variables
remain queryable.

The cumulative REST error counter is historical diagnostic state. Matching
current REST-success and status-update timestamps, together with the successful
operational projection, show no current REST failure.

## 8. Documentation Hardening

This review applies two corrections before SAEF publication:

1. Step 267 no longer exposes the repository-relative private worktree path;
   it describes the source as a dedicated clean Navimow worktree.
2. Step 269 now records the literal `make check` limitation and the exact
   lock-identical equivalent validation path.

Steps 267 through 270 and their README entries remain one local Navimow-only
documentation scope. They are not yet committed or published.

## 9. Architecture Decisions

### AD-NAV-1083: Keep source, private evidence and tools distinct

A shared filesystem parent does not make these layers interchangeable. Each
layer has separate ownership and publication rules.

### AD-NAV-1084: Treat historical dirty worktrees only as recovery input

ControlLight and Statistics reconstruction worktrees must not become source,
build or publication inputs for Navimow.

### AD-NAV-1085: Require lock identity for an external Composer toolchain

External executables are acceptable for validation only when the source and
tool-provider lock files are byte-identical and Composer reports no dependency
delta.

### AD-NAV-1086: Report literal and equivalent gates separately

A failed fixed-path lookup must not be summarized as a successful literal
`make check`, even when every equivalent test and analyzer subsequently passes.

### AD-NAV-1087: Use version-compatible private probes

Read-only recovery checks may not depend on public module methods introduced
after the installed baseline.

### AD-NAV-1088: Keep documentation canonicalization separate from review

Passing this review does not authorize a local commit, push, pull request,
merge, restart, MQTT activation or device command.

## 10. Safety Result

This step performed:

```text
Symcon read-only confirmations: 0
Symcon mutations:               0
module updates:                 0
module reloads:                 0
MQTT activations:               0
credential requests:            0
OAuth actions:                  0
service restarts:               0
mower commands:                 0
repository commits:             0
repository pushes:              0
```

The current read-only confirmation recorded in this report was completed
during the immediately preceding isolation audit, not repeated by this step.

## 11. Gate Status

| Gate | Status |
|---|---|
| standalone correction publication | PASS |
| metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| corrective disabled Symcon update | PASS |
| Account status recovery | PASS |
| workstream isolation | PASS |
| toolchain provenance | PASS |
| variable and archive preservation | PASS |
| local SAEF documentation canonicalization | CLOSED |
| SAEF push or pull request | CLOSED |
| MQTT staging or activation | CLOSED |
| service restart | CLOSED |
| mower command | CLOSED |

## 12. Next Step

Proceed with:

```text
271-navimow-account-status-correction-case-study-canonicalization.md
```

That step should perform a final Navimow-only diff and privacy review, then
create one local documentation commit for steps 267 through 270 and the README
entry only after separate authorization. Push, pull request, merge and all live
operations remain separate gates.
