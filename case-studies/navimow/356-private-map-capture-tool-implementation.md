# 356 Private Map Capture Tool Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Private offline implementation complete; dependency and live gates
remain closed

**Date:** 2026-08-27

## 1. Objective And Boundary

This step implements the private command-free map capture tool designed in
step 355 and validates it without network access.

The implementation remains in the Git-ignored private overlay. This public
report contains only relative paths, hashes, contracts and synthetic results.
No credential, account, device identity, vendor response, real geometry,
private hostname or installation-specific value crosses into the case study.

This step performs no dependency installation, DNS lookup, API request,
account login, vendor-side device registration, token refresh, identity
creation, Symcon action, message transport, mower command or publication.

## 2. Implemented Private Files

```text
private/navimow-capture/capture-private-map-readonly.sh
private/navimow-capture/capture_private_map_readonly.py
private/navimow-capture/reduce-private-map.php
private/navimow-capture/private-map-requirements.txt
private/navimow-capture/private-map-third-party-notice.md
```

The private capture README also records the validation command and temporary
isolated-worktree reducer-root requirement.

Permissions are:

| File role | Mode |
|---|---|
| shell and Python executables | `700` |
| PHP wrapper, dependency pin and notice | `600` |

The repository's root `/private/` ignore rule covers every file.

## 3. Wrapper Contract

The shell wrapper:

- uses `set -euo pipefail` and `umask 077`;
- requires Python and PHP before validation;
- binds Python bytecode to an ignored private cache;
- runs the existing public geometry reducer regression test;
- verifies the exact dependency pin;
- compiles the Python helper and lints the PHP wrapper;
- runs the Python no-network policy and sanitizer suite;
- scans the private client for forbidden write endpoint, message-transport and
  command-capable symbols;
- exits after static validation;
- rejects a normal invocation with exit code `2` before creating output or
  identity state;
- does not install the missing dependency automatically;
- requires both a later live-gate value and an existing isolated virtual
  environment before the Python live path can start.

The temporary environment variable
`NAVIMOW_PRIVATE_MAP_SAEF_ROOT` is accepted only in validation mode while the
step-353 reducer remains on the isolated case-study branch. The live path uses
only the canonical repository-relative reducer location and accepts no reducer
root override.

## 4. Closed Operation Model

The Python client exposes exactly six enum operations:

| Operation | Exact method/path | Limit |
|---|---|---:|
| Passport login | `POST /v3/user/login` | 1 |
| App device registration | `POST /user/user/login` | 1 |
| Vehicle discovery | `POST /vehicle/vehicle/auth-list` | 1 |
| Current location | `POST /vehicle/vehicle/get-location` | 1 |
| Map-list fallback | `POST /map/index/map-list` | 0 or 1 |
| Plain map detail | `POST /map/index/map-detail` | 1 |

There is no string-path dispatch API. Region selection maps to one exact
Passport host and one exact mower-cloud host. Requests are fixed to HTTPS port
443 with system certificate and hostname verification, no redirect handling,
a 20-second request timeout and a 180-second process deadline.

Attempt counters are flushed before dispatch. Each operation can be consumed
once; a second call fails locally. Response headers are bounded to 32 KiB and
endpoint bodies to 256 KiB, 1 MiB or 4 MiB as designed.

Token refresh, automatic region discovery, compressed map detail, endpoint
fallback beyond map list, retry, message transport and mower commands are not
representable.

## 5. Credential And Identity Implementation

The Python process owns all interactive inputs so no password needs to cross a
shell-to-child environment boundary.

The future live path:

- requires the exact terminal phrase `PRIVATE MAP ONCE`;
- accepts only the fixed account regions;
- reads the dedicated-account password with terminal echo disabled;
- clears the password and username fields immediately after Passport login;
- preflights the crypto dependency and embedded public-key hash before asking
  for consent or credentials;
- creates one mode-`600` 32-hex-character app identity after consent;
- hashes the identity for attempt evidence without displaying it;
- refuses a corrupt identity and never regenerates it automatically;
- keeps Passport tokens, account UUID and private-cloud UID process-local;
- persists no login response or token bundle;
- retains the stable identity after the attempt to avoid creating a second
  vendor-side identity silently.

The final report separates local credential cleanup from the unproven
server-side session closure.

No live branch of this code was executed in step 356.

## 6. Cryptographic Source Closure

The private helper adapts only the Passport signing and private request-envelope
contracts from `ilguala/navimow_pro` at fixed commit
`f25f418224681f67e2ad68693cded6c17b11dbe6`.

The private notice contains:

- source URL and immutable commit;
- adapted file inventory;
- upstream copyright;
- full MIT terms;
- explicit exclusion of Home Assistant UI, entities, scheduler, message
  transport and mower-command code.

The embedded RSA public key is checked without the crypto dependency:

```text
DER length: 162 bytes
SHA-256: 589fb9e413ee622d068f307d2c26a5f4e68072f2b516008afff1d3953dedf115
```

The crypto import is lazy and occurs only after the live gate. Its absence
causes `dependency_unavailable` before login or identity creation.

The private requirements file fixes:

```text
cryptography==44.0.3
```

The platform-specific artifact hash is intentionally unresolved because this
step was not authorized to access a package index or download dependencies.
Installation remains **NO-GO** until that hash and platform compatibility are
verified separately.

## 7. Geometry And Evidence Pipeline

The private PHP wrapper:

- accepts a maximum 5 MiB input object;
- invokes the existing `MapGeometryReducer` rather than duplicating polygon
  logic;
- writes the full projection and private validation report atomically with mode
  `600`;
- returns only a generic rejection message on malformed geometry.

The Python structure reporter derives public-candidate booleans from the
private validation report. It does not retain exact counts, ids, names, area
values, coordinates, polygon hashes, host, region or token metadata.

The live output layout remains the one defined by step 355. Validation uses a
system temporary directory and deletes it automatically.

## 8. Offline Validation Evidence

Executed command:

```sh
NAVIMOW_PRIVATE_MAP_VALIDATE_ONLY=1 \
NAVIMOW_PRIVATE_MAP_SAEF_ROOT=<isolated-navimow-worktree> \
  ./private/navimow-capture/capture-private-map-readonly.sh
```

Observed result:

```text
Navimow map geometry reducer checks passed.
Private map no-network validation passed.
Private map capture static policy, reducer and sanitizer validation passed.
```

The validation proves:

- Python 3.9 syntax and PHP syntax pass;
- all six operation specifications are fixed and single-use;
- an arbitrary operation is rejected;
- all network-related counters start at zero;
- token-refresh, write and mower-command counters remain zero;
- over-deep JSON is rejected;
- the synthetic map passes the private wrapper and existing reducer;
- the public map reducer tests reject malformed, self-intersecting,
  out-of-range and over-limit geometry;
- the structure report contains no forbidden private field names or synthetic
  secret values;
- source scans find no write endpoint, message library or command-capable
  method;
- a normal wrapper invocation exits locked before output or identity creation;
- no private map state or output directory was created.

The validation performs no network monkey-patching claim. It follows a code
path that never instantiates `PolicyTransport` and uses only synthetic files.

`shellcheck` is not installed on the Mac and was not run. Bash parsing, strict
wrapper execution, the wrapper's source-policy scans, Python compilation and
PHP linting passed. A later dependency/readiness gate should add `shellcheck`
when the tool is available, without weakening the existing checks.

## 9. Private Tool Hashes

The validated private implementation is bound by:

| Relative file | SHA-256 |
|---|---|
| `capture-private-map-readonly.sh` | `570cc7513866fc9eca35fbaeb3eff9613686ed9732cfc66e589f29fb109ee5f5` |
| `capture_private_map_readonly.py` | `3ad14e330d2c8440346eaaf676ad3cdb26d3565d31aadf31fac7187c6ce86ca2` |
| `reduce-private-map.php` | `49b9030b5d8bde93d294c40104a357b2a9b5598ca0963fda2b00e7452be44f2a` |
| `private-map-requirements.txt` | `cee3e7e1f9673cec2affc563dcb0bb9d78771b608a7dce53912ded15da07541c` |
| `private-map-third-party-notice.md` | `e7b79212636977493beba93208dad2dbec5eb1a5cb7dfb5db3a3f8b91e8bb1bc` |

Any later change requires new hashes and a repeated static review.

## 10. Architecture Decisions

### AD-NAV-356-01: Keep implementation private and evidence public

**Decision:** Protocol and credential-bearing code remains ignored; only its
contracts, hashes and synthetic results enter SAEF.

**Reason:** The tool is installation evidence infrastructure, not a productive
module dependency.

### AD-NAV-356-02: Preflight crypto before authentication mutation

**Decision:** Validate the source constant and import the pinned crypto runtime
before consent, credential input or vendor request.

**Reason:** A missing dependency must not be discovered after Passport login
has already created session state.

### AD-NAV-356-03: Persist identity but not tokens

**Decision:** One stable private app identity is the only credential-adjacent
state intentionally retained by the first attempt.

**Reason:** Regenerating identity risks session interference; retaining tokens
would introduce a larger at-rest lifecycle without need.

### AD-NAV-356-04: Keep dependency installation manual and gated

**Decision:** The wrapper never creates a virtual environment or installs a
package automatically.

**Reason:** Dependency acquisition is a network and supply-chain mutation that
requires its own hash-bound approval.

## 11. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Private tool implementation | **PASS offline** |
| Synthetic policy and sanitizer tests | **PASS** |
| Existing reducer reuse | **PASS** |
| Private-data boundary | **PASS static** |
| Dependency version pin | **PASS** |
| Dependency artifact hash | **OPEN** |
| Isolated dependency installation | **NO-GO** |
| Stable device identity creation | **NO-GO** |
| Dedicated account | **Unconfirmed** |
| Private-protocol acceptance | **Unconfirmed** |
| Vendor login and map capture | **NO-GO** |
| Productive or Symcon integration | **NO-GO** |

The recommended next step is
`357-private-map-capture-dependency-closure-and-live-readiness.md`. After a
fresh gate it may resolve the exact compatible package artifact, verify its
hash, create the ignored isolated environment and run only cryptographic and
synthetic validation. It must still stop before identity creation, credential
input, DNS access to vendor services or vendor login.

Only a later separate gate may record the dedicated-account and private-protocol
acceptance statements and authorize one bounded live attempt.
