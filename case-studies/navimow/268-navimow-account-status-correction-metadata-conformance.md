# 268 Navimow Account Status Correction Metadata Conformance

**Case study:** Navimow native IP-Symcon module

**Status:** Gate B passed through the established exact-official-schema
fallback; corrective Symcon update and MQTT gates remain closed

**Date:** 2026-08-05

**Scope:** Validate all metadata, form and locale inputs for exact published
commit `eda494513826fa43ccc1b28634b06354356f49a4` without accessing Symcon

## 1. Result

The official Symcon Module Validator loaded and accepted the exact published
`library.json` input. The `Validieren` action was triggered, but the page again
failed in its own JavaScript and rendered no schema result.

The established fallback then validated all 13 exact published inputs with
freshly downloaded, unmodified official schemas and the exact AJV version
referenced by the official page.

```text
official page:                    loaded
official input submission:        executed
official rendered result:         none
official tool classification:     runtime error
exact official-schema fallback:   PASS
published inputs:                 13
passed:                           13
failed:                           0
Gate B metadata conformance:      PASS THROUGH ESTABLISHED FALLBACK
```

No metadata defect was observed.

## 2. Exact Publication Binding

```text
repository:    doctee/symcon-navimow
branch:        main
published:     eda494513826fa43ccc1b28634b06354356f49a4
origin/main:   eda494513826fa43ccc1b28634b06354356f49a4
worktree:      clean
Account blob:  ad4432c29613062cd277e44ed161a7877b624da5
```

The publication commit changes only:

```text
NavimowAccount/module.php
```

No `library.json`, `module.json`, `form.json` or `locale.json` changed between
the prior metadata-conformant commit `a8481c97` and `eda4945`.

## 3. Official Validator Attempt

Official page:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/
sdk-tools/tools/module-validator/
```

The page exposed and accepted:

- the `library.json` file-type selection;
- the exact public JSON content;
- the `Validieren` action.

After the action, no success or failure result appeared. Browser diagnostics
reported:

```text
ReferenceError: $ is not defined
    at SetSchema
```

Classification:

| Signal | Result |
|---|---|
| page load | PASS |
| form available | PASS |
| exact public input entered | PASS |
| validation triggered | PASS |
| schema result rendered | NO |
| candidate failure observed | NO |
| official validator success claimed | NO |

The page was interactable and no cookie overlay blocked the controls. The
failure occurred in the validator's `SetSchema()` function. It is classified
as:

```text
official-validator-runtime-error
```

Repeating the remaining 12 inputs in the same broken UI would not produce
independent schema evidence and was intentionally omitted.

## 4. Fallback Policy

The established Gate-B policy permits:

```text
official browser result
OR
exact unmodified official schemas executed with the engine version referenced
by the official validator page
```

The official UI produced no usable result, so the second path applies. Syntax
checks or custom schemas alone would not satisfy this gate.

## 5. Fresh Official Assets

The official page, all four schemas and AJV were downloaded anew after the UI
attempt.

The current page references:

```text
AJV 6.10.2
https://cdnjs.cloudflare.com/ajax/libs/ajv/6.10.2/ajv.min.js
```

It constructs schema URLs below:

```text
/assets/files/validation/<type>Schema.json
```

| Official artifact | SHA-256 |
|---|---|
| validator page | `9e4ba1a35d8da4407272b3439b5e9af7519879b96519e97835f5b10e873f6622` |
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| `formSchema.json` | `b06a1090d42e42d703e3b97bebb00f1706b4f33cf8e85781e62e154cddfe52f7` |
| `localeSchema.json` | `fe013b9036f1c29f9ec76f02f760168fb63b58b4ad035529d9fbd0d50b48f3b2` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

All hashes equal the accepted fresh asset set from step 260. No official
schema, page or engine drift was observed.

## 6. Exact Published Inputs

### Library and modules

| Input | SHA-256 |
|---|---|
| `library.json` | `b111d9ab24cf24a399be59ff97ca04d096ba46eec29033f597231c3dfb8b1d3b` |
| `NavimowAccount/module.json` | `59b36f3c8c0a27b35932104dcad0a77fecfa934c18911712bda2f1ac2ddb6e76` |
| `NavimowConfigurator/module.json` | `695daf986909c1c6c0e896a949292ad1f2e3bb0e208444f6fa2dd68b9b3dc521` |
| `NavimowDevice/module.json` | `70d33d21f6aff75071cd0879d32157ee4497a82d61d3365f4392e7011ce7d449` |
| `NavimowMqttReceiver/module.json` | `0fc4e78681b01cf69d522f2a89fe2389ca49d1ded19940fb79b3e46f82fea932` |

### Forms

| Input | SHA-256 |
|---|---|
| `NavimowAccount/form.json` | `92cd3b4712821c84213e26761f12ac7b26ea17b7b8b6ed812c9df135f785704a` |
| `NavimowConfigurator/form.json` | `fcd94c53e6928bccc104177aaf62dfcafb6ffb08b2523af593250e28597ea6ec` |
| `NavimowDevice/form.json` | `4f2a2c2f245784190de2a5363edeb021e8e223741a4e067484126fe8d1dca742` |
| `NavimowMqttReceiver/form.json` | `075c4285243d814c59e98fb6c635d35467d11c515a120cc1f5f352eb702e3dd0` |

### Locales

| Input | SHA-256 |
|---|---|
| `NavimowAccount/locale.json` | `fe12e326c77bcef5fab060aa117f4f85389177b564ec57723818e75a2fadd4a9` |
| `NavimowConfigurator/locale.json` | `82bd51d7b58e245180421c11825c224cdd73228696ef3c6f48dbeb0a9da9d008` |
| `NavimowDevice/locale.json` | `ad13b09de2b716b163a697a96533c835a8192cb4ab62936de666618e8aac5e3a` |
| `NavimowMqttReceiver/locale.json` | `255ed850692cd06ce4041cff4b9f0882784f0cbcc0e883cfe381ccc9f2535785` |

All 13 hashes equal the previous metadata-conformant publication because the
status correction changed PHP only.

## 7. Exact-Schema Execution

AJV `6.10.2` executed with:

```text
allErrors: true
```

Validation matrix:

| Schema | Inputs | Passed | Failed |
|---|---:|---:|---:|
| library | 1 | 1 | 0 |
| module | 4 | 4 | 0 |
| form | 4 | 4 | 0 |
| locale | 4 | 4 | 0 |
| **Total** | **13** | **13** | **0** |

Every per-file error array is empty. The machine-readable result binds every
input, schema and engine by SHA-256.

```text
validation-results SHA-256:
ac87422be716191690df451ae0da657f68f36f42bc8582189d5989ee89b4eaf3
```

## 8. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
Account variables:             6
Device variables:              8
Archive Control contracts:     5
pilot summary format:          1
pilot summary maximum:         16384 bytes
```

Metadata validation neither installs nor activates the published PHP change.

## 9. Private Evidence

Machine-readable evidence is retained below:

```text
private/navimow-capture/output/
  navimow-account-status-correction-metadata-conformance/
```

It contains:

- freshly downloaded official assets;
- exact AJV execution results;
- the official UI classification;
- commit-bound evidence closure.

The evidence contains no credential, token, ObjectID, hostname, private MQTT
topic, payload, device identity or garden data.

## 10. Architecture Decisions

### AD-NAV-1069: Bind Gate B to the corrective published commit

Clean local and remote equality proves all 13 inputs belong to `eda4945`.

### AD-NAV-1070: Attempt the official UI before fallback

Fallback is used only after the exact public input reproduces the validator's
own runtime defect.

### AD-NAV-1071: Separate cookie state from validator failure

Available controls and a triggered validation prove the visible cookie state
did not block this run.

### AD-NAV-1072: Stop repeated submissions after deterministic UI failure

The missing `$` dependency blocks schema initialization independently of input
type, so another 12 UI submissions would add no valid evidence.

### AD-NAV-1073: Refresh every official fallback asset

Prior assets are comparison evidence only. Current page, schemas and AJV were
downloaded again before execution.

### AD-NAV-1074: Require complete 13-input validation

Library, module, form and locale inputs all belong to the metadata gate.

### AD-NAV-1075: Keep corrective installation separate

Metadata conformance authorizes no Module Control operation or MQTT lifecycle
change.

## 11. Safety Result

This step performed:

```text
repository commits:      0
repository pushes:       0
metadata changes:        0
Symcon reads:            0
Symcon mutations:        0
MC_UpdateModule():       0
MC_ReloadModule():       0
MQTT activations:        0
credential requests:     0
service restarts:        0
mower commands:          0
```

## 12. Gate Status

| Gate | Status |
|---|---|
| exact published commit binding | PASS |
| official validator submission | PASS |
| official rendered result | TOOL RUNTIME ERROR |
| fresh official assets | PASS |
| exact-schema fallback | PASS, 13/13 |
| Gate B metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| Gate C corrective Symcon update | CLOSED |
| Gate D evidence closure | CLOSED |
| MQTT staging or activation | CLOSED |

## 13. Next Step

Proceed with:

```text
269-navimow-account-status-correction-disabled-symcon-update.md
```

That step may execute the bounded `101`-to-`102` corrective update only after
separate authorization. It must keep MQTT disabled and credential-free, use
exactly one `MC_UpdateModule()`, prohibit `MC_ReloadModule()` and perform no
explicit `IPS_ApplyChanges()` call.
