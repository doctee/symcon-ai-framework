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

## Corrected live validation

After the corrected parser was published and the installed library was updated
to that exact public revision, the separately authorized single-location pilot
was repeated. Two activation checks stopped before the request because their
test assertions were stricter than the module contract: persisted historical
runtime state was republished during `ApplyChanges()`, and a numerically equal
elevation used a different JSON number representation. Both checks performed
their prepared rollback, left the timer disabled and issued no provider
request. The assertions were corrected only after independent readback proved
the inactive baseline and unchanged controls.

The final bounded run then:

1. verified the installed revision, inactive configuration, empty cache,
   object structure, source-provider configuration and unchanged solar control;
2. activated the existing isolated weather instance without an HTTP request;
3. issued exactly one explicit Open-Meteo update;
4. received `success: true` with result code `ok`;
5. atomically published current weather, daily/hourly forecast and soil values;
   and
6. restored the unconfigured status and disabled timer in the same execution.

The independent postflight confirmed status `104`, timer interval and next run
of zero, no timer-triggered request, unchanged object structure, unchanged
source and solar controls, and an unchanged root category. The successful pilot
values remain only in the isolated instance variables as evidence; its cache is
not exposed while the instance is unconfigured. No productive provider,
consumer or solar connection was changed.

The existing visibility-gap fixture remains the regression proof for the
provider response shape observed in both live attempts. No private coordinates,
ObjectIDs or installation metadata are included in this report.

## 2026-08-18 follow-up: general hourly model gaps

After the module had run successfully for several days, DWD ICON began
returning HTTP 200 responses whose hourly `temperature_2m` series contained
individual `null` values. The strict parser rejected the otherwise valid
response and both weather instances entered retry state. Version `0.8.8`
generalizes the established visibility-gap rule to every hourly model field:
individual unavailable points are omitted without shifting timestamps, while
an entirely unavailable series, current values and daily values remain invalid.
