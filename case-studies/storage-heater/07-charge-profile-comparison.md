# Prospective charge-profile comparison

Version 0.9.0 adds an independent `ChargeProfileJSON` configuration,
`ProfileComparisonJournal`, `ProfileComparisonStatus` and HTML comparison.
`SHF_GetProfileComparisonJournalJson` is a read-only getter. Empty configuration
disables new issues; existing outcomes continue to be measured.

The profile has schema 1, method `elapsed_eight_bins_v1`, a positive Unix
`trainedThrough` timestamp and eight nonnegative `weights` summing to one.
Calibration values and provenance belong in private configuration. No example
installation parameters are shipped as defaults.

The existing observer and settled plan refresh issue a new paired forecast
before both phase start and the first night's 22:00 boundary. Early selections
wait until the first complete night fits inside the elapsed 48-hour horizon.
The first night's end minus 48 hours is the earliest issue time. The end is
limited by arrival. Missing weather or sensor inputs retry without inventing
predictions. Plan/configuration revisions append; past phases are never backfilled.

Both curves freeze the same temperatures, thermal parameters, weather, solar
assumptions, plan, effective-store initialization, night-energy regression and
window/setback inputs. The reference keeps uniform charging. The candidate
redistributes the same energy within each contiguous enabled/reduced segment,
using the calibrated shape across eight elapsed-time bins between 23:00 and
07:00. Fan-only transitions do not restart the profile. Power saturation is
redistributed inside that segment; zero-weight intervals fall back to elapsed
duration if necessary. This preserves planned segment and night energy across
DST. Mixed reduction and truncated final nights remain conditional approximations.
The simulated thermostat may yield different fan duty as temperatures diverge.
No fast heat-release term, new solar response, window loss or control change is added.

Each forecast hash covers both sets of points, the profile, schedule and all
shared inputs. Existing journals and official/fan forecasts are unchanged.
Actual temperatures are acquired once for the pair, with the existing bounded
archive, sensor-gap and battery rules. Both variants are scored on identical
points. Outcome collection continues after cancellation, restart or disabling
new issues. Measurements and error diagnostics remain outside the frozen forecast.

For evaluation group revisions by actual warming phase, prefer the last valid
pre-phase stand and distinguish full-horizon from phase-only errors. Report
MAE, signed bias, endpoint error and coverage for gallery and living room;
check actual fan operation, altered plans, fireplace, window, auxiliary heat
and supply interruptions. A saved forecast is not proof of improved accuracy.
Old retrospective experiments are not prospective observations.

The UI shows paired curves and measured points. Link the owned comparison
variable into the existing visualization through the installation's guarded
link workflow. The optional comparison has independent error status and uses
no actuator, notification or archive mutation calls.
