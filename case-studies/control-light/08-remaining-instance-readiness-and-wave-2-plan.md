# Remaining-Instance Readiness and Wave 2 Plan

**Gate:** Read-only inventory, dependency and migration-risk assessment
**Result:** PASS WITH EXCLUSIONS
**Date:** 2026-07-19
**Live state:** Unchanged; one v2 pilot and 28 legacy contracts

## Read-only Scope

The assessment compared the private 29-instance inventory with the authorized
live installation. It read wrapper identities, parents, local variable
contracts, target links, target variables, event bindings and references to
local ControlLight variable IDs. No script was executed and no object, value,
event, source file or device was changed.

The previously migrated v2 wrapper was evaluated against its approved v2
identity, not against the obsolete legacy hash retained in the original
inventory baseline.

## Inventory Result

- the shared legacy core is byte-identical to its approved baseline;
- the active v2 pilot is distinct from the Auto-Off-dependent legacy instance;
- all 28 remaining legacy wrapper sources match their inventory hashes;
- all configured target variables are actionable;
- no previously unknown script consumer of a remaining local STATE or DIMMER
  variable was found;
- the only direct script consumer among the remaining instances is the known
  Auto-Off script, whose narrow STATE/DIMMER contract has already been
  migrated;
- all managed target events retain explicit Run Automation action binding;
- visible variable names, positions and other presentation properties were
  observed only and remain user-owned.

The known Homematic contract still has no local STATE action and remains
blocked. A Matter template without local capabilities is not a migration
candidate.

## Risk Groups

The 28 legacy contracts are grouped without exposing installation-specific
names or ObjectIDs:

| Group | Count | Treatment |
| --- | ---: | --- |
| Dependency-free Z2M, feedback aligned | 6 | Candidate pool |
| Z2M with presentation links only, feedback aligned | 13 | Later low-risk waves |
| Matter with links, feedback aligned | 1 | Separate scaling wave |
| External trigger contracts | 2 | Dedicated trigger regression |
| Current local/target feedback mismatch | 4 | Fresh sync analysis before migration |
| Matter template without capabilities | 1 | Exclude |
| Homematic local-action mismatch | 1 | Blocked |

Color, color-temperature and single-capability variants remain separate
subgroups inside these categories so that a successful two-capability pilot
does not overstate their coverage.

## Selected Wave 2 Candidate

The primary candidate is sanitized contract `CL-023`; `CL-025` is the
reserve. Both are Z2M state-and-brightness variants with:

- exact legacy source and topology;
- actionable target STATE and DIMMER variables;
- explicit, active target-feedback events;
- no external trigger;
- no downstream script or event reference to their local variables;
- no presentation link to their local variables;
- no foreign sibling below the owning container;
- equal local and target feedback.

`CL-023` is preferred because STATE is currently false while target and local
DIMMER retain a non-zero brightness. A non-commanding initial synchronization
therefore proves the agreed `reported` semantics directly. The reserve reports
zero brightness while off and would not distinguish `reported` from
`effective` during that gate.

## Wave 2 Preflight

Before any activation, a fresh bounded preflight must verify:

1. ready kernel and exact shared-core, legacy-wrapper and v2-fileset hashes;
2. the selected parent, wrapper, target link and complete child allowlist;
3. unchanged types, profiles, actions, Idents and parent relationships;
4. preservation snapshots for user-owned names, positions, icons and
   visibility;
5. active OnChange target events with explicit action binding;
6. no new local-variable script, event or link consumers;
7. actionable target STATE and DIMMER;
8. authoritative equality with STATE false and retained DIMMER non-zero;
9. absence of pre-existing v2 diagnostic Idents;
10. a reviewed `reported` configuration, exact backup and rollback source.

The evidence expires after fifteen minutes and is not activation authority.

## Activation, Regression and Rollback Plan

After a separate explicit approval, the transaction may replace only the
selected wrapper and run one non-commanding Execute synchronization. It must
require:

- command statistics remain zero;
- authoritative STATE and retained DIMMER remain equal;
- all existing object IDs and presentation properties remain stable;
- diagnostics and events match the fixed owned-object allowlist;
- a second reconciliation produces the identical topology;
- the active first pilot, shared core and other 27 legacy wrappers remain
  byte-identical;
- offline core, runtime and topology suites pass for all 29 sanitized
  contracts.

Any failed gate restores the exact legacy wrapper, executes its non-commanding
initialization, removes only newly created allowlisted diagnostics, and verifies
the complete old topology and feedback snapshot. Unknown objects or ownership
drift stop automated cleanup for review.

## Gate Decision

The read-only readiness gate is **PASS WITH EXCLUSIONS**. `CL-023` is ready
for construction of a private, hash-locked Wave 2 package. This report does not
authorize package staging or live activation.
