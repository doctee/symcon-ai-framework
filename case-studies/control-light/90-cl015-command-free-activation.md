# CL-015 command-free activation

## Outcome

CL-015 Kuschelsofa is active on the member-confirmed v2 runtime. The final
activation completed three command-free reconciliations, including an explicit
topology comparison between the last two runs.

The final state has:

- three member-confirmed STATE observers;
- three member-confirmed brightness observers;
- three member-confirmed color-temperature observers;
- the three existing aggregate target observers;
- the two existing alarm-aware external on/off observers; and
- explicit event action binding on all 14 events.

Facade, group endpoint and every member retained their complete initial values.
Diagnostics show three executions and successes, zero commands, zero errors,
zero confirmation timeouts and an empty error history.

## Protected stopped attempt

The first transaction stopped before wrapper execution because its manually
embedded candidate payload did not reproduce the reviewed candidate hash. The
transaction restored the exact legacy source, verified every tracked value and
left no device command.

The candidate itself was unchanged. The corrected activation used the direct
script-content interface and required exact source readback before execution.
This produced the reviewed candidate hash and removed the lossy manual payload
copy from the activation path.

## Fresh external gate

Immediately before mutation, a separately bounded Zigbee2MQTT extension query
reconfirmed group ID 2 and the same three package-bound members. It issued one
metadata request and no device or group command.

The immutable staged runtime, core and fileset manifest retained their expected
hashes. The inverse alarm remained active for the absent household, so the
activation deliberately used only the `Execute` reconciliation path.

## Consumers and regression

The Alexa expert configuration still consumes the CL-015 facade's separate
STATE, brightness and color-temperature variables. No Alexa object was
rewritten.

The complete structural postflight found:

- 18 active v2 wrappers;
- 11 retained legacy wrappers; and
- no unclassified wrapper source.

The member, target and external event IDs remained stable across the explicit
idempotency run.

## Remaining gate

Activation and structural regression are complete. Presence-bound functional
testing remains separate and must cover:

1. group STATE on/off with all three members confirmed;
2. bounded brightness changes with all three members confirmed;
3. color-temperature changes with all three members confirmed;
4. both physical external trigger directions;
5. Alexa STATE, brightness and color-temperature dispatch; and
6. exact restoration of the initial state.

The hard-outage scenario is not required because the three group members have
permanent power.
