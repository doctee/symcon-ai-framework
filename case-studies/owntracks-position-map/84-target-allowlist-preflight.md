# Gate 84 — target-allowlist preflight and ACL classifier correction

**Status:** Repository corrections and private Windows initializer preflight
complete. After the separately authorized state adoption, the repeated
read-only readiness preflight passed in
[Gate 89](89-target-allowlist-readiness-preflight.md); target installation and
live module gates remain closed, 2026-09-05.

## Scope

This gate prepares the installation-local OwnTracks target binding and runs the
existing channel-v8 initializer only in `-PreflightOnly` mode. It must leave the
installed standalone-module target list empty and must not install channel
files, stage or activate a package, reload Symcon or contact a provider.

The private Windows policy, target definition and exact installation evidence
remain ignored. No live ObjectID, filesystem path, host identity, credential,
coordinate, tracker identifier or movement history is recorded here.

## ACL classifier finding

The first bounded diagnostic run stopped before the initializer and reported
no system mutation. Its ACL classifier combined `Write`, `Modify` and
`FullControl` into one bit mask. Because `FullControl` also contains read bits,
a legitimate read-only ACE for a broad principal intersected that mask and was
incorrectly rejected as writable.

The adapter now tests only mutation capabilities:

- writing data, appending data and writing attributes;
- deleting a child or the protected object;
- changing permissions; and
- taking ownership.

`Modify` and `FullControl` remain rejected because they contain one or more of
those mutation bits. `Read`, `ReadAndExecute`, individual read rights and
`Synchronize` no longer produce a false positive. The protected-principal
allowlist and fail-closed behavior are unchanged.

The correction stays inside the OwnTracks adapter and private gate; it does
not introduce a shared helper or public API.

## Cross-runtime package identity

The corrected ACL preflight then reached the active package identity and
exposed a second portability defect. The Symcon-PHP inventory and package
builder order relative paths bytewise with case sensitivity, while the adapter
used the culture-dependent, normally case-insensitive `Sort-Object` default.
Mixed-case module paths could therefore produce a different aggregate identity
for the same files.

The Windows adapter now stores files under their normalized relative paths and
orders an explicit string array with `StringComparer.Ordinal`. This matches the
PHP `SORT_STRING` contract without changing file contents, accepted paths or
hash inputs. A fixed mixed-case four-file vector has the same canonical digest
in the repository test and private Windows preflight.

## Verification

Repository regression tests pin every mutation bit, the absence of the former
composite mask, acceptance of representative read-only values, rejection of
`Write`, `Modify`, `FullControl`, delete and security-control values, explicit
ordinal sorting and the mixed-case identity vector. The complete OwnTracks
test suite, PHP syntax/style checks, distribution check, module fileset check
and whitespace check pass.

The private revision adds the same positive and negative ACL scenarios plus the
ordinal identity vector before inspecting installation-local paths. Its
internal fourteen-file digest and ZIP integrity are verified.

The approved Windows run passed both classifiers, verified the active
37-file package identity and completed the existing channel-v8 initializer in
`-PreflightOnly` mode. The installed channel policy remained byte-identical,
its standalone-module target count remained zero and neither the initializer
nor the wrapper attempted a system mutation or OpenSSH restart. No module
preflight, activation or provider contact occurred.

The run also confirmed authoritative miss-state format 1 and therefore
reported the expected `miss_state_format_1` blocker. The generated private
policy and target-definition hashes remain installation-local evidence for a
future separately approved gate.

## Remaining boundary

The original passed preflight did not make the adapter activation-ready while
the authoritative miss state remained format 1. Gate 88 completed the separate
reviewed format-2 adoption with byte-exact rollback semantics, and Gate 89
subsequently removed this readiness blocker without changing the installed
target count. Target installation, channel `probe`, inactive `stage`, adapter
`preflight`, `activate`, health/UI postflight, retention, publication and
cleanup remain independently gated.
