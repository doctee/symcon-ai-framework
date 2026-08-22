# Generic Symcon Module Publication Design

**Status:** Implementation design
**Assessment date:** 2026-08-22

## Purpose

This document records the inventory, architecture boundary and migration plan
for generic standalone IP-Symcon module publication in SAEF. It contains no
installation-specific data and authorizes no remote or live operation.

## Baseline inventory

### Open-Meteo publisher

`tools/publish-open-meteo-module.php` is the proven source implementation. Its
824 lines currently combine:

- CLI parsing;
- a hard-coded contract validator;
- candidate construction from the deterministic module fileset;
- metadata and privacy validation;
- local prepare-tree writing and byte verification;
- Git baseline and remote-drift checks;
- direct publication to `main`;
- independent post-push clone verification; and
- temporary-workspace cleanup or retention.

The established read-only baseline is 44 publication files: 40 generated
payload files, two fileset sidecars, `LICENSE` and `README.md`. At extraction
start, `--check`, the focused publication regression and both relevant module
fileset checks passed.

Current entrypoints and callers are:

- `make open-meteo-publication-check`;
- `make open-meteo-publication-prepare`;
- `make test-open-meteo-publication`;
- documented `--check`, `--prepare` and `--apply` commands in the Open-Meteo
  case study; and
- PHPStan coverage through `case-studies/open-meteo/phpstan.neon`.

Observable Open-Meteo behavior must remain equivalent during extraction,
including JSON result fields, idempotent `unchanged`, exact hashes and the
explicit legacy direct-main apply mode.

### MediaCarousel

MediaCarousel already uses `tools/build-symcon-module-fileset.php`. Its generated
fileset contains seven payload files plus `fileset.sources.json` and
`fileset.sha256`. Publication adds canonical `LICENSE` and the case-study
publication `README.md`, producing an exact eleven-file standalone tree.

The handover records the published 0.1.2 fileset hash and standalone commit. The
completed module workstream is not reconstructed; the canonical distribution
and generated fileset in current `main` are the publication inputs.

### Navimow publication utility

`case-studies/navimow/tools/prepare-mqtt-spike-publication.php` is a specialized
temporary-probe staging tool. It contributes useful manifest and byte-identity
tests, but it changes an existing standalone working tree and does not implement
the same module-publication lifecycle. It remains case-study-local and is not a
caller of the generic engine.

### Build and test integration

The repository uses Composer scripts for general checks and Make targets for
Open-Meteo publication. There is no existing generic publication Composer
entrypoint. Tests are standalone PHP programs and use temporary directories,
local Git repositories and command-array subprocesses rather than a testing
framework.

## Architecture boundary

The extracted subsystem belongs below `tools/publication/` because it is
repository tooling. It does not belong in `helpers/`, `references/` or a module
case study.

```text
tools/
  publish-symcon-module.php           Generic CLI
  publish-open-meteo-module.php       Compatibility adapter
  publication/
    ModulePublication.php             Engine and strict contract

deployments/symcon/
  open-meteo-publication.json         Explicit legacy direct-branch contract
  media-carousel-publication.json     PR-based contract
```

The generic CLI requires `--contract=PATH`. The Open-Meteo adapter injects its
contract and preserves the established interface. No contract may select a live
Symcon target. PR contracts may declare a repository-specific confirmation for
the separately invoked integration phase.

## Operating modes

### Check

Build and validate the candidate without creating a clone or target directory.
The result includes publication name, repository, base branch, publication
mode, file count, fileset hash and publication hash.

### Prepare

Write the exact candidate to a new local target and verify every byte. Existing
targets, links, unsafe paths and additional files fail closed.

### Apply

Validate all immutable gates before network access. Clone the expected base,
verify repository identity and clean state, write and stage only classified
paths, commit, recheck remote drift, push, independently verify and optionally
create a non-draft pull request.

For PR mode, branch creation and push are part of the authorized publication
phase. A failure after push retains the workspace and
reports its path for recovery.

### Integrate

Validate the exact candidate hashes, unchanged base commit, PR number, full PR
head and integration token. Independently verify the topic branch, reject draft,
changed, unmergeable, pending or failed PRs, merge with exact-head protection,
then independently verify the merged base commit and complete target tree.

## Approval model

1. Candidate check is read-only.
2. One exact publisher invocation may authorize the bounded repository
   publication phase.
3. PR merge requires a separate fixed integration invocation after CI and
   review; its internal reads, merge and postflight share that one authorization.
4. Any live Symcon update requires fresh installation-specific MCP preflight and
   separate authorization.
5. Recovery-workspace or artifact deletion requires a separate retention gate.

Platform sandbox approval remains independent of SAEF authorization. The
recommended persistent prefix is limited to:

```text
php tools/publish-symcon-module.php
```

## Migration and compatibility

Open-Meteo first receives characterization tests, then delegates to the generic
engine with an explicit `direct_branch` contract. Candidate hashes and prepared
trees must remain byte-identical.

MediaCarousel is the second contract and uses `pull_request`. Its `--check` and
`--prepare` results must produce the exact eleven-file publication tree. No
remote apply is performed as part of this implementation workstream.

On 2026-08-22, a read-only clone of public standalone `main` at
`73472c01b8239087626c876c3d505e00d3f280de` was compared with a newly prepared
candidate. All eleven paths and bytes matched. The verified fileset SHA-256 is
`539e4e3090c4e776e22a056ba68becde033be2b77eebf165cccf835e8dfc54af`; the
publication SHA-256 is
`71d67ecbd61dedba2d00bc57daa9a2ca08c25eca1da4a0b799a508ee75f089d1`.
The comparison performed no push, pull-request creation, merge or live action.

## Non-goals

- No Symcon MCP call or installed-module update.
- No merge during `--apply`; integration is always a separate explicit mode.
- No standalone-history rewrite.
- No cleanup of publication or historical worktrees.
- No general-purpose Git automation API.
