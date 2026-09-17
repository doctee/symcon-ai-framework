# Open-Meteo Local Horizon Model

## Outcome

The Solar runtime has an optional, deterministic local-horizon layer. It uses
an installation-specific list of equally spaced horizon elevations beginning
at north and proceeding clockwise. The profile remains disabled by default and
belongs in private module configuration.

The model is separate from equipment derating, calibration and storage
curtailment. Enabling it changes the configuration hash and invalidates an
incompatible last-good cache before the next complete forecast is accepted.

## Radiation Boundary

Open-Meteo supplies hourly global tilted irradiance and direct normal
irradiance. For each tilted-irradiance interval, the model samples the solar
position at five-minute midpoints. The horizon elevation is linearly
interpolated at the solar azimuth. Solar position uses the configured site
coordinate rather than the provider's potentially shifted model-grid point.

When the sun is below the local horizon and in front of the PV plane, the
estimated blocked direct plane-of-array component is subtracted from global
tilted irradiance. The result is bounded to `0..GTI`. Diffuse sky and
ground-reflected radiation are deliberately retained; the profile is not a
flat loss factor.

This is an hourly approximation. It does not claim to reconstruct sub-hourly
cloud movement or the exact optical transmission of nearby objects.

## Configuration Contract

- `EnableShadingProfile=false` preserves the previous request and calculation
  path and ignores the stored profile.
- `EnableShadingProfile=true` requires `LocalHorizonProfileJson` to contain
  between 8 and 720 finite elevations in degrees.
- Values must be in `0..90`.
- Values have equal angular spacing around 360 degrees, starting at north and
  moving clockwise, matching the PVGIS user-horizon convention.
- The complete JSON input is bounded to 16 KiB.

The runtime requests `direct_normal_irradiance` only while the model is
enabled. Missing, negative, misaligned or otherwise invalid radiation series
reject the complete candidate and preserve the last-good forecast.

## Separation From Calibration

The local horizon is known geometry and belongs in the forward model.
Calibration continues to evaluate residual error after known horizon effects
have been applied. A later factor must therefore not relearn morning shading,
and storage-related curtailment remains classified independently.

For controlled observation the cache also retains a `baseline` series from the
same provider response before horizon processing. It uses no additional HTTP
request and creates no shadow instance. The operative public values and the
`system` API remain horizon-adjusted; baseline data is exposed only through the
bounded forecast API and copied into new immutable calibration snapshots.

## Offline Proof

The focused suite covers:

- profile validation, circular interpolation and wraparound;
- solar elevation and azimuth against the published NREL SPA reference case
  with a `0.05` degree tolerance for the deliberately lighter calculation;
- unchanged GTI with an open horizon;
- bounded direct-component removal with a blocking horizon;
- rejection of misaligned DNI and GTI intervals;
- conditional provider-field selection;
- module fail-closed behavior for an enabled empty profile;
- successful module projection with a valid profile; and
- deterministic inclusion of both horizon classes in the generated fileset.

Publication, public-repository integration, live module update, private
profile activation, manual execution and observation remain separate gates.
