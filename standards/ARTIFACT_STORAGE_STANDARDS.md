# Artifact Storage and Lifecycle

Status: Repository contract for new artifacts; existing producers are not migrated.

## Purpose and scope

Make every SAEF-generated artifact understandable without reconstructing its
origin from a chat. Separate executable dependencies, recovery inputs and
disposable work by role, not by an ambiguous `SAEF-*` name. This standard covers
repository work, Windows operations, tests and transfer copies. It introduces
no new runner, live directory, cleanup service or permission to move data.

Use the least complex route in
[the change workflow](../project/SYMCON_CHANGE_WORKFLOW.md). An existing manifest,
receipt or workstream record remains authoritative. Record missing lifecycle
facts alongside it, not in a replacement deployment manifest.

## Placement contract

Select the configured root and its owner before creating an artifact. Paths
below are logical roles/defaults for future approved producer changes, not
instructions to create or relocate directories.

| Area | Permitted role | Boundary |
| --- | --- | --- |
| Configured Symcon runtime roots | Installed modules, scripts, runtime data and managed dependencies | Keep platform and deployment-owned paths. No new loose upload ZIPs, preparation scripts or backup dumps alongside runtime files. |
| `%ProgramData%\SAEF` operational roots | Operation evidence, deployment state, backups | Reuse existing named roots and their contracts first. Keep adapter state, channel state, filesets and backups in disjoint owned namespaces. |
| Configured test root | Newly owned synthetic fixtures and compiler scratch | Prefer the existing qualification-scratch root where applicable. One run child; no new scattered drive-root `SAEF-*` directories. |
| Configured private Transfer directory | Verified exchange copies and returned results | Never the implicit sole backup or a runtime dependency. Existing sole copies remain protected until another verified copy exists. |
| Repository private overlay | Workstream records, source worktrees, local evidence | Reuse `private/workstreams/<workstream>`, `private/worktrees/<workstream>` and existing evidence locations. Do not create a second per-chat source tree. |

For a new operation with no existing stronger layout, the default is:

```text
<configured-operations-root>/<workstream>/<run-id>/
    artifact-record.local.json
    summary.local.txt
    evidence/
    exports/
```

The prospective Windows operations root is `%ProgramData%\SAEF\operations`.
Do not create unused subdirectories. Backups, quarantine and scratch have
separate configured roots/security roles; link them from the record rather
than placing them inside a report directory by convenience. Existing
`DeploymentRetentionBackups`, recovery roots and qualification roots are not
renamed by this standard.

`Symcon/user` is not a maintenance dump: use it only for intended runtime or
served assets. Keep credentials, backups and diagnostic payloads outside
served content. Location alone does not prove access protection.

## Naming

- New workstream slugs use lowercase ASCII letters, digits and internal hyphens.
- New run IDs use `YYYYMMDDTHHMMSSZ-<12 lowercase hex characters>`: UTC time
  plus a random collision-resistant suffix. A collision must fail, never overwrite.
- Use stable role names inside the run, not `final`, `new`, `copy` or `final-v2`.
  Code/package revisions belong in existing source identity fields, not extra
  nested copies of every previous result.
- New exports use `<workstream>-<run-id>-<role>.local.<extension>` where the
  existing transport contract permits it. Keep runnable package entries at
  archive root when required by the Windows handover contract.
- Preserve existing hash-based filenames, manifest IDs, UTC case conventions
  and pinned paths exactly. This convention never normalizes old names.

## Minimal metadata, not another registry

Use [the artifact record](../templates/artifacts/README.md) once per logical
unit when existing metadata does not cover its lifecycle. For a routine change,
the same facts may live in its existing private change record. No JSON sidecar
is required per file, and no historical manifest must be rewritten.

Required facts are purpose, accountable owner/workstream, producer revision,
role, location, lifecycle state, identity/evidence references, holds,
dependencies and retention/review decisions. A transfer copy identifies its
source. Unknown facts remain explicitly unknown and require retention/review.
Record observation time and distinguish local verification from a historical
report. A recorded path is not proof that it still exists.

For a pinned artifact, keep lifecycle annotations externally in the canonical
private workstream. Do not change its bytes, ACL or inventory to add metadata.
References point to immutable evidence where possible; summaries reference old
reports instead of embedding all historical reports again. Never discard
already retained history merely to implement this recommendation.

The optional JSON schema validates record shape only. It does not resolve
paths, verify hashes, establish ownership or authorize execution/deletion.
Human summaries should show path, role/purpose, owner, reported state,
evidence date, hold and next review. Size is optional; unknown is not zero,
and logical bytes are not reclaimed disk space.

## Protection and retention

- Root configuration, access control and artifact purpose are separate checks.
  Keep per-role least-authority ACLs; no shared-parent ACL reset or blanket
  inherited permissions. Metadata does not replace descriptor validation.
- Runtime, staged, rollback and deployment-state dependencies remain protected
  transitively under [workstream coordination](../project/WORKSTREAM_COORDINATION.md).
  A matching name, old date, `completed` state or negative text search cannot
  establish that an artifact is unused.
- Quarantine is reversible holding, not deletion. Preserve same-volume move
  requirements and exact unit relationships; do not centralize all volumes
  into a C: quarantine. Purge remains a separate gate.
- Every test approval must say what is retained and what exact cleanup, if any,
  is allowed. Failed/uncertain runs, one-run markers and compiler scratch remain
  recorded. The word "temporary" grants no cleanup authority.
- A retention minimum or review date is not an expiry-triggered deletion.
  Unknown dependencies, missing evidence, an active observation, safety hold
  or incomplete recovery blocks any destructive decision.
- Future automated cleanup is limited to separately approved, exact owned
  units after fresh checks. It must not scan and delete by prefix, extension,
  age or this descriptive record alone. Existing safety holds remain binding.

## Exceptions and adoption

An exception records reason, owner, exact location/volume, scope, lifetime,
evidence and review condition in the existing private change/workstream record.
Examples: compiler-required temporary paths or a same-volume synthetic test.
An exception is neither permission to mutate nor a generic path exemption.

Adopt in this order: document and classify existing evidence; adapt a small
number of existing producers with focused regression tests; qualify a bounded
pilot under separate authority. Do not build another wrapper or migrate every
historical file to make the tree look uniform. Installed producers remain
unchanged until their own reviewed deployment gate.

The two local deployment-package builders now offer this storage layout through
explicit version 2 plans; see [their examples](../templates/artifacts/README.md#local-package-builders).
Version 1 compatibility remains an explicit legacy-layout exception. The existing
MediaCarousel Windows package starter has a separately opted-in extraction layout;
see [its bounded adoption](../templates/artifacts/WINDOWS_PRODUCER_ADOPTION.md).
Other producers and all existing installations are not adapted by this change.

## Closure

Complete [the closure checklist](../templates/artifacts/CLOSURE_CHECKLIST.md)
for successful, failed and interrupted work. `completed` describes the work,
not disposability. A documented cleanup completion is historical evidence,
not authorization to repeat it or a global absence proof.

Related: [Testing Standards](TESTING_STANDARDS.md),
[cross-root retention](../project/STANDALONE_MODULE_CROSS_ROOT_RETENTION.md),
[ADR-0012](../adr/ADR-0012-use-risk-tiered-symcon-change-workflows.md).
