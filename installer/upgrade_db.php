<?php
// ==========================================
// 🛡️ RELAY STATION: MIGRATION SCRIPT (OTA UPDATES)
// V8.0 - Security & Architecture Overhaul
// ==========================================
// Skrip ini dirancang secara efisien untuk hanya menyuntikkan
// struktur tabel atau kolom yang belum ada di memori inti.
//
// NOTE: as of v8.0 this file is no longer executed automatically. The OTA
// updater that used to `include` everything matching core/upgrade_db*.php has
// been removed (see core/updater.php). Run this manually after applying an
// update:  php installer/upgrade_db.php

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db_upgrade = new PDO("sqlite:" . $db_file);
    $db_upgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // ⚡ [ V7.2 ] THE SOCIAL SIGNAL
    // Memastikan Brankas Memori & Radar Resonansi tersedia
    // ==========================================
    $db_upgrade->exec("CREATE TABLE IF NOT EXISTS bookmarks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transmission_id INTEGER NOT NULL,
        bookmarked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(transmission_id)
    )");

    $db_upgrade->exec("CREATE TABLE IF NOT EXISTS signal_resonance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        reactor_url TEXT NOT NULL,
        reactor_alias TEXT NOT NULL,
        resonance_type TEXT DEFAULT 'roger',
        reacted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(post_id, reactor_url)
    )");

    // ==========================================
    // 🔁 [ V7.3 ] THE RELAY PROTOCOL
    // ==========================================
    try {
        $db_upgrade->exec("ALTER TABLE transmissions ADD COLUMN is_relay INTEGER DEFAULT 0");
    } catch (Exception $e) {
        // Abaikan diam-diam jika kolom sudah tercipta sebelumnya
    }

    try {
        $db_upgrade->exec("ALTER TABLE transmissions ADD COLUMN origin_id TEXT DEFAULT NULL");
    } catch (Exception $e) {
        // Abaikan diam-diam jika kolom sudah tercipta sebelumnya
    }

    // ==========================================
    // 🔐 [ V8.0 ] VISIBILITY CONSTRAINT REPAIR
    // ==========================================
    // schema.sql declared CHECK(visibility IN ('public','direct')) while
    // core/transmitter.php writes five further values. On a database created
    // from that schema, those five signal types failed with an Integrity
    // constraint violation - a silent, long-standing bug.
    //
    // SQLite cannot alter a CHECK constraint in place, so the table has to be
    // rebuilt. This runs only when the old constraint is actually present, and
    // copies whatever columns exist so it is safe on both old and new
    // installations.
    $needs_repair = false;
    $create_sql = '';
    foreach ($db_upgrade->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='transmissions'") as $row) {
        $create_sql = (string) $row['sql'];
    }
    if ($create_sql !== '' && strpos($create_sql, 'sonar_pulse') === false
        && strpos($create_sql, "CHECK") !== false) {
        $needs_repair = true;
    }

    if ($needs_repair) {
        // Preserve only the columns that actually exist, so this works whether
        // or not the V7.3 columns above were just added.
        $existing = [];
        foreach ($db_upgrade->query("PRAGMA table_info(transmissions)") as $col) {
            $existing[$col['name']] = true;
        }

        $db_upgrade->exec("BEGIN TRANSACTION");

        $db_upgrade->exec("CREATE TABLE transmissions_v8 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            visibility TEXT CHECK(visibility IN ('public', 'direct', 'sonar_pulse', 'ack_receipt', 'scorched_earth', 'global_purge', 'resonance')) DEFAULT 'public',
            target_planet TEXT,
            is_remote INTEGER DEFAULT 0,
            is_relay INTEGER DEFAULT 0,
            origin_id TEXT DEFAULT NULL,
            author_alias TEXT,
            expiry_date DATETIME,
            media_url TEXT DEFAULT NULL,
            sender_ip TEXT DEFAULT NULL,
            status TEXT DEFAULT 'sent',
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Copy the intersection of old columns and the new definition.
        $target_cols = ['id','content','visibility','target_planet','is_remote','is_relay',
                        'origin_id','author_alias','expiry_date','media_url','sender_ip',
                        'status','timestamp'];
        $copy = [];
        foreach ($target_cols as $c) {
            if (isset($existing[$c])) {
                $copy[] = $c;
            }
        }
        if ($copy) {
            $list = implode(', ', $copy);
            $db_upgrade->exec("INSERT INTO transmissions_v8 ($list) SELECT $list FROM transmissions");
        }

        $db_upgrade->exec("DROP TABLE transmissions");
        $db_upgrade->exec("ALTER TABLE transmissions_v8 RENAME TO transmissions");

        $db_upgrade->exec("COMMIT");

        error_log('[RELAY] v8.0 migration: transmissions visibility constraint repaired');
    }

} catch (Exception $e) {
    // Fatal error jika SQLite terkunci atau rusak parah
    error_log("[ MIGRATION FATAL ERROR ] " . $e->getMessage());
}
