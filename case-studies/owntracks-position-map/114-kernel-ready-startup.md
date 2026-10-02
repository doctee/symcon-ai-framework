# OwnTracks kernel-ready startup

During a full kernel start, OwnTracks may initialize before a configured
SharedLocation instance. Reading its descriptor at that point can fail even
with valid configuration. A later location initialization did not retry the map.

ApplyChanges now defers dependency reads until KR_READY. Create subscribes to
the documented kernel-start notification and registers a disabled recovery
timer. MessageSink schedules a single recovery five seconds after that
notification, leaving the synchronous notification before cross-module calls.
The callback disables its timer and runs normal ApplyChanges; invalid
configuration still uses the existing bounded diagnostics without endless retries.

This follows the existing Open-Meteo startup pattern. It changes no shared
helpers, source identities, location configuration, archives or tile policy.
Sender zero is the documented kernel message source, not an object mutation.
The runtime suite exercises pre-ready deferral, sender filtering, asynchronous
recovery, timer disarming and the same behavior in the generated package.

Official contracts: [kernel runlevel](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/programminformationen/ips-getkernelrunlevel/),
[MessageSink](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/messagesink/)
and [SDK messages](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php.md).
A full service-restart verification remains a separate operational test.
