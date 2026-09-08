# Gate 109: Scope-bound one-click profile

**Status:** Repository integration complete; exact Windows qualification,
profile installation and live use remain separately gated, 2026-09-07.

## Purpose

OwnTracks is the first reference target for the generic channel-v8
scope-bound approval contract. One reviewed **Jetzt anwenden** action may cover
the existing target deployment phases without turning the forced-command
channel into general remote execution.

No live Symcon object, package, target policy, provider setting or retained
artifact was changed in this gate.

## Reused contracts

The profile composes the existing channel-v8 staging and target allowlist, the
OwnTracks package and adapter policies, the five-writer quiescence boundary,
targeted Module Control reload, active-identity reseal and byte-exact rollback.
The generic runner owns approval claim and phase persistence; the OwnTracks
adapter continues to own module state, health and recovery.

Reuse Before Extend therefore adds no helper, public PHP API, gateway verb or
target-independent module lifecycle abstraction.

## Adapter extension

The existing adapter now accepts fixed `postflight`, `inspect` and `rollback`
operations in addition to its prior `preflight` and `activate` operations.

- `postflight` independently rechecks active package, configuration,
  presentation and authoritative state after activation.
- `inspect` classifies an interrupted action as activated, rolled back,
  safely not applied or uncertain.
- `rollback` restores the retained pre-activation package and state after a
  later coordinator phase fails.

Only a proven `not_applied` result permits redelivery of the identical,
still-valid approval envelope. Every uncertain mutation requires manual
recovery.

The reseal helper has a fixed coordinator-owned mutex mode and binds the
activation status plus approval-plan identity before changing either policy.

## Private profile material

The public examples contain placeholders only. A runnable private profile must
bind the exact qualified runner, adapter, reseal and evidence hashes; one
opaque channel host; hashed approver and execution-host identities; one
private HMAC secret; and a protected bounded state root.

The server generates the canonical plan during normal read-only preflight.
The POSIX controller stores that plan owner-only, then compares it with a fresh
server plan immediately before constructing the short-lived proof.

## Remaining gates

1. Execute the exact Windows PowerShell 5.1 qualification package.
2. Install the qualified private OwnTracks approval profile without restarting
   OpenSSH or Symcon.
3. Prepare and review the private plan through inactive stage and read-only
   preflight.
4. Authorize one exact **Jetzt anwenden** activation.
5. Verify runtime health through Symcon MCP and the existing browser acceptance
   path.
6. Handle cross-root retention in its separate workstream; this approval does
   not authorize deletion.

Media Carousel may reuse the generic proof, runner and client only after its
own target adapter exposes equivalent fixed postflight, inspection and
rollback contracts and passes a separate Windows qualification.
