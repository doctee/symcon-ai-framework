# 316 Automatic Pilot Closure L2 Corrected Activation

**Case study:** Navimow native IP-Symcon module

**Status:** Corrected one-attempt receive-only pilot activation passed through
two-phase read-only verification; automatic closure is armed

**Date:** 2026-08-14

## 1. Result

After renewed persistence acceptance and confirmation that no manual OAuth,
login or token action had occurred since cleanup, a fresh disabled preflight
passed. Exactly one activation attempt enabled MQTT and position diagnostics.

The direct activation response exceeded the outer orchestration context and
was therefore not interpreted as evidence. No retry followed. Three bounded
read-only observations independently resolved the live state:

```text
installed commit:             888325d8649160c5bae473f4f8a052cf86e703b6
preflight token horizon:      2711 seconds
activation attempts:          1
second activation attempt:    0
phase 1 lifecycle:            Connecting
phase 2 lifecycle:            ShadowActive
session sequence:             5
session started:              2026-08-14 04:39:06 UTC
automatic hard stop:          2026-08-17 04:39:06 UTC
hard-stop interval:           259200 seconds
```

Every accepted read-only MCP observation separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Fresh Preflight

The preflight at `2026-08-14 04:38:53 UTC` proved:

- exact clean and valid commit on `main`;
- healthy Account, Configurator, Device and Receiver instances;
- MQTT and WebSocket inactive and credential-free;
- both MQTT and position properties disabled;
- REST operational and authentication connected;
- 2711 seconds of passive token horizon, above the accepted restart-free
  1200-second threshold;
- exact four-topic allowlist without wildcards; and
- inactive automatic closure with unchanged public, Archive and command
  contracts.

## 3. Two-Phase Evidence

The first read-only observation at `04:40:01 UTC` found the expected transient
state `Connecting`, both features enabled, credentials present in their owned
Core instances, REST operational and closure state `Active`.

The second observation at `04:41:31 UTC` proved `ShadowActive`. Connection
successes increased from 300 to 301 while connection failures remained zero.
The final focused projection at `04:42:13 UTC` proved:

- pilot session sequence 5;
- exact 72-hour absolute deadline;
- closure state `Active` without a pending cleanup phase;
- position diagnostics available with zero new samples at this early baseline;
- 56053 received and 56050 accepted MQTT messages retained in diagnostics;
- zero reconnect attempt and zero connection failures; and
- unchanged REST authority.

## 4. Architecture Decisions

### AD-NAV-1302: Resolve ambiguous mutation output by read-back

An unreadable outer orchestration result does not authorize a retry and does
not prove PHP success. The live state is resolved through bounded read-only
projections with independently checked transport, execution and truncation
channels.

### AD-NAV-1303: Accept transient Connecting only as phase one

`Connecting` is accepted only with enabled receive-only properties, ready
configuration, active closure and healthy REST. Establishing the pilot still
requires a later `ShadowActive` observation without another mutation.

### AD-NAV-1304: Keep automatic closure as the active safety owner

The exact hard stop is anchored to the actual session start and is 259200
seconds later. Manual cleanup is not performed while this accepted pilot is
running; module-owned closure and its mandatory credential-first cleanup remain
the safety boundary.

## 5. Gate State

| Gate | Status |
|---|---|
| renewed persistence acceptance | PASS |
| no manual authentication action since cleanup | CONFIRMED |
| fresh 1200-second token gate | PASS, 2711 seconds |
| exactly one activation attempt | CONSUMED |
| phase-one read-only verification | PASS, Connecting |
| phase-two read-only verification | PASS, ShadowActive |
| automatic 72-hour closure | ARMED |
| REST authority | PRESERVED |
| restart or mower command | NOT PERFORMED |

## 6. Next Step

Let the bounded receive-only pilot run under its module-owned checkpoints and
automatic closure. The next work item is a read-only checkpoint review after
natural MQTT and position activity. Final closure may be claimed only after
the hard stop or another closure reason has disabled both properties, removed
all Core credentials and passed immediate plus delayed read-only cleanup
verification.
