# SAEF Step 381: Local Map Statistics Publication and Live Rollout

## Status

Implementation and offline verification complete. Standalone publication and
the final guarded Symcon verification are pending at the time of this commit.

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

## Gates

| Gate | Status |
| --- | --- |
| Lifecycle correction implementation | Passed |
| Complete offline verification | Passed |
| SAEF branch publication and review | Pending |
| Standalone Navimow publication | Pending |
| Guarded Symcon update | Pending |
| Immediate and delayed read-only verification | Pending |

## Architecture Decision

### AD-NAV-381-01: Finalize Device lifecycle explicitly

**Decision:** A successful Device `ApplyChanges()` explicitly sets status
`102`.

**Reason:** IP-Symcon resets an instance to the creating state while applying
changes. Successful configuration must leave a deterministic active status;
statistics evidence state is represented by dedicated variables and must not
be conflated with the instance lifecycle status.

## Next Step

Publish the hash-pinned candidate through the generic pull-request publisher,
integrate it only after checks pass, update the installed module once and repeat
the bounded immediate and delayed read-only verification.
