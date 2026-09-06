# One-Tile Network Preflight

## Scope and Gate

This gate authorized exactly one real request to the OSM Standard raster tile
service. It did not authorize provider fallback activation, live Symcon module
configuration, a WebHook change, publication, commit, prefetching or a second
request.

The request used the synthetic world tile `0/0/0`. It therefore disclosed no
OwnTracks selection, tracker identifier, WGS84 position, movement history or
private fit bounds. OSMF necessarily received the outbound public address, the
approved application User-Agent and the explicitly approved private Symcon
Connect origin as Referer.

The first process launch was rejected before DNS or network access because the
earlier gate did not explicitly name the sensitive Referer disclosure. The
request was issued only after that disclosure received a separate explicit
authorization.

## Fail-Closed Probe

`tests/real-network-tile-preflight.php` is a manual probe and is intentionally
excluded from the normal offline test suite. It:

- accepts contact and Referer values from process environment only;
- normalizes them through the case-study-local OSM provider policy;
- constructs a one-tile synthetic spatial allowlist;
- permits only `https://tile.openstreetmap.org/0/0/0.png`;
- resolves and pins one reviewed public address;
- verifies the TLS peer and hostname while retaining the original host;
- disables redirects and caps the response at 512 KiB and five seconds;
- requires HTTP 200, `image/png` and the PNG signature; and
- keeps the response in memory and emits sanitized evidence without persisting
  the tile body, peer address, DNS result, contact URL or Referer origin.

The probe must never be added to an ordinary CI, Composer or package-build
command because those paths are required to remain network-free.

## Sanitized Result

The single request completed successfully on 2026-09-01:

- request count: exactly one;
- response: HTTP 200, `image/png`;
- body size: 6,929 bytes, not persisted;
- elapsed transport time: 54 ms;
- DNS address pinned and public peer verified;
- TLS peer and host verification enabled;
- redirect: none;
- cacheable: yes;
- remaining origin cache lifetime: 450,320 seconds at observation time;
- ETag: present;
- Last-Modified: absent.

The cache lifetime is observation data, not a fixed contract. Runtime caching
continues to honor current origin headers and the already reviewed seven-day
fallback rule.

## Compatibility Finding

The successful request exposed a PHP 8.5 deprecation for `curl_close()`, which
has no effect for `CurlHandle` objects on supported PHP versions. The transport
now releases the handle with `unset()` on both success and failure paths. The
source and packaged copy were rebuilt together.

After that local-only correction, no second network request was issued. The
complete OwnTracks suite, distribution and fileset validation, PHPCS and
PHPStan passed offline. The resulting 36-file package identity is:

`ebc2ee0b681c68f32923f7fa4565e954b0ff7bbbc07843e5ba44ca0f21ba6249`

## Boundary After This Gate

The transport is proven against one synthetic tile, but provider fallback
remains disabled. A later gate must separately authorize private runtime
configuration and a bounded live activation. Existing OwnTracks instances,
archives, logging, selection variables, visualization objects and the current
static tile revision remain unchanged.
