# 376 Local Map Runtime Disabled Symcon Rollout And Retention

**Case study:** Navimow native IP-Symcon module

**Status:** Exact standalone commit installed and verified disabled,
credential-free and REST-operational; activation remains closed

**Date:** 2026-08-28

## 1. Result

Two equal bounded read-only preflights passed before exactly one supported
module update. Corrected immediate and delayed postflights then passed.

    installed before: 790f6106c160130bb1931eb3e45f8c027ea9d772
    installed after: 783b37dbdce13dcbddd738a82fbba76bd72d7c86
    MC_UpdateModule calls: 1
    MC_ReloadModule calls: 0
    explicit ApplyChanges calls: 0
    service restarts: 0

Every accepted MCP result separately satisfied:

    transportError: null
    executionError: null
    truncated: false

## 2. Authorization And Channel

The user authorized the complete retention, standalone publication,
metadata-validation and disabled-rollout sequence. The mandatory Symcon MCP
binding was available before live access.

No browser, SSH, PowerShell, Computer Use or temporary Symcon object was used
for live-system work. The rollout did not authorize or perform MQTT or map
activation, credential retrieval, OAuth action, restart or mower command.

## 3. Accepted Preflight

Two independent read-only observations proved:

- exact baseline commit on branch `main`;
- clean and valid module repository;
- ready kernel and all four Navimow module instances active;
- MQTT and WebSocket Core instances inactive;
- MQTT and position diagnostics disabled;
- Authorization header and MQTT username and password absent;
- authentication connected without reauthentication requirement;
- REST operational;
- 14 existing variable identities stable;
- all five user-enabled Archive logging contracts retained;
- Local Map property, variable, timer and wrapper absent as expected before
  the update.

The two observations had equal identity, Archive, Account configuration,
normalized Device configuration and subscription hashes.

## 4. Single Supported Mutation

The mutation probe recomputed every safety condition immediately before
calling `MC_UpdateModule()` once. The call returned `true` and reported exact
target commit `783b37db` from a clean and valid repository.

There was no retry, module reload, explicit ApplyChanges, configuration write,
OAuth action, restart, MQTT activation or mower command.

## 5. Transparent Probe Correction

The first post-update read already proved the target commit, healthy statuses,
disabled features, empty credentials, stable hashes and a hidden empty map
variable. Its aggregate flag failed only because the private probe required an
inactive timer object to exist.

On this installation IP-Symcon does not materialize the registered zero-ms
module timer as an event object. The productive contract is that no active map
timer exists while the feature is disabled. The private probe was corrected to
accept either no timer object or an explicitly inactive timer.

Only the private read-only predicate changed. No product file was changed and
the module update was not repeated.

## 6. Immediate And Delayed Evidence

The corrected immediate and delayed postflights both passed:

    repository commit: 783b37db
    repository clean and valid: true
    Account, Configurator, Device, Receiver: active
    MQTT and WebSocket Core: inactive
    MQTT feature: disabled
    position diagnostics: disabled
    transport credentials: absent
    REST: operational
    Local Map property: present and false
    Local Map variable: present, hidden and empty
    active Local Map timer: absent
    public Local Map wrapper: available

Existing contracts remained byte-stable across the update:

| Contract | SHA-256 before and after |
| --- | --- |
| 14 existing variable identities | `02c2973d5a8d914f33d950b1ac73cb90894807a8178a68661403a2e0869a8ffc` |
| Archive logging and aggregation | `ca553115285c5c5336650ee2d635896df4cbdd109208c00a6f53aecc7f825d81` |
| Account configuration | `43b7c6c99b6f8d0b5a941c5ecb5343ce06c5f228bdb882a3d5e30391bcc458ad` |
| Device configuration without new map defaults | `1317df8adccda9dc4e30ae55c7d634aeb9c38fed23ac6a8264109e7ab4067cb5` |
| exact MQTT subscription structure | `9baf072cbd4986458357e3700203ad06bfbed01f8d7fa6b4ba38d98d59efd6e4` |

This explicitly preserves the user's enabled Battery and other Navimow
Archive logging. The update adds the hidden `LocalMap` presentation variable
without replacing or recreating the established variables.

## 7. Retention Decision

No worktree, branch or private evidence is removed in this step.

**Decision:** Retain the merged source worktree, post-merge documentation
worktree, release worktree, private map package, validator assets and disabled
rollout evidence until:

1. this report is canonical on SAEF `main`;
2. the exact standalone commit remains available as rollback identity;
3. a later local-map activation has passed or been rolled back;
4. an exact reference scan and protected cleanup allowlist are documented.

The broad cleanup authorization does not make deletion useful before these
conditions hold. Deferring cleanup preserves reproducibility at negligible
runtime risk.

## 8. Architecture Decisions

### AD-NAV-376-01: Preserve existing variables and archives

**Decision:** Accept the rollout only with identical pre/post identity and
Archive hashes for all 14 established variables.

**Reason:** The additive map variable must not disrupt the user's historical
Battery, state or command logging.

### AD-NAV-376-02: Treat zero-timer representation as an implementation detail

**Decision:** Require absence of an active timer, not existence of an inactive
event object.

**Reason:** Both representations are fail-closed; only active scheduling would
violate the disabled contract.

### AD-NAV-376-03: Retain rollback evidence through activation

**Decision:** Postpone destructive cleanup despite general authorization.

**Reason:** The first installation-specific map configuration and activation
still needs the exact package, source and previous commit identities.

## 9. Mutation Counts

    accepted preflights: 2
    module updates: 1
    retained probe-classification failures: 1
    corrected immediate postflights: 1
    delayed postflights: 1
    module reloads: 0
    explicit ApplyChanges: 0
    service restarts: 0
    MQTT activations: 0
    map activations: 0
    credential requests: 0
    OAuth actions: 0
    mower commands: 0

## 10. Gate Status

| Gate | Status |
| --- | --- |
| standalone publication | passed in step 375 |
| metadata conformance | passed in step 375 |
| exact disabled Symcon update | passed |
| immediate and delayed postflight | passed |
| existing variables and archives | preserved |
| Local Map default-disabled contract | passed |
| destructive cleanup | deferred by retention decision |
| private map configuration import | closed |
| Local Map activation | closed |
| MQTT activation | closed |

## 11. Next Step

Publish steps 375 and 376 as a documentation-only SAEF pull request and verify
its terminal checks. After canonicalization, prepare a separate
installation-specific Local Map configuration and activation gate bound to the
retained private geometry revision. MQTT remains independent and receive-only.
