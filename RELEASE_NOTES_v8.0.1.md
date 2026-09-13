# 🛠️ RELAY STATION v8.0.1 — "AEGIS" HOTFIX

```
> APPLYING_PATCH_8.0.1...
> SCOPE: CONTROL_ROOM_FORM_STRUCTURE
> DB_MIGRATION: NONE_REQUIRED
> STATUS: STABLE
```

**Hotfix.** One defect, fixed properly, plus the schema gap it exposed.

This release exists because a button did nothing. The interesting part is not that it did nothing — it is *why*, because the same mechanism was quietly corrupting two other buttons that nobody had reported yet.

---

## 🔴 The bug

### Symptom

In **THE CONTROL ROOM**, pressing `[ APPLY_CONFIGURATION ]` had no effect. No toast, no save, no reload. The station could not be reconfigured at all, which is how it surfaced: the operator was trying to switch on **THE LIGHTHOUSE PROTOCOL** and could not.

### Root cause: a form nested inside a form

The Escape Pod card rendered its two buttons inside their own `<form>` elements — and that card sits **inside** the Control Room's `<form id="control-room-form">`:

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

A `<form>` may not contain another `<form>`. That is not a style preference, it is a parser rule, and the browser resolves it silently:

1. The inner `<form>` **start tag is ignored** (a form element is already open).
2. Therefore the inner `</form>` has no inner form to close — it closes the **outer** one instead.
3. Everything after that point falls outside the form: **the Telegram settings, the passcode field, and the APPLY button itself.**

`control-room-form` was being closed in the middle of the Escape Pod card. The APPLY button ended up with **no form owner at all**, and a submit button that belongs to no form does nothing when clicked. The `submit` listener bound to `#control-room-form` never fired, because no submit event was ever dispatched.

### Collateral damage (found while diagnosing, not reported)

The same broken structure silently broke the Escape Pod buttons too:

| Button | What it was actually bound to | What clicking it did |
|---|---|---|
| `[ 📥 EXPORT CORE DATABASE ]` | `control-room-form` | Ran a **settings save** instead of exporting the database |
| `[ 📦 BACKUP WHOLE STATION (ZIP) ]` | an anonymous form nested inside the Control Room form | Its `submit` event **bubbled** into the Control Room handler, which cancelled the real submission and ran the settings save |

Both buttons therefore did the wrong thing — silently, with a success toast. Neither had been reported, because both produce no visible error.

A third, smaller defect: the save handler located the button to relabel with `querySelector('button[type="submit"]')`. `querySelector` walks the DOM and knows nothing about form ownership, so it resolved to the first submit button in the subtree — an Escape Pod button. During a save it relabelled the wrong button and left APPLY untouched.

---

## ✅ The fix

**1. The Escape Pod buttons no longer wrap themselves in forms.** They target standalone forms declared *after* the Control Room form, using the HTML5 `form=""` attribute:

```html
<button type="submit" form="escape-pod-db-form"  name="escape_pod"    value="1">[ 📥 EXPORT CORE DATABASE ]</button>
<button type="submit" form="escape-pod-zip-form" name="export_station" value="1">[ 📦 BACKUP WHOLE STATION (ZIP) ]</button>
```

This is declarative — no JavaScript was added — and it keeps the visual order of the modal intact while giving each button a form owner that is *not* an ancestor of the Control Room form. Each standalone form carries its own CSRF token.

**2. The APPLY button is back inside `control-room-form`**, together with the Telegram and passcode fields.

**3. The save handler targets the Apply button by id** (`#cr-apply-btn`) instead of by position.

---

## 🔬 Verification

Reproducing it mattered more than reading it, so both states were exercised in a real browser.

**Before (v8.0 markup, captured from the live node):**

| Check | Result |
|---|---|
| `APPLY` `closest('form')` | `null` — **no form owner** |
| `#control-room-form` children | ended at the Escape Pod card |
| Clicking `[ EXPORT CORE DATABASE ]` | its label flipped to `[ UPDATING_CORE_MEMORY... ]` — hijacked by the save handler |

**After (throwaway instance, real browser, real PHP, real SQLite):**

| Check | Result |
|---|---|
| `APPLY` form owner | `control-room-form` |
| `EXPORT` form owner | `escape-pod-db-form` |
| `BACKUP` form owner | `escape-pod-zip-form` |
| Telegram + passcode inputs | inside `control-room-form` |
| Nested forms in the repository | **0** |

**Functional end-to-end:** on the throwaway instance, `THE LIGHTHOUSE PROTOCOL` was switched on, the station name and bio were changed, and `[ APPLY_CONFIGURATION ]` was pressed. Result:

- `lighthouse_opt = 1` persisted — the exact operation that was impossible before
- `station_name` and `station_bio` persisted
- the registration payload was built and handed to the hub transport:
  `{"action":"ping","planet_url":"...","station_name":"...","station_bio":"..."}`
- zero errors in the server log
- both Escape Pod buttons now submit **only** their own form

---

## 🗄️ Schema audit (`installer/schema.sql`)

Checked by building a database from `schema.sql` and diffing it against the schema of a live, in-service node:

| Dimension | Result |
|---|---|
| Tables | identical |
| Columns (name / type / NOT NULL / default / PK) | **0 differences** |
| Indexes and UNIQUE constraints | **0 differences** |
| `transmissions.visibility` CHECK | identical, all 7 signal types present |

**One genuine gap was found and closed:** `relay_rate_limits` — the fixed-window rate limiter's counter table — was never declared in `schema.sql`. It is provisioned on demand by `relay_rate_limit()` (`CREATE TABLE IF NOT EXISTS`), so it is not a functional bug: no installation was ever broken by its absence. But a schema file that does not mention a table the application creates is a trap for anyone reading or restoring it. It is now declared, with a definition **byte-for-byte equivalent** to the runtime DDL, so the declaration and the self-provisioning path cannot diverge.

No migration is required. Existing databases keep their current table; the declaration is a no-op for them.

---

## 📦 Upgrading

Replace the application files. **Do not overwrite `data/` or `media/`.**

- **No database migration.** `installer/upgrade_db.php` does not need to be run.
- `sw.js` cache name is bumped to `relay-bunker-v8.0.1`. This is what retires the previous cache — without it, a browser holding the old service worker can keep serving stale assets.
- Source files changed: `console.php`, `version.json`, `sw.js`, `core/security.php` (version comment and outbound user-agent string), `installer/schema.sql`.
- `installer/relay.zip` is **rebuilt from this tree** (26 files, same manifest, same zip attributes as the v8.0 archive). It is a build artifact and is deliberately not tracked by git, so it will not appear in `git status` — ship it alongside the source.

---

## ⚠️ Still true, still unfixed

Nothing about v8.0's security posture changed here — this patch is structural, not a hardening pass. The two known open items stand:

- **`--s3-no-head` and a 2022-era rclone** on the reference deployment's backup path (operational, outside this repository).
- The service-worker **update beacon** still fetches `version.json` over the network; it remains read-only and never executes what it receives.
