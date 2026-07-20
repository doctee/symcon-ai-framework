# SAEF Symcon Bundle Implementation Readiness

## Decision

The minimal Symcon bundle builder, manifest, generated artifacts, offline tests
and isolated live Symcon smoke test are complete. Production autoload
activation and caller migration remain separate, installation-specific changes
that require their own snapshot, rollback plan and authorization.

The canonical source closure is finite, static and compatible with the
token-aware builder. Implementation was performed only after separate
authorization; the original read-only `System.Functions.ips.php` assessment did
not itself authorize code changes.

## Canonical closure

The requested entry file is:

```text
helpers/object/EnsureVariable.php
```

Its complete file-level dependency closure is:

```text
helpers/common/Validation.php
        ↓
helpers/object/EnsureVariable.php
```

No dynamic include, external path, cycle or third-party source occurs in this
closure.

## Export surface

File-level bundling necessarily exports all guarded functions in the validation
dependency, even though the pilot variable helper uses only four of them.

| Canonical file | Exported function |
|---|---|
| `Validation.php` | `SAEF_ValidateParentObject` |
| `Validation.php` | `SAEF_ValidateIdent` |
| `Validation.php` | `SAEF_ValidateVariableType` |
| `Validation.php` | `SAEF_ValidateObjectName` |
| `Validation.php` | `SAEF_ValidateModuleGuid` |
| `Validation.php` | `SAEF_ValidateScriptType` |
| `EnsureVariable.php` | `SAEF_EnsureVariable` |

The bundle therefore exports seven existing functions. This does not create a
new API, but the generated provenance and export test must name all seven. The
builder must not perform function-level tree shaking because that would create
a second transformed implementation surface and weaken source traceability.

## Top-level transformation inventory

The two canonical files contain only the top-level forms expected by the build
design:

- PHP opening tags;
- `declare(strict_types=1)`;
- documentation comments;
- one literal `require_once` based on `__DIR__`;
- `defined()` guards;
- literal guard `define()` calls;
- function declarations inside those guards.

No closing PHP tag, inline output, runtime configuration, credential, private
ObjectID or external implementation block is present.

The builder can therefore use a strict allowlist and fail on any future
top-level construct outside this inventory.

## Runtime dependency inventory

The closure calls the following Symcon functions:

- `IPS_ObjectExists`;
- `IPS_GetModuleList`;
- `IPS_VariableProfileExists`;
- `IPS_ScriptExists`;
- `IPS_GetObjectIDByIdent`;
- `IPS_CreateVariable`;
- `IPS_SetParent`;
- `IPS_SetIdent`;
- `IPS_GetObject`;
- `IPS_GetVariable`;
- `IPS_SetName`;
- `IPS_SetPosition`;
- `IPS_SetIcon`;
- `IPS_SetVariableCustomProfile`;
- `IPS_SetVariableCustomAction`.

All are declared in `stubs/symcon.php`, so the canonical closure already has
static-analysis coverage. The existing stubs are declaration-only and cannot
serve as a behavioral fake runtime.

## Behavioral harness decision

Bundle behavior tests should remain dependency-free executable PHP scripts,
matching the repository's current lightweight fixture-test style.

The bundle test process should:

1. define stateful fake implementations of only the required Symcon functions;
2. load the generated bundle once;
3. run isolated scenarios in separate PHP processes when global function state
   or guard constants would otherwise leak between cases;
4. fail with a non-zero process status on the first assertion failure;
5. emit no installation data or live-system calls.

Do not include `stubs/symcon.php` in the behavioral process because its global
empty functions would conflict with stateful fakes.

## Quality-check integration

The repository checks now cover the canonical sources and bundle toolchain:

| Area | Implemented state | Verification |
|---|---|---|
| PHP syntax lint | Builder, bundle tests and generated PHP artifact included | `composer lint` |
| PHPStan | Builder and generated artifact analyzed with Symcon stubs | `composer phpstan` and `composer phpstan:bundle` |
| PHPCS | Handwritten builder and bundle tests included | `composer phpcs` |
| Behavioral tests | Generated artifact executed against a stateful fake runtime | `composer test:bundles` |
| Determinism | Two independent temporary builds compared byte-for-byte | `composer test:bundles` |
| Generated drift | Temporary output compared without overwriting `dist/` | `composer bundle:check` |

`make check` must invoke all of these before a generated artifact can be reviewed
for deployment.

## Canonical lookup-error decision

The `SAEF_EnsureVariable` lookup contract has been verified against the connected
Symcon runtime:

| Case | Return | PHP error | Exception |
|---|---|---|---|
| Existing Ident | Existing object ID | None | No |
| Missing Ident without suppression | `false` | One reportable `E_WARNING` | No |
| Missing Ident with `@` | `false` | Same event, not reportable | No |

The narrow suppression is accepted and documented in the PHP standard because:

- `SAEF_ValidateParentObject` runs first;
- `SAEF_ValidateIdent` runs first;
- the expression contains one read-only lookup;
- `false` is handled immediately as the create branch;
- exceptions remain unaffected by `@`;
- replacing `@` with a temporary warning handler would suppress the same warning
  scope unless it depended on unstable or localized message text;
- scanning children manually would change lookup semantics and add avoidable
  traversal and race behavior.

The builder must preserve the canonical expression. It may not remove the
suppression, broaden it or introduce a wrapper. Existing and missing lookup
branches belong in the offline behavioral test matrix.

## Implemented boundary

The first authorized implementation was limited to:

```text
bundles/symcon/ensure-variable.bundle.json
tools/build-symcon-bundle.php
tests/bundles/FakeSymconRuntime.php
tests/bundles/ensure-variable-bundle.php
dist/symcon/saef-ensure-variable.php
dist/symcon/saef-ensure-variable.php.sha256
dist/symcon/saef-ensure-variable.sources.json
composer.json
Makefile
tools/lint.php
phpstan.neon
phpcs.xml
```

Changes to `helpers/` are outside this boundary unless the error-suppression
review separately authorizes them. No caller, live Symcon script, public helper
signature or installation configuration belongs in the builder change.

## Acceptance gates for builder implementation

| Gate | Status |
|---|---|
| ADR-0005 accepted | PASS |
| Repository license consistent | PASS |
| Minimal dependency closure known | PASS |
| Export surface enumerated | PASS |
| Top-level token forms bounded | PASS |
| Static Symcon stubs complete | PASS |
| Behavioral fake-runtime design selected | PASS |
| Canonical lookup suppression reviewed | PASS — narrow exception documented |
| Builder and manifest implemented | PASS |
| Determinism and drift tests implemented | PASS |
| Generated artifact independently verified | PASS — offline |
| Isolated live Symcon smoke test | PASS — MCP, disposable objects removed and verified |

## Deployment progression

The private installation mapping, pre-change snapshot, conflict preflight and
rollback plan are complete. It selects a separate SAEF-only autoload artifact
instead of modifying the mixed-origin legacy helper library. No installation
identity or private source is recorded in public SAEF files.

An activation attempt using a separate sibling script passed content-integrity
checks but did not make the SAEF functions available: the connected
installation's autoloader includes specific files and does not discover every
new `.ips.php` script. The failed gate triggered the planned rollback. Object
tree, legacy functions and legacy source hash were verified as unchanged, and
all temporary objects were removed.

The next separately authorized step is evaluation of a minimal bootstrap from
an already included installation file or a filesystem/module deployment. That
evaluation requires its own private snapshot and provenance review. The first
caller migration remains a later, separate approval.

The existing local bootstrap has now been privately captured, backed up and
reviewed. A separate Symcon script object does not materialize as an arbitrary
named file beside the installation's explicitly autoloaded files. The planned
relative include was therefore not added, and the disposable script was
removed with full state verification.

Activation now requires an explicitly authorized physical filesystem
deployment or a module deployment. Runtime source retrieval by private ObjectID
and mixed-source bundle appending remain rejected. Caller migration is still on
hold.

The user subsequently authorized a one-time controlled physical deployment.
The bundle was written atomically, verified by SHA-256 and loaded by one minimal
relative include in the privately backed-up local bootstrap. Fresh runtime
verification passed for all SAEF exports and guards, all inventoried legacy
functions, reflection provenance and source hashes. A separate two-run
idempotency smoke test passed and all disposable objects were removed.

Runtime deployment is now **PASS**. The next gate is the separately authorized
single-caller migration defined in the pilot deployment plan. The autoload
deployment must remain unchanged during that migration.

The sanitized smoke-test evidence is recorded in
[`SAEF_SYMCON_BUNDLE_LIVE_SMOKE_REPORT.md`](SAEF_SYMCON_BUNDLE_LIVE_SMOKE_REPORT.md).
