# Weather pilot and visibility-gap correction

Date: 2026-08-02

## Scope

One previously inactive Open-Meteo weather test instance was selected for a
parallel pilot at one configured location. Existing OpenWeather and SolCast
instances, the Open-Meteo solar instance and all productive consumers remained
out of scope.

The private preflight recorded the exact inactive configuration, object
identity, 41 child variables, values, profiles, actions and references. The
pilot used the existing weather provider only as the private source for the
location definition. It enabled a seven-day weather forecast, hourly polling
and model-grid soil values.

## Result and rollback

`ApplyChanges()` activated the instance without issuing an HTTP request. The
single separately controlled update reached Open-Meteo but returned the module
result `response_invalid`. No candidate cache or successful forecast was
published.

Because the failure policy scheduled an automatic retry, the instance was
immediately restored from the prepared rollback. The independent postflight
confirmed the original inactive status, configuration, name, 41 zero-valued
children and empty reference set. The existing weather provider and the solar
test instance remained unchanged.

Private machine-readable before and after evidence is retained outside the
public repository.

## Reproduction and cause

A sanitized request using a coarse public city coordinate and the same field
profile reproduced the provider shape without installation data. The DWD ICON
response was structurally valid and all requested soil series and units were
present. Hourly `visibility` alone contained provider `null` gaps near the
forecast horizon.

The parser previously rejected every null sample, so one optional visibility
gap invalidated the complete atomic response. The correction permits individual
null gaps only for hourly visibility, preserves the original timestamps of all
usable points, and continues to reject:

- nulls in every other requested field;
- unequal raw time and value-array lengths;
- missing or incompatible units; and
- an entirely unavailable visibility series.

## Next gate

The corrected fileset passed the focused parser, fileset, publication, PHPStan
and PHPCS checks as well as the repository-wide check. Publication and
installation remain separate guarded workflows. A new single-location live
request requires a fresh preflight and explicit activation gate; provider
replacement and consumer migration remain later work.
