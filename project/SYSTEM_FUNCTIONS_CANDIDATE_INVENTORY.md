# System Functions Candidate Inventory

**Release-review status:** 2026-07-20; historical live evidence was not
re-collected during this documentation review.

## Status and scope

This document records the SAEF assessment of the locally preferred
`System.Functions.ips.php` library. It is an analysis and migration decision
record, not a source import. No implementation from the inspected library is
reproduced here.

The inspected library contains 39 functions:

- 35 functions trace to Wilkware's
  [`ips-scripts` repository](https://github.com/Wilkware/ips-scripts);
- 4 functions are local additions whose detailed authorship still needs to be
  confirmed.

The local source was inspected through the connected IP-Symcon MCP server. A
sanitized PHP-token scan of the other live scripts was used only to count direct
global function calls. Function definitions, comments, string contents, method
calls, static calls and longer function names were excluded. Object IDs, object
names, script content, topics, hostnames and other installation data were not
retained in this artifact.

Usage counts are migration indicators, not runtime telemetry. `Scripts` is the
number of scripts containing a direct global call and `calls` is the number of
tokenized direct call occurrences. Dynamic calls and code outside the connected
runtime are not represented. Autoload makes the functions available but does
not itself increase these counts; the function library was excluded from the
caller scan.

## Decision vocabulary

| Decision | Meaning |
|---|---|
| Adopt | Publish substantially unchanged. No current candidate qualifies. |
| Adapt | Specify the engineering pattern first, then create an independent SAEF design if recurring reuse is demonstrated. |
| Keep private | Installation-specific convenience or domain logic remains outside public SAEF artifacts. |
| Replace | Migrate callers to an existing SAEF helper or established Symcon practice. |
| Discard | Do not preserve the wrapper or behavior as a SAEF abstraction. |

## Inventory

### Object and variable creation

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `CreateIdent` | Wilkware | No direct helper | Generic normalization convenience, but implicit naming rules can create unstable or colliding Idents. | 0/0 | **Discard**; require explicit stable Idents in configuration. |
| `GetObjectByIdent` | Wilkware | Native lookup; validation utilities | Thin lookup wrapper without ownership/type contract. | 0/0 | **Discard**. |
| `GetObjectByName` | Wilkware | Native lookup; validation utilities | Name-based and therefore installation- and localization-sensitive. | 0/0 | **Discard**. |
| `CreateCategoryByName` | Wilkware | `SAEF_EnsureCategory` | Generally reusable intent, but name-based identity and weaker ownership validation conflict with SAEF. | 1/2 | **Replace** with the existing helper. |
| `GetCategoryByName` | Wilkware | Native lookup; validation utilities | Name-based, type-specific convenience only. | 0/0 | **Discard**. |
| `CreateDummyByName` | Wilkware | `SAEF_EnsureDummy` | Reusable intent with unstable name identity. | 0/0 | **Replace**. |
| `GetDummyByName` | Wilkware | Native lookup; validation utilities | Name-based convenience without an ownership contract. | 0/0 | **Discard**. |
| `CreateDummyByIdent` | Wilkware | `SAEF_EnsureDummy` | Reusable and Ident-oriented, but already covered. | 0/0 | **Replace**. |
| `GetDummyByIdent` | Wilkware | Native lookup; validation utilities | Thin lookup wrapper. | 0/0 | **Discard**. |
| `CreatePopupByName` | Wilkware | No popup helper | UI-specific, name-based, and the inspected implementation contains unintended self-recursion. | 0/0 | **Discard**. Reassess only with a recurring popup ownership pattern. |
| `GetPopupByName` | Wilkware | No popup helper | UI- and name-specific lookup convenience. | 0/0 | **Discard**. |
| `CreatePopupByIdent` | Wilkware | No popup helper | Potentially reusable UI intent, but no demonstrated usage or SAEF ownership contract. | 0/0 | **Discard for now**; capture requirements before any future design. |
| `GetPopupByIdent` | Wilkware | No popup helper | Thin lookup wrapper with no demonstrated demand. | 0/0 | **Discard**. |
| `CreateVariableByName` | Wilkware | `SAEF_EnsureVariable` | Reusable intent, but name-based identity and implicit profile/action assumptions conflict with SAEF. | 12/111 | **Replace with highest priority**. Migrate callers to explicit Ident, type, profile and action ownership. |
| `GetVariableByName` | Wilkware | Native lookup; validation utilities | Name-based convenience. | 0/0 | **Discard**. |
| `CreateVariableByIdent` | Wilkware | `SAEF_EnsureVariable` | Reusable intent already covered by a stronger helper. | 2/4 | **Replace**. |
| `GetVariableByIdent` | Wilkware | Native lookup; validation utilities | Thin lookup wrapper. | 0/0 | **Discard**. |
| `CreateScriptByName` | Wilkware | `SAEF_EnsureScript` | Reusable intent, but identity is based on a mutable caption. | 0/0 | **Replace**. |
| `GetScriptByName` | Wilkware | Native lookup; validation utilities | Name-based convenience. | 0/0 | **Discard**. |

### Profiles

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `CreateProfile` | Wilkware | `SAEF_EnsureProfile` | Generic profile creation, already covered by an idempotent helper. | 1/1 | **Replace**. |
| `CreateProfileBoolean` | Wilkware | `SAEF_EnsureProfile` | Type-specific convenience adds no independent engineering contract. | 0/0 | **Replace**. |
| `CreateProfileInteger` | Wilkware | `SAEF_EnsureProfile` | Reused locally, but duplicates existing profile reconciliation. | 2/14 | **Replace with medium priority**. |
| `CreateProfileFloat` | Wilkware | `SAEF_EnsureProfile` | Type-specific convenience already covered. | 0/0 | **Replace**. |
| `CreateProfileString` | Wilkware | `SAEF_EnsureProfile` | Type-specific convenience already covered. | 0/0 | **Replace**. |
| `UnregisterProfile` | Wilkware | No removal helper | Destructive lifecycle operation with global impact; ownership and dependency checks are required. | 0/0 | **Adapt as Knowledge**, not as a public helper yet. |

### Events

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `CreateEventByName` | Wilkware | `SAEF_EnsureCyclicScriptEvent` and `SAEF_EnsureTriggeredScriptEvent` cover explicit bounded contracts | Reusable event intent, but name-based ownership, caption-selected actions and implicit target binding are unsafe. | 1/1 | **Replace or adapt after caller mapping**; use an existing helper when its contract matches and document any uncovered schedule semantics before extending it. |
| `CreateEventByNameFromTo` | Wilkware | Partial coverage by `SAEF_EnsureCyclicScriptEvent` | Time-window scheduling is reusable, but boundary, overnight and target-script semantics need an explicit contract. | 1/1 | **Adapt as Knowledge/Reference**. |
| `CreateTimerByName` | Wilkware | Partial coverage by `SAEF_EnsureCyclicScriptEvent` | Timer creation is reusable; name identity and implicit action binding are not. | 1/1 | **Adapt**, preferably by composing or deliberately extending the existing helper. |
| `GetEventByName` | Wilkware | Native lookup; validation utilities | Highly used name-based lookup that exposes migration debt rather than a reusable abstraction. | 36/36 | **Replace with highest priority** using stable Idents and explicit ownership. |

### Actions and `RequestAction`

The inspected library contains no dedicated action helper and no
`RequestAction()` abstraction. SAEF should not add a thin wrapper. Controllable
variables remain governed by the existing standard: use `RequestAction()` for
external control and `SetValue()` only for script-owned internal state.

### Archive and data processing

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `RegisterArchive` | Wilkware | No archive-configuration helper; `EK-003` covers safe processing | Reusable configuration intent, but archive instance ownership, aggregation policy and validation must be explicit. | 1/3 | **Adapt as Knowledge/Reference**; consider a helper only after a second proven use case. |
| `UnregisterArchive` | Wilkware | No removal helper | Destructive configuration operation that needs ownership and retention safeguards. | 0/0 | **Adapt as Knowledge**, not as a helper. |
| `WaitForBoolValue` | Local | `SAEF_WaitForVariable` | Generic polling intent, but special-cases booleans and omits the existing validation/change contract. | 3/3 | **Replace** with the existing helper. |
| `WaitForVariable` | Local | `SAEF_WaitForVariable` | Generic duplicate with different update/change semantics, but no direct caller was found. Earlier text matching confused it with the longer SAEF helper name. | 0/0 | **Discard after an indirect-call audit**; retain `SAEF_WaitForVariable`. |
| `UpdateDeviceWarningSummary` | Local | No direct helper; diagnostics helpers cover metadata, not this domain aggregation | Domain-specific object-tree convention: event children, links, warning polarity, captions and visibility side effects. It also persists runtime object references. | 17/17 | **Keep private**. Extract only a sanitized **Case Study** after documenting the domain contract; do not promote it to a helper. |

### Logging and diagnostics

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `EchoDebug` | Wilkware | Registry, Statistics and ErrorRingBuffer for structured runtime metadata | Thin unstructured output convenience with no demonstrated usage. | 0/0 | **Discard**; use Symcon logging plus existing diagnostics composition as appropriate. |

### Formatting and UI

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `SetHiddenStates` | Local | No helper | Batch UI mutation is generic in shape but receives installation object references and has no ownership/type validation. | 3/5 | **Keep private or discard**. Inline only within an owning configuration script when needed. |

### Other

| Function | Origin | Existing SAEF helper | Reuse and assumptions | Usage (scripts/calls) | Decision and target |
|---|---|---|---|---:|---|
| `ExtractGuid` | Wilkware | No helper | Generic parsing convenience, but accepted input forms and failure behavior are implicit. | 1/1 | **Adapt only if recurring**; otherwise keep at the call site. |
| `RegisterHook` | Wilkware | No hook helper | Reusable integration operation with global routing and ownership/security implications. | 0/0 | **Adapt as Knowledge/Reference**, not a helper. |
| `UnregisterHook` | Wilkware | No hook helper | Destructive global operation; the inspected global function also relies on object context that is not available there. | 0/0 | **Discard implementation**; document safe owned cleanup if a real use case appears. |

## Reuse Before Extend assessment

The current evidence supports migration to existing helpers, not expansion of
the public helper surface:

1. Variable, category, dummy, script and profile creation already have SAEF
   equivalents with stronger idempotency and ownership contracts.
2. Both local wait functions overlap `SAEF_WaitForVariable`; call sites need a
   semantic migration check, not another polling API.
3. Event scheduling has real usage. SAEF already covers cyclic and triggered
   script events, but time-window and caption-selected action semantics remain
   outside those contracts. Caller mapping and an explicit knowledge contract
   should precede any further helper extension.
4. Archive and hook lifecycle operations affect global runtime configuration.
   They require ownership and cleanup guidance before reusable code.
5. The warning summary is repeated domain logic, not general infrastructure.
   Repetition inside one installation does not by itself justify a public API.

## Licensing and attribution gate

The 35 upstream functions are associated with Wilkware's `ips-scripts`
repository, whose
[`LICENSE`](https://github.com/Wilkware/ips-scripts/blob/main/LICENSE) declares
**CC BY-NC-SA 4.0**. SAEF uses the PolyForm Noncommercial License 1.0.0 with
separate commercial licensing. Direct copying or adaptation could impose
attribution and share-alike obligations that do not align automatically with
SAEF's software license or its commercial licensing model.

The upstream license declaration was rechecked against the repository during
the 2026-07-20 release review. This verification does not replace legal review
or establish rights for the four local additions.

Consequences:

- no Wilkware source block is imported by this assessment;
- names, behavior observations and independently stated engineering
  requirements are recorded only for migration analysis;
- any future implementation must pass a license decision before work starts;
- preferred options are explicit permission from the author, a compatible SAEF
  licensing decision, or an independently specified and independently written
  implementation where copyright permits;
- authorship and rights for the four local additions must be documented before
  any sanitized derivative is published.

This is an engineering license gate, not legal advice.

## Candidate SAEF structure

The following structure is a proposal. None of these implementation artifacts
is created by this assessment.

```text
knowledge/
  EK-TBD-event-scheduling-and-ownership.md
  EK-TBD-archive-logging-configuration.md
references/
  RI-TBD-deterministic-event-scheduling.md
  RI-TBD-owned-archive-logging-configuration.md
case-studies/
  device-warning-summary/
    README.md
    01-domain-contract.md
    02-sanitized-design-review.md
helpers/
  object/EnsureEvent.php                  # extend only if the references prove a common contract
  archive/EnsureArchiveLogging.php        # create only after recurring reuse and license clearance
```

Identifiers remain unassigned until the corresponding artifact is approved.
This avoids colliding with Knowledge and Reference identifiers introduced by
independent SAEF development.

The warning summary must not be placed in `helpers/`. A case study may describe
the problem and trade-offs without copying private names, object references or
foreign/local implementation code.

## Risks and open questions

| Area | Risk or question | Required resolution |
|---|---|---|
| Licensing | Can the upstream author grant permission compatible with SAEF's noncommercial and separate commercial licensing model? | Obtain explicit permission before implementing related candidates; CC BY-NC-SA alone is not assumed compatible. |
| Local authorship | Which local additions are wholly original, and under what terms may they be published? | Establish provenance for each local function. |
| Usage evidence | Tokenized direct-call counts do not prove runtime frequency or semantic equivalence and cannot see dynamic invocation. | Review callers in a private migration worksheet; never publish their IDs or names. |
| Variable migration | Does every `CreateVariableByName` caller have a stable Ident and explicit action owner? | Define per-caller target Ident, type, profile and action contract. |
| Wait semantics | Some callers may depend on update timestamps, change timestamps, lookback or strict value comparison. | Compare each caller with `SAEF_WaitForVariable` before replacement. |
| Event ownership | Existing callers may depend on caption-based actions or implicit script binding. | Define target script, schedule, active state, event action and ownership explicitly. |
| Archive ownership | Archive configuration is globally observable and may affect retention. | Define archive instance selection, aggregation policy and safe cleanup. |
| Warning domain | The meaning of warning polarity, child links and global link visibility is installation-specific. | Keep private until a sanitized domain contract is reviewed. |
| Destructive cleanup | Profile, hook and archive removal can affect other owners. | Require ownership proof and dependency checks; default to no deletion. |

## Concrete implementation sequence

This sequence records the original migration order. The private caller
inventory, deterministic runtime deployment and first two low-risk replacements
are now complete as documented in the Wave 1 and pilot records. Remaining steps
are neither completed nor authorized merely because they appear below.

1. Create a private caller migration worksheet for the four highest-impact
   areas: variable creation, event lookup/scheduling, wait semantics and warning
   aggregation. Store no private data in public SAEF files.
2. Migrate a low-risk `CreateVariableByIdent` caller to `SAEF_EnsureVariable`
   privately and verify idempotency before addressing name-based callers.
3. Audit for dynamic or indirect invocation of the unused local
   `WaitForVariable`; if none exists, retire it and retain
   `SAEF_WaitForVariable`. Compare the three `WaitForBoolValue` call sites with
   the SAEF helper's change/update and expected-value semantics.
4. Map event callers to `SAEF_EnsureCyclicScriptEvent` and
   `SAEF_EnsureTriggeredScriptEvent`. Document only the schedule-window or
   action semantics that remain uncovered.
5. Create a future event-scheduling Knowledge article and Reference only after
   that uncovered contract has been approved. Validate the Reference through
   the least invasive authorized test path and remove any temporary live object.
6. Extend `EnsureEvent.php` again only if at least two independent use cases
   require the same uncovered contract without creating a parallel API.
7. Create a future archive-ownership Knowledge article for aggregation and
   cleanup safeguards; defer an archive helper until recurring reuse is
   demonstrated.
8. Capture the warning-summary domain rules privately. Publish only a sanitized
   case study if it teaches a reusable design lesson without installation data.
9. Resolve upstream permission/licensing and local authorship before publishing
   any implementation derived from the inspected functions.
