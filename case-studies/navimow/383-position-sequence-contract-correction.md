# SAEF Step 383: Position Sequence Contract Correction

## Status

Implementation and offline verification complete. Publication and live rollout
remain separate, explicitly authorized gates.

## Context

The bounded mowing-recency pilot received valid MQTT position and task data,
but every Local Map refresh with fresh position evidence set
`StatisticsState` to `Invalid`. A read-only live investigation excluded the
accepted map package, geometry revision, configured zone bindings and task
pass attribution as causes.

The investigation found an integration-contract defect:

- `MqttPositionDiagnostic` retained `sampleSequence`, which counts individual
  position samples;
- `MqttPathSegmenter` required `sessionSequence`, which identifies the native
  MQTT transport session;
- the Account did not pass its existing bounded pilot session sequence into
  the position diagnostic.

Consequently, every retained position point failed the path input contract
before zone statistics could be projected.

## Correction

The correction keeps the two sequence semantics separate:

1. `MqttPositionDiagnostic::reduce()` accepts the current transport-session
   sequence as an optional fourth argument and persists it with new samples.
2. `NavimowAccount` supplies the bounded session sequence from the existing
   pilot observation registry.
3. `MqttPathSegmenter` maps older retained samples without the additive field
   to legacy session `0`.
4. Negative or malformed session values fail closed.

No public variable, Ident, action, profile, configuration property, REST
contract or MQTT command behavior changes. REST remains authoritative and MQTT
remains receive-only.

## Compatibility

The persisted position state keeps format version `1`. Existing samples have
seven fields; corrected samples have the additive eighth
`sessionSequence` field. Validation accepts both bounded forms. Legacy points
are not rewritten and naturally leave the 512-sample retention window.

Using `sampleSequence` as a substitute was rejected because it changes on
every sample and would incorrectly split every retained point into a separate
transport session.

## Verification

The new producer-to-consumer integration test feeds the real projected output
of `MqttPositionDiagnostic` directly into `MqttPathSegmenter`. It proves:

- same-session samples remain in one path segment;
- a real session change creates exactly one transport-session boundary;
- legacy samples without a session field remain readable as session `0`;
- malformed negative session values are rejected.

Account ingestion additionally proves that the live pilot session is passed
to new position samples. Existing position, Local Map evidence and runtime
reducer tests remain green.

The complete Navimow offline check passes, including PHP_CodeSniffer, PHPStan,
distribution validation and the deterministic module-fileset check. The
generic manifest-driven publisher validates a 42-file standalone publication
with fileset SHA-256
`4f73a968a261f38cfb53fb885771a7ec8c6c94adcab6603dd7c177d950cbc8ad`.

## Architecture Decision

### AD-NAV-383-01: Preserve sample and transport-session identity separately

**Decision:** Keep `sampleSequence` as the monotonic diagnostic sample counter
and add the actual bounded transport `sessionSequence` to retained samples.

**Reason:** Sample order and transport-session identity have different
semantics. Conflating them would make path segmentation deterministic but
incorrect.

### AD-NAV-383-02: Migrate retained samples additively

**Decision:** Accept missing session identity only for legacy retained samples
and map it to session `0` at the path boundary.

**Reason:** Existing private position history remains usable after a module
update without an eager attribute rewrite or loss of retained paths. New
samples carry the precise session identity.

## Live Gate

Before the module update, any active receive-only pilot must be closed and the
native MQTT/WebSocket cores must be inactive and credential-free. The update
must then be followed by immediate and delayed read-only verification with
MQTT still disabled. A later receive-only activation is a separate operational
test and is not implied by this correction.
