# 232 Native MQTT Pilot Diagnostics Metadata Conformance

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed through the established exact-official-schema
fallback; disabled Symcon update and MQTT activation remain closed
**Date:** 2026-07-30
**Scope:** Validate metadata, form and locale files for published commit
`793249e`

## 1. Purpose

Step 231 published:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     793249ece1c0944192ea28dade7ecd2340a5135f
```

Step 230 requires an independent metadata-conformance gate before any disabled
Symcon update.

This step:

1. binds the validation to the exact published commit;
2. attempts to obtain the official browser validator;
3. retrieves the current official validator page and referenced assets;
4. executes the established exact-schema fallback;
5. extends validation to forms and locales because both changed in step 231;
6. closes Gate B without accessing Symcon.

No repository, Symcon instance, MQTT transport, service or mower was mutated.

## 2. Candidate Integrity

The standalone checkout remained:

```text
HEAD:        793249ece1c0944192ea28dade7ecd2340a5135f
origin/main: 793249ece1c0944192ea28dade7ecd2340a5135f
branch:      main
worktree:    clean
```

The five core metadata hashes remain equal to the previously validated set.
The changed Account form and locale are bound separately to the exact
published hashes.

## 3. Official UI Attempt

The official page is:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/
sdk-tools/tools/module-validator/
```

The browser-control runtime available to this session reported:

```text
available browser backends: none
```

Therefore:

| Signal | Result |
|---|---|
| public page retrieval | PASS |
| interactive official browser run | NOT EXECUTED |
| public metadata entered | NO |
| validator result rendered | NO |
| official success claimed | NO |
| candidate schema failure observed | NO |

This is classified as:

```text
browser-control-unavailable
```

It is not classified as a module failure or an official UI validation success.

## 4. Current Official Page

The current page was retrieved read-only and retained privately.

It still references:

```text
AJV 6.10.2
https://cdnjs.cloudflare.com/ajax/libs/ajv/6.10.2/ajv.min.js
```

It constructs schema URLs as:

```text
/assets/files/validation/<selected>Schema.json
```

The current source still contains `$` calls in:

```text
LoadData()
SetSchema()
Validate()
SetOutput()
```

The page source alone does not prove the runtime failure because no interactive
browser run occurred. It does prove that the previously identified code path
is still present.

Official page SHA-256:

```text
9e4ba1a35d8da4407272b3439b5e9af7519879b96519e97835f5b10e873f6622
```

## 5. Exact Fallback Policy

Step 204 established:

```text
official browser result
OR
exact unmodified official schemas executed with the engine version referenced
by the official validator page
```

The official UI was unavailable in this session, so no browser result is
substituted or invented. Gate B uses the second established evidence path and
records the limitation explicitly.

A syntax-only custom validator would not satisfy this gate.

## 6. Current Official Assets

All assets were downloaded anew:

| Artifact | SHA-256 |
|---|---|
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| `formSchema.json` | `b06a1090d42e42d703e3b97bebb00f1706b4f33cf8e85781e62e154cddfe52f7` |
| `localeSchema.json` | `fe013b9036f1c29f9ec76f02f760168fb63b58b4ad035529d9fbd0d50b48f3b2` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

The library, module and engine hashes remain equal to step 218. Form and locale
schemas are additionally frozen because those productive files changed in
step 231.

## 7. Published Input Binding

Core metadata:

| Input | SHA-256 |
|---|---|
| `library.json` | `b111d9ab24cf24a399be59ff97ca04d096ba46eec29033f597231c3dfb8b1d3b` |
| `NavimowAccount/module.json` | `59b36f3c8c0a27b35932104dcad0a77fecfa934c18911712bda2f1ac2ddb6e76` |
| `NavimowConfigurator/module.json` | `695daf986909c1c6c0e896a949292ad1f2e3bb0e208444f6fa2dd68b9b3dc521` |
| `NavimowDevice/module.json` | `70d33d21f6aff75071cd0879d32157ee4497a82d61d3365f4392e7011ce7d449` |
| `NavimowMqttReceiver/module.json` | `0fc4e78681b01cf69d522f2a89fe2389ca49d1ded19940fb79b3e46f82fea932` |

Changed presentation inputs:

| Input | SHA-256 |
|---|---|
| `NavimowAccount/form.json` | `92cd3b4712821c84213e26761f12ac7b26ea17b7b8b6ed812c9df135f785704a` |
| `NavimowAccount/locale.json` | `fe12e326c77bcef5fab060aa117f4f85389177b564ec57723818e75a2fadd4a9` |

The private evidence records hashes for all remaining module forms and locales.

## 8. Exact-Schema Execution

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

Every error array is empty.

This extends the earlier five-input fallback and directly validates the two
published presentation-file changes.

## 9. Evidence

Private machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-metadata-conformance/
```

Contents:

```text
assets/module-validator.html
assets/librarySchema.json
assets/moduleSchema.json
assets/formSchema.json
assets/localeSchema.json
assets/ajv-6.10.2.min.js
validation-results.json
evidence-closure.json
```

The evidence contains no credential, device identity, MQTT topic, payload,
coordinate, installation ObjectID or private host.

## 10. Safety Result

This step:

- did not change or publish repository content;
- did not access Symcon;
- did not call `MC_UpdateModule()` or `MC_ReloadModule()`;
- did not retrieve OAuth or MQTT credentials;
- did not activate or publish MQTT;
- did not restart a service;
- did not send a mower command.

Published commit `793249e` is not yet claimed as installed.

## 11. Architecture Decisions

### AD-NAV-842: Do not invent an official UI result

Unavailable browser control is recorded as `NOT EXECUTED`, not as validator
success, validator failure or module failure.

### AD-NAV-843: Re-execute current official assets

The fallback uses freshly downloaded official schemas and engine, not merely
the successful result from step 218.

### AD-NAV-844: Extend conformance to form and locale schemas

Because the publication changed Account `form.json` and `locale.json`, all
four forms and locales are validated alongside the existing metadata set.

### AD-NAV-845: Keep the disabled update gate separate

Metadata conformance authorizes no Symcon update, MQTT activation or service
restart.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| exact published commit binding | PASS |
| official page retrieval | PASS |
| official interactive UI | NOT EXECUTED |
| exact current official assets | VERIFIED |
| 13 exact published inputs | PASS |
| Gate B metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| Gate C disabled Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| mower command | PROHIBITED |

## 13. Next Step

After explicit Gate-C authorization, proceed with:

```text
233-native-mqtt-pilot-diagnostics-disabled-symcon-update.md
```

Recommended authorization:

```text
Symcon-Update auf die native MQTT-Pilotdiagnostik mit deaktiviertem MQTT
freigegeben.
```

That step may execute exactly one `MC_UpdateModule()` and zero
`MC_ReloadModule()` calls, then verify the new diagnostics read-only while MQTT
remains disabled and credential-free.
