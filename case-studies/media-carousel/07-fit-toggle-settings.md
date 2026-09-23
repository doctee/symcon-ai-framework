# Scoped MediaCarousel fit-toggle activation

The module update and the instance setting are separate operations. A successful
upgrade adds the disabled `ShowFitToggle` default; it does not authorize changing
every instance. The settings coordinator enables that one property only for a
fresh, explicitly enumerated private cohort. Instances outside that cohort are
included in the preservation snapshot, not selected for mutation.

## Reuse and boundaries

`Set-SaefMediaCarouselFitToggle.ps1` composes hash-bound channel ACL/JSON/binding
validators and the installed adapter's UTF-8 RPC, ownership and snapshot
functions. It adds no general configuration API, deployment verb, credential
store or initializer behavior. The existing self-extracting operator launcher
has a fixed five-file `settings` profile; schema and binding profiles retain
their original allowlists and entry points.

The private plan pins the entire live configuration inventory, active package,
installed channel/policy hashes, target identities and category ancestry.
Channel-before-adapter mutex ordering excludes concurrent channel operations.
The operation does not replace module files, rewrite protected bindings, reload
Module Control, restart a service, create Symcon objects, request camera images
or alter FitMode, media ordering or layout. `IPS_ApplyChanges` refreshes the
existing module's source subscriptions and visualization bootstrap.

## Preservation and recovery

Before mutation the coordinator retains byte-exact before/expected-after
snapshots in a protected, unique evidence directory. Expected configuration
bytes differ only in the parsed boolean `ShowFitToggle`, without reserializing
existing properties. Every target is rechecked for positive ID, type, module
and parent before each mutator. Each property write is followed by ApplyChanges
and complete inventory/configuration/object-metadata readback. Intent and
verified records are retained separately.

`IPS_SetProperty` stages a desired value; it does not itself apply it. The
pre-ApplyChanges readback therefore accepts either the exact applied baseline
or the exact desired configuration, recording its hash and pending flag.
Any third configuration is still rejected. Only the exact desired state with
no pending changes passes the subsequent full postflight. Rollback observes
the same staged/applied distinction. The synthetic fixture models separate
pending values instead of incorrectly making SetProperty immediately effective.
See the official [SetProperty contract](https://www.symcon.de/en/service/documentation/command-reference/management-instances/configuration/ips-setproperty/).

Symcon has no atomic multi-instance property transaction. Caught failures use
reverse-order compensating rollback, including an uncertain last request.
Rollback accepts only the known before/after configuration hashes; external
drift stops recovery rather than being overwritten. A killed process cannot
promise automatic recovery: retained intent/snapshot evidence and fresh live
readback must be reviewed before retry. No evidence is automatically pruned.
The administrator controls when to run the already authorized package; a
successful operator report is followed by independent MCP read-only postflight.
Physical app acceptance remains a separate result.

## Qualification

The existing Windows PowerShell 5.1 fixture supplies real protected files,
DPAPI, bounded child execution and synthetic UTF-8 RPC. Settings scenarios cover
preflight, exact single-property preservation, an excluded instance, lost
mutation response, ApplyChanges failure, rollback, intervening drift, zero IDs,
scope mismatch, duplicate targets, stale configurations, changed package and
already-enabled settings under three cultures. The fixed settings package is
also extracted and compared byte-for-byte. These tests never contact live
Symcon and must pass before packaging for an operator.
