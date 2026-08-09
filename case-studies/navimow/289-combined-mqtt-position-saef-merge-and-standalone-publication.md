# 289 Combined MQTT Position SAEF Merge and Standalone Publication

**Case study:** Navimow native IP-Symcon module

**Status:** SAEF candidate merged and exact five-file standalone candidate
published and remotely verified; fresh metadata, Symcon and MQTT activation
gates remain closed

**Date:** 2026-08-05

**Scope:** Close Gates P2 and S1 from step 288 without tag, release, Symcon
access, credential retrieval, MQTT activation or mower command

## 1. Result

PR #25 was marked ready for review, merged through an explicit merge commit and
verified as canonical SAEF main. The frozen five-file productive delta was then
copied byte-exactly to the clean standalone repository, committed once and
published by fast-forward to `doctee/symcon-navimow` main.

```text
SAEF pull request:          #25
SAEF candidate:             73d172034e2b31212fdf5cb8925794ae2dd253a9
SAEF merge commit:          d79a4b6ffa4b41c69aeb0d13a7230263681eecc3
standalone baseline:        eda494513826fa43ccc1b28634b06354356f49a4
standalone published:       4b4b4d7b577df2639ed4a82049aa127c56bdc989
standalone subject:         feat(mqtt): add bounded position diagnostics
productive paths:           5
insertions / deletions:     752 / 2
complete distribution tree: 6ea9be105b999c849575a70d6e78e136df4764fe
```

## 2. Authorization Boundary

The user separately authorized:

```text
PR #25 pruefbereit markieren, per Merge-Commit mergen und origin/main
kanonisch verifizieren
```

and then:

```text
Weiter bis S1
```

The latter was applied only to Gate S1 as defined in step 288. Neither
authorization included a tag, release, fresh official Module Validator run,
Symcon access, OAuth action, MQTT activation, restart or mower command.

## 3. Gate P2: SAEF Merge

Immediately before merge, PR #25 was:

```text
state:          open
draft:          false after ready transition
mergeability:   MERGEABLE / CLEAN
head:           73d172034e2b31212fdf5cb8925794ae2dd253a9
commits:        1
changed files:  16
CI validations: 2 passed, 0 failed
```

The merge operation was bound to the exact expected head SHA. Fresh fetch and
read-back proved:

```text
origin/main:    d79a4b6ffa4b41c69aeb0d13a7230263681eecc3
first parent:   cd1c29ab4e913bca7e8ffa92aa73652ec934dc84
second parent:  73d172034e2b31212fdf5cb8925794ae2dd253a9
merged tree:    5b1c9035ee2bf487fe02377251e0b0faaaed0c23
candidate tree: 5b1c9035ee2bf487fe02377251e0b0faaaed0c23
```

No unexpected path or whitespace difference was present.

## 4. Gate S1 Preflight

The standalone checkout and direct remote ref both remained on the frozen
baseline:

```text
local HEAD:         eda494513826fa43ccc1b28634b06354356f49a4
fetched origin/main: eda494513826fa43ccc1b28634b06354356f49a4
direct remote main: eda494513826fa43ccc1b28634b06354356f49a4
worktree:           clean
```

All five SHA-256 values from step 288 were reproduced. The ordered source-path
manifest reproduced:

```text
9ec523096f532961630924e150a34db8705ff2368aa34d31840aec919de3a4a9
```

## 5. Exact Productive Mutation

Only these mappings were applied:

```text
distribution/NavimowAccount/form.json
    -> NavimowAccount/form.json
distribution/NavimowAccount/locale.json
    -> NavimowAccount/locale.json
distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
distribution/libs/Navimow/MqttPayloadParser.php
    -> libs/Navimow/MqttPayloadParser.php
distribution/libs/Navimow/MqttPositionDiagnostic.php
    -> libs/Navimow/MqttPositionDiagnostic.php
```

Every source and destination file compared byte-equal. Every staged Git blob
equaled its canonical SAEF blob. After the copy, the complete standalone tree
equaled the canonical distribution subtree:

```text
6ea9be105b999c849575a70d6e78e136df4764fe
```

## 6. Validation

Before commit and publication, the following checks passed:

- focused Navimow REST and MQTT suite;
- position reducer, Account ingestion and pilot checkpoint tests;
- transport lifecycle, reconciliation and recovery tests;
- distribution structure validation;
- PHP syntax for all standalone PHP files;
- JSON parsing for all 13 standalone metadata inputs;
- PHPCS and PHPStan through the focused suite;
- all 13 metadata inputs against the unchanged established official schema
  asset set and AJV 6.10.2;
- privacy and MQTT-writer scan of the staged delta;
- staged-path and whitespace checks.

The schema execution in this step is a pre-publication structure check. It is
not claimed as the fresh published-commit metadata conformance gate.

## 7. Publication and Remote Verification

The first local `git push` invocation failed before reaching GitHub because
the sandboxed shell could not resolve `github.com`. The result was explicit,
not ambiguous, and direct remote state remained unchanged. One authorized
network-capable retry then fast-forwarded standalone main.

Fresh fetch and direct remote-ref read-back proved:

```text
local standalone HEAD:  4b4b4d7b577df2639ed4a82049aa127c56bdc989
fetched origin/main:     4b4b4d7b577df2639ed4a82049aa127c56bdc989
direct remote main:      4b4b4d7b577df2639ed4a82049aa127c56bdc989
parent:                  eda494513826fa43ccc1b28634b06354356f49a4
published tree:          6ea9be105b999c849575a70d6e78e136df4764fe
canonical SAEF subtree:  6ea9be105b999c849575a70d6e78e136df4764fe
standalone worktree:     clean
```

There was one effective remote update and no blind retry.

## 8. Preserved Runtime Contract

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT publish path:             absent
MQTT mower-command path:       absent
position diagnostics default:  disabled
public variables:              unchanged
Archive Control logging:       unchanged
position detail samples:       maximum 512
serialized position state:     maximum 131072 bytes
native checkpoint coordinates: absent
```

Publication changed only source availability. It did not install or execute
the candidate in Symcon.

## 9. Mutation Counts

```text
SAEF ready transitions:       1
SAEF merges:                  1
standalone files copied:      5
standalone commits:           1
effective remote updates:     1
pre-network push failures:    1
blind retries:                0
tags or releases:             0
Symcon reads:                 0
Symcon mutations:             0
OAuth actions:                0
MQTT credential requests:     0
MQTT activations:             0
mower commands:               0
```

## 10. Architecture Decisions

### AD-NAV-1215: Bind merge to the reviewed head

The ready transition does not relax identity. Merge must fail if the reviewed
head SHA changes.

### AD-NAV-1216: Publish the frozen five-file set only

The standalone update is an exact path allowlist, not a recursive convenience
copy.

### AD-NAV-1217: Require complete-tree equality

Individual file hashes prove the mutation scope; full Git-tree equality proves
that the resulting standalone repository is the canonical distribution.

### AD-NAV-1218: Distinguish local transport failure from ambiguous push

An explicit pre-network DNS failure permits one authorized retry after remote
state is known. An ambiguous remote result would require read-back and no blind
second push.

### AD-NAV-1219: Keep metadata conformance post-publication

Local schema success supports S1 but does not replace fresh validation bound to
the exact published commit.

### AD-NAV-1220: Preserve all live gates

Published disabled code does not authorize module update, credential use,
transport activation or a mower command.

## 11. Gate Status

| Gate | Status |
|---|---|
| Gate P1 SAEF candidate publication | PASS IN PR #25 |
| Gate P2 SAEF merge | PASS |
| Gate S1 standalone five-file publication | PASS |
| remote standalone verification | PASS |
| fresh published metadata conformance | CLOSED |
| Gate L1 disabled Symcon rollout | CLOSED |
| Gate L2 combined pilot activation | CLOSED |
| tag or release | NOT PERFORMED |

## 12. Next Step

Create the separately read-only metadata-conformance step for exact standalone
commit `4b4b4d7b577df2639ed4a82049aa127c56bdc989`. It should attempt the
official Module Validator and, if its known runtime defect recurs, use freshly
downloaded unmodified official schemas and the validator-referenced AJV
version. It must not access Symcon or activate MQTT.
