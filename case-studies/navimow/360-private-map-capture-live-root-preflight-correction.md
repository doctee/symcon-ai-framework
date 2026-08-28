# 360 Private Map Capture Live Root Preflight Correction

**Case study:** Navimow native IP-Symcon module

**Status:** Silent pre-dispatch failure corrected and verified offline; live
attempt remains unconsumed and separately gated

**Date:** 2026-08-27

## 1. Incident

After the final gate from step 359, the first terminal input contained an
accidental leading shell bracket. The shell rejected that token before invoking
the wrapper. A corrected invocation then returned silently to the prompt.

Read-only inspection proved:

- the private Python process never started;
- the Passport endpoint was not dispatched;
- no credential prompt occurred;
- `output/private-map/` remained absent;
- `state/` and the stable identity remained absent;
- no capture lock or attempt ledger existed.

The one authorized vendor-side attempt was therefore not consumed.

## 2. Root Cause

The private wrapper derived its reducer root from its own location in the
canonical checkout. The approved `MapGeometryReducer.php` still exists only in
the isolated `navimow-map-source-readiness` worktree. A bare `test -f` under
`set -e` rejected the missing canonical path with exit status `1` but emitted no
diagnostic.

This was a local pre-dispatch path-resolution defect. It was not a Navimow API,
authentication, dependency, geometry or credential failure.

## 3. Correction

The ignored private wrapper now:

- emits a bounded error and exit status `4` when the reducer root or file is
  unavailable;
- accepts an explicit `NAVIMOW_PRIVATE_MAP_SAEF_ROOT` for preflight and live
  execution;
- allows only the canonical repository root or a descendant of its
  `private/worktrees/` directory;
- resolves the selected root to a physical absolute path;
- requires the exact reducer location under the selected case study;
- verifies reducer SHA-256
  `81ed4d50a62335f1099f8ce6072c1aa0fc9c03682197d0453906942023c644ae`
  before the private Python process can start;
- keeps arbitrary reducer arguments and external source roots impossible.

The wrapper does not search for a worktree automatically. Selection remains
explicit at the terminal and hash-bound.

## 4. Offline Verification

The default-root failure now produces:

```text
Hash-bound Navimow map reducer is absent from the selected SAEF root.
```

with exit status `4` and no state creation.

The explicit isolated-root preflight then passes:

```text
Private map live preflight passed without network or state mutation.
identityState=absent
captureLock=absent
```

The existing static policy, geometry reducer, sanitizer and synthetic
cryptography tests also pass. Before and after all checks, capture output,
identity state and lock remain absent.

## 5. Updated Private Binding

| Relative private file | SHA-256 |
|---|---|
| `capture-private-map-readonly.sh` | `5b920de1ba2e24b56266af49d6b743b01660f31cd35448bfb01f47c8ca5b2b4e` |
| `capture_private_map_readonly.py` | `ad63ac7967c99569a198cb8b50a884a9946767f164f5bb5502d2d24f27b85ccc` |
| `reduce-private-map.php` | `49b9030b5d8bde93d294c40104a357b2a9b5598ca0963fda2b00e7452be44f2a` |
| `private-map-requirements.txt` | `f340a26260a1e6559ad71047e1156fcd31c9f5ab96c5d851a0541fd5a8dee65a` |
| `private-map-third-party-notice.md` | `e7b79212636977493beba93208dad2dbec5eb1a5cb7dfb5db3a3f8b91e8bb1bc` |

All private file modes and dependency-wheel hashes remain unchanged.

## 6. Corrected Future Invocation

After a fresh final gate, the local command includes the explicit isolated
SAEF root:

```sh
NAVIMOW_PRIVATE_MAP_SAEF_ROOT=<SAEF_ROOT>/private/worktrees/navimow-map-source-readiness \
NAVIMOW_PRIVATE_MAP_LIVE_GATE=PRIVATE_MAP_LIVE_GATE_CONFIRMED \
  ./private/navimow-capture/capture-private-map-readonly.sh
```

The root is not a credential. Email and password remain interactive local
terminal inputs and are not passed through environment variables.

## 7. Architecture Decisions

### AD-NAV-360-01: Fail explicitly before Python startup

**Decision:** Replace a silent shell assertion with a classified diagnostic.

**Reason:** The operator must distinguish a local readiness defect from a
consumed or ambiguous vendor request.

### AD-NAV-360-02: Bind the temporary worktree root by boundary and hash

**Decision:** Permit the isolated worktree only through an explicit root inside
the repository's private worktree boundary and an exact reducer hash.

**Reason:** This preserves source isolation without copying reducer logic or
allowing arbitrary executable PHP input.

### AD-NAV-360-03: Refresh authorization after tool-hash change

**Decision:** Do not reuse the previous final execution gate automatically.

**Reason:** Although no attempt was consumed, the wrapper hash and invocation
contract changed. The operator should authorize the corrected baseline.

## 8. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Root cause | **Confirmed** |
| Vendor request attempted | **No** |
| One-attempt budget consumed | **No** |
| Identity or capture state created | **No** |
| Explicit failure diagnostic | **PASS** |
| Allowed-root boundary | **PASS** |
| Reducer hash binding | **PASS** |
| Explicit-root preflight | **PASS** |
| Account and risk statements | **Remain recorded** |
| Corrected live attempt | **NO-GO pending refreshed approval** |
| Retry after any dispatched request | **NO-GO** |

The next action requires a fresh docked-state confirmation and approval for
exactly one invocation using private wrapper hash
`5b920de1ba2e24b56266af49d6b743b01660f31cd35448bfb01f47c8ca5b2b4e`.
The corrected non-mutating preflight runs immediately before the terminal
credential prompts.
