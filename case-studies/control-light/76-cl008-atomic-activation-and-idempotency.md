# CL-008 atomic activation and idempotency

## Outcome

CL-008 and its Auto-Off dependency are now active on their hash-bound sources.
The final committed activation:

- selected the staged member-confirmed ControlLight runtime only from CL-008;
- replaced the two Auto-Off member controls with one facade STATE control and
  facade DIMMER activity;
- reconciled both sources without a device command;
- preserved every group, facade and member value;
- retained the two aggregate group-projection events;
- activated four owned member-feedback events;
- preserved both foreign device-warning events and availability links; and
- passed a second reconciliation with stable object IDs and values.

`System.Locals` remains unchanged and the staged ControlLight fileset is not a
global runtime owner.

## Fail-closed verifier corrections

Two post-write assertions stopped earlier attempts after both candidates had
reconciled:

1. the first verifier incorrectly required update triggers for Boolean STATE
   events, although the SAEF helper correctly selects change triggers;
2. the second verifier expected `member_confirmed`, while the normalized
   contract value is `member-confirmed`.

The first automatic rollback restored both exact script sources and Auto-Off
semantics but initially could not deactivate the new member events because its
runtime configuration omitted the now-mandatory brightness contract. A bounded
repair supplied `reported` explicitly and deactivated all four member events.
The second rollback then completed without an error.

Because Auto-Off deliberately deletes obsolete owned events, its two temporary
rollback member events received new internal ObjectIDs during recovery. No
consumer referenced those owned event IDs; names, Idents, triggers and action
bindings were restored before the corrected activation. The final facade
events likewise have newly allocated internal IDs. This is an ownership-safe
topology change, but it is recorded rather than hidden.

Neither stopped attempt issued a device action or changed a tracked light
value.

## Final runtime evidence

The final registry records:

- version `ControlLight-v2-CL008-member-confirmed-20260726`;
- brightness semantics `reported`; and
- feedback authority `member-confirmed`.

After the committed activation and idempotency run, diagnostics reported six
total reconciliation executions across activation and recovery, six successes,
zero commands, zero errors and zero confirmation timeouts.

The final Auto-Off timer remained active with its existing schedule. Unrelated
motion and light controls were unchanged.

## Complete structural regression

Read-only postflight found all 29 expected wrapper scripts:

- 13 active v2 wrappers;
- 16 retained legacy wrappers; and
- no unclassified wrapper source.

CL-008 and Auto-Off match their exact candidate hashes. All other wrapper
sources were outside the mutation set and retained their identities. The
current ordered wrapper aggregate is recorded in the private evidence.

## Remaining gate

Activation and structural regression are complete. A real-device test remains
separately gated and must cover:

1. group STATE on and off with both members confirmed;
2. retained reported brightness while off;
3. bounded brightness changes with both members confirmed;
4. partial/member failure diagnostics where safely observable; and
5. one real Auto-Off shutdown through the CL-008 STATE facade.

Every test must snapshot and exactly restore the initial group and member
state.
