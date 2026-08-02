# Testing Standards

Status: Draft

## 1. Document expected behavior

Helpers and templates should describe expected inputs, outputs, and edge cases.

## 2. Prefer reproducible tests

Where possible, test cases should be executable without a live private Symcon installation.

## 3. Live-system tests

Tests requiring a real Symcon installation must be clearly marked as integration tests.

## 4. Safety

Tests must not unexpectedly switch real devices or modify production state.

## 5. Live-system change gate

A change to an authorized live IP-Symcon installation shall be treated as a
separate integration gate after repository and offline verification.

Before changing a live script or object:

- read the complete authorized source through the preferred read-only MCP
  operation;
- record a private source hash and a recoverable private backup;
- define the exact caller, object and property scope;
- snapshot object identity, value, relevant metadata, parent and child
  structure, archive configuration and links where applicable;
- define a byte-exact or otherwise deterministic rollback;
- predict whether executing the changed caller could issue a device action,
  notification or other external side effect.

For object mutations, validate the concrete target immediately before the
write. ObjectID `0` is the root category and must be rejected as a mutation
target unless changing the root itself is the explicitly authorized operation.
After any `IPS_Create*()` call, verify a positive ID, object existence and the
expected object type before the first presentation or parent mutation.

Do not infer permission for unrelated live objects from authorization for one
caller or migration cohort.

## 6. Staged live verification

Apply one independently reviewable live change at a time. Keep an unchanged
control where practical.

Use the least invasive verification stage that proves the required property:

1. offline tests and generated-artifact checks;
2. isolated live smoke test without production callers;
3. explicitly authorized caller execution only when side effects are predicted
   and accepted;
4. regular scheduled execution and bounded operational observation.

Do not execute a production caller manually merely to prove source deployment
when its next regular event provides an equivalent and safer observation.

Idempotent configuration changes should be verified twice when execution is
safe. The second verification must confirm stable object identity and structure,
not only a successful function return.

## 7. MCP result evaluation

Follow `project/SYMCON_MCP_SCRIPT_READBACK.md` for authorized live inspection.

- Prefer `symcon_get_script_content` for source reads.
- Use `symcon_run_script_text_ex` only for bounded probes.
- Evaluate `transportError` and `executionError` separately.
- Treat `truncated: true` as an incomplete result.
- Bound `maxOutputBytes` to the expected aggregate result.
- Prefer direct result channels over temporary live objects.

Temporary scripts, variables, events or markers require explicit authorization.
Delete them immediately after use and verify their absence through a separate
read-back.

## 8. Acceptance and rollback

A live migration passes only when all declared invariants remain satisfied.
Relevant invariants may include:

- expected source hash and exact call distribution;
- preserved object identity, value and variable type;
- preserved Ident, parent, profile, action, visibility, position and icon;
- unchanged archive logging, aggregation and link targets;
- no duplicate, deleted or reparented object;
- expected event `LastRun` and `NextRun` progression;
- domain values consistent with the observed runtime inputs;
- no unexplained device action, notification or error transition.

Observation duration shall be risk-based and include enough regular executions
to cover the relevant operating cycle. A time period alone does not replace
the invariant checks.

On failure, stop the migration cohort. Preserve the managed target object and
restore only the changed source or configuration from the prepared rollback.
Do not delete and recreate variables merely to recover from a caller migration.

## 9. Static analysis boundaries for runtime fakes

PHP test entrypoints that emulate IP-Symcon functions in the global namespace
shall not be analyzed in the same PHPStan process as production candidates or
an incompatible test-fake family.

Global PHP functions cannot be namespaced or replaced independently once they
share one static-analysis symbol table. A narrow test double such as an
`IPS_GetVariable()` implementation exposing only timestamp fields can otherwise
replace the canonical API signature for unrelated files and produce false
offset, dead-code and constant-condition findings.

Required practice:

- Reusable helpers, templates and production candidates use the canonical
  Symcon stubs from `stubs/` in the main PHPStan configuration.
- Canonical stubs should model the complete documented API structure needed by
  SAEF rather than the reduced shape of one test double.
- Independently executable tests with global Symcon fakes are excluded from the
  shared production PHPStan process.
- Those tests remain subject to PHP syntax checking and executable regression
  tests.
- If static analysis of such tests is added, run each compatible fake family in
  a separate PHPStan configuration or process together with only its matching
  implementation files.
- Do not weaken production types, suppress genuine findings or disable strict
  PHPDoc evaluation to accommodate a conflicting test fake.

When analysis boundaries change, verify both the complete production PHPStan
gate and every executable test group. A green production gate alone does not
replace test execution, and passing tests do not replace production static
analysis.
