# EK-007 — Managed Runtime Mirrors

**Status:** Draft 1.0
**Topic:** File-backed Symcon runtime discoverability

## Purpose

A managed runtime mirror makes a file-backed shared PHP runtime visible in the
IP-Symcon console without turning the mirror into executable production code.
It is useful when native console source inspection and reference searches are
important to operations, while filesystem deployment remains the better
runtime model.

## Two artifacts, one authority

The authoritative source is the deployed runtime file. Wrappers, autoloaders or
modules execute that file directly. The Symcon script object is a generated,
non-authoritative projection:

```text
authoritative runtime file
        |
        | deterministic generation + pinned hash
        v
managed Symcon script mirror
        |-- explicit private reference index
        |-- __halt_compiler()
        `-- exact runtime bytes
```

The mirror must not be referenced by events, variable actions, wrappers or an
autoload mechanism. Its execution is unnecessary; if it is executed manually,
the preamble performs no external action and PHP stops before the embedded
runtime.

## Why the reference index is separate

A reusable runtime normally receives ObjectIDs through configuration and should
not contain installation IDs. Symcon's reference search can therefore have
nothing installation-specific to index in the runtime source itself.

The generated preamble solves that mismatch by declaring the complete private
dependency set as numeric literals. Generation sorts and deduplicates the IDs,
so equivalent configuration produces identical source. The index is diagnostic
metadata, not runtime configuration.

Private IDs belong in `private/`, `*.local.*` files or another excluded
deployment input. Public examples use synthetic values only in tests.

## Ownership and presentation

The deployment owns exactly one script identified by a stable Ident below a
configured parent. It owns the script type and generated content. The initial
display name and position are creation defaults.

After first creation, private deployment state should also pin the returned
script ID. Parent, Ident and type are then checked against that ID before every
update, so moving, deleting or replacing the object cannot silently create a
duplicate.

After creation, name, position, icon, information text and visibility are user
presentation and must survive reconciliation. Moving the object, changing its
Ident or replacing it with another object type is ownership drift and should
fail explicitly.

## Reconciliation transaction

A safe reconciliation performs these steps:

1. Validate the parent, Ident, runtime path, pinned runtime hash and reference
   IDs before changing Symcon.
2. Read the existing object by Ident and reject a type or ownership conflict.
3. Read and retain the complete previous script content.
4. Generate the mirror deterministically.
5. Skip the write when the current content hash already matches.
6. Write only the script content and read it back directly.
7. Compare the complete readback hash with the generated hash.
8. On failure, restore and verify the previous content; if this execution
   created the still-empty owned script, remove that new script instead.

The transaction must not create events, call `RequestAction()`, write device
variables or restart services. Activation of a mirror has no meaning because it
is not an action path.

## Diagnostics and acceptance

Useful deployment evidence includes:

- authoritative runtime path and SHA-256;
- generated mirror SHA-256;
- normalized reference-index SHA-256;
- script ID only in private evidence;
- created/updated/unchanged outcome;
- readback and rollback result.

The script content readback is the authoritative verification. A console search
is a separate usability acceptance test. If a local installation offers an
undocumented search function, feature-detect it and keep it out of production
logic. A missing optional search function must not make reconciliation fail.

## Performance

Generation is linear in runtime size plus `O(n log n)` for sorting reference
IDs. Reconciliation performs no polling and normally one content read. An
unchanged mirror causes no content write. Hashes should be calculated in memory
from already-read bytes rather than reopening the runtime repeatedly.

This work belongs in deployment or maintenance flow, not in each ControlLight
device action.

## When to use the pattern

Use a managed mirror when all of these are true:

- the executable runtime is file-backed and shared;
- Symcon console visibility or reference search has operational value;
- the mirror can remain completely outside the action path;
- private reference inputs can be managed and reviewed;
- deterministic regeneration and rollback are available.

Do not create mirrors for every source file by default. Small wrappers already
visible in Symcon, generated bundles that are themselves the installed source,
or files without an operational discoverability need do not benefit enough.

## Promotion gate

ControlLight is the first validated implementation. Before SAEF exposes a new
public helper, record a second independent use case and compare at least:

- ownership and placement requirements;
- source and reference-index formats;
- presentation-preservation policy;
- rollback behavior;
- diagnostics returned to deployment tooling.

Until that review, keep provisioner conveniences inside the case study and
reuse the existing `SAEF_EnsureScript()` helper for object creation.

## Related artifacts

- `adr/ADR-0006-managed-symcon-runtime-mirrors.md`
- `standards/SYMCON_STANDARDS.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `case-studies/control-light/30-runtime-mirror-reference-search-pilot.md`
- `case-studies/control-light/31-managed-runtime-mirror-generator.md`
