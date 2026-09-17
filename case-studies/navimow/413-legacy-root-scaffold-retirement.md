# SAEF Step 413: Legacy Root Scaffold Retirement

- **Date:** 2026-09-17
- **Status:** Local implementation and complete repository validation passed;
  SAEF publication remains separate
- **Scope:** Remove the superseded repository-root Navimow scaffold while
  retaining its useful sanitized REST fixture contracts in the canonical test
  suite

## 1. Purpose

The first Navimow MVP created an installable-looking module tree at the SAEF
repository root. Later work established
`case-studies/navimow/distribution/` as the canonical module source and the
manifest-driven standalone publication to `doctee/symcon-navimow`.

Keeping both trees made repository browsing ambiguous. The root scaffold was
not a publication source, differed materially from the current implementation
and was referenced only by its own early fixture test.

## 2. Retired Artifacts

This step removes exactly these historical implementation artifacts:

```text
library.json
library/Navimow/
modules/NavimowAccount/
modules/NavimowConfigurator/
modules/NavimowDevice/
tests/Navimow/payload-mapper-fixtures.php
```

The removal covers 17 files. It does not modify the canonical distribution,
generated module fileset, publication contract or standalone repository.

## 3. Fixture Contract Migration

The old test executed the obsolete root `PayloadMapper` and was not part of
the Composer `check` suite. Its successful result therefore did not validate
the published module.

Before removing it, the still useful assertions were migrated to
`case-studies/navimow/tests/rest-client-auth.php`. The canonical distribution
mapper now directly validates the sanitized captures for:

- successful OAuth token shape;
- mower discovery fields;
- Docked status and battery percentage;
- Running status and battery percentage;
- invalid-token reauthentication classification;
- Dock `alreadyInState`; and
- fail-closed mapping of an unknown vehicle state.

The sanitized fixtures remain under `case-studies/navimow/fixtures/rest/`.

## 4. Historical Documentation

Dated steps 10 through 17 and later evidence reports may still name the old
root paths because those paths were correct when the reports were written.
They remain historical evidence and are not rewritten to imply a different
past repository state.

Current implementation and publication decisions must use the distribution,
fileset and publication contracts instead of those historical paths.

## 5. Architecture Decisions

### AD-NAV-413-01: Keep one productive source tree

**Decision:** Treat `case-studies/navimow/distribution/` as the only canonical
Navimow module source in SAEF.

**Reason:** The manifest already maps every productive module file from that
tree. A second incomplete root tree has no runtime or publication role.

### AD-NAV-413-02: Preserve evidence, not obsolete execution

**Decision:** Retain the sanitized REST fixtures and move their relevant
assertions to the canonical test instead of retaining an isolated legacy test.

**Reason:** Captured payload structure remains useful evidence. Executing it
against code that cannot be published creates misleading assurance.

### AD-NAV-413-03: Do not republish an unchanged module

**Decision:** Keep standalone publication and live Symcon gates closed.

**Reason:** The productive 47-file publication candidate is byte-unchanged by
this repository-only cleanup.

## 6. Verification

The following checks passed from the isolated workstream:

- migrated REST fixture and authentication tests;
- Navimow distribution validation;
- Navimow generated-fileset validation;
- Navimow publication-contract validation;
- complete `make check`, including PHPStan and PHPCS; and
- absence of executable references to the removed paths outside historical
  documentation.

The unchanged publication identity remains:

```text
file count:          47
fileset SHA-256:     751310813282d9c4108d5eacc136720c0ed39005b655492a4c58815de45ccf17
publication SHA-256: ad84079cc886e0dedc7815f48d2337faca9009bf13c79550374e2fdd47036509
```

## 7. Gate Result

| Gate | Status |
|---|---|
| Useful fixture contracts migrated | PASS |
| Legacy root scaffold removed | PASS |
| Canonical distribution unchanged | PASS |
| Full repository validation | PASS |
| Standalone publication required | NO |
| Symcon update required | NO |
| SAEF branch publication | OPEN |

## 8. Next Step

Review and publish this bounded repository cleanup through the normal SAEF
pull-request workflow. No standalone Navimow publication or live Symcon action
is needed afterward.
