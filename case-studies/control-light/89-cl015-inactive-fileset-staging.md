# CL-015 inactive fileset staging

## Outcome

The exact 19-file CL-015-capable ControlLight fileset was staged successfully
through the restricted deployment channel. Server-side package verification,
inactive extraction and the subsequent deployment preflight passed.

The package was transferred as 47 bounded chunks and committed only after its
complete SHA-256 matched. The transfer itself took approximately three minutes.
No retry is required.

## Inactive boundary

The staging operation did not select the new fileset as a global runtime owner
and did not change the CL-015 wrapper source. The wrapper was not executed.
Independent readback confirmed the expected runtime, core and command-exception
hashes under the new immutable directory.

Facade, group endpoint and all three member values remained unchanged:

- STATE was off;
- retained reported brightness was 100 percent; and
- color temperature was zero.

No device or group command was sent.

## Interruption assessment

The user stopped the surrounding agent turn after the remote operation had
already completed. A fresh bounded status read reported the deployment as
`passed`, with the server-side preflight and runtime-mirror checks also passed.
Repeating the upload would therefore add no evidence and is deliberately
avoided.

## Remaining gate

The fileset is available but inactive. CL-015 still contains its exact legacy
source. A later separately approved command-free activation must revalidate the
source and three-member inventory, replace only the wrapper source, reconcile
twice, verify zero commands and run the sanitized 29-wrapper structural
regression.
