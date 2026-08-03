# Zigbee2MQTT V6 Refresh and Color Gate

**Date:** 2026-07-27
**Gate:** Read-only release refresh
**Result:** NO V6 UPDATE AVAILABLE

## Public Candidate

The public `dev_V6` branch still resolves to
`b50f5d39e6f8b28fdbb65887c54497f08e33d82b`, identical to the previously
recorded candidate. It remains a development branch rather than a released
Store artifact.

## Installed Store Contract

Read-only Symcon inspection reports:

- Store channel: Beta;
- installed release: 17261;
- installed library version: 5.43;
- installed build: 543; and
- library status: present without a Store error.

Transport and PHP execution both completed successfully and the bounded output
was not truncated.

## Decision

No module update, event quiescence or service mutation is justified. The
existing maintenance package must be regenerated from a fresh dependency
inventory when an official V6 Store release is actually offered.

CL-011 and CL-021 therefore retain disabled color capabilities. Their STATE,
brightness and color-temperature contracts are independent of this gate. Color
re-enablement remains a separate migration with module-level regression,
authoritative conversion tests and per-instance functional tests.
