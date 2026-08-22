# ADR-0008: Use manifest-driven publication for standalone Symcon modules

Status: Accepted
Date: 2026-08-22

## Context

SAEF builds deterministic IP-Symcon module filesets from canonical case-study
sources. Publishing those filesets to standalone module repositories currently
uses module-specific procedures. Open-Meteo has a guarded publisher with exact
hash, inventory, privacy, remote-drift and independent-clone checks, while
MediaCarousel was published through a manually coordinated pull-request
sequence.

The Open-Meteo implementation proves the required safety properties, but its
contract, implementation functions, command names and direct-main behavior are
specific to one module. Copying that implementation would duplicate security
logic and make future fixes inconsistent.

## Decision

SAEF uses one internal, manifest-driven publication engine for standalone
IP-Symcon module repositories.

The engine is operational tooling, not a public SAEF helper API. Module-specific
values remain in strict versioned JSON contracts. A generic CLI accepts an
explicit contract path, while compatibility entrypoints may bind one established
contract without changing their existing command line.

Every contract declares:

- logical publication identity;
- public and clone repository identities;
- base branch and publication mode;
- repository-specific confirmation token;
- generated fileset, source-map and sidecar paths;
- exact metadata mappings and complete publication inventory;
- bounded privacy-marker policy; and
- deterministic topic-branch and pull-request metadata when PR publication is
  selected.

Unknown fields, unsafe paths, symbolic links, unclassified files and repository
identity drift are rejected.

The first generic implementation supports two explicit modes:

1. `pull_request`, which publishes to a deterministic topic branch and creates
   a non-draft pull request; this is the default contract for new modules.
2. `direct_branch`, retained only for the migrated Open-Meteo contract so its
   established observable behavior remains unchanged. Removing this mode is a
   separately reviewed migration.

Publication authorization is phase-based. One approval may cover one fixed,
hash-pinned publisher invocation and its internal bounded Git/GitHub commands.
Pull-request merge, live Symcon update and destructive retention cleanup remain
separate authorization gates.

## Required safeguards

The implementation must:

- make `--check` strictly read-only and `--prepare` local-only;
- require exact fileset hash, full remote commit, confirmation token and bounded
  commit message for `--apply`;
- run subprocesses through argument arrays;
- clone into a new private temporary workspace;
- stage only contract-classified paths;
- recheck the remote base immediately before push;
- independently clone and verify the pushed tree;
- preserve recovery evidence after any attempted remote mutation followed by
  failure; and
- never merge or access a live Symcon installation.

## Rationale

This applies Reuse Before Extend to an already proven workflow. One engine keeps
the high-risk checks consistent, while declarative contracts keep repository and
module identity outside generic code. PR publication gives new modules a review
and CI boundary without weakening deterministic candidate construction.

Keeping the tool outside `helpers/` avoids presenting repository operations as a
runtime API. A compatibility adapter allows Open-Meteo equivalence to be tested
before its historical direct-main workflow is retired.

## Consequences

### Positive

- Publication safety fixes apply to every contracted module.
- New modules need a reviewed manifest rather than copied process code.
- One fixed CLI prefix can cover an explicitly authorized publication phase.
- PR, merge and live-operation boundaries remain independently auditable.

### Negative

- Contracts contain an intentionally verbose complete inventory.
- GitHub pull-request creation adds a `gh` CLI dependency for PR-mode apply.
- The temporary direct-branch compatibility mode remains a larger mutation
  boundary than PR publication.

## Alternatives considered

### Copy the Open-Meteo publisher

Rejected because duplicated security logic would drift.

### Always publish directly to the base branch

Rejected because it combines publication and integration and bypasses the
review boundary required for new modules.

### Use GitHub Actions for publication

Deferred because workflow changes would create an additional remote execution
and credential boundary. The local fixed command is already compatible with
narrow command-prefix authorization.

### Add publication functions to the SAEF helper API

Rejected because repository publication is development tooling, not reusable
IP-Symcon runtime behavior.

## Related

- `project/GENERIC_MODULE_PUBLICATION_DESIGN.md`
- `project/WORKSTREAM_COORDINATION.md`
- `adr/ADR-0003-private-overlay.md`
- `adr/ADR-0005-generate-symcon-helper-bundles.md`
- `tools/publish-symcon-module.php`
- `tools/publication/`
