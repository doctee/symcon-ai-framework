# CL-008 Auto-Off expiry and Spiegel state alignment

## Outcome

The remaining CL-008 Auto-Off gate is closed. A real shortened timer expiry
switched the CL-008 facade, aggregate group endpoint and both configured group
members off with authoritative feedback. Reported brightness remained at the
stored device level while STATE became false.

The other managed lights were already off and remained off. In particular,
the Spiegel retained its stored brightness unchanged.

## Consumer correction

The safety preflight exposed a legacy consumer mismatch: the Spiegel was
physically off, but its retained brightness was non-zero. Auto-Off still used
that brightness variable as both command and activity state and would therefore
have overwritten retained brightness during an unrelated expiry.

The live configuration was narrowed to the intended contract:

- Spiegel STATE is the off-command and confirmation authority;
- Spiegel STATE and brightness are activity signals; and
- RequestAction remains the only device-control path.

The exact source delta was read back successfully. Two explicit configuration
reconciliations produced one owned STATE event and retained the separate
brightness activity event without duplicates. No device command occurred
during source activation or reconciliation.

## Real expiry

The bounded test began only after all safety predicates held: motion was
inactive, CL-008 was on, and the other managed lights were off. The production
script timer was shortened to five seconds; the actual TimerEvent, rather than
a direct substitute call, executed the shutdown path.

The postcondition confirmed:

- CL-008 facade STATE false;
- aggregate group STATE false;
- both member STATE values false;
- retained facade, group and member brightness unchanged;
- Spiegel STATE false and retained Spiegel brightness unchanged;
- no follow-up cycle; and
- timer inactive.

After the observation, the inactive timer interval was restored to the
production 1800-second contract and the expired internal suppression entry was
cleared. The production source remained unchanged from the corrected readback.

## Diagnostic note

An initial probe intended to emulate console execution placed context before a
strict-types declaration. PHP rejected it before executing any application
code. Symcon reported the fatal text in captured output while leaving the
structured execution-error field empty. The corrected probe preserved
strict-types ordering, and both reconciliations then passed. This reinforces
the SAEF rule that bounded probes must evaluate captured fatal output as well
as transport and structured execution errors.
