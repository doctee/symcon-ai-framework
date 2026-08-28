# 359 Private Map Capture Account Consent And Final Live Gate

**Case study:** Navimow native IP-Symcon module

**Status:** Account and protocol-risk statements confirmed; final live gate
remains closed

**Date:** 2026-08-27

## 1. Objective And Boundary

This step records the two human preconditions required by steps 355 and 358
without collecting account credentials or authorizing a vendor request.

It performs no DNS lookup, credential input, identity creation, authentication,
device registration, map read, Symcon access, message transport, mower command,
publication or retry.

## 2. Recorded Confirmations

The user confirmed both required statements together on 2026-08-27:

1. **Dedicated account:** The credentials belong to a dedicated second Navimow
   account to which the mower is shared; it is not the primary app account.
2. **Private-protocol acceptance:** The user accepts one bounded command-free
   private-cloud login and map-read experiment despite the undocumented
   protocol, possible server-side session and device-identity retention, and
   the absence of a proven logout or revocation endpoint.

No email address, password, account identifier, region, mower identifier or
other installation value is recorded in this public artifact.

These statements close the account and protocol-risk gates only. They do not
authorize execution of the private wrapper and do not authorize a retry.

## 3. Unchanged Technical Baseline

The private baseline remains bound to:

| Relative private file | SHA-256 |
|---|---|
| `capture-private-map-readonly.sh` | `792dfc8b23b53f026afc476d3981332c5f13dfb14f50f6c196ffc93dbd7c2e8f` |
| `capture_private_map_readonly.py` | `ad63ac7967c99569a198cb8b50a884a9946767f164f5bb5502d2d24f27b85ccc` |
| `private-map-requirements.txt` | `f340a26260a1e6559ad71047e1156fcd31c9f5ab96c5d851a0541fd5a8dee65a` |

At this gate:

- `output/private-map/` is absent;
- `state/private-map-device-identity.json` is absent;
- no attempt has been consumed;
- no credential has entered the capture process;
- the dependency and synthetic test results from steps 357 and 358 remain the
  applicable readiness evidence.

## 4. Final Live Preconditions

Immediately before a later live attempt, all of the following must be true:

1. the three private hashes in section 3 still match;
2. the non-mutating preflight passes again;
3. the intended mower is visibly docked according to the official app;
4. no earlier private-map attempt or unresolved capture lock exists;
5. the operator gives a fresh explicit approval for exactly one command-free
   authentication and map capture;
6. credentials are entered only into the local Mac terminal, never into chat,
   a command-line argument, environment variable, public file or Symcon;
7. the operator accepts that the stable private app identity is retained after
   the attempt while tokens are not intentionally persisted.

Failure of any precondition leaves the live gate closed.

## 5. Frozen One-Attempt Procedure

After the final live gate, the operator starts from the repository root:

```sh
NAVIMOW_PRIVATE_MAP_LIVE_GATE=PRIVATE_MAP_LIVE_GATE_CONFIRMED \
  ./private/navimow-capture/capture-private-map-readonly.sh
```

The local terminal then requests, in order:

1. the exact phrase `PRIVATE MAP ONCE`;
2. one fixed account region;
3. the dedicated second-account email;
4. the dedicated second-account password with terminal echo disabled.

The email is visible only in the local terminal input. The password is hidden.
Neither is passed in the process command line or environment.

The procedure executes no mower command and no message transport. It performs
at most one request per fixed authentication or map-read operation. A request
is consumed before dispatch; timeout, connection loss or an ambiguous response
must not be repeated.

## 6. Post-Attempt Handling

Regardless of outcome:

- no automatic second attempt is made;
- the process clears its credential variables and verifies capture-lock
  removal;
- tokens are not intentionally written to disk;
- the private app identity is retained unless a later separately designed
  server-session decision says otherwise;
- raw responses and real geometry remain under ignored private storage;
- only structure-only sanitized candidates may be reviewed for possible public
  evidence;
- local cleanup does not claim vendor-side logout, revocation or identity
  removal.

A failed authentication, timeout, ambiguous transport, missing map, malformed
payload, geometry rejection or cleanup failure requires a new analysis before
any retry can be proposed.

## 7. Architecture Decisions

### AD-NAV-359-01: Record consent without recording account data

**Decision:** Store only the semantic confirmations and date in SAEF.

**Reason:** Account ownership and risk acceptance are auditable without making
credentials or installation identifiers part of the public case study.

### AD-NAV-359-02: Keep execution as a separate final gate

**Decision:** The consent statements do not implicitly authorize network
execution.

**Reason:** A final gate can bind current hashes, docked state and the immediate
preflight to the actual one-attempt moment.

### AD-NAV-359-03: Consume ambiguous dispatches

**Decision:** Any dispatched request consumes its operation even when no clear
response arrives.

**Reason:** Retrying an authentication or registration mutation after an
ambiguous result could create duplicate vendor-side state.

## 8. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Dedicated shared account statement | **PASS** |
| Private-protocol risk acceptance | **PASS** |
| Public-data boundary | **PASS** |
| Private source hashes | **PASS unchanged** |
| Existing capture or identity state | **Absent** |
| Fresh immediate preflight | **Pending final live gate** |
| Docked-mower observation | **OPEN** |
| Exactly one live attempt | **NO-GO pending explicit approval** |
| Retry | **NO-GO** |
| Symcon or productive integration | **NO-GO** |

The next step is a final, immediate live gate. It must combine the user's
docked-mower observation with explicit approval for exactly one command-free
attempt. The unchanged non-mutating preflight runs first. Only if it passes may
the user enter the dedicated account credentials locally in the Mac terminal.
