# RELAY STATION — CHANGELOG

Single source of truth for the release history. Newest first.

| Version | Codename | Type |
|---|---|---|
| [8.0.4](#804--aegis) | Aegis | The fleet reports itself |
| [8.0.3](#803--aegis) | Aegis | Resilience |
| [8.0.2](#802--aegis) | Aegis | UI correctness |
| [8.0.1](#801--aegis) | Aegis | Hotfix |
| [8.0.0](#800--aegis) | Aegis | Security & architecture overhaul |

---

## 8.0.4 — AEGIS

```
> APPLYING_PATCH_8.0.4...
> SCOPE: FLEET VERSION REPORTING (spans relay-station + relay-lighthouse)
> DB_MIGRATION: AUTOMATIC (lighthouse adds a column on demand)
> STATUS: STABLE
```

The lighthouse landing page no longer states a hand-typed release number. It
reports what the fleet is actually running.

### Why the number was wrong before

The page used to fetch the newest tag from `api.github.com`. That was removed
deliberately — it put a visitor's IP into a third-party request on a page whose
entire argument is that a node should beacon to nobody — and replaced with a
static string. The static string then went stale, which is what static strings
do: it still said `V8.0` two releases later.

Every option that reintroduces a third-party call has the same problem as the
original. So the number now comes from the one party that already knows: **the
lighthouse, which every station already reports to.**

### How it works

```
station registers  ──▶  api_register.php   stores it in the registry
                                              │
landing page  ◀──  api_directory.php  ◀───────┘
                    returns version per node
                    + fleet_version (the highest reported)
```

A station reports its version in the same registration payload it already
sends. The directory response gained `version` per node and a top-level
`fleet_version`. The page renders `fleet_version` and shows each station's own
version on its card, so a lagging node is visible at a glance.

Same origin throughout. **No third-party request, from anyone.** And nothing to
remember to edit when a release goes out.

### What it does *not* claim

`fleet_version` is the newest version any registered station has reported — it
is a statement about the fleet, not about what has been published. The page
labels it `Fleet Release` for that reason. When nothing has reported a version
it says `not reported`, not `scanning...` forever.

### The hub tolerates hubs that predate this

The hub has no migration script of its own — its setup file is a run-once
installer that is deleted after use, and re-running it would refuse. So the
write path **adds the column on demand** the first time it is needed, and the
read path falls back to the pre-8.0.4 query if the column is not there yet. No
hub operator has to do anything.

An older station that reports no version is still listed, and a registration
without a version never erases one another registration recorded.

### 🔒 Untrusted input

The version arrives on a public, unauthenticated endpoint, so it is treated as
such: length-capped at 32 characters, restricted to a version-string character
set, and rejected outright otherwise. A leading `v` is normalised away so the
directory cannot render `vv8.0.4`.

### 🔬 Verification

`relay-lighthouse` gained its first tests. `tests/version_test.php` runs against
**two** hubs — one with the new schema, one with the schema as it was before —
and covers validation, the self-healing migration and the fleet tally:
**19 passed, 0 failed.** CI added to both repositories.

Verified in a browser against a hub holding stations at different versions
(including one that reports nothing):

| Station | Card shows |
|---|---|
| reports `8.0.2` | `v8.0.2` + `[ ONLINE ]` |
| reports nothing | `[ ONLINE ]` — no badge |
| *(header)* | `Fleet Release: v8.0.2` |

And with an empty directory: `Fleet Release: not reported`.

### 🪤 Two mistakes worth recording

Both were caught by exercising the code rather than reading it, and both are
now pinned by the test:

1. The self-healing migration matched only SQLite's `no such column` wording.
   That is what **SELECT** reports; an **INSERT** says `table registry has no
   column named version`. The migration looked correct and silently never ran.
2. PDO's SQLite driver uses **native prepares**, so an unknown column is raised
   by `prepare()`, not `execute()`. A `try` block around `execute()` alone
   catches nothing — and looks entirely reasonable.

### 📦 Upgrading

- Replace the application files. **Do not overwrite `data/` or `media/`.**
- **No database migration to run.** `installer/upgrade_db.php` is not needed
  for this release. The lighthouse migrates itself.
- `sw.js` cache name is bumped to `relay-bunker-v8.0.4`.
- The lighthouse side ships separately: `api_register.php`, `api_directory.php`,
  `setup_lighthouse.php` and `index.html` in the `relay-lighthouse` repository.
- Source files changed here: `console.php`, `version.json`, `sw.js`,
  `core/security.php`.

### 📋 Limitation, stated plainly

The version is reported **when the station registers**, which happens when its
settings are saved. A station that is upgraded but whose settings are never
touched will keep showing its previous version until the next save. The page is
therefore honest about the last time each station checked in, not a live
inventory.

---

## 8.0.3 — AEGIS

```
> APPLYING_PATCH_8.0.3...
> SCOPE: PEER RESILIENCE, REGRESSION SUITE, CI
> DB_MIGRATION: REQUIRED (two columns on `following`)
> STATUS: STABLE
```

This release is about what happens when a peer goes away. Three fatal crashes
were reachable from the ordinary state of having a followed node that is
offline, and the Radar Sweep was deleting peers for being briefly unavailable.

### 1. 🔴 Three fatal 500s whenever any peer was offline

The outbound guard refuses **any host that does not resolve** — which is exactly
what an offline peer looks like. `relay_node_curl()` therefore returns `null`,
and in three places that `null` was handed straight to the curl functions:

| Location | Call | Effect |
|---|---|---|
| `core/radar_sweep.php:48` | `curl_getinfo(null)` | Sweep dies, **no node purged**, no summary |
| `core/transmitter.php:303` | `curl_multi_remove_handle($mh, null)` | Broadcast dies **after** the signal is already out |
| `core/transmitter.php:326` | `curl_setopt(null, ...)` | Laser Link dies before anything is sent |

The failure mode is the worst kind: an uncaught `TypeError`, an **empty response
body**, and nothing in the UI to explain it. One offline ally was enough to take
out Radar Sweep, Broadcast and Laser Link at once.

This was not a house-wide lapse. **Nine of the twelve** `relay_node_curl()` call
sites already guarded the null with `if (!$ch) { continue; }`. These three had
been missed.

**Reproduced before fixing**, on a throwaway instance with a dead peer in the
`following` table:

```
PHP Fatal error: Uncaught TypeError: curl_getinfo(): Argument #1 ($handle)
must be of type CurlHandle, null given in radar_sweep.php:48
→ HTTP 500, 0 bytes, both nodes still present
```

### 2. ⏳ Radar Sweep now gives a silent peer a grace period

A node used to be deleted the **first time a single 5-second ping** went
unanswered. A reboot, a laptop lid, a sleeping host or a brief upstream outage
cost the operator the relationship permanently — and the Star Chart silently
forgot a peer that was never gone. The `following` table did not even record
when a node was last heard from.

A node is now purged only after **3 consecutive unanswered sweeps**, and any
successful ping resets the counter:

| After | `failure_count` | Row |
|---|---|---|
| Sweep 1 silent | 1 | kept |
| Sweep 2 silent | 2 | kept |
| Sweep 3 silent | — | **purged** |
| any sweep answered | 0 | kept, `last_seen` updated |

The summary line is now honest about the three outcomes:

```
[ SWEEP COMPLETE ] Active: 2 | Silent (kept, grace 3): 1 | Purged: 0
```

Two columns are added to `following`: `last_seen` and `failure_count`.

### 3. 🧹 `pt-0` removed instead of armed

`console.php` and `bookmarks.php` carried `pt-0` on their top-level container.
`terminal.css` never defined it, so it was always a no-op — and **defining it
would have been the wrong move**: measured in a browser, honouring it pulls
those navbars **20px above** where `direct.php` sits, which uses the same
container without the class. The class was stray, so it is gone. The trap is
removed rather than armed.

### 4. ✅ Continuous integration, and two regression suites

The suite already had real assertions — **180 checks** — but nothing ran them.
That is how a button with no form owner and three curl-null crashes all reached
a live node.

`.github/workflows/ci.yml` now runs on every push, across **PHP 8.1, 8.2 and
8.3**:

- a **lint job** over every PHP file (separate from the tests, because "does not
  parse" and "parses but misbehaves" are different failures)
- a **test job** running all six suites
- a final check that the run **left no database behind** in `data/`

Two new suites target the exact bug classes this project has actually shipped:

| Suite | Guards | Proven to catch it |
|---|---|---|
| `tests/markup_test.php` | nested forms (the v8.0.1 cause), orphan tags, `form=""` references that point nowhere, **inert submit buttons** | Run against the v8.0.0 `console.php`, it fails: *nested form at console.php:1288* and *:1292* — the Escape Pod forms |
| `tests/radar_test.php` | unreachable peers, and the grace period | Run against the v8.0.2 `radar_sweep.php`, it fails on all four fatal checks |

A test that always passes proves nothing, so both were verified by running them
against the **old buggy revision** and confirming they fail.

`core/db_connect.php` gained a `RELAY_DB_FILE` override so a test that touches
the database can never write to the operator's live core memory. Unset — the
normal case — the path is unchanged. CI asserts this.

### 🔬 Verification

| Check | Result |
|---|---|
| `php -l` on 25 files | 0 failures |
| markup_test | 5 passed |
| security_test | 105 passed |
| shield_test | 8 passed |
| media_test | 37 passed |
| radar_test | 12 passed |
| smoke_test | 13 passed |
| **total** | **180 passed, 0 failed** |
| markup_test vs v8.0.0 markup | **fails** (2 nested forms) — the guard is real |
| radar_test vs v8.0.2 radar | **fails** (4 fatal checks) — the guard is real |

### 📦 Upgrading

- Replace the application files. **Do not overwrite `data/` or `media/`.**
- ⚠️ **Run `php installer/upgrade_db.php`** — it adds `last_seen` and
  `failure_count` to `following`. Idempotent, safe to re-run, no data loss.
- `sw.js` cache name is bumped to `relay-bunker-v8.0.3`.
- Source files changed: `core/radar_sweep.php`, `core/transmitter.php`,
  `core/db_connect.php`, `core/security.php`, `installer/schema.sql`,
  `installer/upgrade_db.php`, `console.php`, `bookmarks.php`, `version.json`,
  `sw.js`, plus two new test suites and the CI workflow.
- Artifacts: `relay-station-8.0.3.zip` is the one you deploy (see the layout
  note under 8.0.2).

### 📋 Still open (unchanged)

- **Store-and-forward outbox** — relay is still a single best-effort attempt;
  this release stops it from *crashing* when a peer is offline, but the signal is
  still not queued for later delivery.
- **The duplicated card markup** — five copies of the source-label logic and
  three copies of the card markup. That duplication is what produced both fixed
  bugs. A `core/render.php` remains the right fix, and is now cheaper to attempt
  because the suites above will hold the behaviour in place.
- **Self-resonance is not server-enforced** (UI-level only, by choice).

---

## 8.0.2 — AEGIS

```
> APPLYING_PATCH_8.0.2...
> SCOPE: CARD ACTIONS, PADDING UTILITIES, VAULT LABELS
> DB_MIGRATION: NONE_REQUIRED
> STATUS: STABLE
```

Three UI problems. Two were reported; the third turned up while checking the second, and had been quietly mislabelling content.

### 1. 🚫 No more "Roger That" on your own transmission

Acknowledging your own signal is meaningless, so the button is **no longer rendered** on posts you authored.

| Post kind | `is_remote` | `is_relay` | ROGER THAT | Label |
|---|---|---|---|---|
| Authored by me | 0 | 0 | **hidden** | `LOCAL_AUTHOR:` |
| Incoming from another station | 1 | 0 | shown | `INCOMING FROM:` |
| Relayed by me | 0 | 1 | shown | `[ 🔁 RELAYED_BY_ME ]` |
| Relayed by them | 1 | 1 | shown | `[ 🔁 RELAYED ] INCOMING FROM:` |

The relay cases are deliberate: relaying somebody else's signal does not make it yours, so those keep the button. A post I merely relayed is still their content.

**`ROGER_COUNT` stays visible on your own posts.** Other stations can legitimately acknowledge your signal, and that number is worth seeing — only the button that would let you acknowledge yourself is gone.

UI-level only. The server still accepts a hand-crafted self-resonance POST, because the consequence is a meaningless row on your own timeline rather than a compromise, and enforcement inside the transmission pipeline would carry more risk than the problem it solves.

### 2. 📏 The buttons were sitting 1px from the dashed rules

Not a matter of taste: **`terminal.css` never defined the `pt-*` and `pb-*` utilities.** The markup had been asking for them all along (`t-border-bottom pb-2`, and `border-top` + `pt-2` on the card footers), but only `p-*`, `py-*`, `px-*` and the margin scales existed. Every `pt-*` / `pb-*` in the codebase was a silent no-op.

Measured on the live node before the fix:

| Measurement | Before | After |
|---|---|---|
| `.t-bubble-meta` padding-bottom | 0px | 10px |
| card footer padding-top | 0px | 10px |
| QUOTE / PURGE → dashed rule below | **1px** | **11px** |
| dashed rule above → ROGER THAT / BOOKMARK | **1px** | **11px** |

Declared `pt-1…5` and `pb-1…5`, mirroring the existing scales. Because the failure was global, this also repairs the identical pattern in the **public hologram** (`index.php`), the **Memory Vault** (`bookmarks.php`) and the **direct chat headers** (`direct.php`).

### 3. 🏷️ The Vault credited me with other people's signal

Found while checking the rule above. `bookmarks.php` used a two-way label — `LOCAL_AUTHOR:` when `is_remote == 0`, otherwise `INCOMING FROM:` — while the Timeline used the four-way distinction above. The consequence: **a post I had merely relayed was labelled `LOCAL_AUTHOR` in the Vault**, crediting me with authorship of somebody else's transmission, while the Timeline correctly showed `[ 🔁 RELAYED_BY_ME ]` for the same post.

The Vault now mirrors the Timeline exactly, using the same four-way logic. The Vault query is `SELECT t.*, b.bookmarked_at`, so `is_relay` was already available — the label logic simply was not using it. `$is_me` was replaced by `$is_own_post` / `$is_my_relay`.

### 🔬 Verification

Throwaway instance on the STB with **one post of each of the four kinds**, all four bookmarked, real browser, real PHP, real SQLite.

Timeline (`console.php`) — both render paths, the initial render and the AJAX endpoint `?last_id=`:

| Post | Label | ROGER THAT | ROGER_COUNT | Edge gaps |
|---|---|---|---|---|
| mine (0/0) | `LOCAL_AUTHOR:` | **hidden** | **1** | 11px / 11px |
| incoming (1/0) | `INCOMING FROM:` | shown | 0 | 11px / 11px |
| my relay (0/1) | `[ 🔁 RELAYED_BY_ME ]` | shown | 0 | 11px / 11px |
| their relay (1/1) | `[ 🔁 RELAYED ] INCOMING FROM:` | shown | 0 | 11px / 11px |

Memory Vault (`bookmarks.php`) — full, untruncated output for all four cards:

| Card | Vault label | ROGER THAT | Edge gaps |
|---|---|---|---|
| mine | `LOCAL_AUTHOR: LOCAL_COMMAND` | **hidden** | 11px / 11px |
| incoming | `INCOMING FROM: REMOTE_GUY@…` | shown | 11px / 11px |
| my relay | `[ 🔁 RELAYED_BY_ME ] RELAYED_GUY@…` | shown | 11px / 11px |
| their relay | `[ 🔁 RELAYED ] INCOMING FROM: FOURTH_GUY@…` | shown | 11px / 11px |

On the own post, `ROGER_COUNT` read **1** while the button was absent — proving the count survives the rule. The raw response was inspected byte-wise (`onclick="toggleRogerThat(this, 3, '')"`, no stray backslash). All PHP files lint clean.

### 📦 Upgrading

- Replace the application files. **Do not overwrite `data/` or `media/`.**
- **No database migration.** `installer/upgrade_db.php` does not need to be run.
- `sw.js` cache name is bumped to `relay-bunker-v8.0.2`.
- ⚠️ `terminal.css` is served with a **4 hour Cloudflare edge TTL**. Purge `/relay/assets/terminal.css` (or the whole cache) after deploying, otherwise the spacing change will not appear until that TTL expires. Page output (`console.php`, `bookmarks.php`) is `no-store` and needs nothing.
- Source files changed: `assets/terminal.css`, `console.php`, `bookmarks.php`, `version.json`, `sw.js`, `core/security.php` (version comment + outbound user-agent).

### 📦 Release artifacts — two archives, and which one you want

`install.php` reads `relay.zip` and `schema.sql` from its **own directory**, extracts the inner archive into that same directory, builds the database from the schema, then self-destructs. So the outer archive has to carry all three:

```
relay-station-8.0.2.zip          ← the one you deploy
├── install.php                  the drop-pod installer
├── schema.sql                   core memory definition
└── relay.zip                    the application itself (26 files)
```

**Upload `relay-station-8.0.2.zip` to the target directory, extract it in place, then open `install.php` in a browser**, set the master passcode, and press INITIATE_DEPLOYMENT. When it finishes it deletes `install.php`, `schema.sql`, `relay.zip` **and the outer `relay-station-*.zip` itself**, leaving only the running station.

`relay.zip` on its own is the inner component and is useless without the other two — it is attached separately only so the contents can be inspected without unpacking twice.

A packaging bug was found and fixed while building this release: the self-destruct matched the outer wrapper with the single pattern `Relay-Installer*.zip`, which stopped matching as soon as the archive was shipped under the release naming. The wrapper — which contains the full application source — would have been left sitting in the webroot at a guessable URL. It now matches `relay-station-*.zip` as well, and only those prefixes, so unrelated archives an operator keeps in the same directory are never touched.

Both archives are gitignored by design (build artifacts, not source) — ship them alongside the source.

---

## 8.0.1 — AEGIS

```
> APPLYING_PATCH_8.0.1...
> SCOPE: CONTROL_ROOM_FORM_STRUCTURE
> DB_MIGRATION: NONE_REQUIRED
> STATUS: STABLE
```

**Hotfix.** One defect, fixed properly, plus the schema gap it exposed.

This release exists because a button did nothing. The interesting part is not that it did nothing — it is *why*, because the same mechanism was quietly corrupting two other buttons that nobody had reported yet.

### 🔴 The bug

**Symptom.** In **THE CONTROL ROOM**, pressing `[ APPLY_CONFIGURATION ]` had no effect. No toast, no save, no reload. The station could not be reconfigured at all, which is how it surfaced: the operator was trying to switch on **THE LIGHTHOUSE PROTOCOL** and could not.

**Root cause: a form nested inside a form.** The Escape Pod card rendered its two buttons inside their own form elements — and that card sits **inside** the Control Room's `control-room-form`:

```
<form id="control-room-form">
    ... station name, bio, bunker mode, lighthouse ...
    <div>  <!-- THE ESCAPE POD -->
        <form action="console.php">  ... export ...  </form>
        <form action="console.php">  ... backup ...  </form>
    </div>
    ... telegram, passcode ...
    <button type="submit">[ APPLY_CONFIGURATION ]</button>   <!-- ← orphaned -->
</form>
```

A form may not contain another form. That is not a style preference, it is a parser rule, and the browser resolves it silently:

1. The inner start tag is **ignored** (a form element is already open).
2. Therefore the inner end tag has no inner form to close — it closes the **outer** one instead.
3. Everything after that point falls outside the form: **the Telegram settings, the passcode field, and the APPLY button itself.**

`control-room-form` was being closed in the middle of the Escape Pod card. APPLY ended up with **no form owner at all**, and a submit button that belongs to no form does nothing when clicked. The `submit` listener bound to `#control-room-form` never fired, because no submit event was ever dispatched.

**Collateral damage, found while diagnosing.** The same broken structure silently broke the Escape Pod buttons too:

| Button | What it was actually bound to | What clicking it did |
|---|---|---|
| `[ 📥 EXPORT CORE DATABASE ]` | `control-room-form` | Ran a **settings save** instead of exporting the database |
| `[ 📦 BACKUP WHOLE STATION (ZIP) ]` | an anonymous form nested inside the Control Room form | Its `submit` event **bubbled** into the Control Room handler, which cancelled the real submission and ran the settings save |

Neither had been reported, because neither produced a visible error.

A third, smaller defect: the save handler located the button to relabel with `querySelector('button[type="submit"]')`. `querySelector` walks the DOM and knows nothing about form ownership, so it resolved to the first submit button in the subtree — an Escape Pod button. During a save it relabelled the wrong button and left APPLY untouched.

### ✅ The fix

**1.** The Escape Pod buttons no longer wrap themselves in forms. They target standalone forms declared *after* the Control Room form, using the HTML5 `form=""` attribute:

```html
<button type="submit" form="escape-pod-db-form"  name="escape_pod"    value="1">[ 📥 EXPORT CORE DATABASE ]</button>
<button type="submit" form="escape-pod-zip-form" name="export_station" value="1">[ 📦 BACKUP WHOLE STATION (ZIP) ]</button>
```

Declarative — no JavaScript added — and it keeps the visual order of the modal intact while giving each button a form owner that is *not* an ancestor of the Control Room form. Each standalone form carries its own CSRF token.

**2.** APPLY is back inside `control-room-form`, together with the Telegram and passcode fields.

**3.** The save handler targets the Apply button by id (`#cr-apply-btn`) instead of by position.

### 🔬 Verification

**Before (v8.0 markup, captured from the live node):**

| Check | Result |
|---|---|
| `APPLY.closest('form')` | `null` — **no form owner** |
| `#control-room-form` children | ended at the Escape Pod card |
| Clicking `[ EXPORT CORE DATABASE ]` | label flipped to `[ UPDATING_CORE_MEMORY... ]` — hijacked by the save handler |

**After (throwaway instance, real browser, real PHP, real SQLite):**

| Check | Result |
|---|---|
| `APPLY` form owner | `control-room-form` |
| `EXPORT` form owner | `escape-pod-db-form` |
| `BACKUP` form owner | `escape-pod-zip-form` |
| Telegram + passcode inputs | inside `control-room-form` |
| Nested forms in the repository | **0** |

**Functional end-to-end:** on the throwaway instance, THE LIGHTHOUSE PROTOCOL was switched on, the station name and bio were changed, and APPLY was pressed. Result: `lighthouse_opt = 1` persisted (the exact operation that was impossible before), `station_name` and `station_bio` persisted, the registration payload was built and handed to the hub transport, zero errors in the server log, and both Escape Pod buttons now submit **only** their own form. The hub POST was captured rather than sent, so no test station was registered in the public directory.

### 🗄️ Schema audit (`installer/schema.sql`)

Checked by building a database from `schema.sql` and diffing it against the schema of a live, in-service node:

| Dimension | Result |
|---|---|
| Tables | identical |
| Columns (name / type / NOT NULL / default / PK) | **0 differences** |
| Indexes and UNIQUE constraints | **0 differences** |
| `transmissions.visibility` CHECK | identical, all 7 signal types present |

**One genuine gap was found and closed:** `relay_rate_limits` — the fixed-window rate limiter's counter table — was never declared in `schema.sql`. It is provisioned on demand by `relay_rate_limit()` (`CREATE TABLE IF NOT EXISTS`), so no installation was ever broken by its absence. But a schema file that does not mention a table the application creates is a trap for anyone reading or restoring it. It is now declared, with a definition **byte-for-byte equivalent** to the runtime DDL, so the declaration and the self-provisioning path cannot diverge. No migration is required; the declaration is a no-op for existing databases.

### 📦 Upgrading (8.0.1)

- Replace the application files. **Do not overwrite `data/` or `media/`.**
- **No database migration.**
- `sw.js` cache name was bumped to `relay-bunker-v8.0.1`.

---

## 8.0.0 — AEGIS

Not a feature release. No new capabilities, by design — a security and architecture overhaul.

Every change answered one question: what can an unauthenticated stranger do to this node? The audit found that:

- the transmitter endpoint accepted anonymous POSTs that wrote to the database, dropped arbitrary files into `media/`, and drove the outbound HTTP client (SSRF);
- the OTA updater disabled TLS verification, took its payload from a host with no allow-list, and unpacked a zip straight into the source tree (a remote-code-execution chain);
- the download endpoint would hand over the entire source tree — including the database with the captain hash and the encrypted private key — from a single GET;
- the brute-force lockout and rate limits could be defeated by rotating a spoofed forwarding header.

The release introduced a shared `core/security.php` bootstrap so session handling, CSRF, client-IP resolution, SSRF guarding and curl hygiene exist in **one** place rather than being inlined at roughly ten call sites — the repetition was itself the reason these bugs kept recurring.

All third-party origins (CDN assets and Google Fonts) were removed and the remainder pinned locally, for data minimisation and offline capability.

Full detail is in git history: `git show df6afde:RELEASE_NOTES_v8.0.md`.

---

## Open questions

1. **`pt-0` on `.t-container-fluid`** — `console.php` and `bookmarks.php` both ask for `pt-0`, which has never taken effect. `.t-container-fluid` sets `padding: 20px`, so honouring it would move the navbar up 20px on every page. Measured: the full utility scale shifts the navbar 35px → 15px, the scoped scale shipped in 8.0.2 shifts it 0px. Left alone deliberately; a one-line change if wanted.
2. **Self-resonance is not server-enforced** — 8.0.2 hides the button, but a hand-crafted POST would still insert the row. Chosen deliberately (see 8.0.2 above).
