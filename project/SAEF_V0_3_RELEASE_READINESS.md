# SAEF v0.3 Release Readiness

**Assessment date:** 2026-07-23
**Target:** `v0.3.0`
**Current decision:** RELEASED

## Summary

The proposed v0.3 scope is complete at the repository-feature level. The eight
commits after `v0.2.0` contain the bounded MQTT feedback correction, the
restricted Windows deployment channel, runtime health and managed source
mirror support, the ControlLight availability classification, and the closed
Navimow and System Functions evidence.

The release public API was unchanged from v0.2.0. Repository checks passed, the
generated artifacts are deterministic for framework version `0.3.0`, and the
consolidated pre-release revision and the exact release preparation revision
passed GitHub CI. Deployment channel version 7 has passed its guarded Windows
installation, deep probe and bounded rejection checks. The annotated tag and
GitHub Release were published on 2026-07-23 at release revision `e223b766`.

## Scope Reconciliation

| Cohort | Repository state | Release disposition |
| --- | --- | --- |
| MQTT authoritative feedback | Implemented, tested and live-observed | Include |
| Restricted Windows deployment channel | Implemented and live-verified through channel version 7 | Include |
| Runtime health probe and source mirror | Implemented, repository-tested and covered by the v7 channel gate | Include |
| ControlLight availability classification | Implemented, tested and live-activated | Include |
| ControlLight rollout closure | Seven v2 wrappers, 22 explicit retains | Include as evidence |
| Navimow adaptive polling observation | Passive gate closed without device command | Include as evidence |
| System Functions pilot | Three SAEF calls, final scheduled observation passed | Include as evidence |
| Bulk legacy migration and new device commands | Not authorized | Exclude |

All `[Unreleased]` entries map to one of these included or explicitly excluded
responsibilities. No unrelated feature remains implicitly attached to the
release.

## Gate Matrix

| Gate | State | Evidence or required action |
| --- | --- | --- |
| v0.3 scope decision | PASS | `project/SAEF_V0_3_SCOPE.md` |
| v0.2 publication reconciliation | PASS | Published immutable `v0.2.0` baseline |
| Public API review | PASS | No diff in helpers, stubs or API test; 29 public functions |
| Private-data review | PASS | No private network, host, account, key or ObjectID data found |
| Third-party provenance review | PASS | No external implementation imported; Wilkware remains inventory-only |
| Deterministic artifacts before version bump | PASS | Bundle and both filesets reproduce current `0.2.0` provenance |
| Repository checks | PASS | `make check` and clean public-tree verification |
| Consolidated CI | PASS | GitHub Actions CI run 42 on revision `7fc98ab` |
| Deployment channel version identity | PASS | Post-gate mirror-launch correction advances the repository contract to v7 |
| Windows channel v7 gate | PASS | Guarded install, deep probe, TTY rejection, malformed-command rejection and final ready probe |
| Framework version `0.3.0` | PASS | Both builder constants use `0.3.0` |
| Regenerated v0.3 artifacts | PASS | Bundle and both filesets rebuild deterministically with v0.3 provenance |
| Dated changelog section | PASS | `[0.3.0] - 2026-07-23` |
| Release-note extraction | PASS | Release workflow command extracts 108 non-empty v0.3 note lines |
| Final clean public-tree verification | PASS | Strict Composer validation and full checks pass without `.git`, `private/` or local vendor contents |
| Release preparation CI | PASS | GitHub Actions CI run 43 passed on revision `870f70e` |
| Annotated `v0.3.0` tag | PASS | Published 2026-07-23 at release revision `e223b766` |

## Version Inventory

The release identity is owned by:

- `SAEF_SYMCON_BUNDLE_FRAMEWORK_VERSION` in
  `tools/build-symcon-bundle.php`;
- `SAEF_SYMCON_FILESET_FRAMEWORK_VERSION` in
  `tools/build-symcon-fileset.php`; and
- the deterministic bundle and fileset artifacts generated from those values.

Protocol format versions, builder versions, diagnostic schema versions,
case-study configuration labels and the IP-Symcon `library.json` module
identity are independent contracts and must not be changed merely to publish
SAEF v0.3.

## Public API and Provenance

The v0.3 branch adds deployment operations and case-study-local classes, but no
new public SAEF helper. `ControlLightCommandException` and both fileset entry
classes remain inside their case-study namespaces. The deployment scripts and
reconcilers are operational artifacts, not global helper APIs.

The post-v0.2 implementation contains no copied Wilkware function body. The
legacy inventory documents upstream association and license boundaries without
relicensing or importing that source. Generated artifacts contain only
canonical SAEF source and their deterministic provenance.

## Publication Outcome

The final readiness revision passed CI, the annotated tag resolves to
`e223b76673b495cecae3e2232ce148c5dabb6230`, and the GitHub Release is
published as neither a draft nor a prerelease.

Repository publication does not activate a Symcon fileset. Any later live v0.3
runtime selection requires a separate staged package, preflight, explicit
activation approval and post-restart verification.
