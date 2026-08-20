# 332 Early Closure And Task Parser Publication Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Exact three-file standalone candidate frozen

**Date:** 2026-08-20

## 1. Decision

The step-331 candidate is ready for SAEF pull-request review and later exact
standalone publication. This step freezes bytes and trust boundaries; it does
not itself alter the standalone repository or Symcon.

## 2. SAEF Candidate

```text
branch:             codex/navimow-early-closure-task-parser
implementation:     38b60eeee47a330eb3b34ceb69e57769c9e7b205
base origin/main:   f89e1a0
worktree:           clean before this report
scope:              case-studies/navimow only
```

The candidate was developed in a dedicated worktree from current
`origin/main`. Steps 321 through 330 were first preserved as a separate
documentation commit and contain no productive source change.

## 3. Standalone Baseline

Fresh fetch and direct remote read established:

```text
repository:        doctee/symcon-navimow
branch:            main
local HEAD:        405fd24b5450c909c35e038a12bd69378d33deb6
local origin/main: 405fd24b5450c909c35e038a12bd69378d33deb6
remote main:       405fd24b5450c909c35e038a12bd69378d33deb6
worktree:          clean
files:             31
```

A recursive comparison against the SAEF distribution found exactly three
different files and no additions or deletions.

## 4. Productive Freeze

| Standalone path | Candidate SHA-256 | Candidate Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `1429c2a081032b283c6b4d399879bf9ec293d3acdacdba1145618dcf871c0067` | `986020410f68924703fb25bdadbc6857dae75228` |
| `libs/Navimow/MqttPartialStateAccumulator.php` | `aab45643aaa22ec6b60a522826d10a658a5c61f1d4c34b849cde6853ef15fcee` | `5a27a6389477d1770bb48dcb43b5bec184a97757` |
| `libs/Navimow/MqttPayloadParser.php` | `f2295caec1dec986fc5797b5cc8c806e0142d7292c4f240b840a4adff32cb1be` | `c078ae489718f922dd9de69683819d3f776820d2` |

Ordered publication manifest SHA-256:

```text
da0aa0851b27a3138d5248740fd662733178bdd33c22ba0914c9a2691ec5aa0c
```

The exact rollback baseline hashes are:

| Standalone path | Baseline SHA-256 |
|---|---|
| `NavimowAccount/module.php` | `32addd432fac80c0d1130dfb7829011142670a923d2ce1d954f7d047e0127e43` |
| `libs/Navimow/MqttPartialStateAccumulator.php` | `ff52b5832cb179a238bdcbb61fcd7eaad83f38b00ec27210217aca57b696eb1b` |
| `libs/Navimow/MqttPayloadParser.php` | `777b6cbd2c99b9859ecc5922ee684b91f13a9178829cf00b35378c1ab6a78b1b` |

Any productive byte change invalidates this freeze.

## 5. Verification Freeze

The following passed on the exact candidate:

- every Navimow `mqtt-*.php` test;
- REST and authentication tests;
- pilot observation harness;
- distribution validation;
- complete `composer check` with the canonical dependency toolset;
- repository and Open-Meteo PHPStan;
- canonical PHPCS plus a direct check of every changed PHP file;
- JSON parsing for all MQTT fixtures; and
- `git diff --check`.

The broad direct Navimow PHPCS scan also reports four historical findings in
two unmodified test files. They are outside this candidate; every modified PHP
file passes the same ruleset.

## 6. Fixed Boundaries

```text
REST authority:                    authoritative and unchanged
MQTT direction:                    receive-only
MQTT default:                      disabled
MQTT command/publish paths:        absent
public variables and profiles:     unchanged
Archive identities and logging:    unchanged
raw area identifiers:              not persisted
opaque work-position payload:      not persisted
closure cleanup:                   module-owned and credential-first
```

## 7. Gate Sequence

### P1 and P2: SAEF publication

Push this branch, open one pull request, require green checks, review the exact
diff, merge by merge commit and verify canonical `origin/main`.

### S1: Standalone publication

After SAEF merge, fetch the standalone remote again, require exact baseline
`405fd24b…`, copy only the three frozen files, prove complete-tree equality,
commit once, push once and verify the remote commit and blobs.

Metadata files do not change, but metadata conformance must still be proven
against the published commit.

### L1: Disabled Symcon rollout

Require a credential-free disabled preflight, exactly one supported module
update and immediate plus delayed read-only verification. The update should
reconcile the stale step-330 registry through the module-owned timer without
`MC_ReloadModule()`, external attribute writes or MQTT activation.

### L2: Bounded evidence pilot

Only after L1 passes, one restart-free activation may observe task and area
correlation. It requires the established 1200-second token horizon, explicit
temporary credential acceptance, no activation retry and mandatory automatic
or supervised cleanup. The evidence window may be shortened once the required
natural task transitions have been captured; 72 hours is a maximum, not a
minimum.

## 8. Gate State

| Gate | Status |
|---|---|
| local implementation and freeze | PASS |
| P1 branch and pull request | READY |
| P2 review and merge | PENDING |
| S1 standalone publication | PENDING |
| L1 disabled rollout and stale closure proof | PENDING |
| L2 bounded receive-only evidence | PENDING |
