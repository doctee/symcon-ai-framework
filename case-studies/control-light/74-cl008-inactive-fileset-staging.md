# CL-008 inactive fileset staging

## Outcome

The exact CL-008-capable ControlLight fileset was transferred through the
restricted deployment channel and staged successfully under:

`saef-clhw-84827ffca42391f`

The channel probe, bounded 46-chunk upload, server-side commit and
non-activating preflight all passed. The live directory contains exactly the
expected 19 files.

## Independent verification

Read-only live inspection verified byte-exact hashes for:

- the fileset identity marker and ordered source manifest;
- the bootstrap;
- `ControlLightRuntime.php`;
- `ControlLightCore.php`; and
- `ControlLightCommandException.php`.

The staged runtime, core and exception hashes match the private CL-008 package
manifest.

## Inactive selection proof

`System.Locals` remains byte-identical to the pre-staging baseline. It contains
one reference to the active MQTT fileset and no reference to the newly staged
ControlLight fileset. The activation command was not invoked and the Symcon
service was not restarted.

Post-staging readback also confirmed:

- unchanged CL-008 and Auto-Off source hashes;
- unchanged CL-008 aggregate, Auto-Off member and foreign warning events;
- unchanged group, facade and both member STATE/brightness values; and
- no script execution, object mutation, variable write or device action.

## Remaining gates

The next step is one transaction-bound Zigbee2MQTT group-list metadata query to
reconfirm that group ID 1 still contains the same two IEEE members after
staging. It is a broker metadata publication, not a device/group command.

Atomic CL-008/Auto-Off source activation, reconciliation and all real-device
tests remain later, separately approved gates.
