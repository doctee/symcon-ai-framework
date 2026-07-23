# ADR-0006: Use managed Symcon runtime mirrors for file-backed runtimes

Status: Accepted
Date: 2026-07-20

## Context

Filesystem deployment is the appropriate execution model for shared PHP
runtimes: normal source files support deterministic builds, dependency tooling,
hash verification and atomic replacement. IP-Symcon's console, however, can
only provide its native script-source visibility and reference-search workflow
for source stored in a Symcon script object.

The ControlLight migration exposed this operational gap. Its wrappers execute a
shared, file-backed runtime, while maintainers still need to find references
from the Symcon console without treating a second handwritten copy as runtime
code.

## Decision

SAEF permits an optional **managed runtime mirror** for a file-backed shared
runtime when console discoverability has demonstrated operational value.

The contract is:

1. The filesystem source remains the only authoritative and executable runtime.
2. A dedicated, owned Symcon script object contains generated mirror source and
   is never part of the action, autoload or event path.
3. The generated source contains a deterministic, installation-specific
   reference index before `__halt_compiler()` and the complete authoritative
   runtime source byte-for-byte after it.
4. Private ObjectIDs enter only through private deployment configuration. They
   never enter public SAEF artifacts.
5. The mirror is located by a stable Ident below an explicitly configured
   parent. Its initial name and position are defaults; existing user changes to
   name, position, icon, information text and visibility are preserved.
6. Reconciliation validates the pinned runtime hash, generates deterministic
   content, skips an identical result, writes only the script content, reads it
   back and verifies its hash.
7. A failed update restores and verifies the exact previous content. A failed
   first creation removes only the newly created owned script.
8. Deployment and acceptance must not execute device actions, create events or
   bind the mirror as an action target.
9. Native or third-party console reference-search functions may be used as
   feature-detected acceptance probes. Undocumented functions are not runtime
   dependencies.
10. Reconciliation executed through `IPS_RunScriptTextWait` must explicitly
    load its hash-verified helper closure. That isolated script context must not
    be assumed to inherit functions loaded through `System.Locals`.

Implementations remain local to their ownership boundary. ControlLight mirrors
one case-study runtime plus a private reference index. The restricted
deployment channel mirrors the verified helper-source closure of an activated
fileset and reconciles it automatically after a successful restart. The second
use case validates the common principles but has a different index and payload
contract, so no public SAEF mirror helper is introduced without a separate API
review.

## Rationale

The mirror restores useful console ergonomics without creating two executable
sources of truth. `__halt_compiler()` makes the embedded runtime inert, while
the small preamble gives Symcon's reference tooling explicit numeric references
to index. Hash pinning, readback and rollback keep generation and deployment
auditable.

Keeping both provisioners local follows Reuse Before Extend. Repeated principles
do not by themselves prove that their differing source-selection and indexing
contracts should share a generally stable helper interface.

## Consequences

### Positive

- File-based runtime deployment and normal PHP tooling remain authoritative.
- Maintainers regain console source visibility and reference discovery.
- Generated content is reproducible and can be verified without device actions.
- User presentation changes survive reconciliation.

### Negative

- Each mirror consumes a script object and generated source storage.
- The reference index is private deployment configuration that must be kept
  current when dependencies change.
- Console search results can include both explicit index entries and numeric
  literals present in the embedded source.
- A mirror can become stale until reconciliation runs; its hashes must therefore
  be treated as evidence, not assumed freshness.

## Alternatives considered

### Store the executable runtime only in the Symcon script object

Rejected because it gives up filesystem tooling and reintroduces script-object
deployment as the runtime source of truth.

### Keep a handwritten console copy

Rejected because handwritten copies drift and create ambiguous authority.

### Make the mirror part of autoload or wrapper execution

Rejected because a diagnostic representation must not become a second action
path.

### Add a public helper immediately

Rejected after comparing two independent implementations: their common safety
rules are stable, but their source-selection and index contracts are not one
public responsibility.

## Related

- `standards/SYMCON_STANDARDS.md`
- `knowledge/EK-007-managed-runtime-mirrors.md`
- `case-studies/control-light/30-runtime-mirror-reference-search-pilot.md`
- `case-studies/control-light/31-managed-runtime-mirror-generator.md`
- `deployments/symcon/windows/README.md`
- `adr/ADR-0005-generate-symcon-helper-bundles.md`
