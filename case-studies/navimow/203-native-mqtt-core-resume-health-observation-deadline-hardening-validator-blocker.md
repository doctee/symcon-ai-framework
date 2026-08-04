# Native MQTT Core-Resume Health Observation Deadline Hardening Validator Blocker

**Date:** 2026-07-29
**Status:** Gate B blocked by a reproducible official web-validator runtime
failure; exact-schema diagnostics pass but do not satisfy the gate
**Scope:** Read-only validation of the exact published standalone commit from
step 202

## 1. Objective

Step 201 requires a real successful result from the official Symcon Module
Validator before the disabled Symcon update can be considered.

This run targeted:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     8fdab84bd2a2190a6025cedd11f1ae6248369c0e
```

No Symcon installation, MQTT transport, service, mower or published repository
was mutated.

## 2. Candidate Integrity

The published Account implementation remained byte-equal to the frozen
candidate:

```text
git blob SHA-1: c7d1dfeda3d6aa85841ff71859e81d2457398334
SHA-256:        6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f
```

The standalone worktree remained clean at the exact published commit.

## 3. Official Validator Run

The official validator was opened at:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/module-validator/
```

The public `library.json` and all four public `module.json` files were entered
individually. The page was also reloaded cleanly before repeating the library
attempt.

| Input | Selected type | Official UI result |
|---|---|---|
| library metadata | `library.json` | no result |
| Account metadata | `module.json` | no result |
| Configurator metadata | `module.json` | no result |
| Device metadata | `module.json` | no result |
| MQTT Receiver metadata | `module.json` | no result |

Neither a successful nor a failed schema result was rendered for any input.
The browser console reported:

```text
ReferenceError: $ is not defined
    at SetSchema

ReferenceError: $ is not defined
    at SetOutput
    at Validate
```

`SetSchema` failed during initial page setup and again when changing the file
type. Every `Validieren` invocation then failed in `SetOutput`. A clean page
reload reproduced the same signature.

The user then repeated the `library.json` case independently in a local
browser. The supplied console capture confirmed the same current page
locations:

```text
ReferenceError: Can't find variable: $
    SetSchema     module-validator:672
    Globaler Code module-validator:658

ReferenceError: Can't find variable: $
    SetOutput module-validator:711
    Validate  module-validator:706
    onclick   module-validator:649
```

The independent browser result therefore reproduces both failure paths and
rules out an in-app-browser-only rendering problem.

## 4. Error Classification

The current page source uses `$` in `SetSchema`, `Validate` and `SetOutput`.
The loaded document exposed the AJV script reference but no script source that
provided jQuery or another `$` implementation.

Classification:

```text
browser transport:             PASS
official validator page load:  PASS
input interaction:             PASS
independent local reproduction: PASS
official result rendering:     FAIL
real validator schema error:   NOT OBSERVED
official validator success:    NOT ESTABLISHED
```

This is a web-validator runtime/UI failure. It is not evidence that a module is
invalid, but step 201 explicitly prohibits treating a missing result as a
successful validator run.

## 5. Exact-Schema Diagnostic

To distinguish the UI defect from candidate metadata, the two public schemas
referenced by the official page and the exact AJV version referenced by that
page were downloaded read-only:

| Artifact | SHA-256 |
|---|---|
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

The five unchanged metadata files all passed that exact schema-engine
combination:

| Input | Diagnostic result |
|---|---|
| library metadata | PASS |
| Account metadata | PASS |
| Configurator metadata | PASS |
| Device metadata | PASS |
| MQTT Receiver metadata | PASS |

This diagnostic supports the classification that no candidate schema defect is
currently visible. It deliberately does not replace the required successful
official UI result.

## 6. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| Gate B official Module Validator | BLOCKED |
| exact-schema diagnostic | PASS |
| Gate C disabled Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**Gate B is not complete.**

The official validator must be retried after its page runtime is repaired, or a
real successful official result must otherwise be supplied for the exact
published commit. Until then, the separately authorized disabled Symcon update
must not begin.
