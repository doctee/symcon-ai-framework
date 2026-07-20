# Auto-Off STATE Contract Migration Report

**Gate:** Narrow downstream-consumer migration for the Auto-Off-dependent
legacy instance
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Auto-Off uses STATE for switching and DIMMER for activity

## Scope and Contract

> **Correction (2026-07-20):** The original report called this consumer the
> active hallway pilot's Auto-Off consumer. A later direct live reconciliation
> proved that the Auto-Off consumer belongs to the distinct, still-legacy
> sanitized instance `CL-026`. The active v2 hallway pilot was not its upstream
> ControlLight instance. This correction changes the instance attribution, not
> the recorded STATE/DIMMER contract or the PASS result.

This transaction changed only the existing Auto-Off consumer associated with
the Auto-Off-dependent legacy instance `CL-026`. It deliberately did not
perform the later full SAEF modernization of that script and did not migrate
the upstream ControlLight wrapper.

The activated contract is:

- `STATE` is the on/off truth and the only timer-expiry action target;
- a transition of `STATE` to true arms the timer;
- a transition of `STATE` to false does not arm the timer;
- `DIMMER` remains an activity trigger while `STATE` is true and brightness is
  greater than zero, preserving the documented dimming-extends-timer behavior;
- retained non-zero brightness while `STATE` is false does not arm the timer;
- timer expiry requests `STATE=false` through `RequestAction()`;
- motion, locking, suppression and the existing timer behavior remain intact.

This removes the consumer's dependency on effective-zero DIMMER semantics and
permits a later ControlLight migration to use the agreed `reported` default.

## Activation and Idempotency

The live source was installed from the reviewed private candidate. The write
interface canonicalized two trailing LF characters to one; the resulting live
source identity was therefore verified against that deterministic canonical
form as well as by direct source read-back.

The first reconciliation reused every existing owned object and added exactly
one active STATE OnChange event with explicit Run Automation action binding.
The second reconciliation produced the identical object and event snapshot.

| Check | Result |
| --- | --- |
| Existing timer event reused | PASS |
| Existing DIMMER event reused | PASS |
| Existing motion event reused | PASS |
| Existing suppression variable reused | PASS |
| New STATE event | Exactly one |
| Second reconciliation | Idempotent |
| Timer interval changed | No |
| Suppression state changed | No |
| STATE/DIMMER value or timestamp changed | No |
| ControlLight wrapper changed | No |
| Device command during migration | None |

Transport errors, PHP execution errors and truncation were checked separately
for every bounded live probe.

## Regression Evidence

The offline harness executes the actual candidate in eight isolated scenarios:

1. STATE on arms the timer;
2. STATE off does not arm it;
3. positive DIMMER activity while on arms it;
4. retained brightness while off does not arm it;
5. zero brightness while on does not arm it;
6. motion changes arm it;
7. timer expiry requests only STATE off;
8. active motion prevents timer expiry from switching off.

All eight scenarios passed again after activation. An independent direct live
read-back confirmed the installed script. The Auto-Off-dependent ControlLight
wrapper remained on its exact legacy baseline; the separate active v2 hallway
pilot also remained unchanged.

## Remaining Work

This PASS closed only the narrow consumer-contract dependency for `CL-026`.
Helper reuse, owned-resource reconciliation, structured diagnostics and other
SAEF improvements inside Auto-Off were deferred for evaluation at this gate.
The later separate review implemented the approved modernization scope and
kept runtime state explicit without making upstream v2 diagnostics a consumer
dependency. Its result and functional verification are recorded in
`42-autooff-modernization-and-cl026-contract-verification.md`.

No ControlLight instance migration was authorized by this result.
