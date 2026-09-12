# SAEF Step 403: Analytics Number Spinner Metadata Correction

- **Date:** 2026-09-12
- **Status:** Metadata blocker corrected and verified offline; new publication
  authorization and live gates remain closed
- **Scope:** Navimow Device configuration form only

## 1. Purpose

This step records the metadata gate executed after the HTML SDK Safari
correction was published to the standalone module repository. It captures a
real official-schema failure, the resulting live-rollout stop and the minimum
compatible correction.

No Symcon module update, instance configuration change, MQTT action, OAuth
action, Archive mutation, restart or mower command was performed after the
failure.

## 2. Published Baseline

The verified standalone publication before this correction was:

```text
repository: doctee/symcon-navimow
branch: main
commit: ab73650fecee5f523e50556e74e15e880778a7a3
file count: 47
fileset SHA-256: 17f22de4d78500c135983fcb4ce00dc2ec7ad25b636021d55d92595ed565bc69
```

The generic publisher verified the complete post-merge tree against the SAEF
candidate.

## 3. Validator Result

The official Symcon Module Validator page loaded successfully, but its runtime
failed before producing a candidate result because the page dependency `$` was
undefined. The UI failure is classified separately from module metadata.

The established fallback then used freshly downloaded, unmodified official
schemas and the AJV 6.10.2 engine referenced by the validator page. Twelve of
the thirteen metadata inputs passed. `NavimowDevice/form.json` failed because
the official `NumberSpinner` schema requires integer `minimum` and `maximum`
metadata while these two controls declared fractional minima:

```text
MetersPerLocalUnit:       0.001
CoverageCellSizeMeters:   0.01
```

The affected properties remain floating-point IP-Symcon properties. The defect
concerns only the form metadata bounds.

## 4. Correction

Both decimal controls retain their precision through `digits` and now use the
schema-compatible integer lower bound `0`. Their existing integer maxima are
unchanged.

The analytics reducer continues to require both values to be finite and
strictly greater than zero. A zero or negative value therefore remains invalid
at the productive calculation boundary. Existing valid installation values,
including fractional values, are not changed or rounded.

A focused regression assertion freezes the schema-compatible spinner metadata
for both fields.

The corrected generated candidate is bound as follows:

```text
source base commit:   b64f07b4d21ba87fb70f34d721ea8a66b3f2d763
file count:           47
fileset SHA-256:      82a66fd6b03a2746005d4f01d7ecf55d5fbb88aee4e4c52399d5660fcb931c87
publication SHA-256:  5f5ef8bbc36819ec0d2cc783bcd0a087a992c6353bc18204c8bc0f7533100b34
```

## 5. Architecture Decisions

### AD-NAV-403-01: Keep form bounds and runtime validity distinct

The form uses the strongest lower bound expressible by the official metadata
schema. Productive validity remains enforced by the existing typed reducer,
which is the authoritative calculation boundary.

### AD-NAV-403-02: Stop the live rollout on metadata failure

A successful repository publication does not override a later independent
metadata rejection. The Symcon update remains closed until a corrected
candidate passes all thirteen official-schema inputs and completes the normal
publication sequence.

## 6. Gate Result

| Gate | Status |
|---|---|
| Standalone HTML SDK correction publication | PASS |
| Official validator UI execution | UNAVAILABLE, page runtime failure |
| Initial official-schema fallback | FAIL, 12/13 |
| Corrected official-schema fallback | PASS, 13/13 |
| Focused Navimow validation | PASS |
| Complete `make check` | PASS |
| Corrected SAEF publication | READY, exact publication gate required |
| Corrected standalone publication | CLOSED |
| Symcon update and Safari/iPad validation | CLOSED |

The previous publication approval was consumed by the candidate with fileset
`17f22de4d78500c135983fcb4ce00dc2ec7ad25b636021d55d92595ed565bc69`.
It does not authorize publication of the corrected fileset above.
