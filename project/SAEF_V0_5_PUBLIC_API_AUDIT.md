# SAEF v0.5 Public API Audit

**Baseline:** `v0.4.0`
**Target:** `v0.5.0`
**Audit date:** 2026-09-16
**Result:** PASS

## API Boundary

The supported API is the function and constant inventory documented in
`helpers/README.md` and enforced by `tests/helpers/public-api.php`. Deployment
operations, case-study classes, generated scripts, `@internal` functions and
declaration guards remain outside that API.

## Contract Delta

Both v0.4 and the v0.5 release candidate contain 30 public functions, three
public constants and ten internal functions. A declaration diff across
`helpers/` contains no public function or constant change after `v0.4.0`.

No compatibility shim, migration layer or deprecation is required.

## Behavioral Assessment

The v0.5 changes are outside the helper API:

- MQTT latest-command-wins is private to the exporter runtime and composes the
  existing Registry and Statistics helpers;
- workstream and Composer guardrails are repository tooling;
- Channel-v8, approval and child-process changes are deployment contracts;
- case-study module changes do not export helper symbols; and
- Open-Meteo calibration policy remains case-study implementation behavior.

Existing helper semantics, required parameters, defaults, return types and
public constants are unchanged.

## Verification

- `tests/helpers/public-api.php` reports 30 public functions, three public
  constants and ten internal functions.
- bundle and fileset checks validate complete generated symbol inventories;
- MQTT concurrency tests exercise supersession without a public abstraction;
  and
- the complete repository check passed in the release-candidate workstream.

The unchanged API is compatible with a minor `v0.5.0` release.
