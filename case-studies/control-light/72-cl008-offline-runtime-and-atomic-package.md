# CL-008 offline runtime and atomic package

## Outcome

The member-confirmed Zigbee group contract selected for CL-008 now has a
complete offline implementation and executable regression coverage. A private,
hash-locked candidate binds the implementation to the two previously confirmed
group members and couples the wrapper migration with the required Auto-Off
hand-off.

No live script, object, event or variable was changed. No device or group
command was sent.

## Runtime contract

The existing single-target path remains the default. Group behavior is enabled
only by an explicit `member_confirmed` configuration containing two to 32
uniquely keyed members with STATE and brightness feedback.

For CL-008 the runtime:

- sends exactly one action to the Zigbee2MQTT group endpoint;
- confirms both members within one shared deadline rather than multiplying the
  timeout by member count;
- requires fresh evidence even when a member already has the requested value;
- projects passive STATE as any fresh member on;
- does not project all-off while any required member evidence is stale;
- uses reported group-level brightness without calculating a member average;
- accepts only the configured bounded brightness tolerance; and
- never writes optimistic facade state after an unconfirmed command.

Failures distinguish group-endpoint timeout, unavailable member, stale member,
partial member confirmation and aggregate-projection mismatch. Diagnostic
details remain bounded to member keys and counts.

## Ownership and idempotency

Member STATE and brightness updates are observed through deterministic events
owned below the CL-008 wrapper. Reconciliation reuses compatible owned events,
deactivates obsolete owned member events and leaves the existing
device-warning events and availability links unchanged.

The command loop is bounded by one deadline and performs one linear scan of the
configured members per poll. It therefore scales with member count and poll
count, not with a separate timeout per member.

## Atomic Auto-Off hand-off

The private package changes exactly two script sources:

1. CL-008 selects the member-confirmed runtime and the two resolved member
   feedback mappings.
2. Auto-Off replaces the two individual member brightness controls with the
   CL-008 STATE facade as its single shutdown control and observes both facade
   STATE and DIMMER as activity.

This keeps Auto-Off outside member-level command and confirmation logic. The
package contains byte-exact Base64 rollback sources for both scripts and
requires event reconciliation in both activation and rollback directions.

Package verification proves the fileset/source hashes, both candidate and
rollback hashes, the intended Auto-Off-only configuration delta, the absence of
commands in the wrapper source and the command-free activation expectation.

## Regression result

The aggregate repository check passes, including:

- eight group-runtime scenarios;
- configuration rejection and normalization;
- owned/foreign event topology and repeated reconciliation;
- every existing single-device ControlLight runtime scenario;
- the sanitized 29-instance installed-contract inventory;
- deterministic bundle and fileset checks;
- PHPStan and PHP_CodeSniffer.

## Remaining live gates

Before activation:

1. perform a fresh read-only drift preflight for both script hashes, the staged
   fileset identity, group membership, relevant variable actions and Auto-Off
   event topology;
2. record the Zigbee2MQTT group options if available, without weakening
   member-confirmed authority;
3. obtain explicit approval for the atomic two-script activation;
4. activate and reconcile without a device command, then prove two-run
   idempotency and the full 29-wrapper regression; and
5. obtain separate approval for the real group STATE/brightness and Auto-Off
   functional tests with exact initial-state restoration.
