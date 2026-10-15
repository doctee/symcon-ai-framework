# Prospective warming forecasts

Version 0.6.0 adds a separate observational `WarmingJournal`. Existing electrical
and passive-cooling forecasts, preselection scripts and heater control are retained.
No external variable write, device command, model refit or archive correction occurs.

A future arrival selection triggers a forecast immediately (debounced with the
existing plan observer). The horizon ends at arrival or after 48 elapsed hours.
Changes to plan or model append a revision; a continued unchanged plan receives
another horizon after the previous horizon expires. Actual flags/events changing
at their scheduled start can also create a revision. The first forecast issued
before phase start is distinguishable from updates issued during warming; no
retrospective prediction is created. A missed initial forecast stays missed.

Each curve freezes actual issue time, input knowledge time, model/configuration
hash, plan, controller configuration, initial sensor values, charge history,
weather fetch time and forecast, effective store, charging assumptions and points.
The curve hash is checked before comparison. Measured temperatures and operational
context are stored separately. Restart does not reset evidence.

## Model contract

`WarmingModelJSON`: schema 1, timezone `Europe/Berlin`, positive `trainedThrough`
before issue, `rooms.gallery` and `rooms.sofa`, each containing:

- `tau_hours`, `fan_decay_per_hour`, `k_per_hour`;
- `mass_coupling_per_hour`, `mass_time_hours`;
- four nonnegative `coefficients` for passive store, additional fan/store,
  irradiance per 100 W/m², and bathroom floor-heating duty.

Air and building-mass states use the exact constant-drive matrix solution on a
maximum 15-minute grid. Initial mass temperature equals measured air temperature:
an approximation, especially for a revision during active warming. The effective
store starts at zero seven days earlier and is reconstructed from reliable hourly
counter differences (minus configured standby) and archived fan state. Recent
unsettled hours use archived total kW. This index is neither physical stored kWh
nor SOC; separate room response models are not a shared energy conservation model.

`WarmingOptionsJSON`: positive `regulator` instance, explicit `latitude`,
`longitude`, `maxPowerKw`, `standbyKw`. Private calibration and source bindings
must never enter public artifacts. Existing validated `CoolingBindingsJSON`
provides optional battery/floor sources. Cooling and plan bindings are required.
The regulator must be Heating Control, heating mode, two-point type, gallery
source and the configured single noninverted fan target. Hysteresis uses half the
configured width on either side of the effective setpoint, per the
[official Heating Control contract](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/heating-control/).
Current setback is held; future door states remain unknown.

Hourly DNI and diffuse radiation from the existing OpenMeteoWeather API are
converted approximately to horizontal irradiance with the same NOAA-based solar
geometry equations used in Open-Meteo's private LocalHorizonModel. Interval
semantics and units are checked. A local implementation avoids extending a shared
API or republishing the separately owned weather module. Hourly covariance,
shading and actual window gains remain uncertain. Weather gaps fail closed.

## Charging assumption, not a new validated energy forecast

The existing night-regression coefficients provide **early scenarios** for normal
and reduced operation. These use room temperature, prior charge, prior fan duty
and outdoor history known at issue, held for subsequent nights, rather than
unknown future 21:00 features. Each future night uses forecast temperature.
Energy is spread uniformly over 23–07, capped by configured total power and
conditioned on projected enable/reduction. Network interruptions are unknown.
These assumptions are frozen and visibly marked experimental. The official
21:45 electrical forecast and its accuracy journal are not overwritten. Past
retrospective temperature errors are not claimed as prospective accuracy.

## Comparison and retention

The existing five-minute timer checks at most two pending records per run in
oldest-check order. Raw reads are split into at most 24-hour chunks, each capped
at 2000 records; hourly reads at 48 hours. No future measured temperature enters a
saved forecast. Comparisons interpolate only between real room samples with a
maximum six-hour bracket; battery-zero periods and six hours after recovery are
excluded. Missing values remain gaps; the initial measurement is not scored.
Hourly-point MAE and signed bias are reported per room and revision with coverage.
Overlapping revisions are not independent test phases. Actual fan/auxiliary/
presence/floor duty and settled full-hour electrical measurements provide context;
partial energy coverage has explicit bounds. Fireplace reports update comparison
classification without changing what was known when the prediction was issued.

Outcomes settle six hours after the horizon; late resumes beyond seven days mark
the comparison expired. Maximum 48 records and three MiB; only settled records
can be removed and removals are counted. A full pending journal or corrupt data
stops new issues visibly. Optional rollback: `WarmingModelJSON={}` stops new
curves but retains existing records and comparison. Disabling the whole observer
stops its timers. Export `SHF_GetWarmingJournalJson` before deleting an instance.

`WarmingComparison` is a read-only HTMLBox with two SVG graphs, current forecasts,
measurement coverage/errors, revision labels and assumptions. It can be linked
next to the existing plan view. It makes no comfort guarantee, particularly for
48-hour horizons or reaching 18 °C at a cold-wall living-room sensor.
