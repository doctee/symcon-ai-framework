# 301 Combined MQTT Position Stabilization Offline Verification

**Case study:** Navimow native IP-Symcon module

**Status:** Local candidate verified; publication and live gates closed

**Date:** 2026-08-09

## 1. Verified Scope

The offline candidate adds pilot-wide, coordinate-free position accounting to
the existing bounded MQTT observation registry. It does not change REST
authority, MQTT receive-only semantics, subscriptions, commands, public
variables, profiles, GUIDs or credential handling.

The private harness consumes the additive aggregate when available and retains
its legacy fallback for the currently installed module version.

## 2. Verification Results

The following gates passed from the isolated worktree
`codex/navimow-pilot-stabilization`:

- focused Navimow MQTT shadow, position, lifecycle and checkpoint suite;
- focused PHPStan and PHPCS checks;
- complete repository `make check`;
- private pilot-harness syntax and state-machine validation;
- Git whitespace validation.

The complete repository check used the canonical dependency installation only
after confirming that its `composer.lock` SHA-256 matched the isolated
worktree. The temporary local `vendor` link was removed after verification.

## 3. Regression Boundaries

The tests prove:

1. raw position segments can reset while pilot totals remain monotonic;
2. the observed synthetic `801 + 11` segments yield `812` received samples;
3. coordinates are still removed on `ApplyChanges()` and disconnect cleanup;
4. malformed pilot-registry JSON cannot block ephemeral position cleanup;
5. old registry and checkpoint shapes remain readable without private-field
   leakage;
6. post-deadline normal harness ingestion remains fail-closed.

## 4. Interpretation of the Closed Pilot

The 79 credential rotations are consistent with the existing short-lived
OAuth token lifecycle and its refresh margin. They do not justify weakening
authentication or recovery behavior.

The 14 transport episodes remain genuine bounded observations. Available
status evidence does not identify a narrower WebSocket or upstream WSS cause.
Automatic recovery completed without exhausted reconnects. This candidate
therefore improves evidence continuity only; it does not alter retry policy.

## 5. Architecture Decisions

### AD-NAV-1264: Cleanup remains stronger than diagnostics preservation

Malformed diagnostic state may lose its aggregate, but it must never prevent
credential and coordinate cleanup. The decoder's empty fallback and a dedicated
regression test preserve this ordering.

### AD-NAV-1265: Verification does not imply publication authority

Passing offline gates freezes a reviewable candidate. Commit, push, pull
request, standalone publication, Symcon update and another pilot each remain
separate explicit gates.

## 6. Gate State

| Gate | Status |
|---|---|
| local implementation | PASS |
| focused checks | PASS |
| complete repository check | PASS |
| private harness validation | PASS |
| local commit | NOT PERFORMED |
| SAEF push/PR | NOT PERFORMED |
| standalone publication | NOT PERFORMED |
| Symcon update | NOT PERFORMED |
| MQTT activation | NOT PERFORMED |

## 7. Recommended Next Gate

Review and commit the exact local candidate in the isolated SAEF worktree.
After a separate publication decision, use the normal sequence:

1. SAEF branch push and pull request;
2. merge and canonical `origin/main` verification;
3. exact standalone fileset publication;
4. metadata validation;
5. disabled Symcon update and read-only verification;
6. only then decide whether a new bounded receive-only pilot is warranted.
