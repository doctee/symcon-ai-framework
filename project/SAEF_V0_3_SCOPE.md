# SAEF v0.3 Scope

**Status:** Confirmed release scope; tag-readiness gates passed
**Target:** `v0.3.0`
**Decision date:** 2026-07-21
**Readiness review:** 2026-07-23

## Version Decision

The next release from `main` should be `v0.3.0`.

The post-v0.2 branch contains a backward-compatible MQTT confirmation fix and
a new restricted Windows deployment channel with an operating-system-neutral
SSH client contract. The deployment channel is a material new framework
capability, not a patch-only correction. Releasing the combined branch as
`v0.2.1` would understate its scope.

A maintenance-only `v0.2.1` would require a separate maintenance branch that
contains the MQTT correction but excludes the deployment feature. No such
release is currently required.

## Current Baseline

The framework is an operational pre-1.0 engineering platform:

- `v0.2.0` is published and immutable;
- Stable Draft 1.0 Symcon standards and the helper-first workflow are in use;
- the 29-function public helper API has direct contract tests;
- Runtime Diagnostics, deterministic bundles and filesets are established;
- MQTT, ControlLight and Navimow provide live-system engineering evidence;
- the restricted deployment channel passed its repository and live security
  gates; and
- `make check` covers syntax, generated drift, behavior, static analysis and
  style.

The framework remains pre-1.0 because public APIs, deployment operations and
module guidance are still expanding through controlled use. That does not make
the existing v0.2 contracts provisional or exempt them from SemVer review.

## Proposed v0.3 Scope

### Include

- the bounded MQTT authoritative-feedback correction already active in the
  supervised installation;
- the restricted Windows deployment channel and SSH client contract for
  macOS and suitable iPhone/iPad terminals;
- serialized staging and activation, bounded package expansion, persistent
  storage budgets, machine-scoped credentials and rollback hardening;
- reconciled v0.2 publication and live-rollout status documentation;
- the completed System Functions three-call migration and scheduled-observation
  evidence;
- the passive Navimow natural-transition result; and
- the explicit seven-v2/22-retained ControlLight rollout closure.

### Exclude

- bulk migration of retained private ControlLight wrappers;
- wholesale replacement or publication of the private legacy function library;
- Navimow Start or Stop without their independent evidence gates;
- a general remote shell, SFTP or unrestricted PowerShell channel;
- installation-specific credentials, host data, ObjectIDs or MQTT topics; and
- new public helpers or APIs without demonstrated recurring reuse.

The optional Termius mobile-key extension remains a later operating task. It
does not block the existing macOS deployment path or the v0.3 release.

## Release Gates

Before tagging `v0.3.0`:

1. close or explicitly defer every item in the `[Unreleased]` scope;
2. confirm the completed final System Functions pilot migration and scheduled
   observation in the release evidence;
3. update framework-version constants to `0.3.0` only in a dedicated release
   preparation change;
4. regenerate and verify all deterministic bundles and filesets;
5. review the public API inventory and confirm that no accidental API was
   introduced;
6. run the deployment security gate and full `make check` from a clean
   checkout;
7. repeat the private-data and third-party provenance review;
8. date the `CHANGELOG.md` section and verify release-note extraction;
9. require successful CI on the final release revision; and
10. create the annotated tag only after all preceding gates pass.

Repository publication and live Symcon activation remain separate. A future
v0.3 fileset must be staged, preflighted, activated and verified through the
restricted deployment channel; creating the Git tag alone changes no Symcon
runtime.

## Immediate Next Boundary

Repository consolidation, clean public-tree verification and CI are complete.
Deployment channel version 7 passed its guarded Windows installation, deep
probe and bounded rejection checks without activating a fileset or operating a
device. The immediate next boundary is the framework version change and
deterministic regeneration of the bundle and both filesets.
