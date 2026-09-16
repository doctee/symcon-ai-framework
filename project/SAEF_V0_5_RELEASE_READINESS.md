# SAEF v0.5 Release Readiness

**Assessment date:** 2026-09-16
**Target:** `v0.5.0`
**Scope-freeze base:** `b30dad4bee001d852029bb78b6fd94917e0eaccc`
**Current decision:** RELEASED

## Summary

The frozen v0.5 scope contains 195 commits and 535 changed paths after
`v0.4.0`. It adds the isolated-workstream contract, Channel-v8 target-bound
deployment, scope-bound approval, secure Windows child execution, bounded MQTT
latest-command-wins behavior and the admitted case-study evolution.

The public helper contract remains 30 functions and three constants. Both
canonical framework-version owners use `0.5.0`. Pull request 130, post-merge
CI, GitHub issue 1 closure and the tag-triggered release workflow completed on
the exact release revision.

## Gate Matrix

| Gate | State | Evidence or remaining action |
| --- | --- | --- |
| v0.5 scope decision | PASS | `project/SAEF_V0_5_SCOPE.md` |
| Repository reconciliation | PASS | `project/SAEF_V0_5_REPOSITORY_RECONCILIATION.md` |
| MQTT terminal evidence | PASS | Sanitized report 44 plus retained private terminal evidence. |
| Public API audit | PASS | `project/SAEF_V0_5_PUBLIC_API_AUDIT.md`; 30 functions and three constants. |
| Private-data review | PASS | Candidate diff contains no private installation path, host, SID, address, topic or ObjectID value. |
| Dependency licence review | PASS | Development dependencies remain PHPStan/MIT and PHP_CodeSniffer/BSD-3-Clause. |
| Framework version `0.5.0` | PASS | Both canonical builder constants use `0.5.0`. |
| Deterministic artifact regeneration | PASS | Bundle, MQTT fileset and ControlLight fileset regenerated twice with byte-identical output. |
| Generated artifact drift checks | PASS | All bundle and fileset checks reproduce the tracked candidate. |
| Dated changelog section | PASS | `[0.5.0] - 2026-09-16`; release extraction returns 160 non-empty lines. |
| Full repository checks | PASS | Complete `make check` passed in the isolated candidate worktree with the lock-identical external toolchain. |
| Pull-request CI | PASS | Both validate runs passed on candidate `3c0ffc13c7ebc9267b33f125fb4049b47865ff9c` in PR 130. |
| Protected-main merge | PASS | PR 130 merged as release revision `f02d36a8c404f949b8d4433db8f28fa2d52dd66b`. |
| GitHub issue 1 closure | PASS | Report 44 was present on `main`; the prepared closure comment was posted and the issue was closed on 2026-09-16. |
| Post-merge CI | PASS | Run `35105335059` passed on the exact release revision. |
| Annotated `v0.5.0` tag | PASS | Tag object `0a5cc550ba3f6c3322285b84353300f05255ce82` resolves to the release revision. |
| GitHub Release | PASS | Run `35106400520` published SAEF v0.5.0 as neither draft nor prerelease. |

## Version Inventory

The SAEF release identity is owned only by:

- `SAEF_SYMCON_BUNDLE_FRAMEWORK_VERSION` in
  `tools/build-symcon-bundle.php`; and
- `SAEF_SYMCON_FILESET_FRAMEWORK_VERSION` in
  `tools/build-symcon-fileset.php`.

Both are `0.5.0`. Protocol, builder, deployment-channel, diagnostic-schema and
IP-Symcon module versions remain independent compatibility contracts.

## Artifact Inventory

| Artifact | Deterministic SHA-256 |
| --- | --- |
| EnsureVariable bundle | `802bdd95ba3b75eddf69b099a04b56c569ccaab725c411931af163b31f8e59bc` |
| MQTT Discovery Exporter fileset | `c5dbcdd1a608bf72119990bfca49d680ba829486ac19dc5add740870edaa8144` |
| ControlLight fileset | `3e3636337b9e1f841b304d895effe1f813b82c8acb9b69f9b547436d538b9583` |
| MediaCarousel module fileset | `99c7e30e4d09a0f78ccc7abcb9176e7de495bed4ad785a20f7112c3dd0edab00` |
| Navimow module fileset | `e92ecd29d6fe0d797a5848934aef72a0656cc48ccb0e3fd6fb0255574f0a58fd` |
| Open-Meteo module fileset | `0dbebf397861bc7ecc0b66bae132b1e148a5d5aa362197525c99360a5650c542` |
| OwnTracks module fileset | `9fccc8ef55daa4704186e34e78e44fa70c587a666125e7dbe1e7d53d95e1f45f` |

The framework-version bump changes the EnsureVariable bundle bytes and the
framework provenance in its source map plus the MQTT and ControlLight source
maps. Module filesets do not embed the SAEF framework version and remain
byte-identical.

## Verification Notes

The focused MQTT, helper and Open-Meteo checks passed. The release gate also
found and corrected two overly narrow Open-Meteo PHPDoc contracts and one
multi-line declaration formatting defect introduced immediately before the
scope freeze. Those corrections change neither runtime policy nor generated
module fileset bytes.

## Publication Outcome

The annotated `v0.5.0` tag resolves to
`f02d36a8c404f949b8d4433db8f28fa2d52dd66b`. Release workflow
`35106400520` reran the repository checks, extracted the dated v0.5 changelog
section and published [SAEF v0.5.0](https://github.com/doctee/symcon-ai-framework/releases/tag/v0.5.0)
as neither a draft nor a prerelease on 2026-09-16. The published notes match
the reviewed changelog section.

This repository release did not publish a standalone module, update Module
Control, activate a fileset, restart Symcon, issue a device command or delete
retained evidence. Those operations retain independent gates.
