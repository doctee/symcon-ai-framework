# 37 Owner Decoupling and Subscription Runtime Activation Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Physical fileset-path decoupling, corrected activation and bounded live regression
**Outcome:** PASS — runtime, reconciliation and external Home Assistant state transition verified

## Activation finding

The first activation of the subscription-coverage candidate exposed one
installation-owner coupling before any Home Assistant or device command was
issued. The owner script explicitly required the previous physical fileset
path while `System.Locals.ips.php` selected the newly activated fileset.
Although both files contained the same namespaced classes, PHP treats the two
physical paths as distinct `require_once` identities. A later owner event could
therefore have attempted a duplicate class declaration.

The installation was returned to the last verified runtime before functional
testing. The one affected owner was then changed to rely exclusively on the
authoritative `System.Locals.ips.php` bootstrap. No configuration, object
identity, topic, presentation or device mapping changed.

## Corrected activation

The unchanged repository candidate was packaged again against the exact
active-bootstrap identity, staged through the restricted deployment channel,
preflighted and activated with a controlled IP-Symcon restart.

Independent postflight verified:

- the expected candidate bootstrap identity;
- Reflection paths for both exporter classes inside the selected fileset;
- an owner source without a physical fileset path or runtime `require_once`;
- ready MQTT gateway, command adapter and publisher instances; and
- a complete one-to-one managed deployment/fileset inventory.

## Reconciliation regression

Non-publishing preparation was repeated for both existing exporter owners and
all three managed light entities. The new client-subscription validator
accepted every command topic under the configured site subscription.

The runs preserved the Registry byte-for-byte, including every command
adapter, command event, state event and command-variable identity. Command,
failure and publication counters remained unchanged. This demonstrates that
the runtime-namespace correction does not recreate unchanged owned resources.

## Bounded ingress observation

One `OFF` value was published through the existing IP-Symcon MQTT Client
Device while the authoritative light state was already off. The broker ingress
was observed, proving the configured subscription path. The local test method
also caused the originating MQTT Client Device action and its broker return to
produce two overlapping update dispatches. One dispatch reached the bounded
semaphore failure path; the other correctly timed out because strict
authoritative confirmation requires a fresh state update and an idempotent
already-off ControlLight action produced none.

The final physical and facade state remained off. No compensating command or
second test message was sent. This local double-delivery pattern is not the
normal external Home Assistant path and is retained as diagnostic evidence,
not classified as successful functional command confirmation.

## External Home Assistant state transition

After the site alarm was deactivated, a supervised Home Assistant `ON`/`OFF`
sequence completed through the normal external broker path. Exactly two new
commands were confirmed. Each command produced one command dispatch and one
authoritative state dispatch, with four corresponding runtime publications.
The failure counter did not change.

The final MQTT command, ControlLight facade and retained runtime state were all
off. The command and state events ran at the final transition, the ControlLight
runtime reported a new success and feedback timestamp, and neither its error
nor confirmation-timeout counter changed. This closes the functional gate that
the earlier local same-state observation intentionally left open.

## Retention closure

The deployment store reached its configured retention capacity during the
corrected activation. A subsequent ownership-exact retention gate identified
the superseded pre-owner-decoupling candidate as inactive and unreferenced by
both Symcon scripts and runtime files. The candidate deployment/fileset pair
was backed up with file-level hash verification and then removed leaf-first.

An independent postflight confirmed a consistent store below capacity, absence
of the candidate, unchanged active bootstrap and owner sources, availability of
the active and rollback deployments, and a healthy MQTT gateway. Exact
installation identities, hashes, timestamps and backup locations remain in
private machine-readable evidence.
