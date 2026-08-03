# CL-015 welcome consumer hand-off and ownership closure

## Outcome

The `Beleuchtung willkommen` consumer now uses the CL-015 facade exclusively:

- one facade STATE read replaces three member STATE reads;
- one facade DIMMER action replaces three member brightness actions; and
- one facade STATE-off action replaces three member STATE-off actions.

Every unrelated condition, action and ordering decision remains unchanged. The
script was not executed during migration, no tracked value changed and no
device or group command was issued.

## Protected stopped attempt

The first transaction stopped before its semaphore and before any write because
one fragment matcher contained an over-escaped variable expression. A read-only
count then proved that all four intended fragments were unique. The corrected
transaction required those four exact matches and exact source readback.

## Ownership closure

The complete postflight finds individual Kuschelsofa member IDs only inside the
CL-015 owner configuration. External scripts now consume:

- `Beleuchtung alles-aus`: facade STATE only;
- `Beleuchtung willkommen`: facade STATE and DIMMER only; and
- Amazon Alexa: facade variables, with no member reference.

No foreign script or instance configuration controls an individual Kuschelsofa
member.

CL-015 diagnostics contain nine passive or reconciliation executions, nine
successes, zero commands, zero errors, zero confirmation timeouts and an empty
error history. The additional executions were passive member reports; neither
consumer was run.

The structural inventory remains 18 v2 and 11 legacy wrappers with no
unclassified source.

## Remaining gate

The known ownership bypasses are closed. CL-015 now awaits only its
presence-bound STATE, brightness, color-temperature, physical-input and Alexa
functional test with exact restoration.
