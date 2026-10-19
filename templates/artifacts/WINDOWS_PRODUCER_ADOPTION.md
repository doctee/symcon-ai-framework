# Bounded Windows producer adoption

Status: Focused Windows extraction test passed on 2026-10-19. No installed change.

## Selected producer

`deployments/symcon/windows/adapters/Start-SaefMediaCarouselSchemaPackage.ps1`
is the existing package-local extraction/dispatch starter for schema, binding,
settings and repeatable packages. Its generated operator variants have existing
Windows execution evidence. This change updates the repository source only,
not historical operator copies or their pinned ZIPs.

The optional `ArtifactRoot` parameter selects an **already existing**, canonical,
local, non-drive-root directory. For new operations, select an approved local
operational root, not the designated exchange root, a redirected share or a
Symcon runtime/module/user directory. Earlier approved layouts using a private
local Transfer directory remain retained path exceptions; do not relocate them
or reinterpret their approval. Root-role selection is an operator gate, not a
new automatic share detector in the unchanged starter. The starter
does not provision `%ProgramData%\SAEF\operations` or change an existing root's
permissions. The longest extracted path is bounded to 240 characters before
creating a workstream/run; no OS long-path setting is changed. Omitting the
parameter preserves the legacy extraction layout.

```text
<approved-existing-root>/
    media-carousel-package/
        YYYYMMDDTHHMMSSZ-012345abcdef/
            artifact-record.local.json
            summary.local.txt
            <unchanged extracted package entries>
```

One workstream directory is reused; every extraction creates a new run without
overwrite. The fixed workstream names the producer family, and purpose records
the selected package kind. No extra evidence/export trees are created unused.
`-Verbose` reports the selected run path without changing the child JSON result.

## Unchanged safety boundary

- Reuse `Assert-AdditionPlainPath`, `Assert-AdditionProtectedPath` and
  `Assert-Elevated` from the already hash-bound initializer in the ZIP.
- Keep the existing trusted-owner/untrusted-writer and reparse-point checks.
  This contract does not require a particular descriptor byte layout, SACL or
  disabled inheritance. Do not reinterpret it as a new confidentiality proof.
- New children inherit from the approved root and are checked by the same
  validator. If unsuitable, stop; no ACL reset, permission repair or fallback.
- Keep package entry allowlists, byte/hash limits, exact extracted payloads,
  bounded child execution, confirmations, plan paths and child JSON unchanged.
- Metadata uses exclusive UTF-8 writes, explicit nulls and JSON depth 8. It is
  written before extraction/dispatch with `review-required` and a retention hold.
  It is not a success receipt, tree manifest, authorization or deletion list.
  Source ZIP path/hash and starter hash identify provenance; run-tree identity
  remains null because children may create additional evidence.
- Interruption or a failure retains the run, including partially written files.
  Missing/partial metadata means incomplete evidence, never permission to delete.
  Existing child output and independent postflight remain the closure authority.
- New naming does not add replay permission. Existing one-run/plan constraints
  in each package still apply; this starter does not invent a global retry policy.

Only the **extraction producer** is adapted. Target, backup, compiler scratch,
result and runtime paths in bound plans are not remapped. An existing deployment
generation can depend on extracted files; retain transitively until reviewed.
No automatic migration of old directories, no cleanup command, no new wrapper.

## Focused validation gate

The existing PHP metadata test covers the Windows-shaped record offline.
`tests/deployments/media-carousel-artifact-storage.ps1` provides a narrowly scoped
Windows PowerShell 5.1 test: import the extraction function through the parser,
use synthetic non-executable package payloads, compare all four kinds in legacy
and organized modes, check three cultures, source preservation, metadata arrays,
nulls, root ACL preservation, rejected roots/hashes, untrusted writers and junctions.
It never invokes the starter entrypoint or package children. Only its newly
created synthetic root receives the existing test ACL helper; all fixtures stay.

The test is not automatically run or added to CI. Any execution needs
separate isolated Windows authority, `-RunIsolatedTests` and an existing approved
short `FixtureParent` qualification-scratch directory. It checks the nested path
budget before creating its new run, rather than relying on long-path configuration.
No production package or copied operator command is required for that test.

On 2026-10-19, one authorized run under Windows PowerShell Desktop 5.1 passed
268 checks with exit code 0 and empty stderr. Returned execution and result
records were checked against the original handover manifest and unchanged
repository sources. The test exercised four package kinds and three cultures,
12 organized extractions, legacy controls and the listed rejection cases.
Fixtures were retained; no production operation or cleanup was performed.

This result covers the exact extraction/storage implementation, including its
Windows parsing and tested ACL behavior. It does not qualify the starter's
entrypoint, real package child dispatch or end-to-end deployment. Private raw
receipts and installation paths stay outside the public repository. Source
changes affecting this contract require proportionate regression review; this
historical pass never authorizes a repeat or another operation.

After successful review/qualification, use the new parameter only in the next
separately approved package operation. Do not replay a completed historical
MediaCarousel change just to demonstrate its folder layout.
