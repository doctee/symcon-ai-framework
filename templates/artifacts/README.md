# Artifact Lifecycle Metadata

Use the [storage standard](../../standards/ARTIFACT_STORAGE_STANDARDS.md).
These are descriptive private records, not executable cleanup manifests.
Reuse equivalent facts from the authoritative change/workstream/deployment
record; add a sidecar only for missing lifecycle information. Do not introduce
a second mutable inventory of a hash-pinned fileset.

For the existing Windows MediaCarousel extraction producer, see
[bounded Windows adoption](WINDOWS_PRODUCER_ADOPTION.md). Its optional layout
reuses the existing path/ACL validator and passed a focused Windows extraction
test. Productive use and real package dispatch remain separate gates.

## Fields

| Field | Meaning |
| --- | --- |
| `formatVersion`, `artifactId` | Record shape and stable logical-unit identity; not a path-derived ownership claim |
| `workstream`, `owner`, `purpose` | Canonical workstream, accountable maintainer and reason to retain the unit |
| `producer` | Existing producer name and source revision, or explicitly `unknown` |
| `role` | `source`, `package`, `runtime`, `deployment-state`, `backup`, `evidence`, `transfer`, `scratch`, `quarantine`; `package` means a prepared candidate, not an installed runtime or transfer copy |
| `location` | Literal path and explicit absolute/repository-relative basis; no glob or implicit scan |
| `state` | `active`, `retained`, `review-required`, `completed`, `removed-reported` |
| `identity` | Existing manifest reference and/or SHA-256 of the identified file; null when unavailable, not an invented directory hash |
| `evidence` | Local check timestamp, `local-observation` / `historical-report` / `unverified` basis, references |
| `retention` | `retain` / `review`, minimum date, review date, hold reasons and dependency references; no delete decision |
| `copyOf` | Source identity/reference for a transfer copy; null for an original |

`completed` can coexist with holds; `removed-reported` requires an existing
completion report and is not a new absence observation. For unknown ownership,
dependencies or original identity, record `unknown` and a hold; do not use an
empty list to imply that inspection found no dependencies. A null retention
minimum means unspecified, not expired. Dates never lift holds.

Repository-relative locations refer to the canonical primary checkout, not the
caller's current directory or a nested worktree. Identify other repositories
with an explicit private absolute location. Make evidence/reference strings
unambiguous (repository-relative path or named immutable identity); never
silently resolve them as commands or glob patterns.

The example is synthetic and deliberately retained. Replace all illustrative
values in a private record. Evidence references are not instructions to execute.
The schema rejects unknown fields and delete-authority flags; it validates
structure only. A valid record does not prove a live path safe or unused.
Use the existing Composer `opis/json-schema` dependency for offline validation;
`composer test:artifact-record` tests the example and rejection cases without
reading or changing any referenced path.

Review with [CLOSURE_CHECKLIST.md](CLOSURE_CHECKLIST.md). Other producer
integration, live policy enforcement and cleanup remain separate work.

## Local package builders

For new POSIX-local runtime/module package plans use
[RUNTIME_PACKAGE_PLAN_V2.example.json](RUNTIME_PACKAGE_PLAN_V2.example.json) or
[MODULE_PACKAGE_PLAN_V2.example.json](MODULE_PACKAGE_PLAN_V2.example.json).
These replace `outputPath` with explicit private `storage` configuration; all
candidate identity fields keep their existing contract. Replace illustrative
values before use. Never reinterpret a plan approved for version 1 as a new
version 2 run without approval for the new local output scope.

The example `storage.root` is deliberately a worktree-local private build path,
not the operator's Transfer directory. For new private plans, bind the actual
reviewed fileset/contract inputs and select an already existing non-shared build
root. Missing inputs or root mean preparation is incomplete, not permission to
create a root, rebuild an old package or reuse its approval. Record the exchange
destination by reference to the primary checkout's `private/TRANSFER.local.md`
in the existing handover, outside the package-plan JSON. Copying/exporting is a
separate action with exclusive destination creation and full hash readback.

The root must already exist, be canonical and symlink-free, owned by the local
user and have no group/other POSIX mode permissions. No root is provisioned or
repermissioned automatically. The producer creates only new workstream/run
children, mode 0700, and private files. The root and its ancestors must be under
trusted local control throughout execution; path checks are not race-proof
against a malicious concurrent filesystem writer. POSIX mode checks do not
certify Windows or extended ACLs. Do not use a served, shared or production root.
Windows-hosted version 2 producers fail closed pending separate qualification;
the unchanged generated ZIP can still target the existing Windows channel.

Output is `<root>/<workstream>/<UTC>-<12-hex>/package.local.zip`, with an exact
private input-plan copy, initial receipt, compact summary and completed artifact
record outside the ZIP. The producer revision binds SHA-256 of both the builder
and its internal storage support. The local result names the record/run path;
these results contain private paths and must not be published.

An initial receipt is `review-required`, not proof of completion. A partial or
failed run is retained, with a failure receipt where writing remains possible.
No retry or cleanup is performed. A completed record means a built package,
not installation, deployment qualification or disposal permission. No lifecycle
metadata is added inside the deterministic deployment archive.

Version 1 plans retain their output/result layout. Their historical examples and
Windows checksum manifest are unchanged. New version 2 examples are the preferred
starting point for future approved local preparation; this does not migrate any
existing plan, package or producer installation. Test with
`composer test:package-artifact-storage`; fixtures stay below the calling
worktree's ignored `private/package-artifact-tests/` and are not cleaned up.

## Private module fileset output

The existing `tools/build-symcon-module-fileset.php` accepts an opt-in
`--private-output-plan=/absolute/path/plan.local.json` with
[MODULE_FILESET_STORAGE.example.json](MODULE_FILESET_STORAGE.example.json).
This separate storage plan uses the existing version-2 storage fields. It is
not a new fileset manifest format: manifest, builder version, source-map bytes,
fileset identity and ordinary `dist/` builds remain unchanged. The new option
cannot be combined with `--check`, `--output-root` or another private plan.

Select an existing private local POSIX build root, not a Transfer/shared/runtime
root. The same owner, canonical-path, trusted-ancestor and separately reviewed
ACL prerequisites as the package builders above apply. The tool checks POSIX
mode/ownership and symlink components, not extended ACLs or share configuration;
these remain operator gates, not inferred privacy guarantees. It never changes
existing rights. This mode rejects storage inside source/manifest directories or
the generated fileset, and rejects unsafe or case-colliding output names. Its
portable output names are ASCII; existing Unicode-capable legacy paths are not
reinterpreted. No Windows support is claimed for this private mode.

Each invocation exclusively creates `<root>/<workstream>/<UTC>-<12-hex>/` with
new directories 0700 and files 0600. `fileset/` holds the unchanged relative
`dist/symcon/<name>/` tree. Exact input plan/manifest copies, initial receipt,
summary and completed lifecycle record sit outside that tree. The JSON result
names `runDirectory`, `outputRoot` and `artifactRecordPath`; it contains private
paths. Use the existing `--check --output-root=<returned-outputRoot>` with the
original manifest for a later read-only check, not another private build.

Every output is reopened and byte-compared before completion. The record binds
the existing source map; it does not invent a directory hash. A failed run stays
in place, its initial receipt is not success, and the error names the retained
run. There is no retry, transfer, publication, deployment or cleanup operation.
New output placement is not a reason to rebuild or migrate historical artifacts.

`composer test:module-fileset-storage` exercises this boundary and legacy
equivalence locally. Fixtures remain under the calling worktree's ignored
`private/module-fileset-storage-tests/`; there is no cleanup path.
