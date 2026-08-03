# CL-030 Command-Free Activation and Consumer Handoff

## Outcome

CL-030 is structurally active on the asymmetric manual-on/pulse-off contract.
The activation, adapter reconciliation and complete consumer handoff changed no
device value and issued no device command. The live baseline is now 24 v2
wrappers, five retained legacy wrappers and 29 tracked ControlLight instances.

The real off-pulse sequence remains a separate, explicitly closed test gate.
CL-030 is therefore not yet included in the fully device-tested count.

## Deployment Boundary

The immutable ControlLight fileset was staged and read back byte-exactly. It is
loaded directly by the versioned wrappers. The installation-wide bootstrap was
deliberately not replaced because it currently owns a separate MQTT exporter
fileset and its preservation contract rejected that substitution. Direct
fileset loading keeps both owners independent and requires no service restart.

## Rollback-Safe Activation

The first structural attempt reached an encapsulated ScriptEngine error rather
than the required facade. Its transaction restored the complete original
object and source baseline before any further attempt.

The corrected transaction invoked the bounded reconciliation path directly.
It created the hidden manual-on/pulse-off adapter, retained the original target
state as the adapter's internal state, created the visible ControlLight facade
and completed two equal command-free reconciliations.

A postflight then found two equivalent adapter feedback events. One was
identified as residue from the rolled-back first attempt by exact parent,
trigger and action ownership. Only that duplicate was removed; the pre-existing
event and its user-visible identity were retained and brought under the
deterministic Ident contract.

## Consumer Handoff

The atomic handoff aligned all discovered consumers with the new facade:

- Auto-Off action and feedback observation;
- alarm-warning observation;
- presentation link;
- scene controller;
- Alexa power controller while retaining its row identity and name;
- the SAEF Home Assistant exporter.

The Home Assistant handoff preserved the previous Apple Home/Home Assistant
identity namespace and state/command topic contract. The new entity remains
non-optimistic and publishes authoritative facade feedback. The old exporter
owner and its events are disabled but retained for rollback. The old internal
target remains referenced only by the adapter's required feedback event and
inactive rollback artifacts.

## Postflight

The final read-only postflight proved:

1. measured power, restored supply, internal target state and facade state
   remained at their initial values;
2. adapter and ControlLight command counters remained zero;
3. both runtimes recorded zero errors and zero confirmation timeouts;
4. all active consumers reference the new facade;
5. the retained old exporter cannot accept commands or publish state;
6. 24 wrappers use v2 and five wrappers remain on their explicit legacy
   contracts.

Private ObjectIDs, hashes, rollback sources and exact consumer configurations
are retained in the local machine-readable activation evidence.

## Remaining Functional Gate

The presence-bound test must be separately approved and observed at the lamp:

1. activate the lamp manually;
2. confirm authoritative power feedback and facade on;
3. request off through the intended facade or voice consumer;
4. prove exactly one supply pulse, confirmed lamp-off power and restored relay;
5. repeat the manual-on/immediate-off race if operationally safe;
6. restore the initial off baseline and close diagnostics plus regression
   evidence.

No part of this report authorizes that real device action.
