# 293 Combined MQTT Position Activation Precondition Abort

**Case study:** Navimow native IP-Symcon module

**Status:** Single authorized activation attempt stopped before mutation by the
fresh token-horizon precondition; installation remained disabled,
credential-free and REST-operational

**Date:** 2026-08-05

**Scope:** Execute the separately authorized Gate L2 activation once against
standalone commit `4b4b4d7b577df2639ed4a82049aa127c56bdc989`, without retry

## 1. Result

The mutation-time preflight evaluated every frozen activation condition. All
conditions passed except the minimum token horizon:

```text
token remaining:        1562 seconds
required minimum:       2400 seconds
activation result:      precondition-failed
Account ApplyChanges:   0
cleanup attempted:      false
```

The activation candidate stopped before setting either feature property.
There was no partial state and no cleanup operation was necessary.

## 2. Authorization Boundary

The user authorized one activation of the combined receive-only MQTT and
position pilot on the exact installed commit. The authorization did not permit
automatic retries after a failed mutation-time precondition.

The one script invocation consumed that attempt. A later activation requires a
new explicit gate.

## 3. Private Backup

Immediately before the attempt, one bounded read-only MCP call captured a
private byte-exact configuration backup of Account, Receiver, MQTT Client and
WebSocket Client.

The backup proved the expected commit and inactive Core statuses. It remains
outside public Git and is not reproduced in this report.

## 4. Mutation-Time Preconditions

The following conditions passed:

- repository branch, commit, cleanliness and validity;
- variable, Archive, command, topology and subscription contracts;
- REST operation, connected Account and no reauthentication requirement;
- both feature properties disabled;
- receiver selection and binding;
- disabled transport and position diagnostics;
- inactive MQTT and WebSocket Core instances;
- absent Authorization header and MQTT username/password.

Only `tokenAtLeast2400Seconds` failed.

## 5. Post-Abort Verification

One immediate read-only format-3 projection proved:

```text
projection:               PASS
installed commit:         exact
REST:                     operational and authoritative
MQTT transport:           disabled
MQTT/WebSocket status:    104 / 104
Authorization header:     absent
MQTT username/password:   absent
position status:          disabled
position latest:          null
```

All MCP calls separately reported `transportError=null`,
`executionError=null` and `truncated=false`.

## 6. Side-Effect Accounting

| Operation | Count |
|---|---:|
| private configuration backups | 1 |
| activation script calls | 1 |
| Account property mutations | 0 |
| Account ApplyChanges calls | 0 |
| MQTT activations | 0 |
| credential requests | 0 |
| OAuth or manual token actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

## 7. Architecture Decisions

### AD-NAV-1235: Re-evaluate token readiness at mutation time

A previously passing token horizon is historical evidence, not permission to
activate after the horizon has fallen below the threshold.

### AD-NAV-1236: Do not retry a precondition abort

Exactly-once authorization applies to the script invocation. A safe abort does
not silently extend it into a later mutation.

### AD-NAV-1237: Keep failed preconditions non-mutating

All volatile conditions are evaluated before either feature property is set.

### AD-NAV-1238: Skip cleanup when no mutation occurred

Cleanup is reserved for a failed postcondition after activation began. Running
it after an unchanged precondition abort would add an unnecessary mutation.

## 8. Gate Status

| Gate | Status |
|---|---|
| exact commit and contracts | PASS |
| persistence acceptance | RETAINED |
| mutation-time token readiness | FAIL CLOSED |
| authorized activation attempt | CONSUMED WITHOUT MUTATION |
| MQTT activation | NOT PERFORMED |
| passive token-refresh observation | REQUIRED |
| new activation authorization | CLOSED |

## 9. Next Step

Wait for the normal productive token-refresh window and perform a bounded
read-only observation. Require a new horizon of at least 2400 seconds and user
confirmation that no manual authentication action occurred during that new
window. Then request a new explicit activation gate. Do not retry from this
step.
