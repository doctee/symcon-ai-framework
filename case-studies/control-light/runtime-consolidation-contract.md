# ControlLight runtime consolidation contract

Status: local candidate; no production promotion implied.

## Preserved opt-in contracts

The combined source retains the published CWWW reconnect-readiness correction
and combines two independently qualified extensions:

- normalized float LEVEL for shared power/brightness, with an explicit sibling
  availability source (see `level-target-contract.md`);
- `colorFeedbackQuantization: z2m-v6-rgb`, accepting exact provider XY/HS decoded
  RGB representations while preserving the original color command. This mode
  requires the Z2M RGB target and separate brightness. Black is rejected as a
  color command; power-off remains an explicit state action.

Neither extension changes configuration defaults. Existing configuration hashes,
alarm polarity, direct-dim behavior, groups, off-only restrictions and Kelvin
profiles must remain unchanged. Late CWWW online notification must not clear
committed raw feedback; offline or invalid raw feedback must invalidate readiness.

## Verification and promotion boundary

Public tests cover LEVEL topology, feedback, timeout, lock and invalid-data
behavior, exact Z2M color representations, default RGB compatibility and the
existing CWWW tests. The Z2M matcher suite is part of the Composer color-analysis
test entrypoint. All fixtures are synthetic and contain no installation IDs.

PHP 8.2 remains the minimum version. The opt-in Z2M matcher quantizes decoded
RGB bytes by comparing the actual fractional part with 0.5 (ties upward), not
with PHP's version-dependent `round()` pre-rounding. For example, the HS report
for `5D96C8` is exactly `76BEFE`, not adjacent `76BFFE`. The original golden
vectors and adjacent-byte rejection remain unchanged. Boundary tests and a
dedicated PHP 8.2/8.3/8.4/8.5 CI matrix pin this behavior. Other color conversions
and the existing four-decimal XY encoding are unchanged. See the official
[PHP 8.4 rounding changes](https://www.php.net/manual/en/migration84.other-changes.php).

Private adoption compares each actual configuration against its prior pinned
Core, including normalized shape, commands, feedback conversion, matching and
error behavior. This does not replace runtime integration or physical acceptance.
Keep wrappers and adapters that load the same PHP classes on compatible source
versions within an execution. Changing only one entrypoint of a coupled adapter
pair is not a complete promotion.

The generic fileset includes other adapters. Its presence does not authorize
upgrading every adapter consumer. Manual-on/pulse-off adapters retain their
existing package bindings until their own semantic and recovery review. A
diagnostic mirror is not evidence that all executing packages are identical.

Source merge, immutable package preparation, staging, activation, physical tests,
publication and retention remain separate gates. No global bootstrap update or
service restart is inherently required for this consolidation. Existing runtime
packages remain protected until separately authorized retention review.
