# SAEF Step 387: Active Directional Map Marker Short Pilot

## Status

Complete. One supervised 300-second receive-only pilot proved the active mower
symbol and path-derived heading on the installed Local Map renderer. The
automatic hard stop closed the transport and removed all owned Core
credentials; immediate and delayed closure checks passed.

## Scope and Authorization

The operator confirmed that the mower was visibly mowing and authorized one
bounded receive-only position pilot with temporary Authorization and MQTT
credential storage. The authorization permitted no restart, OAuth action,
module update, MQTT publish or mower command. Cleanup was mandatory regardless
of the result.

## Preflight

The bounded structured Symcon MCP preflight passed at the start of the mowing
interval:

- installed standalone commit `af89eeb3` on clean and valid `main`;
- mower REST state Running, online and three seconds old;
- Account, Configurator, Device and Receiver status `102`;
- MQTT Client and WebSocket Client status `104`;
- MQTT and position diagnostics disabled and credential-free;
- authentication ready with 1560 seconds remaining token horizon;
- previous pilot closed;
- Local Map, zone statistics and 72-hour track retention ready.

## Single Activation

Exactly one Account `ApplyChanges()` enabled the receive-only transport and
position diagnostics with the module's minimum supported pilot duration of 300
seconds. The first projection reported `ReconnectScheduled`; no activation
retry was issued. Forty-one seconds after the activation request, the same
attempt had converged to `ShadowActive` with an Active pilot, WebSocket active,
position diagnostics available and the hard stop armed.

The active transport session was sequence 18. Public evidence retains the
sequence only to correlate lifecycle and cleanup; no topic, credential,
coordinate, device identity or ObjectID is included.

## Position Evidence

The final active checkpoint before the hard stop reported:

| Metric | Result |
|---|---:|
| received position samples | 118 |
| retained position samples | 40 |
| coordinate changes | 117 |
| out-of-order timestamps | 0 |
| invalid MQTT envelopes | 0 |
| receiver handoff failures | 0 |
| invalid Account results | 0 |

The earlier path projection contained 11 valid points in one unchanged
transport session, no source-time or receive-order regression and no invalid
point. This already exceeded the minimum two-point evidence contract.

## Live Map Evidence

One explicit Local Map refresh wrote the current script-owned HTMLBox value.
The following read-only semantic readback passed:

- REST vehicle state remained Running;
- station rendered as Undocked;
- mower rendered as Active;
- directional mower body and inner arrow were present;
- a finite path-derived heading of `-117.334607` degrees was rendered;
- the station occupancy contract remained present;
- the full-size, zero-body-padding HTMLBox contract remained present.

The first assertion compared the successful refresh message with an incorrect
wording. The map had already rendered correctly, so the operation was not
repeated; a read-only SVG projection established the evidence instead.

## Automatic Closure

The immutable hard stop closed the pilot after 300 seconds. A session-bound
fallback cleanup had been prepared but was not executed because automatic
closure had already succeeded.

Immediate and delayed read-only checks established:

- pilot closure state Closed;
- MQTT and position diagnostics disabled;
- MQTT Client and WebSocket Client status `104`;
- empty MQTT username, password and WebSocket headers;
- all four Navimow module instances status `102`;
- installed repository still clean and valid at `af89eeb3`;
- Local Map and zone statistics still available.

The delayed token horizon fell below the 1200-second activation threshold. It
is deliberately excluded from closure success because it governs only a new
activation. No OAuth or token action was executed.

## Architecture Decisions

### AD-NAV-387-01: Five minutes is sufficient for marker verification

The minimum supported pilot duration produced substantially more than two
fresh same-session positions and a live directional marker. Multi-hour pilots
are unnecessary when the sole objective is renderer and heading verification.

### AD-NAV-387-02: Never repeat a successful presentation mutation for a probe typo

A mismatch in expected human-readable return text does not justify a second
map refresh. Verify the already-written owned value read-only and document the
probe defect separately.

### AD-NAV-387-03: Automatic closure takes precedence over fallback cleanup

The session-bound cleanup exists only as a fallback. Once the module has
closed the exact session and removed credentials, a second cleanup mutation is
neither necessary nor permitted.

### AD-NAV-387-04: Test success does not imply continuous position operation

After cleanup, MQTT position diagnostics stop exposing the active track and
the map no longer receives fresh positions. A continuously moving mower symbol
therefore requires a separately designed and authorized receive-only operating
mode; this short pilot proves capability, not that operating policy.

## Next Gate

Decide whether the private installation should use bounded scheduled position
windows or a continuously monitored receive-only transport. That decision must
define credential lifetime, reconnect policy, health monitoring, automatic
cleanup, map freshness and the behavior when REST and MQTT evidence diverge.
REST remains authoritative and MQTT remains command-free.
