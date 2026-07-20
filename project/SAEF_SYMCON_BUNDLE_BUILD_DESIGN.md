# SAEF Symcon Bundle Build Design

## Status and scope

This document specifies the deterministic build and verification process
required by accepted `ADR-0005`. The minimal pilot implementation now follows
this design. It introduces generated deployment artifacts, but no new public
helper API.

The first bundle is intentionally minimal. Its canonical source closure is:

1. `helpers/common/Validation.php`;
2. `helpers/object/EnsureVariable.php`.

The target is a self-contained PHP artifact suitable for an
installation-managed Symcon autoload mechanism. Canonical filesystem and module
consumers continue to use the source files directly.

The implementation-readiness findings are recorded in
[`SAEF_SYMCON_BUNDLE_IMPLEMENTATION_READINESS.md`](SAEF_SYMCON_BUNDLE_IMPLEMENTATION_READINESS.md).

## Non-goals

The first build process does not:

- bundle the complete helper library;
- discover helpers from live Symcon;
- deploy or activate an autoload script;
- contain installation object IDs or private configuration;
- rewrite function names or signatures;
- introduce a runtime loader API;
- copy Wilkware or other external implementation code;
- migrate a production caller.

## Artifact set

The pilot uses the following artifact set:

```text
bundles/
  symcon/
    ensure-variable.bundle.json
dist/
  symcon/
    saef-ensure-variable.php
    saef-ensure-variable.php.sha256
    saef-ensure-variable.sources.json
tools/
  build-symcon-bundle.php
tests/
  bundles/
    ensure-variable-bundle.php
```

`bundles/` contains reviewed build intent. `dist/` contains generated release
artifacts. `tools/` contains the deterministic builder. `tests/bundles/`
contains offline verification. These paths were created under the separately
authorized bundle implementation.

## Manifest contract

The manifest is declarative build input, not a runtime API. The first manifest
needs only:

| Field | Purpose |
|---|---|
| `formatVersion` | Reject unsupported manifest semantics. |
| `name` | Stable artifact name used for output selection. |
| `entries` | Canonical helper entry files requested by the bundle. |
| `phpMinimum` | Minimum PHP version expected by canonical source. |
| `output` | Repository-relative generated PHP path. |
| `exports` | Sorted, exact function export allowlist for drift detection. |

For the pilot, `entries` contains only
`helpers/object/EnsureVariable.php`. The validation file is discovered through
the dependency closure and must not require a duplicate manual entry.

All manifest paths must be normalized repository-relative paths below
`helpers/`. Absolute paths, parent traversal, URLs and live Symcon references
are rejected.

## Dependency discovery

The builder reads canonical files only. It identifies local dependencies from
static `require` and `require_once` expressions based on `__DIR__` and a literal
relative path.

The builder must fail if it encounters:

- a dynamic include expression;
- a dependency outside `helpers/`;
- a missing file;
- a dependency cycle;
- the same canonical path with inconsistent normalization;
- executable top-level logic other than declarations, guards and dependency
  loading that the bundle design has not explicitly approved.

For the pilot, the expected graph is:

```text
helpers/common/Validation.php
        ↓
helpers/object/EnsureVariable.php
```

Dependencies are emitted before consumers. Lexicographic canonical paths break
ties so graph traversal order cannot affect output.

## Deterministic transformation

The builder performs a token-aware transformation. Text replacement alone is
not sufficient.

For each ordered canonical file it:

1. verifies PHP syntax before transformation;
2. removes the file-level PHP opening tag;
3. consolidates `declare(strict_types=1)` into one bundle-level declaration;
4. removes only dependency statements already represented in the resolved
   graph;
5. preserves declarations, documentation, guard constants, functions and their
   order within the canonical file;
6. rejects unexpected closing tags or inline non-PHP content;
7. requires LF-only canonical sources and emits exactly one final newline.

The generated PHP file begins with:

- one PHP opening tag;
- one strict-types declaration;
- a generated-file warning;
- ordered canonical source paths;
- a source-input hash;
- repository revision or release version;
- resolved license/provenance metadata.

The source-input hash covers the normalized manifest plus the raw bytes of all
ordered canonical source files. It is not a self-hash. The final artifact hash
is stored in the `.sha256` sidecar to avoid a self-referential header.

## Source map

The `.sources.json` sidecar records only public build provenance:

- manifest path and format version;
- ordered canonical source paths;
- SHA-256 hash of every source file;
- source-input hash;
- final artifact hash;
- builder version or revision;
- minimum PHP version.

It contains no timestamps, absolute filesystem paths, usernames or installation
data. Omitting timestamps is necessary for byte-for-byte reproducibility.

For the first bundle, the expected export set is exactly the six validation
functions declared by `Validation.php` plus `SAEF_EnsureVariable`. File-level
dependency integrity takes precedence over function-level tree shaking.

## License policy

The repository license and Composer metadata consistently declare
`PolyForm-Noncommercial-1.0.0`. Generated bundles derived only from canonical
SAEF helpers use the same license and retain the required license URL and public
provenance.

Commercial use, including internal use by a for-profit business, requires a
separate written commercial license from the SAEF copyright holder. The bundle
must not imply that commercial rights are included in the public artifact.

The Wilkware CC BY-NC-SA license must not be attached to this bundle because no
Wilkware implementation belongs in its source closure. Any future third-party
source requires a separate compatibility and provenance decision before it can
enter a bundle manifest.

## Conflict policy

The first deployment targets a runtime in which no `SAEF_EnsureVariable`
definition or related SAEF validation definition is present. This absence has
already been confirmed read-only for the pilot installation.

The deployment process must fail before import if the target runtime already
contains any function or guard constant exported by the bundle, unless an
explicit version-compatible update process has been designed and tested.

Do not rely on existing guard constants to silently skip unknown implementations.
PHP cannot reliably prove that an already loaded function has identical source.
The initial deployment therefore requires a clean SAEF helper namespace in the
target runtime.

### Cross-fileset ownership and updates

When the same canonical global helper is present in multiple generated bundles
or filesets, the artifact loaded first by the installation autoloader is the
effective runtime owner. Later `require_once` calls and guard constants prevent
redeclaration; they do not select the later artifact's helper version.

Every deployment record for such an artifact shall therefore include:

- the autoload include order and earliest owning artifact;
- the deterministic source identity of the effective helper;
- all known bundle, fileset and runtime consumers;
- whether a clean PHP context or service restart is required; and
- an after-start Reflection check of the effective file and source hash.

A shared-helper update must be activated through the earliest owner before a
later consumer can claim to use it. Selecting only the later consumer is not a
valid rollout, even when its complete dependency closure contains the corrected
source.

## Canonical lookup behavior

The generated bundle preserves the approved narrow
`@IPS_GetObjectIDByIdent()` suppression from `SAEF_EnsureVariable`. Runtime
evidence confirms that a missing Ident produces `false` plus one `E_WARNING`, not
an exception. Parent and Ident validation precede the call, and `false` is
handled immediately as the idempotent creation branch.

The builder must not rewrite this expression. Offline behavior tests must cover
existing and missing Idents so generated code cannot drift from the canonical
error contract.

## Offline verification matrix

The generated artifact must pass all checks without a private Symcon system.

| Check | Expected result |
|---|---|
| Build twice from identical inputs | Byte-identical PHP and sidecars. |
| PHP syntax check | Success on the supported PHP version. |
| Static analysis with Symcon stubs | No new error relative to canonical helpers. |
| Coding-standard source checks | Canonical sources pass; generated layout is verified separately. |
| Dependency scan | No `require`, `include`, `__DIR__` or unresolved source path remains. |
| Export scan | Only the expected validation functions and `SAEF_EnsureVariable` are exported. |
| Private-data scan | No installation IDs, hostnames, topics, credentials or absolute local paths. |
| Provenance verification | Every bundled declaration maps to a canonical source file. |
| Hash verification | Source-input and final artifact hashes match their sidecars. |

## Behavioral test matrix

Using the existing Symcon stubs or an isolated test harness, verify at least:

- creation below a valid parent;
- second-run idempotency;
- existing compatible variable reuse;
- rejection of an existing non-variable with the same Ident;
- rejection of an existing variable with the wrong type;
- invalid parent rejection;
- invalid Ident rejection;
- empty name rejection;
- invalid variable type rejection;
- missing profile rejection when a profile is supplied;
- missing action script rejection when an action is supplied;
- preservation of the existing variable identity and value;
- deterministic reconciliation of supported metadata.

The tests exercise the generated artifact, not a separately reconstructed copy
of the helper logic.

## Live verification boundary

After offline verification, a live Symcon smoke test may
use an explicitly authorized temporary script. It must:

1. run in an isolated temporary object container;
2. verify that the bundle loads once without declaration conflicts;
3. create only disposable test objects owned by the temporary container;
4. exercise create and second-run behavior without touching production targets;
5. delete all test objects, the script and result markers;
6. verify cleanup through MCP.

Production autoload activation and caller migration remain separate approvals.

## Integration with repository checks

`make check` includes a bundle verification stage that:

1. validates manifests;
2. builds into a temporary directory;
3. compares temporary output with tracked generated artifacts, if artifacts are
   committed;
4. runs syntax, static, export, privacy and hash checks;
5. fails on generated drift.

The builder must never overwrite tracked output during a normal check. Updating
generated artifacts requires an explicit build command followed by review.

## Implementation sequence

1. Review this design against `ADR-0005`, PHP standards and testing standards.
2. Add the minimal manifest and builder in one focused change.
3. Add deterministic-build, dependency and behavioral tests.
4. Generate the minimal artifact and sidecars.
5. Run the full repository checks twice from a clean temporary output directory.
6. Review the generated diff and provenance manually.
7. Perform the isolated live smoke test under separate authorization.
8. Only then revisit the single-caller migration pilot.

## Exit criteria

Bundle implementation is ready for pilot deployment only when:

- `ADR-0005` remains accepted;
- license metadata is consistent;
- two independent builds are byte-identical;
- offline behavior and conflict tests pass;
- the generated artifact contains exactly the selected dependency closure;
- private-data and provenance checks pass;
- an isolated Symcon smoke test passes and cleans up completely;
- no new public helper API has been introduced.
