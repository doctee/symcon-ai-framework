# 358 Private Map Capture Live Preflight And Account Gate

**Case study:** Navimow native IP-Symcon module

**Status:** Technical live preflight complete; account, consent and live gates
remain closed

**Date:** 2026-08-27

## 1. Objective And Boundary

This step performs the last non-mutating technical review before a possible
single private map capture. It verifies the hash-bound private tool from step
357, closes two implementation-to-procedure gaps found during review and adds a
network-free live-preflight mode.

The approval for this step does not authorize Navimow DNS, credential input,
Passport login, vendor device registration, stable identity creation, map
capture, Symcon access, message transport, mower commands, publication or a
retry. None of those actions occurred.

## 2. Review Findings Before Live Access

The step-356 implementation correctly constrained operations, retries,
transport and output locations, but the detailed comparison with step 355 found
two pre-live gaps:

1. the synthetic sanitizer was tested offline, but the live success path did
   not yet rescan the generated structure report against all credential,
   identity, mower and map identifiers known to the process;
2. failure paths stored an outcome but did not yet reconcile the explicit
   `LocallyCleaning`, `FailedCleanly` and `CleanupIncomplete` states required by
   the planned evidence state machine.

These are private evidence and failure-accounting defects. They do not affect
the productive IP-Symcon module, REST authority, MQTT receive-only transport or
mower commands. They are closed before any vendor call.

## 3. Private Hardening

The ignored private helper now:

- collects known credential, stable-identity, account, mower and map values only
  in process memory;
- rejects the allowlisted structure report if any collected private value or
  forbidden key is present;
- rereads and rechecks the atomically written structure report;
- clears the private-value collection during local cleanup;
- enters `LocallyCleaning` for every post-ledger exit path;
- removes and verifies the exclusive capture lock;
- records `LocallyCleaned` for a completed operation;
- records `FailedCleanly` for a handled failure with successful local cleanup;
- records `CleanupIncomplete` and the `cleanup_incomplete` outcome if the lock
  cannot be removed;
- writes the sanitized capture report only after successful local cleanup;
- converts unexpected implementation exceptions to the bounded public result
  class `internal_error` without exposing exception text.

No automatic retry, token refresh, logout claim or identity deletion was added.
The stable identity remains intentionally retained after a later authorized
attempt because silently generating another vendor identity would be riskier.

## 4. Non-Mutating Live Preflight

The new wrapper mode is:

```sh
NAVIMOW_PRIVATE_MAP_LIVE_PREFLIGHT_ONLY=1 \
  ./private/navimow-capture/capture-private-map-readonly.sh
```

It runs inside the ignored private virtual environment and verifies:

- CPython `3.9` on `arm64`;
- exact installed versions `cryptography 44.0.3`, `cffi 2.0.0` and
  `pycparser 2.23`;
- the fixed RSA public-key DER hash;
- an absent capture lock;
- at most 100 retained attempt ledgers and no pending attempt;
- an absent stable identity or an existing mode-`600` valid identity.

It does not instantiate `PolicyTransport`, resolve a vendor host, ask for an
email or password, create an identity, create state/output directories or alter
an existing evidence file.

Observed result:

```text
Private map live preflight passed without network or state mutation.
identityState=absent
captureLock=absent
```

Both `state/` and `output/private-map/` remained absent.

## 5. Synthetic Failure-Path Evidence

The crypto self-test now also exercises local evidence closure:

| Scenario | Expected result | Result |
|---|---|---|
| known private value appears in sanitized report | reject | **PASS** |
| handled synthetic failure, removable lock | `FailedCleanly` | **PASS** |
| handled synthetic failure, deliberately non-empty lock | `CleanupIncomplete` | **PASS** |

The existing AES, RSA-envelope and synthetic response checks remain green.
The no-network reducer, sanitizer and fixed-operation tests also remain green.
A normal invocation without the future live environment gate still exits with
status `2` before creating state.

The complete Navimow suite passes, including REST, pilot recovery, MQTT,
position and task diagnostics, map reduction, distribution, fileset and the
non-mutating publication check. The repository-wide `make check` also passes
through the framework-wide lock-identical Composer toolchain resolver. No
case-study-specific dependency fallback or cross-worktree source path is used.

## 6. Updated Private Tool Binding

| Relative private file | SHA-256 |
|---|---|
| `capture-private-map-readonly.sh` | `792dfc8b23b53f026afc476d3981332c5f13dfb14f50f6c196ffc93dbd7c2e8f` |
| `capture_private_map_readonly.py` | `ad63ac7967c99569a198cb8b50a884a9946767f164f5bb5502d2d24f27b85ccc` |
| `reduce-private-map.php` | `49b9030b5d8bde93d294c40104a357b2a9b5598ca0963fda2b00e7452be44f2a` |
| `private-map-requirements.txt` | `f340a26260a1e6559ad71047e1156fcd31c9f5ab96c5d851a0541fd5a8dee65a` |
| `private-map-third-party-notice.md` | `e7b79212636977493beba93208dad2dbec5eb1a5cb7dfb5db3a3f8b91e8bb1bc` |

The wheel hashes remain unchanged from step 357. The private scripts and
environment roots retain mode `700`; non-executable private contract files
retain mode `600`.

## 7. Account And Consent Gate

The technical preflight cannot establish who owns the future credentials or
accept vendor-side risk. A live attempt therefore remains blocked until the
user confirms both statements together and without qualification:

1. **Dedicated account:** The credentials belong to a dedicated second Navimow
   account to which the mower is shared; they are not the primary app account.
2. **Private-protocol acceptance:** The user accepts one bounded private-cloud
   authentication and map-read experiment despite the undocumented protocol,
   possible vendor-side session retention and absence of a proven logout or
   revocation endpoint.

That confirmation authorizes neither the live attempt itself nor a retry. A
separate final live gate must still authorize exactly one command-free attempt.

Immediately before that future attempt, the operator must additionally confirm
that the intended mower is docked in the official app. This is a physical and
account-state observation; the current preflight does not query the mower.

## 8. Architecture Decisions

### AD-NAV-358-01: Treat procedure drift as a live blocker

**Decision:** Close sanitizer and failure-state gaps before asking for account
or live authorization.

**Reason:** A bounded endpoint list is insufficient if evidence cleanup and
privacy checks do not match the approved procedure.

### AD-NAV-358-02: Keep preflight unable to create state

**Decision:** The preflight may inspect an existing identity and attempt ledger
but may not create directories, identity, lock or evidence.

**Reason:** Readiness verification must remain repeatable without consuming the
one authorized vendor-side attempt or changing its future baseline.

### AD-NAV-358-03: Preserve two distinct human gates

**Decision:** Account ownership and protocol-risk acceptance are confirmed
together first; the actual network attempt remains a later explicit gate.

**Reason:** This prevents a generic engineering-step approval from being
misread as permission to submit credentials or mutate vendor authentication
state.

## 9. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Private source hashes | **PASS** |
| Exact dependency runtime | **PASS** |
| Synthetic cryptography | **PASS** |
| Sanitized-value rejection | **PASS** |
| Failure cleanup states | **PASS** |
| Non-mutating live preflight | **PASS** |
| Existing state or unfinished attempt | **Absent** |
| Stable device identity | **Absent; not created** |
| Dedicated shared account confirmation | **OPEN** |
| Private-protocol acceptance | **OPEN** |
| Docked-mower observation | **OPEN** |
| Vendor authentication and map capture | **NO-GO** |
| Retry | **NO-GO** |
| Symcon or productive integration | **NO-GO** |

The next step is the explicit human account and consent confirmation defined in
section 7. After it is recorded, a final separately approved live gate may run
the unchanged preflight once more and then permit exactly one invocation of the
fixed command-free capture wrapper.

No second attempt follows an authentication error, timeout, ambiguous response,
missing map, reducer rejection or cleanup failure without a new SAEF analysis
and approval.
