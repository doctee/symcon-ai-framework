# Gate 111 - Bounded startup failure diagnostics

**Status:** Repository-only implementation and offline regression complete;
package, Windows and live gates remain closed, 2026-09-11.

## Observed boundary

A read-only health check found one OwnTracks Position Map instance with
unchanged configuration, no pending changes and all externally verifiable
source, location and anchor dependencies available. The instance nevertheless
remained in invalid-configuration status after one separately authorized
`IPS_ApplyChanges()` call.

The module caught the underlying startup exception, but exposed its message
only through `SendDebug()`. The generic visualization error and instance status
therefore proved that startup failed without identifying the responsible
initialization step. Repeating `ApplyChanges()` would add risk without adding
evidence.

## Reuse assessment

The SAEF Diagnostics helpers were reviewed before changing the module. Registry,
Statistics and ErrorRingBuffer all require owned Symcon variables created
through the helper-first Ensure boundary. ConfigurationHash identifies a
configuration but does not classify an initialization failure.

Those helpers are intentionally not used here. The failure occurs before the
module reaches a healthy runtime diagnostics structure, and creating new
variables merely to diagnose module startup would conflict with the
initialization boundary in RS-001 and EK-006. Extending a helper or adding a
public module method would introduce a broader contract without demonstrated
reuse.

## Diagnostic contract

`ApplyChanges()` now tracks only the fixed phase currently being evaluated. If
a throwable reaches the existing catch boundary, the module emits one bounded
operational log entry and the same Instanz-Debug value:

```text
ApplyChanges failed (phase=<fixed-phase>, class=<fixed-class>).
```

The phase identifies one of the existing startup responsibilities, including
reference cleanup, source configuration, provider and tile configuration,
target locations, runtime bounds, external-anchor validation, reference
registration, status activation and visualization bootstrap.

The failure class is reduced to one of four fixed values:

- `invalid_json`
- `invalid_configuration`
- `runtime_state`
- `unexpected`

The exception message is no longer copied into startup debug output. The
diagnostic contains no configuration values, ObjectIDs, paths, provider
responses, credentials or private installation data. Successful startup emits
no operational error log.

## Responsibility boundary

This is a case-study-local observability correction. It does not add a helper,
attribute, variable, public module method or SAEF API. It does not change
configuration validation, reference ownership, provider authority, network
behavior, archive reads, visualization payloads or instance status semantics.

The Symcon log is used because RS-001 explicitly assigns failures before
runtime-diagnostics initialization to logs or exceptions. Once startup
succeeds, normal bounded runtime diagnostics remain responsible for subsequent
operational failures.

## Verification

The offline module harness verifies that:

- valid startup remains active and produces no error log;
- invalid persisted reference state fails closed as `reference_cleanup` and
  `runtime_state`;
- inconsistent tile configuration fails closed as
  `tile_boundary_validation` and `invalid_configuration`;
- Instanz-Debug and the operational log expose the same bounded value; and
- the generic visualization error remains unchanged.

The deterministic OwnTracks module distribution must be regenerated and the
complete repository check must pass before repository integration.

## Remaining gates

The following remain separately authorized:

1. commit, push, pull request and merge;
2. deterministic deployment-package preparation;
3. Windows or channel qualification;
4. inactive staging and target-bound preflight;
5. any module activation or reload;
6. read-only inspection of the resulting bounded log entry;
7. a corrective implementation based on that evidence; and
8. retention cleanup.

No live retry, provider contact, service restart or package activation is part
of this repository-only workstream.
