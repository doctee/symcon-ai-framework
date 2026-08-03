# CL-030 Legacy Home Assistant Exporter Retirement

## Outcome

The CL-030 consumer handoff is complete. The former Home Assistant exporter
owner is now a fail-closed rollback placeholder, while its byte-exact source
and configuration remain available in the private rollback evidence. All
legacy objects were deliberately retained and both legacy command and state
events remain inactive.

The replacement SAEF MQTT Discovery exporter stayed active and preserved the
existing Home Assistant entity identity, authoritative state topic, command
topic and non-optimistic behavior.

## Retirement Procedure

The bounded preflight proved:

- the exact legacy owner source already had a matching private backup;
- the current legacy configuration was additionally backed up byte-exactly;
- no active script referenced the legacy owner;
- the only historical event-action reference was inactive;
- both legacy entity events were inactive; and
- the replacement owner and both of its entity events were active.

Only the legacy owner source was changed. Its replacement is a minimal
fail-closed placeholder that logs accidental invocation and returns. It cannot
reconcile objects, publish MQTT discovery, create events or dispatch a device
command. The placeholder was not executed during activation.

## Identity and Runtime Postflight

The replacement exporter Registry and retained discovery payload independently
confirmed the unchanged entity identity and topic pair. The discovery contract
continues to use explicit on/off payloads and authoritative feedback with
optimistic mode disabled.

Replacement diagnostics remained at zero failures and zero commands during
the retirement. CL-030 state, restored supply, pulse count, ControlLight
command count, errors and confirmation timeouts were unchanged. No MQTT or
physical-device action was attempted.

## Deferred Deletion Gate

No legacy object was deleted. Physical deletion remains a separate,
presence-independent maintenance gate after an observation period. Before
that gate opens, a fresh reference audit must again prove that:

1. the replacement exporter is healthy;
2. the preserved entity identity still resolves correctly;
3. both legacy events are inactive;
4. no active consumer references the legacy owner or its children; and
5. the exact rollback sources are still readable and hash-valid.

Private ObjectIDs, exact topics, source backups, hashes and counter values are
retained only in the local machine-readable evidence.
