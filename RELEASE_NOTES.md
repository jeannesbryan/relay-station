# RELAY STATION — CHANGELOG

Every release, newest first. Each entry states what changed, why, and how it was verified.

| Version | Codename | Type |
|---|---|---|
| [8.0.2](#802--aegis) | Aegis | UI polish |
| [8.0.1](#801--aegis) | Aegis | Hotfix |
| [8.0.0](#800--aegis) | Aegis | Security & architecture overhaul |

---

## 8.0.2 — AEGIS

```
> APPLYING_PATCH_8.0.2...
> SCOPE: CARD ACTIONS + PADDING UTILITIES
> DB_MIGRATION: NONE_REQUIRED
> STATUS: STABLE
```

Two UI complaints, both traced to a real cause rather than papered over.

### 🚫 No more "Roger That" on your own transmission

Acknowledging your own signal is meaningless, so the button is **no longer rendered** on posts you authored.

| Post kind | `is_remote` | `is_relay` | ROGER THAT |
|---|---|---|---|
| Authored by me | 0 | 0 | **hidden** |
| Incoming from another station | 1 | 0 | shown |
| Relayed by me | 0 | 1 | shown |

The relay case is deliberate: relaying somebody else's signal does not make it yours, so it keeps the button. A post I merely relayed is still their content.

**`ROGER_COUNT` stays visible on your own posts.** Other stations can legitimately acknowledge your signal, and that number is worth seeing — only the button that would let you acknowledge yourself is gone.

Applied to all three places the button is rendered:

- `console.php` — the initial server render
- `console.php` — the AJAX pagination endpoint (`?last_id=`)
- `bookmarks.php` — the Memory Vault

The behaviour is UI-level. The server does not reject a hand-crafted self-resonance POST, because the consequence is a meaningless row on your own timeline, not a compromise — and adding enforcement inside the transmission pipeline would carry more risk than the problem.

### 📏 The buttons were sitting 1px from the dashed rules

Not a matter of taste: **`terminal.css` never defined the `pt-*` and `pb-*` utilities.** The markup had been asking for them all along (`t-border-bottom pb-2`, and `border-top` + `pt-2` on the card footers), but only `p-*`, `py-*`, `px-*`, `m-*`, `mt-*`, `mb-*` and `my-*` existed. Every `pt-*` / `pb-*` in the codebase was a silent no-op.

Measured on the live node before the fix:

| Measurement | Before | After |
|---|---|---|
| `.t-bubble-meta` padding-bottom | 0px | 10px |
| footer padding-top | 0px | 10px |
| QUOTE / PURGE → dashed rule below | **1px** | **11px** |
| dashed rule above → ROGER THAT / BOOKMARK | **1px** | **11px** |

Declared `pt-1…5` and `pb-1…5`, mirroring the existing scales. Because the utilities were missing globally, this also repairs the same pattern on the **public hologram** (`index.php`), the **Memory Vault** (`bookmarks.php`) and the **direct chat headers** (`direct.php`) — all of which had the identical defect.

**`pt-0` and `pb-0` are deliberately not declared.** `.t-container-fluid` sets `padding: 20px`, and the only two places asking for `pt-0` are top-level containers, so honouring it would shift the navbar up by 20px on every page. Measured: the full scale moves the navbar from 35px to 15px; the scoped scale moves it 0px. That is a separate decision and is not part of this fix — see *Open questions* below.

### 🔬 Verification

Reproduced and verified in a real browser, against a throwaway instance carrying one post of each kind (own / incoming / relayed), with real PHP and real SQLite:

| Render path | Own post | Incoming | Relayed | Edge gaps |
|---|---|---|---|---|
| `console.php` initial render | button absent | present | present | 11px / 11px |
| `console.php` AJAX `?last_id=` | button absent | present | present | 11px / 11px |
| `bookmarks.php` Vault | button absent | present | present | 11px / 11px |

On the own post, `ROGER_COUNT` read **1** while the button was absent — proving the count survives the rule. All PHP files lint clean.

### 📦 Upgrading

- Replace the application files. **Do not overwrite `data/` or `media/`.**
- **No database migration.** `installer/upgrade_db.php` does not need to be run.
- `sw.js` cache name is bumped to `relay-bunker-v8.0.2`.
- ⚠️ `terminal.css` is served with a **4 hour Cloudflare edge TTL**. Purge `/relay/assets/terminal.css` (or the whole cache) after deploying, otherwise the spacing change will not appear until that TTL expires. Page output (`console.php`) is `no-store` and needs nothing.
- Source files changed: `assets/terminal.css`, `console.php`, `bookmarks.php`, `version.json`, `sw.js`, `core/security.php` (version comment + outbound user-agent).
- `installer/relay.zip` is rebuilt from this tree and is gitignored by design — ship it alongside the source.

---

## 8.0.1 — AEGIS

Hotfix. `[ APPLY_CONFIGURATION ]` in THE CONTROL ROOM did nothing at all.

The Control Room form wrapped two further forms belonging to THE ESCAPE POD, and a form element cannot contain another form. The parser drops the inner start tag, so its end tag closed the **outer** form mid-card — orphaning the Telegram settings, the passcode field and the APPLY button itself. A submit button with no form owner is simply inert.

The same defect silently hijacked both Escape Pod buttons: `[ EXPORT CORE DATABASE ]` ran a settings save instead of exporting, and `[ BACKUP WHOLE STATION ]` bubbled into the same handler.

Fixed by pointing the Escape Pod buttons at standalone forms through the HTML5 `form=""` attribute. Verified by reproducing the original defect against the v8.0 markup (APPLY had no form owner), then confirming the fix end to end on a throwaway instance.

Also closed a schema gap: `relay_rate_limits` was provisioned at runtime by `relay_rate_limit()` but never declared in `installer/schema.sql`.

Full detail: [`RELEASE_NOTES_v8.0.1.md`](RELEASE_NOTES_v8.0.1.md).

---

## 8.0.0 — AEGIS

Not a feature release. No new capabilities, by design — a security and architecture overhaul.

Every change answered one question: what can an unauthenticated stranger do to this node? The audit found that the transmitter endpoint took anonymous POSTs to write the database, drop arbitrary files into `media/` and drive the outbound HTTP client; that the OTA updater disabled TLS verification, took its payload from an unauthenticated host and unpacked a zip straight into the source tree; that the download endpoint would hand over the whole source tree including the database with a single GET; and that the brute-force lockout could be defeated by rotating a spoofed header.

Also removed all third-party origins (CDN assets and Google Fonts) and pinned the remainder locally, for data minimisation and offline capability.

Full detail: `RELEASE_NOTES_v8.0.md` (see git history — `git show df6afde:RELEASE_NOTES_v8.0.md`).

---

## Open questions

1. **`pt-0` on `.t-container-fluid`** — `console.php` and `bookmarks.php` both ask for `pt-0`, which has never taken effect. Honouring it would move the navbar up 20px. Left alone deliberately; say the word and it becomes a one-line change.
2. **Vault label for relayed posts** — `bookmarks.php` uses the looser `$is_me = (is_remote == 0)`, so a post I merely *relayed* is labelled `LOCAL_AUTHOR` in the Vault. `console.php` labels the same post `[ 🔁 RELAYED_BY_ME ] FROM:`. The two disagree. Not touched here, because it would change bookmark labelling — a visible change beyond this release's scope.
3. **Self-resonance is not server-enforced** — see above.
