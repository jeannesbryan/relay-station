# 🛡️ RELAY STATION v8.0 — "AEGIS"

```
> INITIATING_SECURITY_OVERHAUL...
> PERIMETER: HARDENED
> ATTACK_SURFACE: REDUCED
> STATUS: STABLE
```

**Aegis** is not a feature release. It contains **no new capabilities, by design**.

Every change in this release answers one question: *what can an attacker reach, and what do they get when they get there?* The answer in v7.3 was "too much, and everything". The answer now is "nothing they have not authenticated for, and nothing they can spoof their way into".

This was a full security and architecture overhaul of a codebase that had grown organically across many feature additions. The perimeter is re-drawn, the trust model is explicit, and the parts that could not be made trustworthy were deleted rather than patched.

---

## 🔴 The headline changes

### 1. The attack surface was consolidated into one audited layer

Session policy, CSRF, client-identity resolution, outbound-URL validation and TLS-enforced HTTP were previously re-implemented inline in roughly ten places. They had drifted apart — and the copies that drifted were the ones with holes.

They now live in a single `core/security.php` bootstrap that every endpoint requires. A fix lands once and applies everywhere. That is the largest structural change in this release, and most of the fixes below exist because of it.

### 2. An endpoint that trusted everyone

One internal endpoint had no session check and no authentication of any kind. Any request that reached its URL was processed as though it came from the station commander.

That is closed. It requires an authenticated session and a valid request token before it reads a single byte of input.

### 3. State-changing actions are POST-only and token-protected

Several destructive operations — logging out, exporting the database, backing up the station, unfollowing a node, dismissing alerts — could be triggered by an ordinary **GET** request. A link, an image tag, or any page a logged-in commander happened to visit was enough to fire them.

Every state-changing action now requires **POST plus a per-session CSRF token**. Tokens are delivered two ways so nothing silently misses: a hidden field in every form, and a wrapper around `fetch()` that attaches the token to every same-origin POST automatically. The wrapper exists specifically so that a request added in the future is protected by default rather than by someone remembering.

### 4. Outbound requests are contained (SSRF)

Node URLs are supplied by operators *and* learned from other nodes, and were passed to the HTTP client largely unchecked. A node could be pointed at internal infrastructure, loopback services, or a cloud metadata endpoint.

Now: **HTTPS only, standard port only, publicly routable addresses only.** Loopback, private, link-local, carrier-grade NAT, multicast, reserved and metadata ranges are refused across both address families. The address that was validated is then **pinned** for the actual connection, so a hostname cannot be re-pointed between the check and the request.

### 5. TLS verification, everywhere

Five outbound requests had certificate verification switched off. With verification disabled, TLS provides no authentication at all — every guarantee built on top of it becomes decorative.

All of them now verify the certificate **and** the hostname, and redirects are not followed (a redirect is a way to make a validated request land somewhere that was never validated).

### 6. Client identity can no longer be spoofed

The login lockout and the rate limiters keyed off forwarding headers that **any client can set**. Rotating one header per request defeated both — the brute-force protection and the flood protection were, in practice, optional.

Identity now comes from the TCP peer address. Forwarding headers are honoured only when the request genuinely arrives from a published Cloudflare edge range, and the full IPv4 and IPv6 range lists are pinned in the code.

### 7. Sessions hardened

Session identifiers are rotated on login (closing session fixation), strict mode is enforced, cookies are `Secure`, `HttpOnly` and `SameSite=Strict`, and both idle and absolute session lifetimes apply.

### 8. The core database is no longer reachable over HTTP

The SQLite core memory holds the commander's password hash, the encrypted private key and the notification credentials. It sits under the web root — and shipped with **no access rules at all**.

Access rules now deny the database, its write-ahead-log sidecars, dotfiles (including version-control data), and the deployment-local directory. Directory listings are disabled.

### 9. Media uploads are inspected, not trusted

Uploads were accepted on the strength of a filename extension — something the sender chooses freely — and one path decoded attacker-supplied base64 into the web root with **no size limit and no content check**.

Uploads are now size-capped, must be genuine uploads, and are **typed from their own contents**. The extension written to disk is derived from the file's signature, never from the name the sender supplied.

### 10. Hardening pass

- Database errors are logged, never rendered. They were leaking paths and queries into responses.
- Behaviour-controlling values are checked against **strict allowlists** rather than stripped of markup. Removing tags is not validation — it accepts any string that remains.
- The rate limiter was rewritten. The previous version ran a full-table delete plus a write on **every inbound request**; it is now one row per bucket per window.
- An endpoint that had **no rate limit at all** — and could be used to write unbounded records and fire unlimited notifications — is now limited.
- Notification messages are escaped against markup injection, since some of their content originates from other nodes.

---

## ⛔ The automatic updater has been removed

**This is intentional, and it is the most consequential decision in this release.**

The old updater's job was to fetch a code payload and run it. It did so without ever establishing that the payload came from this project: certificate verification was disabled on the version check, the download had no host allowlist and no signature, extraction accepted paths that escaped the destination directory, and the scripts inside were then executed.

Any single weakness along that chain meant **remote code execution on every station that used it**. Together, they meant the updater was the softest target in the entire codebase — and the one with the highest payoff.

A supply chain you cannot verify is not a convenience. It is an attack surface.

**It could not be made safe by adding checks around the edges**, because nothing in the chain proved authenticity. Properly securing it requires release signing with a keypair held offline — real work, and not this release. So the automated path is **gone**, not guarded.

### Updating is now a deliberate, operator-run procedure

The console still performs a **read-only** version check and tells you when a newer release exists. It downloads nothing, extracts nothing, and executes nothing.

```
> 1. Backup core memory:  cp data/relay_core.sqlite ~/relay-backup.sqlite
> 2. Download the release and VERIFY its checksum or signature.
> 3. Replace application files.
>    DO NOT overwrite data/ media/ or installer/
> 4. Run migrations if the release ships one:  php installer/upgrade_db.php
> 5. Confirm the console reports the expected version.
```

**Security over convenience.** This is a slower update path, and it is the correct one.

---

## 🌌 Zero third-party dependencies

Relay Station described itself as sovereign and zero-dependency — while loading its own interface framework from a CDN and its typeface from Google Fonts on every page load.

That is now true rather than aspirational:

| Before | After |
|---|---|
| Stylesheet from jsDelivr | Vendored locally |
| Script from jsDelivr | Vendored locally |
| Typeface from Google Fonts | System monospace — nothing downloaded |
| GitHub API call on page load | Removed |

**A node now makes zero third-party requests.** Your visitors are beaconed to nobody.

This also fixed a real failure mode: the offline service worker precached those remote URLs, and its cache install **rejects entirely if any one of them fails**. A CDN or font outage therefore took the offline mode down with it — on a node built to keep working when the uplink dies. It now precaches local assets, so offline mode depends on nothing outside your server.

---

## 🐛 Bug fixes

**Tactical signals never worked on a freshly installed station.** The database schema's constraint permitted only two of the seven values the application actually writes. Five signal types — including sonar pulses, acknowledgements and purge commands — failed with a constraint violation on any station installed from that schema, and the failure surfaced as a bare error object. Fixed in the schema, with a migration that repairs existing databases by rebuilding the table and preserving all rows.

**Two installer-class flaws in the deployment-local scripts.** The installer could be reached remotely at any time and, on a successful run, would set the master passcode from an unauthenticated request and authenticate the caller as commander — whoever reached it first owned the station. It now refuses to run once a database exists. Its archive extraction also validated nothing, so an entry could escape the destination directory and overwrite any writable file; entries are now validated before anything is written, and a single hostile entry rejects the whole archive.

**Node removal from the public directory was unauthenticated.** Any host could remove any other node's listing. It now requires an admin token, and is **disabled rather than open** when no token is configured.

**Health-check reads mutated the database.** A directory lookup performed cleanup writes on every read, turning reads into write transactions and letting an unauthenticated caller drive unbounded delete work by polling.

**The outgoing-request path could be reached anonymously.** One endpoint combined unauthenticated database writes, unauthenticated file writes into the web root, and an unauthenticated outbound request to a caller-chosen host.

---

## 📋 Compatibility & migration

- **PHP:** 7.4, 8.0, 8.1, 8.2+ (unchanged — the new code is deliberately 7.4-compatible)
- **No new extensions required.**
- **Action required:** after applying, run `php installer/upgrade_db.php` once to repair the visibility constraint on existing databases. Back up `data/relay_core.sqlite` first.
- **If you used the one-click updater:** it is gone. Use the manual procedure above.
- **`khusus/` is now `installer/`.** The directory only ever held this node's installation kit; the name was a leftover. Update any deployment scripts that reference the old path.
- **Node delisting needs no secret.** Appearing in the public directory is opt-in. To opt out, turn the setting off: the node stops reporting in and the directory's 7-day sweeper drops it. Removing *other* nodes is a hub-operator action, and the admin token for it lives on the Lighthouse - never on a node.
- **The Lighthouse is a separate project.** The hub-side scripts (`api_register.php`, `api_directory.php`, the lighthouse setup and its admin config) are not part of this repository and are no longer bundled with a node installation.

---

## ✅ Verification

This release was developed against a live PHP 8.5 runtime rather than by reading alone, and ships with a test suite:

| Suite | Result |
|---|---|
| Session, CSRF, IP spoofing, SSRF, allowlists | **105 / 105** |
| HTTPS-shield header spoofing | **8 / 8** |
| Upload and media validation | **37 / 37** |
| Entry-point load (fatal-error guard) | **13 / 13** |
| Syntax lint, all files | clean |

The smoke test exists because of a mistake made during this work: an automated patch produced a call to a function that did not exist, in syntax that linted perfectly and would have fataled on every request to five endpoints. Syntax checking cannot see that class of error, so the test now loads every entry point and fails on any fatal.

Confirmed at zero in application code: disabled TLS verifications, database-error leaks, third-party asset references, and GET-based state changes.

---

## 🔭 What's next

v8.0 deliberately added nothing. With the perimeter closed, the next milestone can return to capability — on ground that is now stable enough to build on.

```
> AEGIS ONLINE.
> THE CONSTELLATION IS SECURE.
> TRANSMIT WHEN READY.
```

---

*Full historical changelog: [`version.json`](version.json) · Hardening guide: [`SECURITY.md`](SECURITY.md)*
