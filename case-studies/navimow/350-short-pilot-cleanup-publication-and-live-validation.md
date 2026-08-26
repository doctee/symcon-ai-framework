# 350 Short Pilot Cleanup Publication And Live Validation

**Case study:** Navimow native IP-Symcon module

**Status:** Passed; transport disabled and credential-free

**Date:** 2026-08-26

## 1. Publication

The manifest-driven generic module publisher prepared, published and
integrated the exact cleanup-hardening candidate into the standalone Navimow
repository.

- previous standalone commit: `865ed9230973aa3a84af4464bae2f3f59de0fab9`;
- publication pull request: `doctee/symcon-navimow#2`;
- integrated standalone commit:
  `790f6106c160130bb1931eb3e45f8c027ea9d772`;
- fileset SHA-256:
  `b07c068dc510857744e536a8eec19b9d767585dcadccccaba606af4f8d20cdbb`;
- publication SHA-256:
  `d2bac5a158e4be83c8d0257d7bf8d667fc0819f13508ddcf39b80af699b5f62f`.

The publisher independently verified all 35 standalone files. The repository
reported no configured checks; the integration result recorded a check count
of zero rather than assuming an unreported pass.

## 2. Disabled Rollout

One supported module update installed the exact standalone commit. The
immediate read-only postflight proved:

- Account, Device and Receiver status 102;
- MQTT and WebSocket status 104;
- MQTT and position diagnostics disabled;
- Authorization header, MQTT user and MQTT password absent;
- REST connected without reauthentication requirement;
- all 14 established variable identities retained;
- default pilot duration 259200 seconds available.

No restart, OAuth action or mower command occurred.

## 3. Bounded Live Validation

After a fresh passing restart-free token preflight, exactly one Account
`ApplyChanges` configured and activated a 300-second receive-only pilot.

- start: `2026-08-26T16:53:14Z`;
- absolute deadline: `2026-08-26T16:58:14Z`;
- lifecycle: `ShadowActive`;
- session duration projection: 300 seconds;
- MQTT messages were received and accepted;
- mower command count: zero.

The module requested closure exactly at the absolute deadline. One second
later it had cleared credentials, disabled MQTT and position diagnostics and
completed the closure with reason `deadline-reached`.

## 4. Immediate And Delayed Postflight

Both read-only postflights proved:

- MQTT and WebSocket status 104;
- WebSocket inactive;
- all transport credentials absent;
- MQTT and position diagnostics disabled;
- transient position observation absent;
- three bounded anonymous task passes retained;
- Account status 102;
- REST connected and no reauthentication required.

Every structured MCP call had `transportError = null`,
`executionError = null` and `truncated = false`.

## 5. Result

The former chat-dependent short-test cleanup risk is closed for the tested
restart-free path. External automation may observe a pilot but is no longer
its cleanup owner.

The configured maximum remains 300 seconds after closure. This is a harmless
disabled setting and provides a fail-safe bound for a separately authorized
future short pilot. Longer operation requires an explicit configuration
decision before activation.

No restart validation of the new short duration and no permanent MQTT
operation are implied by this result.
