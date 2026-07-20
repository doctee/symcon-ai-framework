# SAEF Symcon Bundle Live Smoke Report

## Scope

The minimal `saef-ensure-variable` bundle was tested in the connected
IP-Symcon runtime through MCP. The test used only disposable objects and did
not modify the production autoload configuration, existing callers or existing
variables.

This report intentionally omits all transient and installation-specific object
IDs, local script names and other private installation data.

## Procedure

1. Create one uniquely named temporary PHP script below the Symcon root.
2. Load the generated bundle as the complete script source.
3. Create one temporary owned category below that script.
4. Call `SAEF_EnsureVariable()` twice with the same configuration.
5. Store a bounded JSON result in the created string variable.
6. Read the category, variable metadata and result through MCP.
7. Replace the temporary script content with cleanup-only logic.
8. Delete the variable and category, then delete the temporary script.
9. Verify every deletion through MCP object lookup, child lookup and script
   name lookup.

## Results

| Check | Result |
|---|---|
| Generated bundle loads in live Symcon | PASS |
| `SAEF_EnsureVariable()` is available | PASS |
| First call creates one string variable | PASS |
| Second call returns the same object identity | PASS |
| Temporary category contains exactly the expected variable | PASS |
| No unrelated production object is modified | PASS |
| Temporary variable deleted | PASS — absence verified through MCP |
| Temporary category deleted | PASS — absence verified through MCP |
| Temporary script deleted | PASS — object and name absence verified through MCP |

## Decision

The generated bundle has passed its offline and isolated live-runtime gates.
This proves bundle loading and the minimal create/idempotency contract in the
connected Symcon runtime.

It does not authorize production autoload activation or caller migration. The
private deployment mapping and rollback plan have since been completed and
select a separate SAEF-only autoload artifact. Public SAEF artifacts contain no
target identity or other installation-specific data. Production activation and
the first caller migration remain separate gates.

## Subsequent activation evidence

A later activation attempt stored the reviewed bundle byte-identically in a
separate sibling script. A fresh runtime execution showed that the installation
autoloads only explicitly selected files, not every new `.ips.php` script. SAEF
therefore remained unavailable and the activation gate failed.

The planned rollback removed the new script and all diagnostic markers. MCP
verified restoration of the original object tree, legacy-function availability
and legacy source hash. No caller was migrated and no production artifact from
the failed activation remains.

A subsequent bootstrap assessment confirmed that a normal Symcon script object
does not create a freely named file in the directory used by the explicit
autoload configuration. The proposed relative include was not installed. The
local bootstrap remained byte-identical, and all disposable probe objects were
removed. Further activation requires a separately authorized physical file or
module deployment.

## Successful filesystem activation

The user later granted a one-time exception for a controlled physical file
deployment. The bundle was written atomically and verified by SHA-256 before a
single relative include was added to the privately backed-up local bootstrap.

Fresh runtime verification confirmed all SAEF exports and guards, unchanged
legacy-function availability, separate reflection provenance and matching
bundle, bootstrap and legacy hashes. A fresh idempotency smoke test through the
autoload path created one disposable string variable and returned the same
identity on its second Ensure call. All smoke-test objects and markers were
deleted and their absence was verified through MCP.

No caller was modified. Runtime deployment is ready for the separately gated
single-caller migration.

The later caller migration and operational observation are recorded separately
in `SYSTEM_FUNCTIONS_MIGRATION_WAVE_1.md` and
`SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md`.
