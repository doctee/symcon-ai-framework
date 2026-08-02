# Open-Meteo Inactive Live Preflight

**Gate:** Authorized inactive installation test
**Result:** BLOCKED before mutation
**Date:** 2026-08-01
**Live changes:** None

## Outcome

The authorized preflight confirmed that the candidate library and its two
module definitions are absent from the target IP-Symcon 9.0 installation. No
weather or solar candidate instance exists, so there is no collision and no
candidate state to preserve.

The deterministic candidate remained unchanged and passed both its generated
artifact check and its standalone module-fileset regression. Its complete
fileset identity is:

```text
65ccca59e36b7e352f89fc90b9b0dd5b1967bb290c81ad5aeb83ba130abd2aa6
```

## Supported Installation Boundary

The live Module Control exposes `MC_CreateModule`, whose contract requires a
module repository URL. The candidate is currently present only as local,
uncommitted repository content and therefore has no URL from which Module
Control can install it.

The guarded SAEF deployment channel was also healthy, but its staging contract
places immutable candidates below its managed inactive fileset root. It does
not register an IP-Symcon module library and does not write to the module
development directory. Using it would therefore not make the two candidate
module definitions available to `IPS_CreateInstance()`.

## Safety Result

The run stopped at this boundary. In particular:

- no module repository was added;
- no file was staged on the target;
- no IP-Symcon object or instance was created;
- no `ApplyChanges()` mutation was invoked on a candidate instance;
- no timer, HTTP request, device action or provider change occurred; and
- no service restart or cleanup was needed.

The bounded connector calls reported neither a transport error nor a PHP
execution error and were not truncated. Exact evidence is retained only in the
ignored private machine-readable record.

## Next Gate

The inactive installation can continue after one supported delivery path is
authorized and available:

1. publish the standalone generated library as a Git repository and install
   that URL through Module Control; or
2. explicitly authorize a controlled local module-development deployment,
   including its filesystem target and any required reload or service restart.

Neither option is implied by the completed authorization. OpenWeather and
SolCast remain authoritative and untouched.
