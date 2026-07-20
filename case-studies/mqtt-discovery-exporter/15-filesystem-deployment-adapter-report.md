# 15 Filesystem Deployment Adapter Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Deployment adapter
**Result:** OFFLINE PASS
**Date:** 2026-07-15
**Live status:** No filesystem deployment or IP-Symcon load performed

## 1. Decision

The exporter uses a deterministic filesystem fileset rather than an expanded
single-file helper bundle.

The existing `build-symcon-bundle.php` contract is intentionally optimized for
global guarded helper functions below `helpers/`. The exporter additionally
contains two namespaced classes. Combining both forms would require namespace
rewriting or a second transformed implementation surface.

The fileset adapter instead copies the complete dependency closure byte for
byte and preserves every canonical relative `require_once` statement. This is
the smaller and more traceable extension for an installation that can load a
filesystem artifact.

## 2. Artifact Contract

The declarative manifest is:

`deployments/symcon/mqtt-discovery-exporter.fileset.json`

It defines:

- the candidate runtime entry;
- PHP 8.2 as the minimum version;
- the generated output directory;
- the complete sorted SAEF function-export allowlist;
- the two namespaced class exports.

The builder follows only literal `require`/`require_once` expressions based on
`__DIR__`. It rejects includes, dynamic dependencies, traversal outside the
approved helper/candidate roots, missing files and cycles.

## 3. Resolved Closure

The automatically resolved closure contains twelve canonical PHP files:

- candidate Core and Runtime;
- common Validation;
- EnsureCategory, EnsureEvent, EnsureInstance and EnsureVariable;
- ConfigurationHash, ErrorRingBuffer, Registry and Statistics;
- WaitForVariable.

Every generated source copy has the same SHA-256 and exact bytes as its
canonical repository file. No helper body, class or include expression is
rewritten.

## 4. Generated Deployment Tree

The tracked generated root is:

`dist/symcon/saef-mqtt-discovery-exporter/`

It contains:

- the twelve files below their canonical relative paths;
- `bootstrap.php`;
- `fileset.sources.json`;
- `fileset.sha256`.

The bootstrap performs a conflict preflight for all exported functions,
classes and helper guard constants before requiring the candidate Runtime. It
contains no installation path or ObjectID. The private installation needs only
one relative include to this reviewed bootstrap after physical placement.

## 5. Determinism and Provenance

The provenance sidecar records:

- manifest and entry paths;
- ordered source paths and individual hashes;
- bootstrap hash;
- aggregate fileset hash;
- function, class and guard-constant surfaces;
- builder/framework versions and license metadata.

No timestamp, username, absolute local path, host, topic, credential or private
installation identity enters the deterministic hash.

## 6. Offline Verification

`tests/bundles/mqtt-discovery-exporter-fileset.php` verifies:

1. two independent builds produce identical file names and bytes;
2. tracked generated output has no missing, extra or stale file;
3. all twelve source copies equal their canonical sources byte for byte;
4. function and class namespace conflicts fail before loading;
5. the bootstrap contains no absolute user path or installation ObjectID;
6. generated helpers and both candidate classes become available;
7. diagnostics initialization succeeds through the generated deployment tree.
8. the existing `SAEF_EnsureVariable()` contract remains idempotent through
   the fileset bootstrap.

Repository `composer check` includes fileset drift and behavioral verification.
The pre-existing EnsureVariable single-file bundle remains unchanged and keeps
its own tests.

## 7. Installation and Rollback Boundary

A future live deployment must account for the already active minimal
EnsureVariable bundle. Both artifacts export the same canonical helper names,
so they must never be loaded beside each other in one PHP process. The
bootstrap conflict preflight deliberately rejects accidental coexistence.

The activation transition must:

1. build or verify the approved fileset hash;
2. copy into a new staging directory on the target filesystem;
3. compare every target file hash with `fileset.sources.json`;
4. snapshot the minimal bundle, private bootstrap and migrated caller;
5. atomically select the complete fileset directory;
6. replace the minimal-bundle include with the fileset include;
7. restart IP-Symcon into a clean function/class namespace;
8. verify the previous seven exports and the existing migrated caller before
   loading the exporter candidate;
9. perform the isolated no-entity load twice;
10. restore the old include/bundle and restart if any gate fails.

Do not overlay individual files into an active directory. Version replacement
requires a new directory and a separately designed update/restart procedure,
because already loaded PHP functions and classes cannot safely be redefined in
the same process.

## 8. Gate Decision

The deployment adapter is **offline PASS**.

The next G6 sub-gate is a read-only private activation-transition inventory and
snapshot, followed by an explicitly authorized clean-process fileset load with
no configured entity, MQTT publication or device action. Its private path
mapping, bootstrap mutation, restart and rollback execution are not authorized
by this report.
