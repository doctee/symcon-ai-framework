# SAEF Step 401: Mowing Analytics Contract Revision Correction And PR Re-Review

- **Date:** 2026-09-12
- **Status:** Blocking review finding corrected and verified offline
- **Pull request:** `#112`

## 1. Purpose

This step records the focused review of the step-400 candidate after initial
SAEF branch and pull-request publication. It resolves one correctness blocker
before merge and freezes the corrected standalone identity.

The initial pull-request head was:

```text
36eaa78bafce510760e87ac9f7c0132930418596
```

Both initial CI jobs passed, but green tests did not replace the required
content review.

## 2. Blocking Finding

The retained reducer revision was selected only by the accepted map geometry
key. Existing aggregate days and runs would therefore have remained in the
same revision after changing one of these calculation inputs:

- local-to-metre scale;
- cutting width;
- coverage raster size;
- statistics time zone;
- zone-to-task binding; or
- configured subarea geometry.

That could mix incompatible area values or reinterpret old values under a new
physical or spatial contract. The behavior contradicted the stated
revision-bound analytics contract and blocked merge.

## 3. Correction

Each retained analytics revision now carries a deterministic contract key that
binds:

```text
algorithm version
accepted geometry key
statistics time zone
metres per local unit
cutting width
coverage cell size
sorted zone bindings
sorted subarea identities, parent zones and rings
```

Update and projection require the same contract key. A bound-input change
creates a separate bounded revision instead of merging with or presenting
older aggregates. Labels and 7/14-day display thresholds remain outside the
key because changing them does not alter retained measurements.

The reducer also rejects a zone-binding list that does not exactly match the
accepted scene and now rejects non-string time-zone input directly.

## 4. Regression Evidence

The focused tests prove that:

- replaying an identical retained scene remains idempotent;
- changing cutting width creates a second revision and preserves the original
  projection unchanged;
- changing a subarea polygon creates another revision;
- a mismatched zone binding is rejected;
- a non-string time zone is rejected;
- the Device integration supplies the exact current zone-binding contract;
- candidate and distribution behavior remain equivalent; and
- the generated standalone distribution is current.

PHPCS, focused PHPStan and the complete repository check pass. CI must pass
again on the corrected pull-request head before merge.

## 5. Corrected Candidate Identity

```text
fileCount:         47
filesetSha256:      af05215075f633f87ab1a7cda5d51a85d1a15db135214089e656af3284748aa6
publicationSha256: f356fdbe03de8e4c3233227f1fdb6446dc826d07a95884582ba67067f20c0d7e
```

## 6. Architecture Decision

### AD-NAV-401-01: Bind retained values to every interpretation input

Persisted measurements may outlive configuration. Any input that changes how
positions become distance, date buckets or area must therefore participate in
the retained revision identity. Presentation-only inputs remain mutable
without duplicating measurements.

## 7. Gate Result

| Gate | Status |
|---|---|
| Blocking correctness finding | RESOLVED |
| Focused functional and static checks | PASS |
| Corrected complete repository check | PASS |
| Corrected PR CI | REQUIRED before merge |
| Merge | CLOSED until both checks pass |
| Standalone publication and all live gates | CLOSED |
