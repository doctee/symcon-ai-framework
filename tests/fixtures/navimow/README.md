# Navimow Test Fixture References

The current REST MVP scaffold uses sanitized fixtures from:

```text
case-studies/navimow/fixtures/rest/
```

The test runner references those files directly to avoid duplicating fixture
payloads before the module test structure stabilizes.

Do not place raw captures, real tokens, real device IDs or local garden data in
this directory.
