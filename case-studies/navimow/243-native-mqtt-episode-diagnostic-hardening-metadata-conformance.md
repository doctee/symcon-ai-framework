# 243 Native MQTT Episode Diagnostic Hardening Metadata Conformance

**Case study:** Navimow native IP-Symcon module

**Status:** Gate B passed through the established exact-official-schema
fallback; disabled Symcon update and MQTT activation remain closed

**Date:** 2026-07-31

**Scope:** Validate all metadata, form and locale files for published commit
`79686e5`

## 1. Purpose

Step 242 published:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     79686e52f0bbaad77d37b9cd6e4b367797d96f2e
```

Step 241 requires an independent metadata-conformance gate before any disabled
Symcon update.

This step:

1. binds validation to the exact published commit;
2. executes the official browser validator as the preferred path;
3. records the validator's own runtime failure without treating it as a module
   failure;
4. retrieves the current official page, schemas and referenced AJV engine;
5. executes the established exact-official-schema fallback over all 13 inputs;
6. closes Gate B without accessing Symcon.

No public repository, productive module, Symcon instance, MQTT transport,
service or mower was mutated.

## 2. Candidate Integrity

The standalone checkout remained:

```text
HEAD:        79686e52f0bbaad77d37b9cd6e4b367797d96f2e
origin/main: 79686e52f0bbaad77d37b9cd6e4b367797d96f2e
branch:      main
worktree:    clean
```

The publication commit changes exactly:

```text
NavimowAccount/module.php
```

Its published Git blob remains:

```text
cfa3028861e7b6343bde41a36bc65c4fd7e19f82
```

No metadata, form or locale file changed in the publication commit.

## 3. Official UI Attempt

The official page is:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/
sdk-tools/tools/module-validator/
```

The page loaded successfully and exposed the expected:

- file-type selector;
- content input;
- optional file chooser;
- `Validieren` action.

The public `library.json` from the exact published commit was entered and
validation was triggered. The validator did not render a result. Its browser
runtime reported:

```text
ReferenceError: $ is not defined
```

The error occurred in the page's own `SetSchema()` and `SetOutput()` paths.

Therefore:

| Signal | Result |
|---|---|
| public page retrieval | PASS |
| interactive browser available | YES |
| validation triggered | YES |
| official result rendered | NO |
| official success claimed | NO |
| candidate schema failure observed | NO |

This is classified as:

```text
official-validator-runtime-error
```

It is not a failure of the Navimow metadata. The remaining 12 inputs were not
submitted to the broken UI because the same missing runtime dependency blocks
result rendering independently of input content.

## 4. Exact Fallback Policy

Step 204 established:

```text
official browser result
OR
exact unmodified official schemas executed with the engine version referenced
by the official validator page
```

The official UI produced no usable validation result. Gate B therefore uses
the second established evidence path and records the UI limitation explicitly.

A syntax-only or custom substitute validator would not satisfy this gate.

## 5. Current Official Assets

The official page and all validator assets were downloaded anew.

The page still references:

```text
AJV 6.10.2
https://cdnjs.cloudflare.com/ajax/libs/ajv/6.10.2/ajv.min.js
```

Asset hashes:

| Artifact | SHA-256 |
|---|---|
| validator page | `9e4ba1a35d8da4407272b3439b5e9af7519879b96519e97835f5b10e873f6622` |
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| `formSchema.json` | `b06a1090d42e42d703e3b97bebb00f1706b4f33cf8e85781e62e154cddfe52f7` |
| `localeSchema.json` | `fe013b9036f1c29f9ec76f02f760168fb63b58b4ad035529d9fbd0d50b48f3b2` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

These hashes equal step 232. The freshly retrieved official validator asset
set has not drifted.

## 6. Published Input Binding

Core metadata:

| Input | SHA-256 |
|---|---|
| `library.json` | `b111d9ab24cf24a399be59ff97ca04d096ba46eec29033f597231c3dfb8b1d3b` |
| `NavimowAccount/module.json` | `59b36f3c8c0a27b35932104dcad0a77fecfa934c18911712bda2f1ac2ddb6e76` |
| `NavimowConfigurator/module.json` | `695daf986909c1c6c0e896a949292ad1f2e3bb0e208444f6fa2dd68b9b3dc521` |
| `NavimowDevice/module.json` | `70d33d21f6aff75071cd0879d32157ee4497a82d61d3365f4392e7011ce7d449` |
| `NavimowMqttReceiver/module.json` | `0fc4e78681b01cf69d522f2a89fe2389ca49d1ded19940fb79b3e46f82fea932` |

Forms and locales:

| Input | SHA-256 |
|---|---|
| `NavimowAccount/form.json` | `92cd3b4712821c84213e26761f12ac7b26ea17b7b8b6ed812c9df135f785704a` |
| `NavimowConfigurator/form.json` | `fcd94c53e6928bccc104177aaf62dfcafb6ffb08b2523af593250e28597ea6ec` |
| `NavimowDevice/form.json` | `4f2a2c2f245784190de2a5363edeb021e8e223741a4e067484126fe8d1dca742` |
| `NavimowMqttReceiver/form.json` | `075c4285243d814c59e98fb6c635d35467d11c515a120cc1f5f352eb702e3dd0` |
| `NavimowAccount/locale.json` | `fe12e326c77bcef5fab060aa117f4f85389177b564ec57723818e75a2fadd4a9` |
| `NavimowConfigurator/locale.json` | `82bd51d7b58e245180421c11825c224cdd73228696ef3c6f48dbeb0a9da9d008` |
| `NavimowDevice/locale.json` | `ad13b09de2b716b163a697a96533c835a8192cb4ab62936de666618e8aac5e3a` |
| `NavimowMqttReceiver/locale.json` | `255ed850692cd06ce4041cff4b9f0882784f0cbcc0e883cfe381ccc9f2535785` |

## 7. Exact-Schema Execution

The exact downloaded AJV `6.10.2` asset executed with:

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
| **total** | **13** | **13** | **0** |

Every error array is empty. The machine-readable result binds each input and
schema by SHA-256.

## 8. Evidence

Private machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-episode-diagnostic-hardening-metadata-conformance/
```

Contents:

```text
assets/module-validator.html
assets/librarySchema.json
assets/moduleSchema.json
assets/formSchema.json
assets/localeSchema.json
assets/ajv-6.10.2.min.js
validate-official-assets.cjs
validation-results.json
evidence-closure.json
```

The evidence contains no credential, device identity, MQTT topic, payload,
coordinate, installation ObjectID or private host.

## 9. Safety Result

This step:

- did not change or publish the public module repository;
- did not change productive Navimow PHP or metadata;
- did not access Symcon;
- did not call `MC_UpdateModule()` or `MC_ReloadModule()`;
- did not retrieve OAuth or MQTT credentials;
- did not activate, connect or publish MQTT;
- did not send a REST live request;
- did not restart a service;
- did not send a mower command.

Published commit `79686e5` is not yet claimed as installed.

## 10. Architecture Decisions

### AD-NAV-892: Classify the official UI failure at the tool boundary

The reproducible `$ is not defined` error prevents result rendering. It is
recorded as a validator-tool runtime failure, not as candidate failure.

### AD-NAV-893: Stop repeated submissions after deterministic UI failure

One exact public input proves the input-independent runtime blocker. Repeating
the other 12 inputs cannot create valid official evidence and is omitted.

### AD-NAV-894: Re-execute all current official schemas

Gate B uses newly downloaded, unmodified schemas and the exact AJV version
referenced by the current official page over all 13 published inputs.

### AD-NAV-895: Keep the disabled update gate separate

Metadata conformance authorizes no Symcon update, MQTT staging or activation.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| exact publication binding | PASS |
| official browser execution | TOOL RUNTIME ERROR |
| current official asset retrieval | PASS |
| exact-official-schema fallback | PASS, 13/13 |
| private/public evidence closure | PASS |
| Gate B metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| Gate C disabled Symcon update | CLOSED |
| MQTT staging | CLOSED |
| MQTT activation | CLOSED |

## 12. Next Step

The next SAEF step is:

```text
244-native-mqtt-episode-diagnostic-hardening-disabled-symcon-update.md
```

It requires separate explicit authorization:

```text
Symcon-Update auf die MQTT-Episoden-Diagnosehärtung mit deaktiviertem MQTT
freigegeben.
```

The step must perform a fresh read-only preflight, one supported Module Control
update and immediate plus delayed disabled, credential-free compatibility
checks. It must not use `MC_ReloadModule()` and must not activate MQTT.
