# Shared System-Wide Location Instances

## Outcome

The published library contains a provider-neutral `SharedLocation` module. One
instance represents one physical location. The authorized private deployment
created two explicitly named, active instances. Their names, productive
coordinates and ObjectIDs are deliberately absent from the public repository.

The controlled rollout contacted no forecast provider and changed neither the
existing inactive Weather/Solar controls nor the legacy OpenWeather and SolCast
providers. Private machine-readable evidence retains the installation-specific
preflight, rollback proof for an initially rejected assertion and the completed
independent postflight.

## Contract

Each `SharedLocation` instance owns:

- a stable lowercase key (`^[a-z][a-z0-9_]{0,63}$`);
- WGS84 latitude and longitude;
- an optional explicit elevation; and
- an IANA time zone.

The module creates no variables, timers, categories or links. Its only public
method, `GetDescriptor()`, returns bounded, installation-sensitive JSON
containing either a normalized location or a classified configuration error;
its result must not be logged or published. Invalid configuration fails
closed with module status `200`; incomplete configuration remains inactive at
status `104`.

The API is intentionally provider-neutral even though its first packaging and
consumer live in the Open-Meteo library. If other modules later consume the
contract, moving it into a dedicated library can be evaluated without changing
the descriptor fields.

## Weather Compatibility

`OpenMeteoWeather` adds an optional `LocationInstanceId` property and registers
an IP-Symcon object reference only after all of these checks pass:

1. the target instance exists;
2. its exact module GUID is `SharedLocation`;
3. its JSON result is bounded and structurally valid; and
4. the returned location fields pass local validation again.

A selected shared location takes precedence. The existing
`LocationConfigured`, coordinate, elevation and time-zone properties remain as
a legacy fallback, so an installed weather instance keeps its current behavior
after a library update. Clearing `LocationInstanceId` returns to that fallback.
An invalid replacement retains the last valid registered reference and fails
closed.

The location values are included in the existing weather configuration hash.
Changing the shared location therefore invalidates an old forecast cache before
new values are published.

## Controlled Private Rollout

The private rollout remains split into separately authorized gates:

1. the exact published library revision was installed without configuring
   consumers;
2. two explicitly named `SharedLocation` instances were created under a
   freshly verified, non-zero parent;
3. both locations were configured from unchanged private source instances and
   their normalized descriptors were independently read back;
4. the next gate may point one inactive or shadow weather instance at its
   matching location with automatic updates disabled, then verify reference,
   status and request URL without changing legacy providers;
5. migrate the second weather instance only after the first proof; and
6. retain the direct fields until rollback and consumer checks are complete.

The completed postflight verified exactly two active instances, stable source
configuration, empty child/timer/reference sets and unchanged root, parent,
Weather and Solar controls. Every further configuration change still requires
a fresh live preflight and explicit authorization. Parent ObjectID `0` is never
treated as an editable category; object creation must use a freshly resolved,
validated parent ID.

The Weather module therefore exposes an explicit automatic-update switch.
Disabling it keeps a valid configured instance active while its normal polling
timer and all transport-error retry timers remain at zero. Manual updates stay
available as a separately controlled action. The switch defaults to enabled so
existing configured installations preserve their scheduling behavior after an
update.

## Offline Proof

The scaffold test verifies that shared locations are idempotent and have no
variables, timers or transport surface; valid and invalid configurations get
the expected status; Weather resolves a valid descriptor, registers the
reference, preserves the last valid reference after an invalid replacement and
can return to the legacy direct-coordinate fallback.
