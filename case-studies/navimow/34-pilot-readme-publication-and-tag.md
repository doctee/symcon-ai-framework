# 34 Pilot README Publication and Tag

**Case study:** Navimow native IP-Symcon module
**Status:** Prepared locally; remote push pending
**Date:** 2026-07-09
**Scope:** Publish private-pilot README guidance and create first pilot tag

## 1. Purpose

This step executes the publication plan from
`33-release-metadata-and-tag-plan.md`.

It publishes the improved private-pilot README to the dedicated module
repository and creates the first pilot tag for the REST MVP with Dock
verification.

No productive PHP behavior and no `library.json` metadata were changed.

## 2. Publication Boundary

Dedicated module repository:

```text
doctee/symcon-navimow
```

Changed module repository file:

```text
README.md
```

Unchanged module metadata:

```text
version: 0.1
build: 0
date: 0
```

## 3. Local Validation Before Commit

Validation performed before the module repository commit:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
git diff --check
```

Results:

| Check | Result |
| --- | --- |
| REST/Auth/fixture/static checks | passed |
| Distribution structure validator | passed |
| Publish clone whitespace check | passed |
| Publish clone `.DS_Store` scan | no files found |
| Publish README privacy scan | no private-data hit |
| Changed files in publish clone | `README.md` only |

## 4. Local Module Repository Commit

Created local commit in the dedicated publish clone:

```text
692ea0350bb73e6581e4643a931837ae48b49ede docs: add private pilot usage guidance
```

The commit updates the module repository README with:

- private-pilot status;
- implemented and excluded features;
- installation/update guidance;
- OAuth privacy notes;
- safe Dock command use;
- Dock verification behavior;
- known limitations;
- privacy boundaries.

## 5. Pilot Tag

Created local annotated tag:

```text
pilot-0.1.0.1
```

Tag object:

```text
21dc3eb0d2912ec3e957eb5e6ddfba88d290d0a2
```

Tag message:

```text
Private pilot 0.1.0.1: REST MVP with Dock verification
```

The tag points to the module repository commit that includes both:

- the REST MVP Dock verification code from `a6178dc`;
- the private-pilot README guidance from `692ea03`.

## 6. Remote Push Status

The agent environment attempted to push `main`, but the SSH connection to
GitHub failed because DNS resolution for `github.com` was unavailable in the
execution environment.

Observed sanitized failure:

```text
Could not resolve hostname github.com
```

The remote push therefore remains a manual user action.

Required push commands:

```bash
cd <local-navimow-publish-clone>
git push origin main
git push origin pilot-0.1.0.1
```

After those commands succeed, the dedicated module repository will contain the
pilot README commit and the annotated pilot tag.

## 7. Architecture Decisions

### AD-NAV-064: Pilot tag includes documentation guidance

**Decision:** Create the first pilot tag only after the private-pilot README is
included in the module repository.

**Rationale:** The pilot tag should identify a usable and documented controlled
testing state, not only a code snapshot.

**Consequence:** `pilot-0.1.0.1` points to `692ea03`, not directly to the
earlier code-only commit `a6178dc`.

### AD-NAV-065: Keep pilot metadata unchanged

**Decision:** Do not alter `library.json` for the pilot tag.

**Rationale:** The tag is a controlled pilot marker. It is not a broad public
release or Symcon Store release.

**Consequence:** Module metadata remains `version 0.1`, `build 0`, `date 0`.

## 8. Recommended Next Step

After the user pushes `main` and `pilot-0.1.0.1`, create:

```text
35-pilot-publication-verification.md
```

That step should verify:

- remote branch contains commit `692ea03`;
- remote tag `pilot-0.1.0.1` exists;
- Symcon can still update from the module repository;
- the README is visible in the GitHub repository;
- no additional files were accidentally published.
