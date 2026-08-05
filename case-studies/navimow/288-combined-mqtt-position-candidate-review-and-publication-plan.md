# 288 Combined MQTT Position Candidate Review and Publication Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Exact local candidate reviewed and frozen by five-file productive
hash; no commit, push, publication or live operation performed

**Date:** 2026-08-05

## 1. Candidate Result

The combined receive-only transport and position candidate is ready for local
canonicalization.

The standalone delta against clean local `symcon-navimow` main at
`eda494513826fa43ccc1b28634b06354356f49a4` is exactly:

```text
NavimowAccount/form.json
NavimowAccount/locale.json
NavimowAccount/module.php
libs/Navimow/MqttPayloadParser.php
libs/Navimow/MqttPositionDiagnostic.php
```

No other standalone path differs.

## 2. Productive Hash Freeze

| Path | SHA-256 |
|---|---|
| `NavimowAccount/form.json` | `757841b3905bf9a854f859abd6a7cc877dbba7669026bf9bb48d4a7471b9698e` |
| `NavimowAccount/locale.json` | `350de7e6cffe0d80e4c0cf8fcdc226e8997d7ab9ce52b82d1e7b797aef1edd61` |
| `NavimowAccount/module.php` | `ec284c98de1a1b79b0dd8336e5179a3fd25f55ae5fb6393c125dc2677ce00972` |
| `libs/Navimow/MqttPayloadParser.php` | `777b6cbd2c99b9859ecc5922ee684b91f13a9178829cf00b35378c1ab6a78b1b` |
| `libs/Navimow/MqttPositionDiagnostic.php` | `733ad081c2d237ac6268657e97f3822ab0eae640ed94711af6780a593ddd6728` |

The ordered five-file hash manifest has SHA-256:

```text
9ec523096f532961630924e150a34db8705ff2368aa34d31840aec919de3a4a9
```

Any productive byte change invalidates this freeze.

## 3. SAEF Candidate Scope

The SAEF branch additionally contains:

- steps 284 through 288 and README index entries;
- parser, reducer, Account-ingestion and pilot-checkpoint tests;
- the isolated Navimow check-runner dependency-path improvement.

Private captures, analyzers, harness state and probes remain excluded from Git.

## 4. Review Findings

No blocking finding remains after offline review.

Confirmed boundaries:

- REST stays authoritative;
- MQTT remains receive-only;
- no MQTT publish or mower command path exists;
- no new public variable or Archive logging exists;
- position collection is opt-in and disabled by default;
- the detail track and serialized state are bounded;
- native checkpoints contain counters but no coordinates;
- cleanup clears the position diagnostic with existing MQTT ephemeral state;
- multiple devices and malformed state fail closed;
- standalone delta is exactly five files.

## 5. Verification Gate

The following checks pass against the unfrozen working tree:

```text
private combined-harness validation
focused Navimow MQTT suite
distribution structure validation
PHPCS
PHPStan
git diff --check
complete composer check
```

The complete check uses the dependency installation from the canonical main
workspace only as immutable toolchain input. No Open-Meteo or ControlLight
worktree contributes source to this candidate.

## 6. Reduced Approval Sequence

The workflow groups mechanically coupled actions while preserving genuinely
different trust boundaries.

### Gate P1: SAEF candidate publication

One explicit authorization may cover:

1. final status and diff preflight;
2. stage the exact SAEF candidate;
3. create one conventional commit;
4. push `codex/navimow-position-diagnostics`;
5. open one pull request against SAEF `main`.

It does not authorize merge, standalone publication or Symcon access.

### Gate P2: SAEF merge

After review and green checks, one authorization may cover merge plus
canonical `origin/main` verification.

### Gate S1: Standalone five-file publication

One exact hash-bound authorization may cover:

1. verify clean standalone main at the expected remote commit;
2. copy exactly the five frozen productive files;
3. validate byte equality and metadata structure;
4. commit and push once to standalone main;
5. verify the resulting remote commit.

This should use the future generalized SAEF fileset publisher when available.
Until then, the same expected-base, expected-fileset-hash and exact-path
preconditions must be enforced manually.

### Gate L1: Disabled live rollout

One separately explicit live authorization may cover metadata conformance,
one supported Symcon module update and immediate plus delayed disabled,
credential-free read-only checks. It may not activate MQTT.

### Gate L2: Combined pilot activation

One final activation authorization may cover:

- position diagnostics and receive-only MQTT activation;
- automatic native five-hour checkpoints;
- bounded read-only status observations;
- mandatory cleanup at completion or any stop condition.

The exact installed commit, accepted persistence terms and fresh token
readiness must be bound before this gate begins.

## 7. Rollback

Before standalone publication, rollback is simply abandonment of the local
candidate.

After publication but before activation:

- disable MQTT;
- verify the chain is inactive and credential-free;
- update to the previously proven standalone commit if required.

During the pilot, the pre-authorized terminal action is always MQTT disable
plus one Account `ApplyChanges()`, followed by immediate and delayed read-only
verification.

## 8. Gate Status

| Gate | Status |
|---|---|
| exact productive fileset | FROZEN |
| productive manifest hash | FROZEN |
| local offline verification | PASS |
| Gate P1 SAEF candidate publication | CLOSED |
| Gate P2 SAEF merge | CLOSED |
| Gate S1 standalone publication | CLOSED |
| Gate L1 disabled rollout | CLOSED |
| Gate L2 pilot activation | CLOSED |

## 9. Next Step

Request Gate P1 for one bounded SAEF candidate commit, branch push and pull
request. Do not publish the standalone module or access Symcon in that step.
