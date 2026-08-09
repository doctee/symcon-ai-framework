# 291 Combined MQTT Position Disabled Symcon Rollout

**Case study:** Navimow native IP-Symcon module

**Status:** Exact published position-diagnostics commit installed through one
supported update and verified disabled, credential-free and REST-operational;
MQTT activation remains closed

**Date:** 2026-08-05

**Scope:** Execute Gate L1 from step 288 for standalone commit
`4b4b4d7b577df2639ed4a82049aa127c56bdc989` without activating MQTT,
requesting credentials, restarting Symcon or sending a mower command

## 1. Result

Two equal read-only preflights proved the previous installation healthy and
eligible. Exactly one supported `MC_UpdateModule()` call then updated the
module from `eda494513826fa43ccc1b28634b06354356f49a4` to
`4b4b4d7b577df2639ed4a82049aa127c56bdc989`.

Immediate operational evidence and three corrected delayed probes proved:

```text
Account status:                 102
Configurator status:            102
Device status:                  102
Receiver status:                102
MQTT Core status:               104
WebSocket Core status:          104
REST:                           operational
MQTT transport:                 disabled
Authorization header:           absent
MQTT username/password:         absent
position property:              present and false
position API:                   available
position status:                disabled
position observation:           null
public variables:               14, unchanged
Archive Control logging:        5, unchanged and queryable
```

No live defect was observed.

## 2. Authorization Boundary

The user explicitly authorized:

```text
Weiter mit Gate L1
```

The authorization covered the disabled supported module update and its bounded
read-only verification. It did not authorize MQTT staging or activation,
credential retrieval, OAuth actions, Symcon restart, `MC_ReloadModule()`, an
explicit `IPS_ApplyChanges()` call or a mower command.

## 3. MCP Channel

The mandatory Symcon MCP binding was available before live access. Every probe
used bounded `symcon_run_script_text_ex` output and independently checked:

```text
transportError: null
executionError: null
truncated:      false
```

No browser, SSH, PowerShell, Computer Use or temporary Symcon object was used
as a fallback.

## 4. Preflight

Two persisted preflights were byte-identical and passed:

```text
installed commit:       eda49451
repository branch:      main
repository clean/valid: true / true
kernel runlevel:        10103
all module statuses:    102
MQTT/WebSocket status:  104 / 104
REST operational:       true
authentication:         connected, no reauth required
MQTT feature:           disabled
credentials:            absent
position property/API:  absent / absent as expected before update
```

Instance, configuration, identity, archive, command-evidence and subscription
hashes were equal across both observations.

## 5. Single Mutation

The mutation-time probe recomputed all safety conditions and bound the old and
new commits before calling:

```text
MC_UpdateModule():       1
MC_ReloadModule():       0
IPS_ApplyChanges():      0 explicit calls
retry count:             0
```

Result:

```text
returned:                true
target observed:         true
installed commit:        4b4b4d7b
repository clean/valid:  true / true
```

## 6. Post-Update Evidence

Observations were retained at approximately:

```text
+17 s:  operational module evidence; private probe contract too narrow
+72 s:  corrected probe PASS
+111 s: corrected probe PASS
+183 s: final normalized-hash probe PASS
```

The `+17 s` probe already proved the repository, statuses, REST, credentials,
variables and Archive contracts healthy. Its overall result was false only
because the private probe expected the wrong disabled-position representation.

## 7. Probe Correction

The published API and its offline test define the disabled state as:

```json
{
  "featureEnabled": false,
  "transportEnabled": false,
  "status": "disabled",
  "observation": null
}
```

The initial private L1 probe incorrectly expected `inactive` and used the null
coalescing operator to test an explicitly present `null`. Because `??` also
selects its fallback for `null`, it misclassified the valid response.

One targeted read-only API call confirmed the exact published contract. The
probe was corrected to use `array_key_exists()` plus strict null comparison.
The original failed evidence was retained and no module correction or second
update was performed.

## 8. Contract Preservation

Pre-update and final post-update hashes prove:

| Contract | Result |
|---|---|
| instance topology and statuses | unchanged |
| 14 variable identities | unchanged |
| five Archive Control contracts | unchanged |
| command evidence | unchanged |
| exact MQTT subscriptions | unchanged |
| configuration excluding new property | unchanged |

The complete configuration hash changed only because
`EnableMqttPositionDiagnostics=false` was registered. Removing that one key
from the post-update projection reproduces the exact pre-update configuration
hash.

## 9. Preserved Architecture

```text
public state authority:          REST
MQTT direction:                  receive-only
MQTT publish path:               absent
MQTT mower-command path:         absent
position diagnostics default:    disabled
position detail retained live:   none
public-variable additions:       none
Archive Control changes:         none
```

## 10. Mutation Counts

```text
persisted preflights:       2
module updates:             1
module reloads:             0
explicit ApplyChanges:      0
service restarts:           0
MQTT activations:           0
credential requests:        0
OAuth actions:              0
mower commands:             0
```

## 11. Architecture Decisions

### AD-NAV-1225: Use a version-aware disabled probe

The old installation must pass without the new Property or wrapper, while the
new installation must expose them in their disabled empty state.

### AD-NAV-1226: Require two equal mutation preflights

Live update eligibility is established at the mutation boundary, not inferred
from an earlier publication or metadata check.

### AD-NAV-1227: Update once through Module Control

No reload, explicit ApplyChanges or retry is permitted around the supported
module update.

### AD-NAV-1228: Retain a failed probe classification

Probe defects are evidence and are corrected transparently. They do not justify
a second module mutation.

### AD-NAV-1229: Normalize only the newly registered property

Removing exactly `EnableMqttPositionDiagnostics` from the post-update Account
configuration must reproduce the full pre-update configuration hash.

### AD-NAV-1230: Keep activation separately gated

A healthy disabled installation does not authorize credentials or MQTT
transport activation.

## 12. Gate Status

| Gate | Status |
|---|---|
| Gate P2 SAEF merge | PASS IN STEP 289 |
| Gate S1 standalone publication | PASS IN STEP 289 |
| metadata conformance | PASS IN STEP 290 |
| Gate L1 disabled Symcon rollout | PASS |
| Gate L2 combined pilot activation | CLOSED |

## 13. Next Step

Prepare Gate L2 readiness for the combined receive-only transport and position
pilot. Before activation it must bind the installed commit, renew explicit
persistence acceptance, prove passive token readiness and initialize the
private format-3 harness. No activation belongs to that readiness step.
