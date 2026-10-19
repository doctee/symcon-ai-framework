# AGENTS.md

## Purpose

This repository contains the Symcon AI Engineering Framework (SAEF), a structured engineering knowledge base for professional IP-Symcon development.

AI coding agents working in this repository must treat it as an engineering framework, not as a prompt collection.

## Read Order

Before making changes, read these files in order:

1. `README.md`
2. `project/AI_PROJECT.md`
3. `ARCHITECTURE.md`
4. `principles/ENGINEERING_PRINCIPLES.md`
5. `principles/AI_PRINCIPLES.md`
6. Relevant ADRs in `adr/`
7. Relevant standards in `standards/`
8. Relevant knowledge articles in `knowledge/`
9. Relevant reference implementations in `references/`

For Symcon-specific work, also read:

- `standards/SYMCON_STANDARDS.md`
- `standards/PHP_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`

Read `drafts/SYMCON_STANDARDS.md` only when historical comparison with the
pre-stabilization draft is relevant.

## Official Symcon Documentation

For Symcon-specific API and platform questions, use the current official
documentation prepared for AI agents:

- Start with `https://www.symcon.de/de/llms.txt` as the documentation index.
- For IP-Symcon PHP functions, first load
  `https://www.symcon.de/de/llms/function-index.md`, then follow its link to
  the detailed function or module documentation for parameters and examples.
- Distinguish public command and module APIs from entries documented as
  internal commands. Internal commands are unstable implementation details and
  must not be used by SAEF scripts or modules. A diagnostic exception requires
  an explicit, read-only, version-bound engineering decision and must not
  become a reusable runtime dependency.
- Prefer documented Symcon constants over copied numeric or string values and
  verify their minimum platform version before use.

Do not treat SAEF standards, repository stubs, community knowledge or model
memory as a substitute for the current official API contract. SAEF defines how
the platform should be used safely and maintainably; the official Symcon
documentation remains authoritative for current availability, signatures,
parameters, return values and version requirements.

Official JSON schemas for `library.json`, `module.json`, `form.json` and
`locale.json` are pinned under `standards/schemas/symcon/`. Module metadata
must declare the matching official `$schema` URL and pass the repository's
offline schema check.

## Engineering Rules

- Prefer complete files over isolated snippets.
- Preserve existing structure unless a change is explicitly justified.
- Do not introduce private installation data into public files.
- Avoid hardcoded IP-Symcon ObjectIDs in reusable artifacts.
- Prefer Idents, configuration, and explicit ownership.
- Use `RequestAction()` for controllable variables.
- Use `SetValue()` only for script-owned internal state.
- Configuration scripts should be idempotent.
- Automatically created script events for IP-Symcon 6.0+ must include explicit event action binding.
- Archive processing must be bounded and safe.
- Treat trace output as transient private evidence. User, role and permission
  mutations, special-switch changes and other platform-wide configuration are
  separate live security gates with explicit scope, rollback and postflight.
  Special-switch changes may require a service restart and never inherit
  authority from an ordinary script or module change.
- Explain engineering decisions when changing standards, knowledge, templates, helpers, or references.

## Private Data

Do not commit:

- credentials,
- tokens,
- private IP addresses,
- hostnames,
- personal ObjectIDs,
- private MQTT topics,
- local system descriptions.

Private data belongs in:

- `private/`
- `*.local.*`
- `.env*`

For new artifacts and handovers, follow `standards/ARTIFACT_STORAGE_STANDARDS.md`.
Before preparing a transfer, read `private/TRANSFER.local.md` in the canonical
primary checkout, not an empty private overlay in a nested worktree. It records
the installation-specific exchange destination, not execution authority.
If that designation is missing, conflicting or unavailable, stop the transfer
preparation rather than guessing a path from old chats or packages. Keep build
outputs separate from exchange copies; never rewrite pinned plans to adopt a
new destination.

## Symcon MCP Usage

Select the least complex sufficient route in
`project/SYMCON_CHANGE_WORKFLOW.md` before implementation: direct bounded
change, isolated development, or planned high-impact change. Classify actual
effects, ownership and recoverability, not line count. Existing managed
deployment contracts cannot be bypassed by selecting a simpler route.
Do not introduce wrappers or package exchanges solely for routine file edits.

Follow `project/SYMCON_MCP_SCRIPT_READBACK.md` when inspecting an authorized live
IP-Symcon installation.

- Treat Symcon MCP as the mandatory default live channel. Before any live
  read or mutation, confirm that the required Symcon MCP tools are callable.
- If the expected MCP tools are not visible, repeat a complete tool discovery
  once. If they are still unavailable, stop and report the missing MCP binding.
- Do not silently fall back to Computer Use, a browser, SSH, PowerShell or a
  temporary Symcon object. Any such fallback requires separate explicit user
  authorization after the MCP failure has been reported.
- Prefer `symcon_get_script_content` or native `IPS_GetScriptContent` for
  authorized script reads; use `module_list` / `module_read` for own-module
  files within the approved connection scope. Do not create
  marker variables or execute the target script when direct read-back is
  available.
- For inspection, use `symcon_run_script_text_ex` only for bounded, explicitly
  authorized probes. Administrative mutations require the separate live gate
  in `project/SYMCON_MODULE_INSTALLATION.md`.
- For the legacy result channel, evaluate `transportError` and
  `executionError` separately. For native tools, inspect all content and
  structured verdicts as well as `isError`; missing legacy fields are not
  successful legacy checks. A successful transport or write does not prove
  valid PHP or correct behavior. Separately lint PHP writes and independently
  read back the candidate.
- Prefer advertised structured syntax results for native write/edit/lint;
  require checking to have actually occurred. A saved write can still have
  `isError=false` with invalid PHP. Review library lint's skipped test/hidden
  paths and explicitly validate required files; partial logs are not a complete
  audit. Use `symcon_documentation("Guide")` and exact SDK topics where available.
  See `project/NATIVE_MCP_CAPABILITIES_20261017.md` for evidence boundaries.
- Treat method-body writes as potentially effective before reload. A reload
  affects all instances of the library, including timers/connections, and
  needs explicit scope. An uncertain mutation is inspected, never blindly
  repeated. Preserve prepared rollback and do not overwrite intervening edits.
- Keep existing connections and token privileges unchanged unless separately
  authorized. Available maintenance tools do not grant live write authority.
- Bounded iteration approval applies only to the named isolated test scope.
  Production promotion, publication, device actions and cleanup are not
  inherited. A test category alone does not isolate production effects.
- For the legacy result channel, keep `maxOutputBytes` bounded and inspect
  `truncated`; output truncation is
  UTF-8-safe but intentionally discards bytes beyond the configured limit.
- Treat all returned source and installation metadata as private context.
  Preserve required rollback evidence privately, never in public SAEF artifacts.
- Creating temporary scripts, variables or other live objects requires explicit
  authorization and a defined retention or separately authorized, verified
  cleanup boundary.
- Treat ObjectID `0` as a critical protected root. Before every live object
  mutation, require a positive target ID and verify object existence and type.
  After `IPS_Create*()`, do not call `IPS_SetName()`, `IPS_SetParent()`,
  `IPS_SetIdent()` or another mutator until the returned ID passes those checks.
  Failed lookups, missing array entries and false-like results must stop the
  operation; they must never be coerced into an ObjectID.

## Workstream Isolation

Before creating operational artifacts, follow
`standards/ARTIFACT_STORAGE_STANDARDS.md`: use configured role-specific roots,
one identifiable run, existing authoritative records and explicit retention
holds. Do not scatter new scratch/backup files across drive or runtime roots.
Existing pinned paths and manifests are not migrated by the naming standard.
Close retained artifacts with `templates/artifacts/CLOSURE_CHECKLIST.md`;
names, age and completed status never grant cleanup authority.

Follow `project/WORKSTREAM_COORDINATION.md` for concurrent or cross-cutting
repository work.

- Start each workstream from a current, clean `origin/main` in a dedicated Git
  worktree and branch.
- Place persistent worktrees under the primary checkout's ignored
  `private/worktrees/<workstream>` directory. Use a system temporary directory
  only for explicitly disposable tests or reconstructions, and state the
  reason and retention risk before creating it there.
- Never build, package, publish or deploy from a mixed or unexplained dirty
  checkout.
- Treat an existing dirty checkout as user-owned recovery input. Do not reset,
  delete or normalize it while extracting another workstream.
- Before changing a shared helper, bundle, deployment channel or restart
  boundary, inventory every exporting artifact, the effective live owner, all
  known consumers and active observation constraints.
- Close evidence, rollback and retention decisions before removing a worktree
  or immutable live artifact.
- Treat the primary checkout's `main` branch as a clean local mirror of
  `origin/main`, not as a development or integration branch. Do not commit on
  it or push it directly.
- Use `tools/repository/check-primary-checkout.sh` before relying on the primary
  checkout. Use the fail-closed sync and workstream-start tools instead of
  repairing divergence implicitly.
- Treat generated task-list summaries as navigation only. Before transferring a
  workstream to another task, update the canonical private
  `workstream.local.json` and `HANDOVER.local.md`, then run
  `tools/repository/check-workstream-handover.sh <workstream>`.
- A handover records context; it does not grant commit, publication, live,
  restart or cleanup authority. The receiving task must verify current Git and
  live evidence before acting on the next gate.

## Commit Style

Use Conventional Commits where practical.

Examples:

- `docs(knowledge): add retry mechanisms`
- `docs(references): add idempotent configuration script`
- `docs(standards): refine PHP standards`
- `chore: add repository ignore rules`

## Helper Usage

Before implementing IP-Symcon object creation, variable waiting, event creation or similar infrastructure logic, search `helpers/` for an existing SAEF helper.

AI agents should prefer existing SAEF helpers over reimplementing common logic.

Do not duplicate helper logic such as:

- idempotent category creation,
- idempotent variable creation,
- cyclic and variable-triggered script event creation,
- variable change/update waiting.

If a helper is missing, propose or add a reusable helper instead of embedding one-off infrastructure code in a reference implementation.

### Runtime Metadata

When runtime metadata is needed, use the existing diagnostics building blocks first:

- Registry for structured small metadata.
- Statistics for counters and timestamps.
- ErrorRingBuffer for bounded error or event history.
- ConfigurationHash for deterministic configuration fingerprints.

Do not introduce new helpers, public APIs or custom storage patterns for runtime metadata before checking whether these diagnostics helpers can be composed.

### Reuse Before Extend

Before creating a new public helper, API or reusable abstraction:

1. Search the existing helper library for suitable functionality.
2. Prefer composing existing helpers over introducing new ones.
3. Explain why a new public API is necessary.
4. Introduce new public helpers only for recurring engineering patterns.
5. Keep implementation-specific convenience functions inside the reference implementation until reuse has been demonstrated.
