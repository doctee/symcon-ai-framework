# Home Assistant Entity Upstream Handoff

**Date:** 2026-07-27
**Result:** DRAFT PULL REQUEST PUBLISHED

## Finding

The upstream Home Assistant Entity module composes the shared device action
core, which reads the boolean `EmulateStatus` property. The Device module
registers that property, but the Entity module on current upstream `master`
does not. This is the same contract defect previously repaired locally for the
CL-020 target.

## Minimal Fix

The prepared upstream commit adds the missing boolean property registration to
the Entity module with the same `false` default used by the Device module.

An executable property-contract regression scans all property reads in the
shared device core and verifies matching typed registrations in both concrete
modules. The test is included in upstream CI so another shared-property drift
cannot silently reintroduce the defect.

## Verification and Handoff

The upstream CI-equivalent syntax, JSON, locale and property-contract checks
all pass. One unrelated PHP 8.5 deprecation remains pre-existing in the
presentation library.

The private handoff contains a standard one-commit patch against upstream
commit `3e9241d9c67c1d433256a71e4c9e7a546f4cd6da`.

After installing and authenticating the GitHub CLI, the branch was pushed to
the public `doctee/SymconHomeAssistant` fork and opened as draft pull request
[#1](https://github.com/bumaas/SymconHomeAssistant/pull/1) against upstream
`master`. The verified PR contains exactly commit
`2978ebc8c39c77ad9599bb811b1c45f1f2cddf40` and the four reviewed files.
GitHub had not yet reported a workflow result immediately after PR creation.
