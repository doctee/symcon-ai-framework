# 15 Loader Fix Report

**Case study:** Navimow native IP-Symcon module  
**Status:** Distribution published; direct Symcon retest pending  
**Date:** 2026-07-08  
**Build boundary:** This step corrects repository distribution and loader
structure only. It adds no OAuth, REST polling, MQTT/WSS or mower commands.

## 1. Purpose

This report records the first direct IP-Symcon loader finding and the
resulting distribution decision.

It follows `14-symcon-loader-test-report.md`.

## 2. Observed Loader Results

The first test used the SAEF repository itself as the module source.

### 2.1 Default branch did not contain `library.json`

Initial Symcon result:

```text
This repository seems corrupted. library.json is at least missing.
```

The scaffold existed only on the feature branch. Commit `8047957` was
therefore cherry-picked to `main` as commit `313d877`.

### 2.2 SSH clone URL was unsupported

The source:

```text
git@github.com:doctee/symcon-ai-framework.git
```

produced:

```text
Error: unsupported URL protocol
```

The Symcon Module Control requires the HTTPS repository URL for this workflow.

### 2.3 SAEF root directories were interpreted as modules

After the HTTPS source loaded from `main`, Symcon reported:

```text
Modul symcon-ai-framework beinhaltet ungültiges Module adr
(module.json fehlt)
```

The directory was `adr`, not an unknown payload field. IP-Symcon treated a
first-level SAEF framework directory as a module candidate.

## 3. Root Cause

The SAEF repository and an installable IP-Symcon library have incompatible
root-layout responsibilities:

- SAEF organizes first-level directories by engineering artifact type;
- an IP-Symcon library expects installable module directories at its
  repository root;
- non-module PHP dependencies belong in the reserved `libs` directory.

Adding `library.json` to the SAEF root is not sufficient. The framework root
must not be used directly as a Symcon module source.

## 4. Architecture Decision

**Decision:** Development and distribution are separated.

- `symcon-ai-framework` remains the engineering source and case-study record.
- `case-studies/navimow/distribution/` is the canonical installable
  distribution snapshot.
- a separate private GitHub repository named `symcon-navimow` publishes only
  the contents of that distribution directory.
- IP-Symcon loads the dedicated repository through HTTPS.

### Rationale

This boundary:

- preserves the SAEF repository architecture;
- gives Symcon the root layout it expects;
- prevents framework directories from being interpreted as modules;
- provides a reproducible private-Git transfer path to the Win11 host;
- allows distribution validation before publication.

### Consequences

- distribution changes must be synchronized from the case-study source to the
  dedicated repository;
- only the dedicated repository URL may be configured in Symcon;
- the distribution structure must be validated before each publication;
- the direct loader gate remains open until the new repository is tested.

## 5. Corrected Distribution Layout

```text
library.json
README.md
libs/
└── Navimow/
    ├── ApiClient.php
    ├── PayloadMapper.php
    └── Profiles.php
NavimowAccount/
├── form.json
├── locale.json
├── module.json
└── module.php
NavimowConfigurator/
├── form.json
├── locale.json
├── module.json
└── module.php
NavimowDevice/
├── form.json
├── locale.json
├── module.json
└── module.php
```

The account and device include paths now resolve the shared profile helper
from:

```text
../libs/Navimow/Profiles.php
```

## 6. Validation Contract

Run:

```text
php case-studies/navimow/tools/validate-distribution.php
```

The validator checks:

- `library.json` exists and parses;
- every non-reserved first-level directory contains `module.json`;
- all expected module directories exist;
- module metadata, forms and locale files parse;
- every module contains `module.php`.

PHP lint and fixture mapper tests remain separate verification steps.

## 7. Correct Symcon Source

The dedicated private repository was published with:

```text
Repository: doctee/symcon-navimow
Branch: main
Commit: abaa08abc1bd67539d2101f941a4cd4e89886f2b
```

The only supported Module Control source for this test is:

```text
https://github.com/doctee/symcon-navimow.git
```

Do not use:

```text
https://github.com/doctee/symcon-ai-framework.git
git@github.com:doctee/symcon-navimow.git
```

## 8. Direct Retest Procedure

1. Add the dedicated HTTPS source in Symcon Module Control.
2. Confirm the `Navimow` library loads without repository-layout errors.
3. Confirm account, device and configurator modules are listed.
4. Create one instance of each module.
5. Open and save each form without credentials.
6. Confirm the documented profiles and variables are created.
7. Review the Symcon log for loader, include, lifecycle and profile errors.

No OAuth flow, REST call or command may run in this test.

## 9. Decision Gate

The loader correction passes only when:

- the dedicated private repository loads;
- all three modules can be created;
- forms open without errors;
- profiles and variables are created;
- no unresolved loader or lifecycle error remains.

After this gate passes, the next SAEF step is:

```text
case-studies/navimow/16-auth-and-readonly-rest-plan.md
```
