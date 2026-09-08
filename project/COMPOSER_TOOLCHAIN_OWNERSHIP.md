# Composer Toolchain Ownership

Status: Stable Draft 1.0

## Purpose

SAEF must run the same PHP analyzers against the same dependency lock in a
normal checkout, an isolated worktree and CI. A `vendor/` directory is executable
validation input. It is not interchangeable merely because it contains tools
with familiar names.

## Current project boundary

The repository root is the canonical Composer project. Its `composer.json` and
`composer.lock` jointly own the PHPStan and PHP_CodeSniffer versions used by the
repository-wide check.

Historical branches and worktrees may contain older copies of these files.
Those copies are revisions of the same project, not intentionally separate
Composer projects. They must not be normalized in place and their vendor trees
must not be selected for a current worktree unless the lock files are
byte-identical.

Case-study modules currently participate in the root project check. They do not
own independent Composer installations merely because they have focused test
scripts or publication contracts.

## Repository inventory and cause

The tracked tree contains one `composer.json` and one `composer.lock`, both at
the repository root. There is no nested Composer project. The effective entry
points are:

- root `composer check` and the `Makefile` wrapper;
- `.github/workflows/ci.yml` and `.github/workflows/release.yml`, which prepare
  the root lock and then run that same Composer graph;
- `case-studies/open-meteo/tools/check-offline.sh`;
- `case-studies/navimow/tools/check-mqtt-shadow.sh`; and
- `case-studies/navimow/tools/check-mqtt-symcon-probe.sh`.

The three focused shell checks already use the shared resolver. References to
`vendor/bin/phpcs` in older case-study reports describe historical commands;
they are not executable tool selection. New operational documentation should
use the resolver contract.

The observed failure was not a separate module dependency boundary. A clean
worktree had no local `vendor/`, while the former direct analyzer lookup assumed
one. At the same time, historical revisions represented both the old
PHP_CodeSniffer 3.13.5 lock and the security-updated 3.13.6 lock. The changelog
already described 3.13.6 although current `origin/main` still selected 3.13.5.
The correction makes 3.13.6 the canonical root lock and validates the actually
selected vendor owner before every Composer check.

## Resolution contract

The selected toolchain is resolved by
`tools/resolve-composer-vendor-dir.sh`:

1. Without `COMPOSER_VENDOR_DIR`, use the repository-local `vendor/` directory.
2. With `COMPOSER_VENDOR_DIR`, resolve relative paths from the checked repository
   root and absolute paths as supplied by the local environment.
3. Require executable `phpstan` and `phpcs` binaries.
4. Require the `composer.lock` beside the selected `vendor/` owner to be
   byte-identical to the checked repository lock.
5. Fail before analysis when any requirement is missing or mismatched.

`composer check` invokes this verification before the repository tests and
analyzers. `make check` additionally runs focused checks that use the same
resolver. The environment path is local input and must not be committed.

The resolver does not install packages, copy dependencies, access the network
or fall back to another vendor tree. A failed selection requires an explicit
operator decision: choose a lock-identical existing owner or create a local
installation in a separately authorized dependency-preparation step.

## CI and local equivalence

CI creates the repository-local vendor tree from the committed lock and then
runs `composer check`. Local checks may use the same local layout or an explicit
lock-identical external owner. Both paths execute the same toolchain verification
and the same Composer check graph.

PHP_CodeSniffer is constrained to at least 3.13.6 so the root project cannot
resolve the earlier affected patch line. PHPStan remains unchanged. Historical
vendor owners keep their historical meaning; they are neither upgraded nor
deleted by this repository change.

## Separate Composer projects

A future module may own a separate Composer project only when all of the
following are deliberate and documented:

- its project root and ownership purpose;
- its own `composer.json` and committed `composer.lock`;
- its own vendor resolution and check entrypoint;
- the repository check that invokes that entrypoint; and
- the rule preventing analyzer reuse across a different lock.

Such a project is checked independently. Its lock is not copied to the SAEF
root, and the SAEF root vendor is not silently reused for it. Identical projects
must instead remain lock-identical and use the existing root resolver.

## Migration effect

Existing clean worktrees on the current revision can use a vendor owner only
after adopting the updated root lock. Older worktrees remain valid recovery
inputs, but a current external toolchain must reject their old locks and an old
toolchain must reject the current lock. No source tree, vendor directory or
historical worktree is modified to make that comparison pass.
