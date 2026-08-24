# Testing Standards

Status: Draft

## 1. Document expected behavior

Helpers and templates should describe expected inputs, outputs, and edge cases.

## 2. Prefer reproducible tests

Where possible, test cases should be executable without a live private Symcon installation.

## 3. Live-system tests

Tests requiring a real Symcon installation must be clearly marked as integration tests.

## 4. Isolated worktree toolchains

An isolated worktree may reuse a canonical Composer installation by setting
`COMPOSER_VENDOR_DIR`. Without that variable, checks use the worktree-local
`vendor` directory. Relative values are resolved from the repository root.

External tools are validation input only. Their parent `composer.lock` must be
byte-identical to the worktree lock file, and required analyzer executables
must exist before any analyzer runs. Resolution must not copy source, install
dependencies or access the network. Missing, incomplete or lock-mismatched
toolchains fail closed with a deterministic diagnostic.

## 5. Safety

Tests must not unexpectedly switch real devices or modify production state.

## 6. Live-system change gate

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

## 7. Staged live verification

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

## 8. MCP result evaluation

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

## 9. Acceptance and rollback

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

## 10. Live evidence closure

An authorized live mutation or real-device test is not complete when the
runtime check passes. Its evidence and the framework's current-state claims
must also be reconciled.

Keep exact installation evidence in a private machine-readable artifact. JSON
is recommended for deterministic review and follow-up automation. The artifact
should record, where applicable:

- a format version, UTC timestamp, phase and outcome;
- the explicit authorization kind and bounded scope;
- MCP transport errors, PHP execution errors and output truncation as separate
  fields;
- package, source, configuration or activation hashes needed to identify the
  tested candidate;
- whether mutation and device actions were attempted, including the exact
  intended action count;
- initial, intermediate and final domain values;
- diagnostic counter baselines and deltas;
- compensation, rollback and initial-state restoration outcomes;
- the size and result of any source or topology regression; and
- an explanation when asynchronous feedback produces a later settled counter
  state that differs from the immediate test result.

Private evidence may contain installation ObjectIDs, names and paths only below
`private/` or in an ignored `*.local.*` artifact. Do not copy those details into
public documentation.

Add or update a sanitized public report when the live result changes a
framework case study, current support statement, migration classification or
regression fixture. The report explains the engineering decision and result,
while the private JSON retains exact reproducibility evidence.

After a successful live gate:

1. preserve release reports and dated readiness reports as historical
   snapshots;
2. update only current-status documentation and sanitized fixtures;
3. update executable expectations derived from those fixtures;
4. add an Unreleased changelog entry when the repository's documented
   capability or current rollout state changed; and
5. rerun the relevant focused tests followed by the complete repository gate.

A private JSON file without current fixture reconciliation is incomplete when
the repository claims to model the live cohort. A public report without exact
private evidence is incomplete when installation-specific rollback or audit
details are required.

## 11. Static analysis boundaries for runtime fakes

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

## 12. Standalone module publication

Repository publication is an independently authorized engineering phase. It is
not a live Symcon operation and does not authorize pull-request merge, Module
Control updates or retention cleanup.

A reusable standalone-module publisher shall provide:

1. a read-only candidate check without clone or remote mutation;
2. a deterministic local prepare mode that requires a new target;
3. exact contract, inventory, fileset and publication hashes;
4. repository identity and full remote-commit preconditions;
5. allowlisted staging and a remote-drift check immediately before push;
6. an independent post-push clone and byte comparison;
7. PR publication as the default integration boundary for new contracts; and
8. retained recovery evidence after a remote mutation followed by failure.

Contracts must reject unknown fields, unsafe paths, symbolic links,
unclassified files and configured private markers. Tests shall cover unchanged
behavior, deterministic preparation, malformed contracts, wrong immutable
gates, baseline and staged-path violations, pre-push drift, push failure,
post-push PR failure and successful non-draft PR creation.

One explicit authorization may cover the internal subprocesses of one fixed,
hash-pinned publisher invocation. Platform sandbox approval is independent of
that SAEF phase authorization. Merge, live installation and destructive cleanup
remain separate gates.
