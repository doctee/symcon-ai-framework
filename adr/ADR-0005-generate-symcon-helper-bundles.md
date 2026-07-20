# ADR-0005: Generate Symcon helper bundles from canonical helper files

Status: Accepted
Date: 2026-07-14

## Context

SAEF helpers are canonical PHP files with explicit relative dependencies. This
layout is appropriate for modules, filesystem deployments, tests and normal PHP
tooling.

Some IP-Symcon script installations instead make a shared script library
available through an installation-level autoload mechanism. A script in such an
installation cannot safely depend on the SAEF repository path, and a relative
`require_once` from a repository helper does not by itself define a portable
Symcon deployment.

The first `System.Functions.ips.php` migration pilot demonstrates this gap:

- the three caller contracts are compatible with `SAEF_EnsureVariable`;
- the existing target variables are compatible;
- the legacy helper library is available through the installation's autoload
  mechanism;
- `SAEF_EnsureVariable` and its validation dependency are not available in the
  connected runtime.

SAEF needs a deployment adapter without creating a second helper API, copying
helper bodies into callers or introducing installation ObjectIDs.

## Decision

Keep the files below `helpers/` as the only handwritten source of truth.

For Symcon script environments that cannot consume the filesystem layout,
generate a self-contained PHP helper bundle from an explicit manifest. The
bundle is a deployment artifact, not a new helper library.

The generation contract is:

1. A manifest selects public SAEF helper files required by a deployment.
2. The generator resolves the complete local dependency closure.
3. Dependencies are ordered deterministically before their consumers.
4. File-level PHP opening tags and relative `require_once` statements are
   removed or transformed only as part of generation.
5. Function implementations, names and contracts remain those of the canonical
   helpers.
6. The generated artifact carries source paths, SAEF version or revision,
   license/provenance information and a deterministic content hash.
7. Generated bundles contain no installation IDs, object names, topics,
   hostnames, credentials or deployment commands.
8. Generated files are never edited manually. Changes originate in canonical
   helpers and are regenerated.

The first pilot bundle should be minimal and contain only:

- `helpers/common/Validation.php`;
- `helpers/object/EnsureVariable.php`.

The installation decides how the generated script is imported and attached to
its existing autoload mechanism. That installation mapping is private
deployment configuration and is not embedded in the public bundle.

Filesystem and module consumers continue to load canonical helper files with
normal PHP dependency semantics. They do not consume the generated Symcon
bundle.

## Rationale

This separates reusable implementation from installation transport:

- helper behavior has one reviewable source;
- script-only Symcon installations receive a portable artifact;
- modules retain ordinary PHP loading and development tooling;
- the public API does not change;
- no private ObjectID is needed to locate another script at runtime;
- the bundle can be reproduced and compared by hash.

A minimal manifest also follows Reuse Before Extend: the pilot deploys only the
existing validation and variable helpers that it actually needs.

## Required safeguards

Before the first bundle is used in a live installation, its build and deployment
process must prove:

- deterministic output from identical source and manifest inputs;
- PHP syntax validity of the generated artifact;
- no unresolved relative includes;
- no duplicate function declaration when loaded once through the intended
  autoload path;
- clear failure behavior if an incompatible SAEF helper is already present;
- absence of private installation data;
- traceability from every bundled function to its canonical source file;
- deletion of temporary test scripts and markers used for verification.

The existing helper guards may be retained by the generated bundle. The build
must not silently hide an incompatible pre-existing implementation merely
because a guard constant or function name already exists.

## Consequences

### Positive

- One canonical helper implementation supports both filesystem and script-only
  Symcon environments.
- Live callers do not contain copied infrastructure code.
- Deployment artifacts can be versioned, hashed and reproduced.
- Pilot migrations can use the existing SAEF API without private script IDs.

### Negative

- SAEF needs a deterministic bundling and verification process.
- Generated artifacts add a release surface that must stay synchronized.
- Autoload activation remains installation-specific and needs a private
  deployment record.
- Bundle conflicts must be detected explicitly during deployment testing.

## Alternatives considered

### Copy helper functions into each caller

Rejected because it duplicates infrastructure logic and breaks Reuse Before
Extend.

### Maintain a handwritten Symcon version of each helper

Rejected because filesystem and Symcon implementations would drift and create
parallel public APIs in practice.

### Fetch and evaluate another Symcon script by ObjectID at runtime

Rejected because it introduces a private installation dependency, weakens
traceability and turns source retrieval into runtime behavior.

### Require filesystem deployment for every consumer

Rejected because it does not support established script-library installations
and would block otherwise compatible migrations.

### Bundle every SAEF helper by default

Rejected for the pilot because it increases surface area without demonstrated
need. Manifests should select the smallest complete dependency closure.

## Implementation gate

The architecture decision is accepted and the minimal implementation gate has
passed. The reviewed build design, deterministic builder, generated artifact,
offline verification and isolated live smoke test demonstrate the safeguards
above. Production deployment and caller migration remain separately authorized
operations with their own snapshot, rollback and observation requirements.

## Related

- `helpers/README.md`
- `helpers/common/Validation.php`
- `helpers/object/EnsureVariable.php`
- `principles/ENGINEERING_PRINCIPLES.md`
- `standards/SYMCON_STANDARDS.md`
- `project/SYSTEM_FUNCTIONS_MIGRATION_WAVE_1.md`
- `project/SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md`
- `project/SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md`
