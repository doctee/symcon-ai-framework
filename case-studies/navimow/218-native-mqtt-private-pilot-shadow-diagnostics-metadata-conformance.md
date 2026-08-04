# 218 Native MQTT Private Pilot Shadow Diagnostics Metadata Conformance

**Case study:** Navimow native IP-Symcon module
**Status:** Gate B passed through the established exact-official-schema
fallback; disabled Symcon update and all pilot gates remain closed
**Date:** 2026-07-29
**Scope:** Validate metadata for the exact published version-2 MQTT-shadow
diagnostic commit

## 1. Purpose

Step 217 published:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     3d223a9c24e396d4ba55ca40aede6742592fbe8f
```

Step 216 requires metadata conformance before a disabled Symcon update.

This step:

1. attempts the official Symcon Module Validator;
2. classifies the rendered behavior;
3. applies the step-204 fallback if the known UI defect recurs;
4. validates the exact published metadata with the exact official schemas and
   validator-referenced engine;
5. closes Gate B without accessing Symcon.

No repository, module installation, MQTT transport, service or mower was
mutated.

## 2. Candidate Integrity

The standalone checkout remained:

```text
HEAD:        3d223a9c24e396d4ba55ca40aede6742592fbe8f
origin/main: 3d223a9c24e396d4ba55ca40aede6742592fbe8f
branch:      main
worktree:    clean
```

The metadata did not change in step 217. Only
`NavimowAccount/module.php` changed between the publication baseline and the
published commit.

## 3. Official Validator Attempt

The official page was opened at:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/
sdk-tools/tools/module-validator/
```

The exact published `library.json` was entered with the selected
`library.json` file type and `Validieren` was invoked.

The page rendered no successful or failed schema result.

Browser errors:

```text
ReferenceError: $ is not defined
  at SetSchema

ReferenceError: $ is not defined
  at SetOutput
  at Validate
```

Classification:

| Signal | Result |
|---|---|
| official page load | PASS |
| official input control | PASS |
| public metadata submission | PASS |
| `SetSchema()` | FAIL |
| `SetOutput()` | FAIL |
| schema result rendered | NO |
| candidate schema failure observed | NO |
| official UI success established | NO |

The failure signature is identical to steps 203 and 204. It occurs in the
validator page before a usable result can be rendered.

## 4. Fallback Policy

Step 204 established:

```text
official browser result
OR
exact unmodified official schemas executed with the engine version referenced
by the official validator page
```

The fallback must bind:

- exact published commit;
- exact metadata inputs;
- exact schema hashes;
- exact engine version and hash;
- complete per-file results.

A custom syntax-only validator is not sufficient.

## 5. Current Official Assets

The current validator page references:

```text
AJV 6.10.2
https://cdnjs.cloudflare.com/ajax/libs/ajv/6.10.2/ajv.min.js
```

It constructs schema URLs as:

```text
/assets/files/validation/<selected>Schema.json
```

The exact current public assets were downloaded read-only:

| Artifact | SHA-256 |
|---|---|
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

These hashes equal the assets used in step 204. No schema or engine drift was
observed.

## 6. Exact Published Inputs

| Input | SHA-256 |
|---|---|
| `library.json` | `b111d9ab24cf24a399be59ff97ca04d096ba46eec29033f597231c3dfb8b1d3b` |
| `NavimowAccount/module.json` | `59b36f3c8c0a27b35932104dcad0a77fecfa934c18911712bda2f1ac2ddb6e76` |
| `NavimowConfigurator/module.json` | `695daf986909c1c6c0e896a949292ad1f2e3bb0e208444f6fa2dd68b9b3dc521` |
| `NavimowDevice/module.json` | `70d33d21f6aff75071cd0879d32157ee4497a82d61d3365f4392e7011ce7d449` |
| `NavimowMqttReceiver/module.json` | `0fc4e78681b01cf69d522f2a89fe2389ca49d1ded19940fb79b3e46f82fea932` |

## 7. Exact-Schema Results

AJV `6.10.2` executed:

- `librarySchema.json` against `library.json`;
- `moduleSchema.json` separately against all four `module.json` files;
- `allErrors: true`.

Results:

| Published input | Result | Errors |
|---|---|---|
| `library.json` | PASS | `[]` |
| `NavimowAccount/module.json` | PASS | `[]` |
| `NavimowConfigurator/module.json` | PASS | `[]` |
| `NavimowDevice/module.json` | PASS | `[]` |
| `NavimowMqttReceiver/module.json` | PASS | `[]` |

All five exact published metadata inputs conform.

## 8. Evidence

Private machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-private-pilot-shadow-diagnostics-metadata-conformance/
  evidence-closure.json
```

The same private evidence directory retains the exact downloaded public schema
and engine assets used by the fallback.

The evidence contains no credential, device identity, MQTT topic, payload,
coordinate, installation ObjectID or private host.

## 9. Safety Result

This step:

- did not change metadata;
- did not commit or push;
- did not access Symcon;
- did not call `MC_UpdateModule()` or `MC_ReloadModule()`;
- did not retrieve OAuth or MQTT credentials;
- did not activate MQTT;
- did not restart a service;
- did not send a mower command.

The last accepted installed state remains `main@8fdab84b`, disabled and
credential-free. The published commit is not yet claimed as installed.

## 10. Architecture Decisions

### AD-NAV-789: Do not classify a blank UI result as validation

**Decision:** Record the official browser run as inconclusive.

**Reason:** The page failed in its own `SetSchema()` and `SetOutput()` functions
before rendering a schema result.

### AD-NAV-790: Re-execute the exact fallback

**Decision:** Download the currently referenced official assets and validate
all exact published inputs again.

**Reason:** Prior success alone would not prove the new published commit or
exclude asset drift.

### AD-NAV-791: Accept unchanged official asset hashes

**Decision:** Treat equality with step-204 schema and engine hashes as confirmed
continuity, while still executing validation anew.

**Reason:** Both provenance and current per-input results are required.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| official validator page load | PASS |
| official browser UI | INCONCLUSIVE TOOL DEFECT |
| exact official schemas and engine | VERIFIED |
| all five metadata inputs | PASS |
| Gate B metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| Gate C disabled Symcon update | CLOSED |
| inactive pilot preflight | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| mower command | PROHIBITED |

## 12. Next Step

After explicit Gate-C authorization, proceed with:

```text
219-native-mqtt-private-pilot-shadow-diagnostics-symcon-update.md
```

Recommended authorization:

```text
Symcon-Update auf die MQTT-Shadow-Diagnostik v2 mit deaktiviertem MQTT
freigegeben.
```

That step may execute exactly one `MC_UpdateModule()` and zero
`MC_ReloadModule()` calls, then verify the version-2 empty shadow diagnostics
read-only. It must leave MQTT disabled and credential-free.
