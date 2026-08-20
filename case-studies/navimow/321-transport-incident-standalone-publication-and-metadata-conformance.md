# 321 Transport Incident Standalone Publication And Metadata Conformance

**Case study:** Navimow native IP-Symcon module

**Status:** Exact one-file standalone publication and metadata conformance
passed; Symcon rollout and MQTT activation remain closed

**Date:** 2026-08-17

## 1. Result

Gate S1 published the incident reducer from canonical SAEF main to the
dedicated standalone Navimow repository.

```text
SAEF merge commit:       35eb0e21dd27bad1bbd84a60af9234441cadeeb6
standalone baseline:     888325d8649160c5bae473f4f8a052cf86e703b6
standalone publication:  405fd24b5450c909c35e038a12bd69378d33deb6
changed standalone files: 1
```

The published repository is byte-equal to the complete canonical SAEF
distribution. All 13 metadata inputs remain conformant through the established
fresh-asset and input-byte-equivalence fallback.

No Symcon access, module update, MQTT activation, credential retrieval, OAuth
action, restart or mower command occurred.

## 2. Standalone Preflight

Immediately before mutation, the clean publication clone and the direct remote
read both resolved to the frozen baseline:

```text
repository:        doctee/symcon-navimow
branch:            main
local HEAD:        888325d8649160c5bae473f4f8a052cf86e703b6
local origin/main: 888325d8649160c5bae473f4f8a052cf86e703b6
remote main:       888325d8649160c5bae473f4f8a052cf86e703b6
worktree:          clean
files:             31
```

The baseline Account module matched the step-320 freeze:

```text
SHA-256: 0b59e196c2c31ca0336c3485b7631b05bf5962cbe48bee4dfc9618ba5dc0564f
Git blob: eb656eaac4fa618ba66412665b00387fb53058d9
```

## 3. Exact Publication

Only this mapping was applied:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
```

The copied candidate matched the frozen artifact:

```text
SHA-256: 32addd432fac80c0d1130dfb7829011142670a923d2ce1d954f7d047e0127e43
Git blob: f7106189c0e015b1ef7d0b669d3a90a474494c1a
```

The standalone commit contains exactly:

```text
modified paths: 1
added paths:    0
deleted paths:  0
insertions:     431
deletions:      9
```

Commit message:

```text
fix(mqtt): reduce transport incidents
```

The push was one fast-forward update from the exact baseline to
`405fd24b5450c909c35e038a12bd69378d33deb6`.

## 4. Publication Verification

Post-publication evidence proves:

- local HEAD and local `origin/main` equal the published commit;
- direct remote read-back equals the published commit;
- the commit parent equals the frozen baseline;
- the commit changes only `NavimowAccount/module.php`;
- the published Account blob equals the frozen candidate;
- all PHP files pass syntax validation;
- all JSON files parse successfully;
- metadata files are unchanged; and
- the complete 31-file standalone tree is byte-equal to the canonical SAEF
  distribution.

## 5. Metadata Conformance

The publication changed PHP only. These 13 inputs are unchanged:

```text
library.json
NavimowAccount/module.json
NavimowConfigurator/module.json
NavimowDevice/module.json
NavimowMqttReceiver/module.json
NavimowAccount/form.json
NavimowConfigurator/form.json
NavimowDevice/form.json
NavimowMqttReceiver/form.json
NavimowAccount/locale.json
NavimowConfigurator/locale.json
NavimowDevice/locale.json
NavimowMqttReceiver/locale.json
```

Fresh copies of the following official public assets were downloaded:

- Symcon Module Validator page;
- `librarySchema.json`;
- `moduleSchema.json`;
- `formSchema.json`;
- `localeSchema.json`; and
- AJV 6.10.2.

All six assets are byte-equal to the accepted set used by the prior executed
official-schema evidence. All 13 current inputs are byte-equal to their prior
valid counterparts.

```text
official assets equal: 6 / 6
metadata inputs equal: 13 / 13
prior valid inputs:    13 / 13
result:                PASS BY BYTE EQUIVALENCE
```

The local shell did not expose a Node.js runtime, so the prepared AJV runner
could not be executed again. This is reported as runtime unavailability, not
as schema success or failure. The deterministic byte-equivalence proof follows
the accepted step-303 fallback and is sufficient because both the complete
official asset set and every validation input are unchanged.

## 6. Preserved Contracts

```text
public device-state authority:        REST
MQTT direction:                       receive-only
MQTT default:                         disabled
MQTT publish and command paths:       absent
recoverable independent incidents:    1
maximum episodes per incident:        3
maximum incident duration:            1800 seconds
sustained-health incident reset:      900 seconds
public variables and profiles:        unchanged
Archive identities and logging:       unchanged
automatic cleanup ordering:           credential first and idempotent
```

## 7. Private Evidence

Commit-bound machine-readable evidence is retained under:

```text
private/navimow-capture/output/
  transport-incident-s1-metadata-conformance/
```

It contains only downloaded public validator assets, local validation tooling
and hash-bound results. It contains no credential, token, private MQTT topic,
payload, coordinate, device identity, ObjectID, hostname or installation
metadata.

## 8. Architecture Decisions

### AD-NAV-1320: Publish only the frozen productive file

Documentation and tests remain in SAEF. The standalone mutation is restricted
to the byte-frozen Account module and accepted only with complete-tree equality.

### AD-NAV-1321: Bind metadata evidence to the exact published commit

Remote commit equality and unchanged metadata prove that all 13 inputs belong
to the exact publication, not merely to a local candidate.

### AD-NAV-1322: Classify unavailable execution separately

The missing local Node.js runtime is neither a validator failure nor a newly
executed schema pass. Full fresh-asset and input equality provides the accepted
deterministic fallback without overstating the evidence.

### AD-NAV-1323: Keep publication independent from installation

Publishing and validating the module authorize no Module Control operation or
credential-bearing transport activity.

## 9. Gate State

| Gate | Status |
|---|---|
| SAEF main merge | PASS |
| exact standalone publication | PASS |
| remote commit and blob read-back | PASS |
| complete distribution equality | PASS |
| metadata conformance | PASS BY BYTE EQUIVALENCE |
| Gate L1 disabled Symcon rollout | CLOSED |
| Gate L2 bounded live pilot | CLOSED |

## 10. Next Step

Proceed with Gate L1 only after separate authorization. It may perform one
supported `MC_UpdateModule()` call with MQTT and position diagnostics disabled,
followed by immediate and delayed structured read-only verification. It must
not call `MC_ReloadModule()` or `IPS_ApplyChanges()` and must not activate MQTT.
