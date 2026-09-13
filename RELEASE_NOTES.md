# RELAY STATION — CHANGELOG

Single source of truth for the release history. Newest first.

| Version | Codename | Type |
|---|---|---|
| [8.0.2](#802--aegis) | Aegis | UI correctness |
| [8.0.1](#801--aegis) | Aegis | Hotfix |
| [8.0.0](#800--aegis) | Aegis | Security & architecture overhaul |

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
- `installer/relay.zip` is rebuilt from this tree and is gitignored by design — ship it alongside the source.

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
