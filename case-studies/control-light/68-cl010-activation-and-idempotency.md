# CL-010 activation and idempotency

## Outcome

CL-010 is active on ControlLight v2 with authoritative feedback and `reported`
brightness semantics. The source transition, two non-commanding reconciliation
runs and complete 29-wrapper postflight passed. No device command, runtime
error, confirmation timeout or rollback occurred.

The enabled STATE and brightness device-capability matrix remains a separate
approval gate.

## Fail-closed preflight correction

The first fresh probe stopped before mutation because its locally supplied
expected hash for the independent light-on warning owner was incorrect. Direct
script readback confirmed that the short diagnostic source itself was
unchanged. The expected hash was corrected and the complete read-only
preflight was rerun.

This was a verifier-input correction, not live drift. The failed probe changed
no script, object or variable and sent no device action.

## Fresh activation gate

The successful repeated preflight confirmed:

- the exact legacy CL-010 source;
- the exact immutable staged runtime;
- zero source mismatches across all 29 wrappers;
- an operational and available single-device Zigbee2MQTT target;
- matching local and authoritative STATE and brightness values;
- both existing active feedback events with explicit action binding;
- the target link, two user presentation links and all names and positions;
- the independent diagnostic STATE observer and its owner source.

CL-008 was not part of this transaction and remains behind its separate Z2M
group-semantics gate.

## Source transition and reconciliation

The candidate source was written only after the successful gate and read back
with its exact expected hash before execution. It selects the already staged
immutable ControlLight runtime used by the Hue Wall cohort but has no Hue Wall
input dependency of its own.

Both explicit runs succeeded. Their combined diagnostics were:

| Counter | Result |
| --- | ---: |
| Executions | 2 |
| Successes | 2 |
| Commands | 0 |
| Errors | 0 |
| Confirmation timeouts | 0 |

Local and native values remained `STATE=true` and `DIMMER=100`. The Registry
records the expected version, deterministic configuration hash and `reported`
brightness contract. The bounded error history is empty.

The existing STATE feedback event was reconciled from change to update
triggering, as required to observe authoritative same-value updates. The
brightness event retained change triggering. Both event objects and their
explicit actions were reused in place.

## Preservation and regression

The activation preserved:

- the container and wrapper presentation;
- both local variable identities, names, positions, profiles and actions;
- the hidden target link;
- both user-facing presentation links;
- the independent diagnostic STATE observer;
- every unrelated wrapper source.

The current inventory is therefore 12 active v2 wrappers and 17 retained
legacy wrappers. Ten active wrappers have complete enabled-capability device
evidence; CL-010 now awaits its separately approved STATE/brightness device
test.
