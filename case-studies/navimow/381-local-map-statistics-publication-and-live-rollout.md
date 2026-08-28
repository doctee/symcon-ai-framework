# SAEF Step 381: Local Map Statistics Publication and Live Rollout

## Status

Complete. The corrected module is published, installed and verified through
immediate and delayed read-only postflights.

## Scope

This step closes the publication and live rollout of the Local Map state-color
and zone-statistics extension from step 380. It also records one lifecycle
defect found by the first guarded live activation.

The scope remains deliberately narrow:

- explain all station and mower state colors in the map legend;
- expose stable statistics variables only for accepted, bound map zones;
- preserve all pre-existing variable identities and Archive logging settings;
- keep REST authoritative and MQTT receive-only;
- do not activate MQTT, obtain credentials, authenticate, restart Symcon or
  issue a mower command.

## Published Color Contract

The station symbol is REST-authoritative:

| Color | Meaning |
| --- | --- |
| Green | Mower docked |
| Orange | Mower returning to the station |
| Gray | Station unoccupied while the mower is away |
| Teal | Station state unknown, stale or unsupported |

The mower symbol is also colored by the REST-authoritative vehicle state:

| Color | Meaning |
| --- | --- |
| Green | Active, including mowing or mapping |
| Yellow | Paused, idle or ready |
| Orange | Returning to the station |
| Red | Attention required, lifted or error |
| Gray | Offline |
| Teal | Unknown, stale or unsupported |

A productive mower marker is rendered only when current position evidence is
fresh. Retained paths never masquerade as the current mower position. While the
mower is docked, the green station symbol represents its location and the
separate mower marker is hidden.

## Stable Statistics Contract

Statistics are opt-in through `EnableZoneStatistics`. Enabling the property
creates global state and update variables plus four variables for each accepted
zone that is bound to a manufacturer zone ID:

- pass progress;
- observed area;
- last observed timestamp;
- evidence quality.

The private accepted map currently binds three zones. The unbound fourth zone
does not receive statistics variables. Disabling statistics retains existing
variables and marks the statistics state disabled; the module never changes
Archive logging configuration.

Pass progress and observed area describe bounded MQTT path evidence. They are
not geometric proof that the whole physical zone has been mowed.

## First Live Activation Evidence

The guarded rollout first updated the standalone module successfully and then
stopped before activation because IP-Symcon serialized the newly registered
`EnableZoneStatistics=false` default into the Device configuration. The module
tree was clean and valid, but the byte-exact configuration hash changed as a
normal consequence of the newly introduced property.

A separately bounded continuation set only `EnableZoneStatistics=true`, ran
exactly one Device `ApplyChanges()` and refreshed the local map once. Read-only
evidence then established:

- all 14 expected statistics variables exist;
- no statistics variable exists for the unbound zone;
- the statistics state is `No Data`, which is expected with MQTT disabled;
- the established variable identity and Archive logging hashes are unchanged;
- MQTT remains disabled and its native transport is credential-free;
- the expanded map legend is present exactly once;
- no retained position is rendered as a current mower marker.

The same read-back exposed Device status `101`. The Device implementation called
`parent::ApplyChanges()`, which enters the creating state, but unlike the
Account module it did not finalize successful application with status `102`.
This was not a statistics-data error.

## Lifecycle Correction

The correction adds one terminal active-status transition after every
successful Device `ApplyChanges()`. It does not change configuration,
communication, polling, commands, map reduction or statistics semantics.

The existing Symcon harness already models the parent transition to `101`.
The statistics lifecycle test now asserts that the Device finishes at
`IS_ACTIVE`, preventing recurrence.

## Verification

The following checks pass with the canonical lock-identical Composer toolset:

- focused Local Map statistics lifecycle test;
- complete Navimow offline check including PHPStan;
- deterministic Navimow module fileset build and check;
- generic manifest-driven publication check;
- complete repository `composer check`.

The isolated worktree intentionally contains no `vendor/` directory. Tooling
was resolved through the canonical checkout's lock-identical vendor directory;
no source or generated artifact was taken from another worktree.

## Publication Evidence

The generic manifest-driven publisher created and integrated standalone PR 6.
Independent post-merge verification established:

- standalone `main`: `3926bbdf5211b32fc315ac5d4eacda06e1a8a3cf`;
- candidate head: `d1cbbfef43b6f5eb1e5f0c670d6cdc14e2203cbb`;
- files: 42;
- fileset SHA-256:
  `e711fa5220d81a915478c38833184e943cd6e5fd8afa7e71d08c6d0d52f23d4c`;
- publication SHA-256:
  `5b0411a3bebcae92094417bce2d6414b4866faf23db466e8511305559f4ce1b5`;
- reported standalone checks: none configured, represented explicitly as
  `checkCount: 0`.

## Final Live Evidence

The final live mutation performed exactly one module update and no explicit
Device `ApplyChanges()`. The module update itself reconciled the instances.
Transport succeeded, PHP execution had no error and output was not truncated.

Both the immediate and delayed read-only postflights passed. They confirmed:

- exact installed standalone commit prefix `3926bbdf` on clean, valid `main`;
- Account, Configurator, Device and Receiver status `102`;
- native MQTT and WebSocket transport inactive at status `104`;
- MQTT disabled and both native transport instances credential-free;
- statistics enabled with all 14 variables present;
- no statistics variable for the unbound zone;
- statistics state `No Data`, expected without fresh MQTT evidence;
- unchanged established variable-identity and Archive-logging hashes;
- exactly one expanded legend with all station and mower meanings;
- no retained path endpoint rendered as a current mower marker.

No OAuth action, MQTT credential request, MQTT activation, Symcon restart or
mower command occurred.

## Gates

| Gate | Status |
| --- | --- |
| Lifecycle correction implementation | Passed |
| Complete offline verification | Passed |
| SAEF branch publication and review | PR 89 published; final CI pending |
| Standalone Navimow publication | Passed |
| Guarded Symcon update | Passed |
| Immediate and delayed read-only verification | Passed |

## Architecture Decision

### AD-NAV-381-01: Finalize Device lifecycle explicitly

**Decision:** A successful Device `ApplyChanges()` explicitly sets status
`102`.

**Reason:** IP-Symcon resets an instance to the creating state while applying
changes. Successful configuration must leave a deterministic active status;
statistics evidence state is represented by dedicated variables and must not
be conflated with the instance lifecycle status.

## Next Step

Merge SAEF PR 89 after its final checks pass and verify the canonical `main`
tree. Statistics may then accumulate during a separately authorized bounded
receive-only MQTT observation; no transport activation is implied by this step.
