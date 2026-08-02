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
4. one inactive Weather instance was linked to its matching shared location
   with automatic updates disabled; reference, active status, empty cache and
   request structure were verified without executing the request;
5. exactly one bounded manual Weather update was executed successfully while
   automatic updates remained disabled;
6. a separate Weather shadow instance was created for the second location with
   automatic updates disabled and its request structure was verified without
   executing the request;
7. the next gate may execute exactly one bounded manual update for that second
   shadow or extend the first controlled request profile; and
8. migrate consumers only after the corresponding runtime proofs, while
   retaining direct fields until rollback checks are complete.

The SharedLocation-creation postflight verified exactly two active location
instances, stable source configuration, empty child/timer/reference sets and
unchanged root, parent, Weather and Solar controls at that gate. Later Weather
gates retained the same root, parent, Solar and source baselines. Every further
configuration change still requires a fresh live preflight and explicit
authorization. Parent ObjectID `0` is never treated as an editable category;
object creation must use a freshly resolved, validated parent ID.

The Weather module therefore exposes an explicit automatic-update switch.
Disabling it keeps a valid configured instance active while its normal polling
timer and all transport-error retry timers remain at zero. Manual updates stay
available as a separately controlled action. The switch defaults to enabled so
existing configured installations preserve their scheduling behavior after an
update.

The first controlled shadow-link attempt was rejected by an over-strict
operational-metadata assertion and restored the safe inactive configuration.
Changing the location intentionally changes the forecast configuration hash,
which resets the runtime state and its previous attempt timestamp without
issuing a request. A fresh, explicitly authorized attempt accepted that reset
and independently proved an active reference with interval, next run and last
run all at zero. Legacy providers and the second shared location remained
unchanged.

The first authorized provider request returned a complete valid current,
hourly and seven-day daily forecast. Independent cache reads proved curated
current fields and bounded forecast slices without another request. Different
hourly fields intentionally retain their source interval semantics:
instantaneous observations may have zero-width validity points, while
accumulated precipitation uses intervals and may overlap one additional query
boundary. Automatic interval, next run and last run all remained zero after the
successful manual update.

The second Weather shadow was created below the same freshly verified,
non-root system category through the existing SAEF instance helper. Its stable
Ident, exact module type, shared-location reference, active status, empty cache
and zero timer state were independently read back. The first Weather forecast
and both location definitions remained unchanged, and no provider request was
issued for the second shadow.

## Offline Proof

The scaffold test verifies that shared locations are idempotent and have no
variables, timers or transport surface; valid and invalid configurations get
the expected status; Weather resolves a valid descriptor, registers the
reference, preserves the last valid reference after an invalid replacement and
can return to the legacy direct-coordinate fallback.
