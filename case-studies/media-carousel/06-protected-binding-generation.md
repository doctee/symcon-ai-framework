# Protected MediaCarousel binding generations

Updating an installed adapter is not target addition and must not rerun the
general initializer. MediaCarousel's UTF-8 transport and accepted one-deployment
schema transition require a new protected adapter/policy pair, not a runtime
module activation.

## Decision

The operator coordinator imports exact existing channel validators and adapter
snapshot/schema functions without executing their entry points. It accepts only
a hash-bound local plan and exact candidate channel/policy bytes. All installed
bindings are verified, including unrelated approval paths and hashes.

Channel changes are restricted to four MediaCarousel fields: two paths and two
hashes. Its private policy may add only the deployment-bound configuration
transition. The existing adapter verifies the transition against fresh instance
snapshots, including byte-exact preservation of every old property. The
coordinator invokes only read-only RPCs: no reload, configuration write, camera
request, service operation or module activation.

After channel-before-adapter locking, fresh baseline checks and protected
backups, the coordinator creates one immutable generation below the installed
MediaCarousel target directory. Only the channel-policy pointer is atomically
replaced. Old adapter/policy files remain untouched. Channel ACL identity is
preserved and verified. Caught post-publication failures restore the original
channel bytes only when the current hash still matches the candidate. External
drift fails closed and is never overwritten during recovery.

This replaces the initial three-file replacement proposal: one pointer switch
has fewer crash states and provides an atomic binding for the adapter/policy
pair. An interrupted operation leaves either the old pointer or the complete
new generation. Reusing an existing generation is deliberately rejected pending
independent recovery review; there is no blind retry. Generations and evidence
are retained, with a limit of 16 directories and a separate cleanup gate.

The self-extracting schema-package launcher is reused with a second fixed
`binding` profile. Each profile has an exact entry allowlist and fixed bounded
child entry point; no package may choose executable paths or command text.

## Repeatable deployment bootstrap

A separate `reviewed_baseline_reconciliation` plan accepts only a retained,
hash-bound review of the active package and every existing instance configuration.
It removes a consumed schema transition and preserves all other policy fields.
It must not silently learn a new baseline from whatever happens to be live.

Its optional `approvalBootstrap` composes the existing scope-bound approval
initializer, runner and target-bound reseal implementation. While holding the
same channel-before-adapter locks, it prepares a shadow channel and a complete
approval profile inside the new, unpublished generation. Initializer preflight,
installation and postflight run against that shadow. Source hashes, native
Windows qualification, protected secret identity and installed bindings must
agree before the single channel-pointer publication. The secret stays on the
target; it is never included in the transfer ZIP or result output.

The candidate channel may additionally change only the four MediaCarousel
approval-binding fields. Every other target and unknown field must remain
unchanged. Existing approval profiles cannot be overwritten through this path.
Old files, secrets and generations remain untouched. Failures before publication
leave the live channel unchanged; caught postflight failures use the same
hash-guarded byte-and-ACL rollback as ordinary binding updates. An interrupted
generation is retained for review, not blindly reused or deleted.

The fixed `repeatable` ZIP profile carries the reviewed baseline, qualification
evidence and pinned profile sources. Its self-extracting launcher executes one
administrative coordinator, not a full channel reinstall. This bootstrap neither
activates module code nor changes instance configuration, reloads Module Control
or restarts services. Runtime deployment remains a subsequent independently
checked operation through the installed deployment channel.

Native Windows tests exercise real initializer child processes and protected
filesystem operations in three cultures: successful staging/publication,
qualification rejection before publication and rollback after publication.
Extraction and launcher tests cover both binding package profiles. Test-fixture
qualification records are synthetic and must never be shipped as live evidence.

## Impact and verification

Existing channel and adapter APIs remain unchanged. Consumers are the local
operator launcher, protected channel target dispatch and later MediaCarousel
preflight. OwnTracks and other target records must compare unchanged, including
unknown extension fields. No restart boundary or remote verb is added.

Windows qualification covers real filesystem/ACL operations under three
cultures, preservation, publication, pre-publication failure, postflight rollback,
external drift and retained-generation rejection. Existing schema and HTTP
Unicode qualification remains separate. Actual target installation and later
runtime activation each require independent evidence; synthetic success alone
does not certify a live update.
