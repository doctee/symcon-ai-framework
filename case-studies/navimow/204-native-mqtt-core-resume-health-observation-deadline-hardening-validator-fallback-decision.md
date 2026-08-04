# Native MQTT Core-Resume Deadline Hardening Validator Fallback Decision

**Date:** 2026-07-29
**Status:** Gate B passed through the established exact-official-schema
fallback; Gate C remains closed
**Scope:** Reassess the web-validator-only requirement from step 201 and close
Gate B without a Symcon or repository mutation

## 1. Decision Request

After the reproducible official web-validator failure recorded in step 203, the
user requested evaluation of whether a successful browser result was actually
required and authorized this formal decision:

```text
ok, so machen wir Schritt 204.
```

This decision does not authorize a Symcon update, MQTT activation, service
restart, MQTT publication or mower command.

## 2. External Classification

The original Symcon Community announcement describes the Module Validator as a
Beta tool comparable to JSON Lint:

```text
https://community.symcon.de/t/php-module-json-dateien-mit-dem-module-validator-ueberpruefen/51071
```

The current official tools overview describes the development tools as helpful
for Module Store development and says that the validator checks syntax and
required information:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/
```

The official structure documentation defines the required library and module
layout:

```text
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/struktur/
```

None of these sources makes a successful invocation of the browser UI a
normative prerequisite for installing, updating or reviewing a module. The
normative concern is valid structure and conforming metadata, not the
availability of one presentation layer.

## 3. SAEF Precedent

The Navimow case study already established the equivalent fallback:

| Step | Existing rule |
|---|---|
| 18 | exact official schemas executed with AJV `6.10.2` after the same `$` failure |
| 61 | official validator **or** previously adopted equivalent official schemas |
| 68 | official validator **or** established exact-schema fallback |
| 79 | official schemas and AJV `6.10.2` accepted while the browser validator failed |

Step 201's demand for a real successful browser result was therefore stricter
than the established SAEF evidence model without identifying an additional
technical property that only the browser UI could prove.

## 4. Exact Published Candidate

The fallback applies to the exact published state:

```text
repository: https://github.com/doctee/symcon-navimow
branch:     main
commit:     8fdab84bd2a2190a6025cedd11f1ae6248369c0e
```

The standalone worktree was clean. The Account implementation remained equal
to the frozen candidate:

```text
git blob SHA-1: c7d1dfeda3d6aa85841ff71859e81d2457398334
SHA-256:        6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f
```

Only that PHP implementation changed in step 202. The metadata under
validation remained unchanged.

## 5. Fallback Evidence

The public schemas and validation engine referenced by the current official
page were used without modification:

| Artifact | SHA-256 |
|---|---|
| `librarySchema.json` | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| `moduleSchema.json` | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| AJV `6.10.2` | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

Results:

| Published input | SHA-256 | Result |
|---|---|---|
| `library.json` | `b111d9ab24cf24a399be59ff97ca04d096ba46eec29033f597231c3dfb8b1d3b` | PASS |
| `NavimowAccount/module.json` | `59b36f3c8c0a27b35932104dcad0a77fecfa934c18911712bda2f1ac2ddb6e76` | PASS |
| `NavimowConfigurator/module.json` | `695daf986909c1c6c0e896a949292ad1f2e3bb0e208444f6fa2dd68b9b3dc521` | PASS |
| `NavimowDevice/module.json` | `70d33d21f6aff75071cd0879d32157ee4497a82d61d3365f4392e7011ce7d449` | PASS |
| `NavimowMqttReceiver/module.json` | `0fc4e78681b01cf69d522f2a89fe2389ca49d1ded19940fb79b3e46f82fea932` | PASS |

This is stronger than a custom syntax-only check: it executes the exact public
schemas with the exact engine version selected by the official page.

## 6. Web Failure Remains a Tool Defect

The web UI still produces no schema result because its own JavaScript calls an
undefined `$` in `SetSchema` and `SetOutput`. The failure was reproduced in the
in-app browser and independently in the user's local browser.

That evidence remains preserved in step 203. It is not reclassified as a
successful web run and is not erased by this decision.

## 7. Decision

The evidence requirement is restated as:

```text
official browser result
OR
exact unmodified official schemas executed with the engine version referenced
by the official validator page
```

The fallback must identify the exact candidate files, schema hashes, engine
version and complete results. A custom distribution validator alone remains
insufficient.

For the exact published commit, those conditions are met.

## 8. Gate Status

| Gate | Decision |
|---|---|
| Gate A standalone publication | PASS |
| Gate B metadata conformance | PASS through established exact-schema fallback |
| official browser UI | INCONCLUSIVE due reproducible tool defect |
| Gate C disabled Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |

**Gate B is complete.**

Gate C still requires the separate exact authorization from step 201:

```text
Symcon-Update auf die MQTT-Core-Resume-Deadline-Härtung mit deaktiviertem MQTT freigegeben.
```

## 9. Symcon Defect Handoff

The web-tool failure is useful upstream feedback even though it no longer
blocks this case study. A short German forum reply and a self-contained
technical correction proposal were prepared separately without publishing or
sending them.

Prepared artifacts:

| Artifact | Purpose |
|---|---|
| `forum/module-validator-referenceerror-forum-reply.md` | short reply for the existing community topic |
| `forum/module-validator-referenceerror-correction-proposal.md` | editable technical source |
| `output/pdf/symcon-module-validator-referenceerror-korrekturvorschlag.pdf` | visually verified four-page forum attachment |

The final PDF SHA-256 is:

```text
6648eaf51551acc4fbffc314401d093e5603b1b264c19222f635fd5618149c7c
```

The forum reply remains an unsent draft.
