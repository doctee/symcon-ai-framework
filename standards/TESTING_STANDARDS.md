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

The repository root is one Composer project. Different lock files in historical
worktrees represent different revisions of that project, not independent
dependency owners. A nested module becomes a separate Composer project only
when its boundary, lock file, vendor installation and check entrypoint are all
explicitly documented. Root and nested projects must never borrow analyzers
across non-identical lock files. See
`project/COMPOSER_TOOLCHAIN_OWNERSHIP.md` for the executable contract.

## 5. Safety

Tests must not unexpectedly switch real devices or modify production state.

### 5.1 Symcon module metadata schemas

Tracked `library.json`, `module.json`, `form.json` and `locale.json` files shall
declare the matching official Symcon `$schema` URL and pass offline validation
against the reviewed snapshots in `standards/schemas/symcon/`.

Schema snapshots are immutable test inputs identified by source URL, retrieval
date and SHA-256. Updating one is a separately reviewable dependency change:
retrieve all four official files, review their semantic delta, update the
manifest hashes and run the complete repository check. CI must not download a
floating schema during validation.

The schema check complements domain-specific module tests. It does not prove
runtime compatibility, API availability or correct module behavior.

## 6. Live-system change gate

A change to an authorized live IP-Symcon installation shall be treated as a
separate integration gate after repository and offline verification.

Choose the route in `project/SYMCON_CHANGE_WORKFLOW.md` by impact and
recoverability. A direct bounded change does not need custom deployment
packaging. New behavior is first developed in isolation; migrations, deletion,
security and coupled state changes need a specific high-impact plan. Existing
transaction-owned targets retain their stronger contracts.

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

For control optimization, prefer an observation-only candidate: compare its
hypothetical decisions against the baseline on the same inputs without actuator
or production-state writes. Define representative conditions, domain metrics,
duration, resource bounds and abort criteria before starting. Creating
observation objects/events still needs mutation authority.

Bounded development approval may cover repeated tests and corrections on a
named isolated system. Verify isolation of credentials, references, connections,
archives, events and device/MQTT outputs; a test category is insufficient.
Record iteration limits and stop conditions. It does not permit production
promotion, publication, cleanup or bypass of consumed one-run markers.
Use the smallest reproducer and relevant regressions first, then the broader
applicable suite before promotion. A large check count is not coverage.

## 8. MCP result evaluation

Follow `project/SYMCON_MCP_SCRIPT_READBACK.md` for authorized live inspection.

- Prefer direct script-content operations or own-module read tools for source.
- For inspection, use `symcon_run_script_text_ex` only for bounded probes.
  Administrative mutations require the separate live authorization described
  in `project/SYMCON_MODULE_INSTALLATION.md`.
- For the legacy result channel, evaluate `transportError` and
  `executionError` separately, reject `truncated: true` and bound
  `maxOutputBytes` to the expected aggregate result.
- For native tools, inspect all content blocks, structured verdicts, warnings
  and `isError`. Missing legacy fields must not be fabricated as successful
  checks. Unknown or incomplete result semantics block acceptance.
- A write with `isError=false` and a syntax warning is not successful
  deployment. A confirmed saved invalid file is a mutating failure.
- Validate locally before writing; separately lint the changed PHP file and
  the module folder after writing. Validate metadata and the relevant behavior
  separately. Check deployed inventory when folder lint encounters development
  stubs; do not silently omit files or accept an error as success.
- Read back exact candidate bytes independently. Reload results must cover all
  library/module registrations and affected instances, not just a top-level OK.
- After ambiguous mutation, inspect instead of retrying. Rollback must itself
  have complete verification; otherwise report recovery required.
- Prefer direct result channels over temporary live objects.

Temporary scripts, variables, events or markers require explicit authorization.
Only perform their exact authorized cleanup and verify absence through a
separate read-back. When test objects must be retained, preserve them and
record that cleanup was not performed; do not invent cleanup authority.

The synthetic examples in `tests/workflows/mcp-result-cases.json` and
`composer test:mcp-workflow` specify the acceptance decisions offline. They
are not a live client, automatic free-text classifier or Windows qualification.

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

Check for intervening writers before restoration. Source rollback does not
restore data migrations or physical effects; require a compatible state backup
and explicit compensation scope where applicable. A failed or unverified
restore is recovery required, not a successful rollback.

## 10. Live evidence closure

An authorized live mutation or real-device test is not complete when the
runtime check passes. Its evidence and the framework's current-state claims
must also be reconciled.

For routine changes, use one compact private record following
`templates/CHANGE_RECORD.md`. Reference existing backups/results rather than
building another wrapper or evidence package. Mark inapplicable checks with a
reason. Stronger existing managed-deployment receipts remain binding.

Keep exact installation evidence in a private machine-readable artifact when
automated follow-up or an existing deployment contract requires it. JSON
is recommended for deterministic review and follow-up automation. The artifact
should record, where applicable:

- a format version, UTC timestamp, phase and outcome;
- the explicit authorization kind and bounded scope;
- the actual MCP result contract, its errors, completeness and semantic verdicts
  (legacy transport/PHP/truncation fields only for that channel);
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
