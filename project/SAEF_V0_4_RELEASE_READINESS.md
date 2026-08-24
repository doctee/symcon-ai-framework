# SAEF v0.4 Release Readiness

**Assessment date:** 2026-08-24
**Target:** `v0.4.0`
**Scope-freeze base:** `f394d02ed4e511341b887a51cd07ee242d10d22f`
**Current decision:** RELEASE CANDIDATE PREPARED - PUBLICATION PENDING

## Summary

The frozen v0.4 repository scope is complete at the release-candidate level.
The scope-freeze base contains 174 commits and 744 changed paths after
`v0.3.0`. It adds the manifest-driven module-publication platform, standalone
module distributions, safer object mutation, serialized Statistics updates,
worktree-isolated tooling and the included case-study evolution.

GitHub issue #1 remains open and is explicitly deferred. Navimow Zone 2 and
Zone 3 natural observations, private legacy migration, live activation,
standalone-module publication and retention cleanup remain outside the release.

The public helper contract contains 30 functions. Both framework-version
owners now use `0.4.0`, and all deterministic artifacts were rebuilt twice from
the clean worktree with identical output. Tagging and the GitHub Release remain
a separate final publication gate.

## Scope Reconciliation

| Cohort | Release disposition |
| --- | --- |
| Mutable-object validation and Ensure hardening | Include as public API addition and safety correction. |
| Per-variable Statistics serialization | Include as signature-compatible behavioral hardening. |
| Manifest-driven module publication | Include as operational tooling, not helper API. |
| Worktree toolchain and coordination contracts | Include. |
| Deployment-retention invariant | Include as guarded operational tooling. |
| MediaCarousel, Open-Meteo and Navimow distributions | Include as deterministic case-study and module artifacts. |
| ControlLight and MQTT exporter evolution | Include with sanitized engineering evidence. |
| GitHub issue #1 latest-command-wins | Defer to a post-v0.4 workstream. |
| Remaining private observations, migrations and live operations | Exclude. |

Every Unreleased changelog entry maps to an included cohort or an explicit
boundary decision. No additional feature is admitted by release preparation.

## Gate Matrix

| Gate | State | Evidence or remaining action |
| --- | --- | --- |
| v0.4 scope decision | PASS | `project/SAEF_V0_4_SCOPE.md` |
| Repository reconciliation | PASS | `project/SAEF_V0_4_REPOSITORY_RECONCILIATION.md` |
| Issue #1 disposition | PASS | Open and deferred to post-v0.4. |
| Public API audit | PASS | `project/SAEF_V0_4_PUBLIC_API_AUDIT.md`; 30 functions. |
| Private-data review | PASS | No private path, network, key, credential or installation identifier in the release delta. |
| Third-party provenance review | PASS | No third-party implementation imported; provider and upstream analysis remains attributed documentation. |
| Dependency licence review | PASS | Development dependencies remain PHPStan/MIT and PHP_CodeSniffer/BSD-3-Clause. |
| Framework version `0.4.0` | PASS | Both canonical builder constants use `0.4.0`. |
| Deterministic artifact regeneration | PASS | Complete builders ran twice with identical output. |
| Generated artifact drift checks | PASS | All bundle and fileset checks reproduce the tracked candidate. |
| Dated changelog section | PASS | `[0.4.0] - 2026-08-24` with a new empty Unreleased boundary. |
| Release-note extraction | PASS | Release workflow expression extracts 84 non-empty note lines. |
| Full clean-worktree checks | PASS | Full `make check` passed with the lock-identical external toolchain. |
| Pull-request CI | PENDING | Require success on the exact candidate head. |
| Post-merge CI | PENDING | Require success on the exact release revision. |
| Annotated `v0.4.0` tag | PENDING | Separate final publication gate. |
| GitHub Release | PENDING | Created by the verified tag workflow only. |

## Version Inventory

The SAEF release identity is owned only by:

- `SAEF_SYMCON_BUNDLE_FRAMEWORK_VERSION` in
  `tools/build-symcon-bundle.php`; and
- `SAEF_SYMCON_FILESET_FRAMEWORK_VERSION` in
  `tools/build-symcon-fileset.php`.

Both are `0.4.0`. Protocol format versions, builder versions, deployment-channel
versions, diagnostic schema versions and IP-Symcon module `library.json`
versions remain independent compatibility contracts.

## Artifact Inventory

| Artifact | Deterministic SHA-256 |
| --- | --- |
| EnsureVariable bundle | `d06e021f1a46b3dff90232de66866bb8c7a3509b12105c1e8eb37ef75f8626a3` |
| MQTT Discovery Exporter fileset | `21b96591c7256e6ab8e9224bc9faa89b6fd51ef2bba7bea19c2078ff9d3fbe7c` |
| ControlLight fileset | `3e3636337b9e1f841b304d895effe1f813b82c8acb9b69f9b547436d538b9583` |
| MediaCarousel module fileset | `99c7e30e4d09a0f78ccc7abcb9176e7de495bed4ad785a20f7112c3dd0edab00` |
| Navimow module fileset | `785c7b365b1818ab4e1af7a13c1518d88e233074ca87f523bfb35246155cafba` |
| Open-Meteo module fileset | `5aff1882845f59b3eb57b4a079ada1249d09a91238af2cd30b2d62d1d5af16eb` |

The framework-version bump changes the bundle content hash and the framework
provenance in the bundle and two SAEF fileset source maps. Module filesets do
not embed the SAEF framework version and therefore remain byte-identical.

## Provenance and Privacy

Generated artifacts contain canonical SAEF source only and retain the PolyForm
Noncommercial 1.0.0 identity. MediaCarousel documents that no Wilkware
ImageViewer source was copied. The System Functions inventory remains analysis
only and imports no Wilkware implementation. Open-Meteo provider attribution
requirements and Navimow upstream observations remain documented without
embedding private coordinates, credentials, tokens, device identifiers or
captured payloads.

The Composer lock contains only development analyzers: PHPStan under MIT and
PHP_CodeSniffer under BSD-3-Clause. No runtime dependency or vendored
third-party source is added by v0.4.

## Publication Boundary

This candidate preparation does not create a tag or GitHub Release and does not
publish a standalone module. It does not update Module Control, activate a
fileset, restart Symcon, issue a device command or authorize retention cleanup.

After the candidate merge and exact-revision CI pass, the remaining operation
is one separately verified annotated-tag publication. The release workflow must
rerun `composer check`, extract the dated v0.4 changelog section and create a
non-draft, non-prerelease GitHub Release.
