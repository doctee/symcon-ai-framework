# Standalone Symcon Module Publication

SAEF publishes deterministic module filesets through one strict,
manifest-driven repository tool:

```console
php tools/publish-symcon-module.php \
  --contract=deployments/symcon/media-carousel-publication.json \
  --check
```

## Contracts

Publication contracts are public, versioned JSON files under
`deployments/symcon/`. They declare repository identity, generated fileset,
metadata mappings, complete target inventory, privacy policy and publication
mode. Unknown fields and unclassified files fail closed.

Current contracts:

| Contract | Mode | Status |
| --- | --- | --- |
| `open-meteo-publication.json` | `direct_branch` | Explicit legacy compatibility |
| `media-carousel-publication.json` | `pull_request` | Default PR workflow |

New module contracts use `pull_request`. Direct base-branch publication is not
the default and requires a separately justified compatibility decision.

## Modes

### Check

`--check` validates the contract, deterministic fileset, metadata, privacy,
inventory and hashes. It performs no clone or remote operation.

### Prepare

`--prepare=/absolute/new/target` writes and byte-verifies the exact candidate.
The target must not exist. Omitting the target selects a new private temporary
directory.

### Apply

PR publication requires all immutable gates:

```console
php tools/publish-symcon-module.php \
  --contract=deployments/symcon/media-carousel-publication.json \
  --apply \
  --expected-fileset-sha256=<64-hex> \
  --expected-publication-sha256=<64-hex> \
  --expected-remote-commit=<40-hex> \
  --confirm-publication=publish-doctee-saef-media-carousel-pr \
  --commit-message="<reviewed bounded message>"
```

The command publishes a deterministic topic branch and creates a non-draft
pull request. It never merges. After any attempted remote mutation followed by
failure, it preserves the exact temporary workspace and reports its path.

## Authorization boundaries

The fixed apply command is one repository-publication phase. Pull-request
merge, live Symcon update and destructive recovery cleanup each require a
separate authorization.

The recommended narrow persistent command prefix is:

```text
php tools/publish-symcon-module.php
```

## Verification

Run:

```console
composer test:module-publication
make check
```

The regression uses local Git remotes and a simulated `gh` executable. It does
not publish to GitHub or access IP-Symcon.
