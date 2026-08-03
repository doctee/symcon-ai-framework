# HS-native Feedback Matcher

**Gate:** Repository implementation and offline regression
**Result:** PASSED — LIVE ACTIVATION NOT ATTEMPTED
**Date:** 2026-07-27
**Live impact:** None

## Scope

This change implements the format-specific feedback rule derived in the
preceding CL-020/CL-021 conversion analysis. It changes only the SAEF
ControlLight candidate and its generated inactive fileset artifacts.

No live source, Symcon object, module configuration or device value was
changed. No `RequestAction()` call or device test was attempted.

## Contract

Targets configured as `HS_ARRAY_STRING` now confirm authoritative color
feedback in native hue/saturation coordinates:

- hue distance is circular across the zero/360-degree boundary;
- hue and saturation have separate finite configuration bounds;
- both bounds default to 0.5;
- configuration rejects either bound above 5;
- hue is ignored only while both values remain inside the configured
  achromatic saturation boundary; and
- malformed, incomplete, non-numeric or out-of-domain target values fail
  closed.

The local `~HexColor` value remains the canonical full-value RGB projection of
the confirmed chromaticity. DIMMER remains the independent intensity contract.

`INT_HEX`, `RGB_ARRAY_STRING` and `RGB_OBJECT_STRING` retain exact canonical
RGB equality. The change therefore does not weaken the Z2M CL-021 boundary and
cannot accept its observed xy/brightness-induced color shift.

## Configuration

The normalized candidate adds:

```php
'colorHueToleranceDegrees' => 0.5,
'colorSaturationTolerancePercentagePoints' => 0.5,
```

The fields are explicit in the wrapper template, but their defaults make the
fileset backward-compatible with existing normalized wrapper configurations.
They affect confirmation only when `colorTargetFormat` is
`HS_ARRAY_STRING`.

## Artifact Identity

- candidate core SHA-256:
  `e14c11f41c0dc2513007145fb9a83ccd771476c6036419539d638ee4163f1a5a`
- generated bootstrap SHA-256:
  `92f980d6fe7be4a6f6fd17b33cabb8478ab3f6e87af8d3d9505abbacf7fc9db6`
- generated fileset identity:
  `36bbbece4dc5751423915bd999f1fb9cd0c28df7112866bbfd3cefd26f65393b`

## Regression

The focused suites prove:

- the exact observed CL-020 feedback is accepted;
- deviations beyond either bound are rejected;
- all six representative hue sectors pass bounded normalization;
- hue wrap-around is circular;
- hue is not trusted outside the achromatic boundary;
- exact-boundary values pass and over-boundary values fail;
- invalid HS JSON and domains are rejected;
- the CL-021 observation remains rejected; and
- all sanitized installed instance contracts still normalize.

The focused suites and the complete `composer check` repository gate passed.
The generated ControlLight fileset must remain inactive until a separate live
gate is approved.

## Next Gate

Prepare a CL-020-only, hash-locked activation package with:

1. the current legacy wrapper and target/consumer snapshot;
2. deterministic fileset and wrapper rollback;
3. two command-free reconciliation runs;
4. a supervised color test that checks native HS confirmation and verifies
   that brightness did not move; and
5. exact restoration followed by Alexa and scene color/brightness regression.

CL-021 is outside that gate and remains color-disabled.
