# Risk-Tiered Symcon Changes

Status: Accepted workflow

## Purpose and scope

Improve automation with the shortest workflow that preserves the required
safety properties. Deployment means controlled adoption of a tested change;
it does not imply a ZIP, a custom runner or a service restart. Rollback means
recovering declared state, not merely restoring a source file.

This workflow implements [ADR-0012](../adr/ADR-0012-use-risk-tiered-symcon-change-workflows.md)
and the live gates in [Testing Standards](../standards/TESTING_STANDARDS.md).
It grants no access, publication, production or cleanup authority.

## Choose the route before implementation

| Route | Admission | Minimum evidence and verification |
| --- | --- | --- |
| Direct bounded change | Known target/owner, independently reversible source or configuration change, limited understood effects; no migration, deletion or security change | Complete before-copy and identity/hash; local lint and focused tests; fresh drift check; scoped MCP write; separate validation and readback; bounded observation |
| Isolated development | New or larger automation/module behavior, new dependencies or uncertain behavior that can first be tested away from production | Authoritative Git source, focused offline tests, isolated integration where necessary, explicit promotion candidate and production acceptance |
| Planned high-impact change | Data/state migration, archive correction, deletion, permissions, broad physical impact, shared runtime owner or coupled writes needing consistency | Exact target/candidate plan, applicable locks/quiescence, recoverable data and state, failure/crash recovery, independent postflight and explicit execution gate |

Unknown target, ownership or physical effects stop production mutation.
Do not classify a heating threshold change as low risk merely because it is
one line. Do not classify a large static display as high risk merely because
it contains many files. Reclassify when dependencies or effects change.
Isolated development does not downgrade the later production transition.

Read-only diagnosis does not need a rollback package. Scope, privacy, bounded
queries and complete evidence still apply. A read-only report is not an
authorization to delete its candidates.

### Scripts, modules and source ownership

Use a script for a focused installation-specific rule when that is the simplest
maintainable design. Prefer a module when lifecycle, multiple instances,
configuration UI, encapsulated persistent state or reusable integration
justifies it. Neither choice determines the risk route.

Before editing, identify the authoritative source and its live owner:

- script source maintained in a private Git project or explicitly designated
  live script;
- own module source and its library identity;
- Git/Store-managed library and its update channel; or
- SAEF managed fileset/runtime mirror with installed transaction contracts.

Do not edit generated output as canonical source. Capture unexpected live drift
and reconcile it deliberately; never overwrite or auto-push it. Private backups
may contain secrets and must not enter a public repository.

For managed filesets and transaction-owned modules, retain the existing adapter,
locks, pins and reseal requirements. A route change is a separately reviewed
ownership migration, not a shortcut justified by this document.
Store forks require an explicit fork decision, retained upstream identity and
a named update/maintenance responsibility; do not fork automatically.

## Small-change sequence

1. Define target, purpose, owner, route, allowed effects and success criteria.
2. Read complete current source/configuration and save a private recoverable
   before-copy with identity and hash. A redacted configuration is not a full
   backup of its secrets.
3. Prepare the full candidate locally; run syntax/schema and focused behavior
   tests before any write. Record applicable tests and why others do not apply.
4. Obtain the concrete live approval, including any expected immediate effect,
   necessary quiescence, validation and narrowly specified recovery actions.
5. Re-read the baseline immediately before writing. On drift, stop and review.
   Client-side comparison is not an atomic compare-and-swap guarantee; if
   concurrent writers cannot be excluded, use a stronger existing mechanism.
6. Apply one independently reviewable change through the approved MCP tool.
   Evaluate the entire result using the MCP workflow, not a success flag alone.
7. Independently validate and read back. Observe agreed domain invariants;
   prefer normal scheduled execution over an unnecessary manual device action.
8. Close the compact change record. On failure, stop; use only the authorized,
   prepared recovery, then validate and read back the restored state.

A method-body write may affect the next call before any reload. A UI file may
update an open configuration form. If that exposure is unacceptable, arrange
authorized quiescence or use an isolated system first; do not pretend that an
unreloaded write is inactive staging.

Restore only the changed source/configuration/state, after checking for later
writers. Do not erase someone else's intervening change. New-file rollback
requires explicit, exact deletion scope. Never omit a path from module deletion
as a recovery shortcut. Reload and device compensation need their own explicit
scope even when included in the same reviewed change approval.

An unknown write outcome requires read-only reconciliation, not a retry.
If recovery cannot be proved or is outside authority, report recovery required
and preserve evidence. Git revert alone does not restore archive data, object
identity, persistent module state or already performed physical actions.

## Isolated development and bundled authorization

An authorization may cover a bounded sequence of code corrections and repeated
tests without asking again for each test. It must identify:

- the repository/worktree and exact test system or newly owned fixture roots;
- allowed creation, writes, executions, reloads and test process termination;
- permitted ACL/audit/compiler operations, if actually needed;
- maximum duration/iterations/resource use and stop conditions;
- retained artifacts and whether any specific cleanup is permitted; and
- the separate, ungranted production and publication gates.

No cleanup is implied by the word "test". Retain fixtures when instructed.
Do not remove one-use markers or re-run a once-authorized package. Fresh runs
under a valid iteration scope receive distinct identities and retained results.
Sandbox/OS permission checks remain independent of this engineering approval.

Prove isolation before testing: no production credentials, actuator bindings,
shared writable archives, production MQTT topics or active production events.
Check copied references and connection endpoints. A test category on the live
server is not isolation, and a second instance of the same library still shares
code and reload effects. Prefer a separate Symcon runtime for module iteration.
Stop if the boundary fails, effects escape it or the same unexplained failure
persists within the agreed budget.

Do not create fresh generic wrappers to work around every test failure.
Use existing helpers, native diagnostics and the smallest regression reproducer.
Once the focused regression passes, run the relevant broader suite once before
promotion; neither test count nor repeated green runs replace coverage.

## Observation-only comparison

For control optimization, separate decision calculation from actuation. Feed
the candidate bounded recorded inputs or explicitly authorized observations.
Let it record hypothetical decisions only in owned test/diagnostic storage,
without device calls, notifications or production state writes.

Before starting, define baseline, representative operating conditions, metrics
(for example comfort deviation, switching frequency or energy use), duration,
resource limits and abort criteria. Include stale/missing-input behavior and
manual overrides. Compare candidate and baseline on the same inputs; do not
equate syntax correctness or a short quiet interval with an improvement.
Observation setup that creates objects or events is still a mutation gate.
Promotion to real actuation is separately authorized and supervised.

## Promotion, publication and evidence

Pin the tested candidate, confirm target ownership and drift, then select the
appropriate production route. No automatic pull from an agent-writable
development branch. Use an explicitly reviewed revision and independent
readback. Publication, merge, deployment, activation, restart, device action
and retention are distinct scopes; a single approval covers only the scopes
it expressly names. Existing channel-specific gates still apply.

Keep one compact private record with target/authority, baseline and backup,
candidate, tests, operations/results, readback, observation and recovery.
The [change-record template](../templates/CHANGE_RECORD.md) is a checklist,
not a new runtime schema or capability token. Omit inapplicable fields with a
reason. Existing deployment receipts may be referenced instead of duplicated.
Do not generate nested evidence packages for a simple change.

Nothing here modifies existing connections, credentials or token privileges.
Concrete connection names and target bindings belong in the private overlay.
Sources for the native tool behavior and its version-sensitive limits are in
[the MCP workflow](SYMCON_MCP_SCRIPT_READBACK.md).
