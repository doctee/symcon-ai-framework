# 11 Implementation Start Decision

**Case study:** Navimow native IP-Symcon module  
**Status:** Conditional implementation start decision  
**Date:** 2026-07-08  
**Build boundary:** This document approves the next implementation step only. It
does not introduce productive PHP code.

## 1. Purpose

This document resolves the open scaffold decisions from
`10-module-scaffold-plan.md` and decides whether the first real module scaffold
may be created.

It is the transition gate between analysis/planning and implementation. The
decision is intentionally narrow: only the REST MVP scaffold and fixture-driven
parser work are approved. Cloud command execution, MQTT/WSS, map rendering and
location features remain outside the approved implementation start.

## 2. Decision Summary

**Decision:** Conditional Go for the first REST MVP module scaffold.

The case study has enough evidence to start creating a native IP-Symcon module
family with account, configurator and device instances. The implementation must
start with structural scaffolding, protected account state, profile/variable
registration planning and fixture-based payload mapping. It must not start with
live command execution or MQTT/WSS.

The next implementation step is approved only if it follows these boundaries:

- create module scaffold files for `NavimowAccount`, `NavimowConfigurator` and
  `NavimowDevice`;
- create a small shared library namespace for REST payload mapping, profiles
  and REST client structure;
- create fixture-based tests or documented fixture checks before behavior is
  considered reviewable;
- use sanitized fixtures only;
- keep all credentials, tokens, private device IDs and raw captures out of
  public files;
- avoid automatic live API calls in tests.

## 3. Evidence Used

This decision is based on:

- `09-rest-mvp-readiness-review.md`;
- `10-module-scaffold-plan.md`;
- `03-variable-and-action-contract.md`;
- sanitized REST fixtures in `fixtures/rest/`;
- SAEF module template guidance in `templates/module/README.md`;
- SAEF standards for explicit ownership, stable Idents, diagnostics and
  internal state.

## 4. Resolved Scaffold Decisions

### AD-NAV-017: Repository placement for first module scaffold

**Decision:** Use the planned top-level module layout:

```text
modules/
  NavimowAccount/
  NavimowConfigurator/
  NavimowDevice/
library/
  Navimow/
tests/
  Navimow/
  fixtures/navimow/
```

**Rationale:** The repository currently contains no existing native
IP-Symcon module implementation to mirror. The selected layout keeps module
instances, shared library code and verification artifacts separate and matches
the scaffold target already documented in `10-module-scaffold-plan.md`.

**Consequence:** The first code step must verify official IP-Symcon metadata
requirements before committing module metadata details. If a required
`module.json` or library metadata file is needed, it may be added as part of the
scaffold, but only with placeholder-free public values.

### AD-NAV-018: Account instance role

**Decision:** Implement `NavimowAccount` as the account-owning parent instance
with explicit message handling for device and configurator children.

It should behave like a splitter-style parent for the module family, but the
REST MVP must not pretend that MQTT, IO sockets or push routing already exist.
The approved contract is explicit JSON message exchange:

- child instances request discovery, status or command forwarding;
- the account instance owns authentication, token refresh and REST transport;
- token values never leave the account instance.

**Rationale:** The fixture evidence is account-centric: discovery and token
state belong to the cloud account, while status variables belong to one mower.
Explicit account-child messages keep token ownership reviewable and make later
MQTT routing possible without changing the device variable contract.

**Consequence:** `NavimowDevice` must not build bearer headers, store refresh
tokens or call the Navimow cloud directly.

### AD-NAV-019: Protected token storage

**Decision:** Store access and refresh tokens as protected internal module
state owned by `NavimowAccount`, not as variables and not as ordinary public
configuration.

The implementation should prefer IP-Symcon module attributes or another
instance-internal persistent mechanism that is not displayed as user-facing
state. Public diagnostics are limited to:

- `ConnectionState`;
- `ReauthRequired`;
- `TokenExpiresAt`;
- `LastRestSuccess`;
- `RestErrorCount`.

Temporary authorization-code input may appear in the account configuration form
only as an exchange helper and must be cleared after successful token exchange.

**Rationale:** Tokens are operational secrets, not device state. Exposing them
as variables or public reusable defaults would violate the private data rules
and make accidental sharing more likely.

**Consequence:** The first scaffold may define token-state methods, but it must
not log token values, expose them in debug variables or add token-containing
fixtures.

### AD-NAV-020: Configurator inclusion

**Decision:** Include `NavimowConfigurator` in the first scaffold as a minimal
discovery-based instance.

The configurator may be thin in the first implementation. Its approved
responsibilities are:

- ask `NavimowAccount` for discovered devices;
- display device ID, name, model and firmware from discovery;
- create or match `NavimowDevice` instances by configured device ID.

It must not own tokens, poll status or execute commands.

**Rationale:** Multiple mowers are plausible and discovery is fixture-backed.
Including the configurator early avoids hardcoding a single device flow into
the device module.

**Consequence:** If IP-Symcon configurator mechanics require more boilerplate
than expected, the first scaffold may contain only metadata and a documented
stub, but the account/device contract must remain configurator-ready.

### AD-NAV-021: Fixture-based verification tooling

**Decision:** Start with fixture-based PHP verification focused on pure mapping
logic.

If the repository has no established PHP test runner, the first implementation
may add a lightweight deterministic test script under `tests/Navimow/` before a
larger test framework is introduced. The test runner must:

- load only sanitized fixtures;
- validate JSON parsing;
- test payload mapper behavior;
- avoid live network calls;
- avoid command execution against real devices.

**Rationale:** The highest implementation risk is schema interpretation, not
module lifecycle boilerplate. Pure mapper tests give fast feedback without
private credentials or live cloud dependency.

**Consequence:** A future switch to PHPUnit or another runner remains allowed,
but it should preserve the fixture assertions defined in
`10-module-scaffold-plan.md`.

### AD-NAV-022: `Online` semantics

**Decision:** Keep the public Ident `Online` for MVP, but document and
implement it as conservative status freshness until a dedicated online field is
fixture-backed.

Allowed MVP behavior:

- a successful status response may mark the device as reachable/fresh;
- an explicit future offline state may mark it offline;
- a missing online field must not be interpreted as proof of offline;
- transport/auth failures should update diagnostics and freshness, not invent a
device state.

**Rationale:** `Online` is useful for visualization and automation, but the
current fixtures do not prove a dedicated cloud field. The variable is kept for
UX stability while its semantics remain intentionally conservative.

**Consequence:** Implementation comments and user-facing labels should avoid
claiming a proven cloud online flag unless a future fixture confirms it.

## 5. Approved Implementation Scope

The next implementation step may create:

```text
modules/NavimowAccount/
modules/NavimowConfigurator/
modules/NavimowDevice/
library/Navimow/
tests/Navimow/
tests/fixtures/navimow/
```

Approved first-file categories:

- IP-Symcon module metadata required for loading the three instances;
- minimal `module.php` lifecycle stubs;
- form definitions with non-secret configuration only;
- locale files;
- shared profile helper structure;
- REST client skeleton without live credentials;
- pure payload mapper;
- fixture-based mapper checks using sanitized fixtures.

## 6. Explicit No-Go Scope

The next implementation step must not add:

- MQTT/WSS connection code;
- map rendering;
- location tracking variables;
- automatic live command tests;
- hardcoded real device IDs;
- real OAuth tokens or refresh tokens;
- raw capture payloads;
- command retry behavior;
- behavior that writes command intent directly into `VehicleState`.

## 7. Required Implementation Order

The approved order is:

1. Create module and library skeleton.
2. Add profile and variable registration structure.
3. Add pure payload mapper for existing fixtures.
4. Add fixture-based verification.
5. Add account REST client structure.
6. Add authentication exchange and protected token state.
7. Add discovery.
8. Add read-only status polling.
9. Add defensive command forwarding only after status polling is working.

This order is mandatory for reviewability. Command behavior depends on status
poll verification and must not be the first live behavior.

## 8. First Scaffold Definition of Done

The first scaffold is done when:

- module folders and metadata exist;
- all public configuration fields are non-secret;
- access and refresh token values have no public variable representation;
- `NavimowDevice` has stable Idents matching
  `03-variable-and-action-contract.md`;
- sanitized fixtures are available to tests without copying raw private data;
- mapper checks cover docked, running, discovery, token success, invalid token
  and `alreadyInState`;
- no MQTT/WSS, map or location behavior exists;
- no tests call the live Navimow API.

## 9. Residual Risks Accepted for Implementation Start

| Risk | Accepted for scaffold | Mitigation |
| --- | --- | --- |
| Official cloud API remains undocumented. | yes | Keep REST client defensive and fixture-driven. |
| True command success response is not fixture-backed. | yes | Commands do not set state directly; verify later by polling. |
| Dedicated online field is not fixture-backed. | yes | `Online` means conservative freshness in MVP. |
| Exact IP-Symcon metadata details may need adjustment. | yes | Verify during scaffold creation before adding behavior. |
| MQTT feasibility is unknown. | yes for REST only | Keep MQTT completely out of the scaffold. |

## 10. Start Gate

**Gate result:** Open for the next SAEF step.

The next step may create real module scaffold files, provided the work remains
within the approved scope above.

Recommended next SAEF step:

```text
case-studies/navimow/12-rest-mvp-scaffold.md
```

That step should either:

- create the approved scaffold and document what was generated; or
- document any blocking IP-Symcon metadata requirement discovered before code
  creation.
