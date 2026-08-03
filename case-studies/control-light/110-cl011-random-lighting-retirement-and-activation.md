# CL-011 Random Lighting Retirement and Activation

**Date:** 2026-07-27
**Gate:** Approved dependency retirement and command-free activation
**Result:** STRUCTURALLY PASSED; DEVICE TEST AND CONSUMER HANDOFF OPEN

## Random-Lighting Dependency

The dedicated random-lighting instance was inactive and owned only the three
Grillbereich member-color targets plus two presentation links. The central
shutdown automation first disabled that instance, waited five seconds and then
switched off all three members.

The automation was changed narrowly: only the obsolete random-lighting action
and its dedicated delay were removed. All three existing member-off actions
were retained. Exact source backups and hashes were verified before and after
the write. The links, child state variable and random-lighting instance were
then retired, followed by an independent absence check. No light command was
issued.

## CL-011 Contract

CL-011 now uses the shared v2 runtime as a member-confirmed three-light Z2M
group:

- STATE uses any-member-on for passive projection and requires all members to
  match commanded state;
- DIMMER uses reported group brightness;
- color temperature requires all configured members to match;
- confirmation uses one shared deadline across member feedback; and
- partial, stale or unavailable member feedback fails closed.

Color remains deliberately disabled because the installed Z2M module does not
yet provide the required authoritative color round trip.

## Activation Evidence

The wrapper was written and read back byte-exactly. Two waiting reconciliation
runs completed with:

- two executions and two successes;
- zero commands, errors and timeouts;
- unchanged facade, group and member values;
- three explicit target feedback events, with color inactive; and
- nine explicit member feedback events for state, brightness and color
  temperature.

The dedicated diagnostics registry, counters and bounded error history were
created idempotently and remained clean.

## Open Gates

A real device matrix remains presence-bound. Until it passes, the two central
shutdown consumers deliberately retain their direct member-off actions. The
later handoff must be a separate rollback-backed transaction and must prove
that all three members still switch off through the facade.
