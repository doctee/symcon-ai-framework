# CL-015 shutdown consumer hand-off

## Outcome

The central `Beleuchtung alles-aus` consumer now sends one STATE-off action to
the CL-015 facade instead of three individual Kuschelsofa member actions.

The exact source was secured before mutation. The guarded transaction required
one unique three-line member sequence, changed only that sequence, performed
exact source readback and did not execute the shutdown script. Facade, group
endpoint and all three member values remained unchanged.

The complete ControlLight postflight remains at 18 v2 and 11 legacy wrappers
with no unclassified source. CL-015 diagnostics contain no command, error or
confirmation timeout.

## Additional dependency found

The post-mutation installation-wide reference scan found a second previously
unknown consumer: `Beleuchtung willkommen`.

That script:

- reads all three Kuschelsofa member STATE values;
- writes brightness 50 to all three members independently; and
- writes STATE off to all three members independently.

This is another ownership bypass. It was outside the approved mutation and
therefore remains unchanged.

The correct future hand-off is:

1. derive the sofa-on condition from the CL-015 facade STATE;
2. replace three member brightness actions with one facade DIMMER action;
3. replace three member off actions with one facade STATE action;
4. preserve every unrelated condition and action;
5. do not execute the welcome script during migration; and
6. retain exact rollback plus complete reference and wrapper regression.

This second script requires a separate explicit live-mutation approval.
