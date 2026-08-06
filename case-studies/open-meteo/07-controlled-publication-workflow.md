# Controlled Open-Meteo Publication Workflow

## Outcome

The SAEF repository is the only editable source of truth. The public
`doctee/saef-open-meteo` repository is a generated, one-way release mirror for
IP-Symcon Module Control.

The workflow is implemented by:

```text
tools/publish-open-meteo-module.php
```

Its immutable publication contract is:

```text
deployments/symcon/open-meteo-publication.json
```

The current public tree contains exactly 43 allowlisted files:

- 39 byte-exact module payloads from the module-fileset source map;
- `fileset.sources.json` and `fileset.sha256`;
- the repository-root `LICENSE`; and
- `case-studies/open-meteo/publication/README.md` as the canonical public
  README.

Unknown local or remote paths stop publication. The publisher never reads
arbitrary files from the SAEF working tree and never synchronizes a directory
without the manifest allowlist.

## Safe Modes

`--check` is read-only and performs no network operation:

```console
make open-meteo-publication-check
```

It verifies the generated fileset, every payload hash, metadata URLs, license,
README, privacy markers, exact file count and the aggregate publication hash.

`--prepare` creates a new local directory and never opens Git or the network:

```console
make open-meteo-publication-prepare
php tools/publish-open-meteo-module.php --prepare=/absolute/new/target
```

An existing target is rejected. The command does not merge into or delete from
another directory.

## Apply Gate

There is intentionally no Make target for publication. `--apply` is available
only through the complete explicit command:

```console
php tools/publish-open-meteo-module.php \
  --apply \
  --expected-fileset-sha256=<64-character-fileset-hash> \
  --expected-remote-commit=<40-character-main-commit> \
  --confirm-publication=publish-doctee-saef-open-meteo-main \
  --commit-message="<reviewed commit message>"
```

Before any remote mutation the publisher:

1. revalidates the complete local candidate;
2. clones only the configured public repository and `main` branch;
3. requires a clean clone at the explicitly expected full commit;
4. rejects every remote path outside the 43-file allowlist;
5. stages only allowlisted changed paths;
6. rechecks the remote branch immediately before push; and
7. relies on a fast-forward push to reject a concurrent update.

The remote baseline may omit newly added candidate paths that are already in
the current allowlist. Any existing remote path outside that allowlist still
stops publication before files are written or a mutation is attempted.

After a push it performs a second independent shallow clone and verifies the
new full commit and all 43 file hashes. A post-push verification failure is
reported as a mutation-attempted failure and must be investigated rather than
retried automatically.

If the candidate already equals the expected remote revision, `--apply`
returns `outcome: unchanged` with `mutationAttempted: false` after independent
remote verification. It creates neither a commit nor a push.

## Boundaries

The publisher does not:

- update IP-Symcon Module Control;
- create or configure IP-Symcon instances;
- issue HTTP, MQTT or device operations;
- tag a release;
- publish without the explicit apply gates; or
- commit any SAEF working-tree change.

Repository publication and a later `MC_UpdateModule` remain separate approvals.
Live instances are not touched by this workflow.

## Module Update Reconciliation

A separately authorized Module Control update may reload consumer instances
before all referenced `SharedLocation` module contexts are ready. That
temporary ordering does not justify a service restart or another provider
request. Reconciliation follows this bounded sequence:

1. verify that every referenced `SharedLocation` instance is active and returns
   a structurally valid descriptor;
2. call `ApplyChanges()` once for each affected Weather instance;
3. verify Weather status, references, configuration hash, cache and fetch
   timestamps without issuing a request;
4. call `ApplyChanges()` once for each affected Solar instance only after its
   Weather reference is active; and
5. repeat the read-only projection to prove that a second reconciliation would
   be a no-op.

`MC_ReloadModule()` and service restarts are outside this recovery path. Any
changed fetch timestamp or new provider traffic stops the gate and requires
separate investigation.

## No-op Acceptance

The complete `--apply` path passed its first controlled no-op acceptance on
2026-08-02 against public `main` commit
`16552dc9355bf5e3e7382db1d1421bfe63850aaa`. An independent preflight clone was
byte-identical to the prepared 27-file candidate. The publisher then returned:

```text
outcome: unchanged
mutationAttempted: false
filesetSha256: 2e2418f62f953d7bc8674417e4479f0030dbc977ddceb2a68d1404584b4de2f7
publicationSha256: 36ce9bbe2082ceb3c56ecf8e826f014a8f8caa0a1443232e275816c385186d67
```

This proves the complete authentication, clone, remote-baseline and independent
verification path without creating a commit or attempting a push. A future
changed publication remains a separate explicit approval.

## Regression

`case-studies/open-meteo/tests/publication.php` proves:

- deterministic 43-file preparation;
- equality with the 39-payload source map;
- canonical README and license copying;
- stable publication identity across check and prepare;
- rejection of existing prepare targets;
- rejection of an ungated apply before network access;
- rejection of directory symbolic links before file filtering; and
- absence of any IP-Symcon live-operation surface in the publisher.
