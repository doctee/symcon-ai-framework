# 113 Native MQTT Diagnostics Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Published and remotely verified; Symcon update remains blocked
**Date:** 2026-07-28
**Scope:** Gate A publication of the bounded read-only MQTT diagnostics from
step 111

## 1. Decision

Gate A from step 112 was explicitly authorized with:

```text
Veröffentlichung der bounded MQTT-Diagnostik freigegeben.
```

The exact three-file increment was published to the dedicated
`doctee/symcon-navimow` repository. No IP-Symcon update, live mutation, MQTT
activation, connection attempt, mower command, tag or module reload occurred.

## 2. Publication Baseline

The established standalone clone was clean and synchronized before copying:

```text
repository:   doctee/symcon-navimow
branch:       main
local HEAD:   6cc41d32df6cc2e528bdd4059dda3e006055241a
origin/main:  6cc41d32df6cc2e528bdd4059dda3e006055241a
```

The baseline subject was:

```text
feat: add native MQTT shadow lifecycle
```

The canonical and standalone manifests contained the same 30 productive files.
Only the three files approved in step 112 differed.

## 3. Published Boundary

Exactly these files were synchronized from the canonical distribution:

```text
NavimowAccount/module.php
NavimowAccount/form.json
NavimowAccount/locale.json
```

The staged delta was:

```text
modified files: 3
insertions:     224
deletions:      0
```

It contains:

- the bounded read-only `GetMqttDiagnostics()` projection;
- allowlisted diagnostic lifecycle, result and error codes;
- bounded counters and aggregate shadow counts;
- a maximum diagnostic attribute input size;
- one read-only configuration-form button;
- the corresponding German locale entry.

It adds no public variable, profile, timer, action contract, MQTT publish path,
REST command or installation-specific identifier.

Candidate SHA-256 hashes:

```text
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a  NavimowAccount/module.php
2291ca4b9e07e305daa5dc94b22e7bb8ea9473324f2eec909ea3f96703979e63  NavimowAccount/form.json
a4cc9cd7dd0f71f78b0902e7796e432f7f33d89ab43e5f88f04f3d744564acd2  NavimowAccount/locale.json
```

## 4. Validation

The complete MQTT shadow gate passed before publication. It covered:

- REST authentication and mapping regressions;
- command regressions;
- MQTT fixtures, envelopes and payload parsing;
- Receiver and Account pairing;
- targeted REST reconciliation;
- credential and transport lifecycle;
- bounded diagnostics, malformed-state privacy and `ShadowActive`;
- strict distribution structure;
- PHP syntax;
- PHPCS;
- PHPStan.

The copied standalone files additionally passed:

```text
PHP syntax:       PASS
JSON syntax:      PASS
git diff --check: PASS
privacy review:   PASS
exact delta:      PASS
```

The privacy scan found only expected source identifiers for configured or
runtime-held credentials. It found no credential value, token, private topic,
payload, device identity or installation metadata.

## 5. Official Module Validator

The official Symcon Module Validator was opened and exercised on 2026-07-28.
The form was usable and no cookie overlay was present.

The page again failed before producing a candidate result. Its inline
validation code invokes `window.$`, while `window.$` is undefined. The result
elements remained hidden after validation. This is classified as a validator
page failure, not as a candidate schema failure.

The current official schemas and the dependency referenced directly by that
page were therefore downloaded temporarily:

```text
librarySchema.json
moduleSchema.json
localeSchema.json
formSchema.json
AJV 6.10.2
```

AJV SHA-256:

```text
25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549
```

Equivalent validation result:

```text
PASS library.json
PASS 4 x module.json
PASS 4 x locale.json
PASS 4 x form.json
```

All 13 JSON artifacts passed. The temporary validator resources were not
published.

## 6. Commit and Remote Verification

The standalone commit is:

```text
efb8343e50dbea612db26e49324130ed3d039e90
feat: expose bounded MQTT diagnostics
```

The push was a fast-forward:

```text
6cc41d3..efb8343  main -> main
```

After a fresh remote fetch:

```text
local HEAD:   efb8343e50dbea612db26e49324130ed3d039e90
origin/main:  efb8343e50dbea612db26e49324130ed3d039e90
FETCH_HEAD:   efb8343e50dbea612db26e49324130ed3d039e90
worktree:     clean
```

The three remote blobs match the committed files, and the complete standalone
repository is byte-equal to the canonical distribution when private
`.DS_Store` files are excluded.

No tag was created.

## 7. Architecture Decision

The diagnostic surface remains an explicit read-only projection rather than
exposing internal attributes directly. This preserves:

- REST authority over public mower state;
- bounded output size and nesting depth;
- allowlisted status vocabulary;
- aggregate counts instead of private device identities;
- no credential, endpoint, topic or payload disclosure;
- no diagnostic side effect.

Publication alone does not prove IP-Symcon runtime compatibility. The update
and runtime read-back remain a separate authorization gate so that repository
publication cannot implicitly mutate the private pilot installation.

## 8. Gate Status

Gate A is complete.

Gate B remains closed. Its required authorization is:

```text
Symcon-Update und read-only Diagnoseprüfung freigegeben.
```

After that authorization:

1. the user updates the module through Module Control;
2. existing instance, variable and archive identities are checked;
3. MQTT remains disabled;
4. `NAVAC_GetMqttDiagnostics()` is invoked read-only;
5. its bounded, redacted result is verified;
6. the result is documented in
   `114-native-mqtt-diagnostics-symcon-retest-report.md`.

The one-shot connection retest remains behind the later, independent Gate C.
