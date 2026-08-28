# 357 Private Map Capture Dependency Closure And Live Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Dependency closure and synthetic crypto validation complete; live
gate remains closed

**Date:** 2026-08-27

## 1. Objective And Boundary

This step closes the platform-specific dependency supply chain for the private
map capture implemented in step 356. It creates an ignored Mac-local virtual
environment and verifies the private cryptographic envelope without contacting
Navimow services.

The approval for this step covers only official package metadata, artifact
download, hash verification, private environment installation and synthetic
tests. It does not authorize Navimow DNS, authentication, device identity
creation, map capture, Symcon access, message transport, mower commands or
publication.

## 2. Target Platform

The dependency closure is bound to the machine that executes the private
capture tool:

| Property | Value |
|---|---|
| Host role | Private capture Mac |
| Architecture | `arm64` |
| Python | CPython `3.9.6` |
| Python platform | `macosx-10.9-universal2` |
| Private environment | `private/navimow-capture/.venv-private-map/` |
| Private wheel cache | `private/navimow-capture/.dependency-cache-private-map/` |

No package is installed system-wide, on the Windows IP-Symcon host or inside
IP-Symcon. The environment and wheel cache are covered by the repository's
root `/private/` ignore rule and have mode `700` at their roots.

## 3. Official Artifact Closure

Release metadata was obtained from the official PyPI JSON API. The selected
files were downloaded from `files.pythonhosted.org` and their local SHA-256
values were compared independently with the metadata before installation.

| Package | Exact wheel | SHA-256 |
|---|---|---|
| `cryptography==44.0.3` | `cryptography-44.0.3-cp39-abi3-macosx_10_9_universal2.whl` | `5639c2b16764c6f76eedf722dbad9a0914960d3489c0cc38694ddf9464f1bb2f` |
| `cffi==2.0.0` | `cffi-2.0.0-cp39-cp39-macosx_11_0_arm64.whl` | `de8dad4425a6ca6e4e5e297b27b5c824ecc7581910bf9aee86cb6835e6812aa7` |
| `pycparser==2.23` | `pycparser-2.23-py3-none-any.whl` | `e5c6e8d3fbad53479cab09ac03729e0a9faf2bee3db8208a550daf5af81a5934` |

Primary metadata sources:

- `https://pypi.org/pypi/cryptography/44.0.3/json`
- `https://pypi.org/pypi/cffi/2.0.0/json`
- `https://pypi.org/pypi/pycparser/2.23/json`

The private requirements file now pins all three packages and their exact
artifact hashes. Installation was performed offline with `--no-index`, the
private wheel cache, and `--require-hashes`. The wrapper contains no package
installation or update path.

## 4. Environment Verification

The isolated environment reports:

```text
python=3.9.6
platform=arm64
cryptography=44.0.3
cffi=2.0.0
pycparser=2.23
```

`pip check` reports no broken requirements. The installed environment occupies
approximately 30 MiB and the retained private wheel cache approximately 6.7
MiB. Neither is a public or productive module dependency.

## 5. Synthetic Crypto Contract

The private tool now exposes a separate `--crypto-self-test` path through the
wrapper variable `NAVIMOW_PRIVATE_MAP_CRYPTO_TEST_ONLY=1`. This path:

- validates the embedded RSA public-key bytes and fixed DER hash;
- imports only the pinned cryptographic runtime;
- verifies an AES-CBC encrypt/decrypt roundtrip with synthetic bytes;
- verifies that the private request envelope contains exactly `d`, `h`, `k`,
  `p` and `t`;
- checks encrypted block alignment, the 128-byte wrapped key and digest shape;
- constructs and decodes one completely synthetic encrypted response;
- performs no transport construction, DNS lookup, identity creation, file
  output, credential input or vendor request.

Observed result:

```text
Private map synthetic cryptography self-test passed.
```

## 6. Regression And Lock Verification

The existing no-network validation was repeated after the dependency changes:

```text
Navimow map geometry reducer checks passed.
Private map no-network validation passed.
Private map capture static policy, reducer and sanitizer validation passed.
```

A normal wrapper invocation still exits with status `2` and reports the closed
live gate. Before and after all tests, neither `output/private-map/` nor
`state/` existed. Thus no stable app identity, capture session or credential
state was created.

The Navimow-specific suite and the complete repository `make check` also pass.
Because the isolated worktree intentionally has no local Composer dependencies,
both checks use the framework-wide `COMPOSER_VENDOR_DIR` contract and
`tools/resolve-composer-vendor-dir.sh`. The resolver verified byte-identical
`composer.lock` files with SHA-256
`b108c9f037ca0e575cd827914baf355131205825752b474c1799dfd14f07547c`
and the required `phpstan` and `phpcs` executables before accepting the external
toolchain. Only dependency binaries were reused; all checked sources and
configuration came from this isolated worktree. No case-study-specific
Open-Meteo toolchain fallback was used.

## 7. Updated Private Tool Hashes

| Relative private file | SHA-256 |
|---|---|
| `capture-private-map-readonly.sh` | `22e445fd258f7a618112c86fb55101aa5e0b97614edc8dfca65a33b7dee69ac4` |
| `capture_private_map_readonly.py` | `4ff9a5b2bf8b8729dfe6efcd02fb0c0f722264db1105601a33039a31f728a79a` |
| `reduce-private-map.php` | `49b9030b5d8bde93d294c40104a357b2a9b5598ca0963fda2b00e7452be44f2a` |
| `private-map-requirements.txt` | `f340a26260a1e6559ad71047e1156fcd31c9f5ab96c5d851a0541fd5a8dee65a` |
| `private-map-third-party-notice.md` | `e7b79212636977493beba93208dad2dbec5eb1a5cb7dfb5db3a3f8b91e8bb1bc` |

The wheel hashes in section 3 bind the ignored dependency cache separately.

## 8. Architecture Decisions

### AD-NAV-357-01: Install only on the capture Mac

**Decision:** Keep the package in one ignored project-local virtual environment
on the Mac that owns the private capture process.

**Reason:** The dependency belongs to private interoperability evidence, not to
the IP-Symcon module runtime or the Windows host.

### AD-NAV-357-02: Resolve and retain the complete wheel closure

**Decision:** Pin `cryptography` and both runtime dependencies to exact,
platform-compatible wheels and hashes.

**Reason:** A top-level version pin alone does not make the transitive
installation reproducible or independently verifiable.

### AD-NAV-357-03: Install only after metadata and local hashes agree

**Decision:** Compare every downloaded wheel with official release metadata,
then install exclusively from the local cache with hash enforcement.

**Reason:** This separates network acquisition from trusted installation and
prevents an unverified artifact from entering the environment.

### AD-NAV-357-04: Keep crypto readiness separate from live readiness

**Decision:** Passing the synthetic cryptographic tests does not open the live
gate.

**Reason:** A live attempt additionally mutates vendor authentication state and
creates a persistent app identity. Dedicated-account suitability and explicit
private-protocol acceptance remain human decisions.

## 9. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Official package metadata | **PASS** |
| Complete artifact hashes | **PASS** |
| Offline isolated installation | **PASS** |
| `pip check` | **PASS** |
| Synthetic crypto envelope | **PASS** |
| Existing reducer and sanitizer regression | **PASS** |
| Default live lock | **PASS** |
| Navimow DNS or API access | **Not performed** |
| Stable device identity creation | **NO-GO** |
| Dedicated shared account | **Unconfirmed** |
| Private-protocol acceptance | **Unconfirmed** |
| Vendor login and map capture | **NO-GO** |
| Symcon or productive integration | **NO-GO** |

The recommended next step is
`358-private-map-capture-live-preflight-and-account-gate.md`. It should verify
the unchanged private file hashes, the dedicated shared-account boundary,
operator acceptance of the private protocol and retained vendor identity, the
exact one-attempt evidence path and the post-attempt cleanup limits.

Only a subsequent explicit live gate may authorize the fixed Navimow DNS and
authentication calls, create one stable app identity and execute one bounded,
command-free map capture. Dependency readiness alone is not that authorization.
