# SAEF Step 386: Local Map Visual Refinement Publication and Disabled Rollout

## Status

Complete for publication, disabled module rollout and docked-state map
verification. Directional mower rendering during natural mowing remains a
separate receive-only evidence gate because native MQTT stayed disabled.

## Published Revisions

| Artifact | Revision |
|---|---|
| SAEF merge | `5b9732d61ac85593e0fd8e9530e41d1418dda3b4` |
| Standalone module merge | `af89eeb3b7360c7c8b3cf81db4b2f07bfc9370cb` |
| Module fileset | `84290154e0600439e52e04c21809f00428d0e242bbd72c731a0d5d6bfddb48f8` |
| Publication hash | `8170346f84a278bd9473634111d7aaaffb15698559e8dbe33375e147dd0fb06e` |

SAEF pull request 94 passed both repository checks and was merged through the
reviewed path. The generic manifest publisher created standalone pull request
8 from the exact 42-file inventory and independently verified the integrated
standalone tree. The standalone repository reported no configured checks;
the publisher recorded `checkCount: 0` rather than treating missing checks as
an implicit success signal.

## Disabled Live Preflight

The bounded structured Symcon MCP preflight verified before mutation:

- installed standalone commit `81c9ca07` on clean and valid `main`;
- Account, Configurator, Device and Receiver status `102`;
- MQTT Client and WebSocket Client status `104`;
- MQTT and position diagnostics disabled;
- empty MQTT username, password and WebSocket headers;
- Local Map and zone statistics enabled;
- 29 variable contracts and all Archive settings fingerprinted.

No ObjectIDs, values, coordinates, topics or credentials were retained in the
public evidence.

## Controlled Update

Exactly one supported `MC_UpdateModule()` call updated the standalone module
to `af89eeb3`. The structured result separately confirmed:

- transport error: none;
- PHP execution error: none;
- output truncation: false;
- update result: true;
- repository branch: `main`;
- repository clean and valid: true.

No `MC_ReloadModule()`, instance `ApplyChanges()`, restart, OAuth action, MQTT
credential request, MQTT activation or mower command was executed.

## Stability Verification

Immediate and delayed read-only postflights both passed. The four reusable
instance configuration hashes, the 29-variable contract hash and the Archive
logging and aggregation hash remained identical to the preflight. All module
instances remained status `102`; MQTT and WebSocket remained status `104` and
credential-free.

This preserves user-enabled logging, including the existing battery and state
histories.

## Live Map Verification

One explicit `RefreshLocalMap()` call refreshed only the script-owned HTMLBox
content. The current REST-authoritative vehicle state was Docked. The resulting
SVG proved the deployed visual contract:

- zero HTML body padding and a full-size SVG root;
- reduced geometry viewport padding;
- separate station base, guide and occupancy shapes;
- Docked station state with the occupancy contract present;
- directional mower body and inner-arrow definitions;
- stronger attention and unknown colors;
- reduced obstacle fill opacity.

The refresh reported that the map was rendered without fresh MQTT evidence.
Consequently no separate mower marker or heading was rendered while the mower
was docked. This is intentional: the station occupancy shape represents the
docked mower, and the Device hides an external mower marker for Docked or
stored-only evidence.

## Architecture Decisions

### AD-NAV-386-01: Keep disabled rollout evidence distinct from active motion

Installation and docked presentation are proven without opening a cloud
transport. A directional mower marker requires fresh position evidence and
must not be inferred from dormant renderer definitions or retained path data.

### AD-NAV-386-02: Preserve redundant state encoding

Station occupancy is encoded by both color and geometry. Mower state is
encoded by color while direction is encoded by marker orientation and its
inner arrow. This avoids relying on color alone.

### AD-NAV-386-03: Refresh only owned presentation state

The live visual check invokes the module's public Local Map refresh once. It
does not change configuration, transport state, device state or Archive
settings.

## Next Gate

Run one bounded receive-only MQTT and position observation during a supervised
natural mowing interval. It must retain the exact published module commit,
render a fresh directional mower marker, preserve REST as state authority and
perform mandatory credential cleanup with immediate and delayed closure
verification. No MQTT device command is permitted.
