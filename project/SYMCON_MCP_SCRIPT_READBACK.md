# Symcon MCP Script Read-Back Workflow

## Purpose

Connected Symcon MCP servers may expose legacy result-channel operations,
native kernel operations and specialized module tools. Select the verified
contract, not a server alias. Direct read-back is read-only. In this inspection
workflow, text execution is restricted to bounded read-only probes.

Separately authorized administrative mutations are governed by
[the change workflow](SYMCON_CHANGE_WORKFLOW.md) and
[module installation](SYMCON_MODULE_INSTALLATION.md); they must not be
disguised as probes. Tool availability never grants operation authority.

This workflow is operational guidance. It must not be used to persist private
script content or installation metadata in public artifacts.

## Preferred read-back workflow

1. Obtain authorization for the target and purpose.
2. Confirm that the required Symcon MCP tools are callable before selecting any
   other live-system tool.
3. If the expected tools are not visible, repeat complete tool discovery once.
4. If they remain unavailable, stop and report that the Symcon MCP binding is
   missing. Do not silently change channels.
5. Read authorized script source directly with `symcon_get_script_content`
   or native `IPS_GetScriptContent`, using the already approved connection.
   For own-module files use `module_list` and `module_read` when available
   within the approved scope.
6. Inspect the result in transient working context; retain an authorized
   recoverable before-copy only in the private overlay when preparing a change.
7. Do not execute or modify the target merely to read it.
8. Do not reproduce source or installation metadata in public SAEF artifacts.

For small authorized probes that cannot use direct read tools, select an
explicitly reviewed execution/result contract. Native text execution is not a
drop-in replacement for a legacy structured return. When using the legacy
`symcon_run_script_text_ex`, review the supplied PHP for side effects, keep
output bounded and inspect `transportError` and
`executionError` separately. A successful MCP transport may still contain a PHP
execution error.

For that legacy operation, set `maxOutputBytes` according to the expected result
size and always inspect `truncated`. Truncation is UTF-8-safe, but bytes beyond
the configured limit are intentionally discarded.

## Small native reads

Prefer direct native read tools for a fixed, authorized set of values or
metadata. Do not execute PHP, create temporary objects or build a new wrapper
when an existing read tool supplies the required evidence. Match the available
tool contract and current target identity; a stored ID alone is not ownership
proof. Tool availability does not expand the approved targets or call budget.

When both value and timestamps are needed, prefer one `IPS_GetVariable` call
per target if the current response supplies `VariableID`, `VariableType`,
`VariableValue`, `VariableUpdated` and `VariableChanged`. Check identity, required
fields and type consistency without truthiness coercion. A separate `GetValue`
is unnecessary unless independent value comparison is part of the actual task.
When only a value is required, `GetValue` can suffice. Missing required metadata
stops acceptance; do not silently add calls, retries or another channel.
The field definitions are in the official
[IPS_GetVariable reference](https://www.symcon.de/en/service/documentation/command-reference/management-variables/ips-getvariable/).
Check availability in the current server contract rather than assuming every
installed version exposes the same fields.

The MCP content representation and the PHP return type are different layers:

- A text block containing `[]`, `false` or `0` may be a string variable's
  literal value. Do not infer its type from JSON-looking text or parse it
  recursively. Use verified type evidence and the operation's serialization
  contract; if type fidelity matters and those are unknown, stop.
- An empty text block can be a valid empty string under that known contract;
  it is not interchangeable with missing content, an empty content list or
  a failed/uncertain call. Preserve false, zero and empty values distinctly.
- Parse JSON only where that operation's contract specifies JSON, and parse
  the whole payload. Do not extract a plausible fragment from warning/error
  text. Inspect all content and structured evidence as required below; neither
  `isError=false` nor a missing optional `isError` is sufficient alone.

Record stored values, update/change times and observation time separately.
Old timestamps do not prove present physical health; a recent timestamp alone
does not prove a fresh device response either. Zero timestamps need explicit
interpretation, not a claim of recent feedback. A pending flag is observation,
not authority to apply configuration, start an update or actively poll a device.
Do not infer an atomic snapshot from several calls or from metadata co-location
without a documented consistency guarantee.

Use this verified pattern at real diagnostic need, not as an endless series of
live tests for further subsets. Migration of existing automation callers,
retirement of a legacy connection, retries and cleanup retain their own gates.
Successful small reads do not qualify server timeout, output-limit or
truncation behavior. Keep complex archive/file/recovery checks on their reviewed
contracts until equivalent evidence and scope have been established.

## Safety constraints

- Obtain authorization for the target and purpose before reading source.
- Prefer direct read-only MCP operations over stateful marker workarounds.
- Treat `symcon_run_script_text_ex` as code execution, not as source read-back.
  Its probe text must not mutate production state.
- Do not return or record object IDs, object names, topics, hostnames,
  credentials or source content in public SAEF documents.
- If an explicitly authorized fallback requires a temporary object, use a
  unique name and keep its lifetime as short as possible.
- Do not alter the target script content or execute the target merely to read
  it.
- Treat cleanup verification as part of the operation, not as an optional final
  step.
- For inventory work, derive sanitized aggregates in Symcon where practical and
  read back only those aggregates.

## Legacy fallback

If direct read-back is temporarily unavailable, do not silently recreate the
former marker-variable workflow. Creating temporary variables or scripts is a
live-system mutation and requires explicit user authorization, immediate
cleanup and verification that no temporary object remains.

Computer Use, browser access, SSH and PowerShell are likewise separate live
channels, not implicit MCP substitutes. Report the MCP failure first and use
one of those channels only after the user has explicitly authorized the named
fallback and its scope.

## Required result validation

Choose the result contract per operation. Do not apply legacy field names to a
native response and interpret missing fields as success.

For every `symcon_run_script_text_ex` result, record or explicitly verify all
three independent conditions:

- `transportError` is explicitly `null`;
- `executionError` is explicitly `null`; and
- `truncated` is `false`.

A transport success with a PHP execution error is a failed probe. Correct the
probe or stop; never interpret it as valid live evidence.

For native MCP results:

- distinguish transport/call failure from the tool's `isError` indication;
- inspect every content block and structured result, including warning text,
  per-file verdicts and per-instance reload errors;
- require a complete, understood response and operation-specific postconditions;
- treat missing, truncated, contradictory or unfamiliar evidence as unverified;
- absence of a tool error does not prove syntax, registration or behavior.

An MCP response may legally omit `isError`; omission is not itself evidence of
failure or success. Record the actual response and its completeness, not
fabricated `transportError=null` / `executionError=null` / `truncated=false`
fields. A response ending in "OK" cannot cancel an earlier error.

An error returned after dispatch does not prove that nothing changed. Preserve
whether a mutation was attempted, confirmed or remains uncertain. After an
ambiguous mutation, independently inspect state within authority; never blindly
retry. Read-only access through a maintenance-capable connection still requires
an approved target/purpose and does not authorize its write tools.

## Archive, utility and Store tools

An operator may enable additional tool groups without changing SAEF's approval
boundaries. Record the connection's current catalog privately, with observation
date and evidence level: **advertised**, **contract-reviewed**, or
**behavior-qualified for a named scope**. An enabled group is not a successful
live test. Historical missing-tool findings remain dated evidence, not current
capability claims. Do not automatically amend active plans, increase budgets,
retry a consumed run or retire a legacy connection because more tools appear.

| Group | Simpler read route | Separate effects and gates |
| --- | --- | --- |
| Archive (`AC_*`) | Direct logging/aggregation metadata and bounded logged/aggregated value queries can replace custom PHP when they supply the required evidence. | Add/delete values, change variable IDs, reaggregate and setters change archive state or initiate work. They retain archive migration, backup and mutation approval gates. |
| Util Control (`UC_*`) | Documented reference, log and statistics queries can support an authorized investigation. | Delete, duplicate, replace and reset operations are not reads. Availability does not authorize object creation, cleanup, source edits or loss of diagnostic history. |
| Module Store (`store_*`) | `store_search` and `store_details` can support package discovery and installed/release comparisons. | `store_install`, `store_fork` and `store_uninstall` change installed code or ownership and use the existing installation/change gates. |

### Archive reads

Use the current [Archive Control reference](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/)
and the exact MCP schema. Native wrappers may supply the Archive Control
instance automatically; do not copy a PHP signature blindly into MCP arguments.
Fix the approved variables, time interval, aggregation level where applicable,
record limit, call budget and response budget before querying. The documented
maximum of 10,000 records for logged/aggregated reads is a ceiling, not a default
workload. Reaching a limit is not completeness proof; pagination or a larger
window needs to fit the existing authorization. No automatic continuation.
Keep existing archive ordering, consistency and recovery requirements from
[archive processing](../knowledge/EK-003-archive-processing.md).

### Utility evidence

The official [Util Control reference](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/util-control/)
documents public UC functions; do not classify the entire prefix as internal.
Conversely, exposure alone does not establish a public API contract. Verify the
exact function and version. A tool absent from that reference remains
contract-unverified until authoritative documentation resolves it; absence does
not itself prove that it is internal. Specifically internal APIs remain excluded
from reusable runtime dependencies under the existing internal-API rule.

`UC_FindReferences` scans system references and script text, not merely the
requested object. Authorize that broader read scope and account for scan cost
before calling it. Its text matches can include false positives and miss
dynamically constructed IDs; results can repeat an object. **An empty reference
result is not deletion clearance.** It does not replace ownership, filesystem
consumer or retained-evidence checks. Validate the positive target ID and its
existence independently. Do not send ObjectID `0`.

Log buffers and counters are observations, not a complete audit. In
`UC_GetLogMessageStatistics`, `MessageTimeStamp` is a sequence number, not Unix
time. Preserve reset boundaries when comparing counters. Do not reset buffers
or statistics as part of a read-only check or ongoing observation.

For tools whose exposed descriptions include broader effects, review those
effects explicitly before proposing use: empty file selections in
`UC_FindInFiles` / `UC_ReplaceInFiles` can select all scripts; replacement is a
write without built-in undo. `UC_DeleteObjects` can include descendants, and
`UC_DuplicateObject` can apply copied instance configuration. These descriptions
are catalog evidence, not behavioral qualification or a substitute for a
verified public contract. Never use them to bypass exact-target cleanup gates.

### Store ownership and rollback

Review the current exposed Store schemas and installed ownership before use.
The reviewed catalog advertises that `store_install` installs or updates from
a channel and runs module code; its channel selector is not an exact-version
pin. `store_fork` removes the Store installation, loads an own-module copy and
redirects existing instances to it. **A Store fork is not an inert backup.**
`store_uninstall` can leave instances without their implementation.

Before an authorized Store mutation, preserve recoverable source/configuration
and state as needed, identify affected instances/timers/connections, specify
rollback and independently read back the outcome. Do not assume the Store can
reinstall the prior exact release. Do not mix Store and own-module writers or
bypass an existing managed deployment. Search/details results do not authorize
installation, update, fork or uninstall. No Store mutation was exercised by
this catalog review.

## Native own-module workflow

The following describes the exposed tool contracts reviewed on 2026-10-17,
not a promise for every Symcon build. Before a live change, check the current
schemas, installed version and scope. Keep existing read-only and legacy
connections unchanged; mapping concrete aliases to roles is private.
See the [dated capability review](NATIVE_MCP_CAPABILITIES_20261017.md) for
announced fixes, observed contracts and still-open verification boundaries.

| Tool | Use and boundary |
| --- | --- |
| `module_list`, `module_read` | Inventory own modules and read exact source; the list can provide file sizes/hashes. Distinguish own modules from Store installations and detect duplicate ownership. |
| `module_write` | Creates/replaces complete UTF-8 files and can create directories. Preserve before-bytes; there is no built-in undo or expected-baseline-hash argument. |
| `module_edit` | Prefer a unique exact replacement for a small edit. Keep `replace_all=false` unless the full replacement scope is explicitly reviewed. A unique match is not a whole-file concurrency guard. |
| `module_lint` | Separate compile-only check of each changed PHP file; omit the path for the library scan, which excludes test and hidden directories in the newer contract. Review checked/skipped coverage; explicitly lint required skipped files by path. It does not prove runtime behavior. |
| `module_validate` | Checks library.json and module.json and reports the first problem. It does not replace all metadata schemas, form/locale checks or behavior tests. |
| `module_reload` | Registers changed manifests/public methods; reconnects all instances of the library and restarts their timers/connections. Explicitly authorize this effect; inspect all registration and instance results. |
| `module_delete` | Destructive; omission of path removes the entire module. Not an implicit rollback or test-cleanup operation. |

PHP method-body changes can take effect on the next call. Changes to form.json
and locale.json can update open forms. New folders, manifests and new/changed
public methods require reload under the reviewed contract. Therefore writing
and reloading are separate effects, but writing is not necessarily inactive.
Do not call `IPS_ApplyChanges` or restart the service as a generic substitute.

A reported 2026-10-15 check found that `module_write` saved invalid PHP with
`isError=false` while explicitly warning about syntax in the response text.
Separate file and folder lint correctly reported `isError=true`. This is a
dated observation, not an immutable server specification or a test performed
by the repository check. Apply the defensive workflow to edit as well, without
claiming the same behavior was independently tested for that operation.

Newer schemas add `structuredContent` to write, edit and lint responses.
Prefer these typed verdicts over parsing warning prose, while still checking
all content for failures and contradictions:

- Write/edit report `bytes`, `created`, `module`, `path`, a **short** `sha256`
  and `syntax`. The short hash is not a full backup-integrity hash or an
  expected-baseline concurrency guard.
- For PHP, require `syntax.checked === true` and `syntax.ok === true`.
  Inspect `error`, `line` and `unavailable` when present. An unavailable check
  is not successful lint, even if `ok` looks successful.
- `syntax: null` is expected for non-PHP files only; their schema/content
  validation remains separate. Missing or malformed expected structured
  evidence is unverified. Do not downgrade silently to text-only success.
- Lint reports integer `checked` and `skipped` counts and an `errors` list.
  An empty list alone proves neither coverage nor successful checking.
  Reconcile against the required PHP paths. A library result can exclude
  `tests/` and hidden directories; do not move files into the runtime tree to
  force coverage. Keep stub/test validation isolated where appropriate.
- Older text-only builds still require explicit complete-content review and
  separate validation. Record which contract is actually available; do not
  invent structured success fields for them.

Classify a confirmed saved file with failed lint as **written-invalid**. Stop
promotion/reload and follow the authorized recovery path; do not report
"nothing changed". Validate candidates locally before writing, and separately
lint after writing even when the write response includes a syntax check.
Independently read back exact bytes and relevant state after success or
recovery. Missing or mismatched readback cannot pass.

The native tools do not document a multi-file transaction, writer lock or
atomic compare-and-swap. Respect existing source owners and deployment
contracts. Quiescence, protected state backups and state migration remain
explicit where needed; native access is not permission to bypass them.

Use direct documented discovery, function-call, bounded wait, log and debug
tools instead of custom PHP or sleep loops when their verified contracts fit.
Calling a module function can still have side effects; a self-test name does
not make execution read-only. Starting debug-file capture also needs its
write/lifetime scope. Reuse existing SAEF helpers when a script is necessary.

## Contract regression evidence

Run `composer test:mcp-workflow` for the offline response-decision examples.
The synthetic fixtures exercise success flags with semantic failures,
independent lint/readback requirements and uncertain-result handling. They are
not a live MCP client, natural-language error parser or native qualification.
No production source is written to reproduce these cases.

The tests also cover structured syntax failures, unavailable checking,
non-PHP results, missing advertised fields and skipped-file coverage. They
model SAEF decisions; they do not qualify a particular server build.

Synthetic direct-read cases additionally cover type-preserving values, missing
metadata, mismatched identities, whole-response rejection and stored timestamps
without a device-health verdict. Their test-only decision model neither calls
Symcon nor implements a reusable MCP parser, retry policy or client.

Documentation regressions additionally guard the archive/utility/Store scope
and approval boundaries. They check this guidance, not server behavior or the
permissions of any connection.

Official documentation starts at [the index](https://www.symcon.de/de/llms.txt)
and [function index](https://www.symcon.de/de/llms/function-index.md).
For tool-specific behavior, inspect the server's current exposed contracts.
The maintainer's [native MCP announcement](https://community.symcon.de/t/symcon-mcp-server-rust-only/144513/3)
and [reported write/lint distinction](https://community.symcon.de/t/symcon-mcp-server-rust-only/144513/50)
provide dated context, not authorization or a substitute for the current API.

## Longer read-only probes

Keep `symcon_run_script_text_ex` probes small and bounded. If an analyzer cannot
reasonably run through that channel, a temporary script is a live-system code
mutation and may be used only after explicit authorization:

1. obtain explicit user authorization because creating a temporary script is a
   live-system code mutation;
2. create a clearly named temporary PHP script in an approved container;
3. set complete script content including the PHP opening tag;
4. execute it once and read only a sanitized aggregate marker;
5. delete the temporary script and marker immediately;
6. verify through MCP that both objects no longer exist.

This fallback does not broaden the authorized task. The temporary script must
remain read-only with respect to production objects and must not execute the
caller scripts being analyzed.
