# Latest-Command-Wins Live-Adoption Preparation

**Status:** Repository-only transaction and private input preparation complete; all live gates closed
**Preparation date:** 2026-09-14
**Repository base:** `5ffa0862e42c9a86dd21710855d3a298206cebcf`
**Runtime mutation:** None
**Owner mutation:** None

## Purpose

This workstream prepares the two deliberately separate migrations required to
adopt the V05-001 latest-command-wins behavior. It does not stage or activate a
fileset, change an owner script or event, initialize live diagnostics, publish
MQTT, invoke a device action, restart a service or remove retained evidence.

The workstream turns the fresh private inventory into a deterministic input for
a later reviewed transaction. Installation identities, owner sources and
configuration remain private.

## Fresh Baseline Finding

The bounded read-only R3 inventory still found exactly two exporter owners, ten
active owner events and the previously reviewed finite consumer set. The owner
dispatches still use the historical three-argument call and therefore do not
capture the immutable event value required for the strongest supersession
guarantee.

The active installation also still loads the historical exporter Runtime and
Core generation. That is not contradictory to report 39. Report 39 compared
the installation with the repository candidate that existed when that evidence
was collected. Report 40 subsequently changed the candidate and deterministic
fileset, but a repository merge does not deploy Symcon automatically. The
active runtime therefore became historical relative to current `main` without
drifting from its own installed fileset.

## Two-Sequence Adoption

The live adoption must preserve this order:

1. The current deterministic runtime fileset is built and staged through the
   existing Channel-v8 runtime-fileset path.
2. A read-only channel preflight binds the exact staged package, active source
   generation and rollback material.
3. The fileset is activated through its own gate and independently verified by
   effective Reflection paths and hashes after the required clean runtime
   transition.
4. Only after the current runtime is proven active may a fresh owner inventory
   create the source-migration plan.
5. The separate owner transaction adds the immutable event value, initializes
   the new diagnostics directly through the proven runtime and restores the
   reviewed event activity.

The current runtime remains backward compatible with the three-argument owner
call. Runtime activation can therefore precede owner migration without a mixed
contract failure. Reversing the order is forbidden because historical runtime
bytes do not implement the event-value contract being adopted.

## Reuse Decision

Channel v8 remains the only runtime-fileset staging and activation authority.
The owner migration is not another channel verb and does not duplicate the
deployment gateway. It is a case-study-local transaction because it binds
exporter Registry structure, owner sources and event identities.

The migration composes existing SAEF behavior:

- the existing configuration hash validates each owner configuration;
- Registry and Statistics diagnostics are initialized through the current
  exporter runtime;
- the existing Symcon event action and owner Registry identify the complete
  event set; and
- one named Symcon semaphore serializes apply, inspect and rollback.

No public helper or storage API is introduced.

## Repository Artifacts

- `deployment/MqttSupersessionOwnerSource.php` performs the token-aware,
  exactly-once owner-call transformation and extracts a read-only configuration
  loader.
- `deployment/MqttSupersessionMigrationEnvironment.php` defines the narrow
  transaction environment.
- `deployment/MqttSupersessionOwnerMigration.php` implements preflight, apply,
  inspect, automatic rollback and explicit rollback.
- `deployment/MqttSupersessionSymconEnvironment.php` supplies exact Symcon
  readback, the two approved production mutators and append-only claim state.
- `tools/prepare-supersession-live-input.php` converts reviewed private
  inventory and owner backups into a mode-`0600` input bound to current source
  hashes.
- `tools/render-supersession-owner-migration-script.php` renders a complete,
  uniquely namespaced private script without overwriting an existing output.
- `tests/mqtt-discovery-exporter/supersession-owner-migration.php` verifies the
  transaction, real filesystem claim adapter and generated-script syntax.

## Transaction Contract

The read-only preflight validates exactly two owners, every expected event,
configuration and Registry hash, original source bytes, the candidate runtime
identity and the absence of supersession diagnostics. Its review plan binds:

- repository base and input hash;
- target and allowed operation;
- Runtime, Core and fileset identities;
- owner source and configuration hashes;
- all event identities, trigger variables, action binding and activity;
- creation and expiry time; and
- a cryptographically random nonce.

Apply acquires the transaction semaphore, repeats the complete baseline read,
and atomically claims the exact plan hash before production mutation. It then:

1. disables all reviewed owner events and verifies inactivity;
2. writes and reads back both exact candidate owner sources;
3. initializes the arbitration Registry and superseded counter directly;
4. verifies candidate sources, diagnostics and inactive events;
5. restores the reviewed event activity; and
6. performs an independent state readback before marking the claim complete.

No owner script is executed by the transaction. The implementation contains no
`RequestAction()`, MQTT publication or script-run call.

## Claim and Recovery Boundary

The one-use claim is a plan-hash directory below a separately protected private
root. The contract file and ordered phase files are created with exclusive
creation and are never overwritten. Phases record claim, event quiescence,
source update, diagnostics initialization, completion and rollback.

The later Windows gate must create and verify the claim root with protected ACLs
before an apply can be admitted. Repository tests prove filesystem semantics,
not the installation-specific Windows ACL.

If a post-claim step fails, automatic rollback first disables the reviewed
events, restores both original sources byte for byte and then restores the exact
reviewed event activity. Newly created diagnostics may remain inert; their
retention is a later, separate decision. A rollback failure returns
`manual_recovery_required` and preserves all claim and source evidence.

There is no blind resume after interruption. `inspect` classifies only exact
baseline, migrated and rolled-back states; mixed source, event, diagnostics or
claim state requires manual recovery. A lost response is handled by read-only
inspection, never by repeating apply.

## Threat Model

| Threat | Fail-closed control |
| --- | --- |
| Stale plan or TOCTOU | Short expiry, exact plan hash and complete baseline recheck under the migration semaphore before claim and mutation. |
| Replay or double click | Atomic plan-hash claim directory and immutable phase files reject every second apply, including after rollback. |
| Concurrent migration | Named Symcon semaphore plus the filesystem claim; failure to acquire either stops before mutation. |
| Partial source update | Exact source readback followed by automatic restoration of both original sources and event activity. |
| Crash or lost result | Append-only phases support read-only classification; no automatic continuation is permitted. |
| Configuration or event drift | Exact hashes, ownership, action, trigger, count and activity are revalidated before claim. |
| Wrong runtime generation | Runtime, Core, fileset and Reflection ownership must equal the prepared input. |
| Unintended command or MQTT traffic | Events are quiesced before source mutation; no owner execution, MQTT call or device action exists in the transaction. |
| Private evidence disclosure | Complete sources and configuration loaders remain only in ignored private mode-`0600` files; public status uses counts and hashes. |

## Symcon MCP Transport Boundary

`symcon_run_script_text_ex` is approved only for bounded read-only probes and
must not execute this mutating transaction. The rendered script is an inert
private artifact until a later gate proves that the available Symcon MCP
binding can install, execute, read back and remove one exact temporary script
object under an approved private parent.

That live wrapper must additionally bind the approval to the current operator
and host and must preserve the plan, input and rollback evidence. If the
required MCP mutation tools are unavailable, work stops. SSH, PowerShell,
browser and Computer Use are not implicit fallbacks and require a new explicit
authorization after the missing MCP binding is reported.

## Verification

The repository regression covers source transformation, loader rejection,
read-only preflight, runtime and source drift, event ownership, quiescence,
successful apply, independent inspection, duplicate apply, replay after
rollback, automatic and explicit rollback, partial state and semaphore
contention. The real claim adapter additionally proves exclusive creation,
ordered transition, nonce binding and stale-transition rejection.

The renderer regression creates a complete synthetic private input, emits the
uniquely namespaced script, runs PHP syntax validation and rejects output
overwrite. PHPStan and PHPCS include the deployment implementation. The
existing exporter fileset remains byte-identical to report 40.

## Deferred Live Gates

The next steps remain separately authorized:

1. Windows PowerShell 5.1 parser and protected claim-root ACL qualification.
2. Rebuild and hash-review of the exact current runtime-fileset package.
3. Inactive Channel-v8 staging only.
4. Fresh read-only runtime preflight and byte-exact rollback backup.
5. Runtime-fileset activation, required runtime transition and independent
   Reflection/hash postflight.
6. Fresh read-only owner, event, diagnostics and consumer inventory.
7. Symcon MCP mutation-capability discovery plus user, host and temporary-script
   lifecycle binding.
8. Read-only owner-migration preflight and server-side review-plan generation.
9. Exact owner-plan apply with no MQTT publication or device action.
10. Independent read-only owner, event, diagnostic and consumer postflight.
11. A separately authorized supervised rapid-command scenario with immediate
    compensation, followed by passive observation.
12. Separate issue closure and retention decisions for previous filesets,
    owner backups, claims and diagnostics.

Open-Meteo observation does not block these gates. Navimow runtime and its
independent workstreams are outside this transaction and remain unchanged.
