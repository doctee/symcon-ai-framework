# 302 Position Accounting Stabilization Publication

**Case study:** Navimow native IP-Symcon module

**Status:** SAEF candidate merged and exact standalone change published

**Date:** 2026-08-09

## 1. Result

The reviewed stabilization candidate was published through SAEF pull request
#36 and merged by merge commit. The exact productive delta was then published
to the dedicated standalone repository.

```text
SAEF candidate:       af9c876a0ec52a1f5a6e6149e5c8b1e92a56196e
SAEF pull request:    #36
SAEF merge commit:    37b7f12481069c4598c22aa5955fe6477742b521
standalone baseline:  4b4b4d7b577df2639ed4a82049aa127c56bdc989
standalone published: 50b365200e0c5c55990214c31f4a46f28b1406c7
```

GitHub CI passed before the SHA-bound merge. The merged SAEF tree equals the
reviewed candidate tree.

## 2. Standalone Fileset

Only this productive path changed:

```text
distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
```

After the copy, the complete standalone repository tree was byte-equal to the
canonical SAEF distribution. Local HEAD, `origin/main` and direct remote
read-back all resolved to the exact published commit.

## 3. Preserved Contracts

- REST remains authoritative for public device state.
- MQTT remains receive-only.
- No MQTT publish or mower-command path was introduced.
- Coordinates remain ephemeral and are cleared on transport cleanup.
- Public variables, profiles, actions, GUIDs and Archive contracts are
  unchanged.
- MQTT and position diagnostics remain disabled by default.

## 4. Architecture Decisions

### AD-NAV-1266: Publish one productive file only

Documentation, fixtures and tests remain in SAEF. The standalone mutation is
restricted to the exact productive Account module file.

### AD-NAV-1267: Require complete-tree equality after publication

The one-file delta is accepted only when the resulting standalone tree equals
the complete canonical distribution, not merely when the changed file hash
matches.

### AD-NAV-1268: Bind merge and publication independently

The SAEF merge is bound to its reviewed head SHA. The standalone update is
bound independently to its known baseline and exact resulting commit.

## 5. Mutation Counts

```text
SAEF commits:                 1
SAEF pull requests:           1
SAEF merge commits:           1
standalone files changed:     1
standalone commits:           1
standalone remote updates:    1
tags or releases:             0
Symcon mutations:             0
MQTT activations:             0
mower commands:               0
```

## 6. Next Gate

Validate the metadata contract for exact standalone commit `50b3652` before a
disabled Symcon update.
