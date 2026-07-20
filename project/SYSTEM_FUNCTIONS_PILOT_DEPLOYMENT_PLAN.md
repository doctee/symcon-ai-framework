# System Functions Pilot Deployment Plan

**Release-review status:** 2026-07-20; recorded evidence was reviewed without a
new live-system read or mutation.

## Status

The three four-argument `CreateVariableByIdent` calls were ready for controlled
migration from a data and object-contract perspective. Runtime deployment is
available, and the first call completed the authorized two-run pilot and an
operational observation period exceeding 48 hours. Exactly one further call
has now been migrated with an unchanged target contract. The final legacy call
remains unchanged as the control for the next regular scheduled observation.

This plan contains no private script names, object IDs, Idents or captions. It
records only sanitized aggregate results from the separately authorized live
change.

## Read-only deployment evidence

| Check | Result |
|---|---|
| Legacy function available at runtime | Yes |
| `SAEF_EnsureVariable` available at runtime | Yes |
| Separate generated runtime artifact | Active and hash-verified |
| Live calls using `SAEF_EnsureVariable` | 2 |
| Four-argument pilot calls | 3 |
| Calls with direct parameter mapping | 3 |
| Existing names matching the caller contract | 3 |
| Existing names differing from the caller contract | 0 |
| Target parent relationship | All three targets are directly below the caller's parent |

The direct parent relationship is strong structural ownership evidence. The
private migration record must still state that each caller owns configuration of
its target variable; ancestry alone must not become an implicit public ownership
rule.

## Parameter mapping

The four supplied arguments map positionally:

| Legacy responsibility | SAEF responsibility | Pilot result |
|---|---|---|
| Parent object | `parentID` | Direct mapping; existing parent resolves. |
| Technical Ident | `ident` | Direct mapping; caller-local constant is SAEF-valid. |
| Display name | `name` | Direct mapping; current target name already matches. |
| Variable type | `type` | Direct mapping; current target type already matches. |

No pilot caller supplies position, icon, profile or action arguments.

## Behavioral differences

The replacement is not merely a function rename:

- the SAEF helper rejects invalid parents, Idents, names and variable types;
- it rejects an existing non-variable or a variable with the wrong type;
- it validates optional profiles and action scripts when supplied;
- it reconciles the display name on every run, whereas the legacy function
  leaves an existing object's name unchanged;
- it does not normalize an invalid Ident into another value.

The read-only checks remove the immediate pilot risk: all current names match,
all Idents are already valid, and all existing objects are compatible. The
stricter behavior is therefore expected to be observationally neutral for these
three calls.

## Deployment resolution

The repository helper remains the canonical source. A deterministic generated
bundle provides the minimal helper closure to the connected runtime through a
separate, hash-verified filesystem artifact and one private bootstrap include.
The legacy helper library remains unchanged.

Do not solve this by:

- copying helper bodies into each caller;
- introducing a private hardcoded script ID;
- evaluating source fetched from another Symcon object;
- adding a second convenience wrapper around the SAEF helper.

The deployment decision considered two supported SAEF deployment models:

1. a generated/bundled Symcon library script containing approved existing SAEF
   helpers and dependencies; or
2. a filesystem/module deployment that preserves the repository dependency
   structure.

ADR-0005 selected the generated-bundle option for the script-only pilot. It now
has a reproducible builder, manifest, provenance sidecars, offline tests,
isolated live smoke test and activated runtime deployment. No new public helper
API was introduced.

[`ADR-0005`](../adr/ADR-0005-generate-symcon-helper-bundles.md) now accepts the
first option for script-only Symcon environments: generate a minimal,
self-contained deployment bundle from canonical helper files while preserving
normal filesystem loading for modules and other file-based consumers. The build
contract is specified in
[`SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md`](SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md).

The subsequent
[`SAEF_SYMCON_BUNDLE_IMPLEMENTATION_READINESS.md`](SAEF_SYMCON_BUNDLE_IMPLEMENTATION_READINESS.md)
records the completed implementation, verification and deployment gates.

## Controlled pilot boundary

The approved pilot migrated only one of the three calls first. Its boundary
was:

- one caller source change;
- one existing target variable;
- no target recreation or deletion;
- no profile, action, position or icon change;
- no change to the other two calls;
- no removal of the legacy library function.

At that stage, the other two calls remained the control group until the pilot
passed twice. The current distribution is recorded in the status and next
decision sections.

## Pre-change snapshot

Record a private, temporary snapshot containing only what is needed for
rollback verification:

- caller source checksum and recoverable source backup;
- target object kind and variable type;
- target Ident and object identity;
- name, profile, custom action, position and icon;
- current value and archive association where applicable;
- target child count beneath the owning parent.

Do not place this snapshot in public SAEF artifacts. Do not include credentials,
topics, hostnames or unrelated objects.

## Two-run acceptance test

### First configuration run

The run passes only if:

- the existing target object identity is unchanged;
- no object is created, deleted or reparented;
- variable type and current value are unchanged;
- name, profile, action, position and icon remain unchanged;
- archive history and logging configuration remain attached;
- the caller finishes without validation or runtime errors.

### Second configuration run

Run the same caller again without changing configuration. It passes only if:

- the object-tree snapshot is identical to the first post-change snapshot;
- the target identity and all checked metadata remain unchanged;
- no additional log error, warning or duplicate object appears.

This second run is the idempotency proof. Function return success alone is not
sufficient.

## Rollback

If either run fails:

1. stop the pilot; do not migrate the other callers;
2. restore only the caller's previous source;
3. do not delete or recreate the target variable;
4. rerun the restored caller once;
5. compare the result with the pre-change snapshot;
6. record the failed gate without publishing installation data.

Rollback must preserve variable identity, value, links and archive history.

## Next decision

The first migrated call passed both live runs. Its target identity, value,
metadata, archive/link state and surrounding object structure were unchanged.
The real-device caller was executed only after a read-only safety gate proved
that no actor change or notification branch was expected. Both runs returned
success, and all temporary markers were removed.

The first subsequent regular scheduled execution also passed. The event
advanced at its configured cadence, retained its explicit action binding and
scheduled the next run normally. The caller hash and one-SAEF/two-legacy call
distribution remained unchanged. Target identity, value, metadata,
archive/link state and surrounding object structure remained stable. Runtime
inputs selected the expected already-off control branch; actor, state-machine
and error values were consistent with that branch. The temporary private
observation marker was removed and its absence verified.

The operational observation is complete. Dozens of regular executions retained
the expected event cadence, source identity, one-SAEF/two-legacy distribution,
target compatibility, object structure, archive/link state and domain values.
The final read-only check used direct bounded MCP result channels and required
no temporary live marker.

Exactly one of the two remaining calls has now been migrated. Direct source
read-back confirmed the intended two-SAEF/one-legacy distribution. The selected
target retained its identity, value, metadata, archive/link state and
surrounding object structure; the event schedule and domain state were also
unchanged. No manual caller execution, temporary live object or device action
was used for this verification.

The next gate is the first regular scheduled execution after this second
migration. The final legacy call remains a control until that observation has
passed. No third migration is authorized by this plan before that result.

**Caller contract: PASS — Runtime deployment: PASS — First live migration: PASS — Operational observation: PASS — Second migration immediate verification: PASS — Scheduled observation: PENDING.**
