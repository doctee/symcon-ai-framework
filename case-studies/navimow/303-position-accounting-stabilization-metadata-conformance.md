# 303 Position Accounting Stabilization Metadata Conformance

**Case study:** Navimow native IP-Symcon module

**Status:** Metadata gate passed by fresh official-asset and published-input
byte equivalence

**Date:** 2026-08-09

## 1. Result

All 13 metadata inputs of exact standalone commit
`50b365200e0c5c55990214c31f4a46f28b1406c7` are byte-equal to the prior
executed 13/13 official-schema fallback pass.

The official validator page, all four official schemas and AJV 6.10.2 were
downloaded freshly. Every asset is byte-equal to that same executed evidence
set. Therefore both validator inputs are identical:

```text
published metadata inputs: 13 / 13 equal
official validator assets:  6 / 6 equal
prior executed result:      13 passed, 0 failed
current deterministic gate: PASS BY BYTE EQUIVALENCE
```

## 2. Interactive Validator Classification

The interactive browser surface was unavailable in the current execution
session. No official Webvalidator success or candidate rejection is claimed.

The embedded JavaScript runtime also rejected AJV's dynamic code generation.
This is an execution-environment restriction, not schema evidence. The gate
therefore relies on the stronger byte-equivalence proof to the previously
executed official-schema result.

## 3. Scope Reasoning

The standalone publication changed PHP only. No `library.json`, `module.json`,
`form.json` or `locale.json` changed. Fresh official assets also showed no
drift. Re-executing identical deterministic inputs would not add distinct
schema evidence.

## 4. Architecture Decisions

### AD-NAV-1269: Do not misclassify unavailable tooling

An unavailable browser or restricted JavaScript runtime is neither a validator
success nor a candidate failure and must be reported separately.

### AD-NAV-1270: Accept deterministic byte-equivalence only end to end

Equivalence is sufficient only because every published metadata input, every
official schema, the validator page and the exact AJV engine bytes match a
previously executed passing set.

### AD-NAV-1271: Keep metadata validation live-system free

Metadata conformance performs no Symcon access, credential retrieval, OAuth
action, MQTT activation or mower command.

## 5. Gate State

| Gate | Status |
|---|---|
| exact published commit | PASS |
| fresh official assets | PASS, unchanged |
| 13 published metadata inputs | PASS, unchanged |
| prior executed schema result | PASS 13 / 13 |
| current metadata conformance | PASS BY BYTE EQUIVALENCE |
| disabled Symcon update | READY |

## 6. Next Gate

Perform one supported disabled Symcon module update with two equal read-only
preflights and immediate plus delayed post-update verification.
