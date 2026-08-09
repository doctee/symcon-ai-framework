# 290 Combined MQTT Position Metadata Conformance

**Case study:** Navimow native IP-Symcon module

**Status:** Exact published commit passed metadata conformance through the
established fresh official-schema fallback; Symcon update and MQTT activation
remain closed

**Date:** 2026-08-05

**Scope:** Validate all metadata, form and locale inputs for exact standalone
commit `4b4b4d7b577df2639ed4a82049aa127c56bdc989` without accessing Symcon

## 1. Result

The official Symcon Module Validator loaded and accepted the exact published
`library.json` input. The `Validieren` action was triggered, but the page again
failed in its own JavaScript and rendered no schema result.

Freshly downloaded, unmodified official schemas and the exact AJV version
referenced by the official page then validated every published metadata input.

```text
official page:                    loaded
official input submission:        executed
official rendered result:         none
official tool classification:     runtime error
exact official-schema fallback:   PASS
published inputs:                 13
passed:                           13
failed:                           0
metadata conformance gate:        PASS THROUGH ESTABLISHED FALLBACK
```

No metadata defect was observed.

## 2. Exact Publication Binding

```text
repository:    doctee/symcon-navimow
branch:        main
published:     4b4b4d7b577df2639ed4a82049aa127c56bdc989
local HEAD:    4b4b4d7b577df2639ed4a82049aa127c56bdc989
origin/main:   4b4b4d7b577df2639ed4a82049aa127c56bdc989
direct remote: 4b4b4d7b577df2639ed4a82049aa127c56bdc989
worktree:      clean
```

The validation was executed after the S1 publication and is bound to the exact
published files by per-input SHA-256.

## 3. Official Validator Attempt

Official page:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/
sdk-tools/tools/module-validator/
```

The page exposed and accepted:

- the `library.json` file type;
- the exact public JSON content;
- the `Validieren` action.

After the action, no success or failure result appeared. Browser diagnostics
reported:

```text
ReferenceError: $ is not defined
    at SetSchema
```

The cookie component did not cover the validator controls. The failure belongs
to the page's `SetSchema()` implementation, not to the candidate.

Repeating the other 12 inputs in the same broken UI would not produce
independent evidence and was intentionally omitted.

## 4. Fresh Official Assets

The official page currently references:

```text
AJV 6.10.2
https://cdnjs.cloudflare.com/ajax/libs/ajv/6.10.2/ajv.min.js
/assets/files/validation/<type>Schema.json
```

All assets were downloaded after the official UI attempt.

| Official artifact | SHA-256 |
|---|---|
| validator page | `9e4ba1a35d8da4407272b3439b5e9af7519879b96519e97835f5b10e873f6622` |
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| `formSchema.json` | `b06a1090d42e42d703e3b97bebb00f1706b4f33cf8e85781e62e154cddfe52f7` |
| `localeSchema.json` | `fe013b9036f1c29f9ec76f02f760168fb63b58b4ad035529d9fbd0d50b48f3b2` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

The hashes equal the previous accepted official asset set. No schema, engine or
page drift was observed.

## 5. Validation Matrix

| Schema | Inputs | Passed | Failed |
|---|---:|---:|---:|
| library | 1 | 1 | 0 |
| module | 4 | 4 | 0 |
| form | 4 | 4 | 0 |
| locale | 4 | 4 | 0 |
| **Total** | **13** | **13** | **0** |

Every error array is empty. The changed Account form and locale are included:

| Changed metadata input | SHA-256 | Result |
|---|---|---|
| `NavimowAccount/form.json` | `757841b3905bf9a854f859abd6a7cc877dbba7669026bf9bb48d4a7471b9698e` | PASS |
| `NavimowAccount/locale.json` | `350de7e6cffe0d80e4c0cf8fcdc226e8997d7ab9ce52b82d1e7b797aef1edd61` | PASS |

Machine-readable private result:

```text
validation-results SHA-256:
b37c932882c859f7ac964681019de206cf6e15f91d0ba445767a7410e635299f
```

## 6. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
position diagnostics default:  disabled
public variables:              unchanged
Archive Control contracts:     unchanged
```

Metadata validation neither loaded nor executed the module in Symcon.

## 7. Mutation Counts

```text
official validator submissions: 1
official asset downloads:       6
metadata inputs validated:      13
public repository mutations:    0
Symcon reads:                   0
Symcon mutations:               0
OAuth actions:                  0
MQTT credential requests:       0
MQTT activations:               0
mower commands:                 0
```

## 8. Architecture Decisions

### AD-NAV-1221: Attempt the official validator first

The fallback is used only after the official UI has accepted the exact input
and failed to render a schema result.

### AD-NAV-1222: Classify the page defect separately

`ReferenceError: $ is not defined` in `SetSchema()` is an
official-validator runtime failure and is not candidate rejection evidence.

### AD-NAV-1223: Bind fallback evidence to fresh assets and published inputs

Schemas, engine, page and all 13 inputs are hash-bound. Custom or stale schemas
alone cannot satisfy this gate.

### AD-NAV-1224: Keep metadata validation live-system free

A valid repository structure does not authorize installation, credential use
or MQTT activation.

## 9. Gate Status

| Gate | Status |
|---|---|
| exact published commit binding | PASS |
| official Module Validator attempt | RUNTIME ERROR, NO CANDIDATE FAILURE |
| fresh official-schema fallback | PASS 13 / 13 |
| metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| Gate L1 disabled Symcon rollout | CLOSED |
| Gate L2 combined pilot activation | CLOSED |

## 10. Next Step

Prepare Gate L1 for one supported Symcon module update to exact commit
`4b4b4d7b577df2639ed4a82049aa127c56bdc989` while MQTT remains disabled.
The gate must include fresh read-only preconditions plus immediate and delayed
credential-free post-update verification. It must not activate MQTT.
