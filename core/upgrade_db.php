<?php
// ==========================================
// 🛡️ RELAY STATION: MIGRATION SCRIPT (OTA UPDATES)
// V7.3 - The Relay Protocol Update
// ==========================================
// Skrip ini dirancang secara efisien untuk hanya menyuntikkan
// struktur tabel atau kolom yang belum ada di memori inti.

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
    // Menyuntikkan mesin estafet (Anti-Loop Shield & Indikator Relay)
    // ==========================================
    
    // 1. Injeksi penanda pesan estafet
    try {
        $db_upgrade->exec("ALTER TABLE transmissions ADD COLUMN is_relay INTEGER DEFAULT 0");
    } catch (Exception $e) {
        // Abaikan diam-diam jika kolom sudah tercipta sebelumnya
    }

    // 2. Injeksi DNA pelacak sumber asli (Mencegah Echo Chamber)
    try {
        $db_upgrade->exec("ALTER TABLE transmissions ADD COLUMN origin_id TEXT DEFAULT NULL");
    } catch (Exception $e) {
        // Abaikan diam-diam jika kolom sudah tercipta sebelumnya
    }

} catch (Exception $e) {
    // Fatal error jika SQLite terkunci atau rusak parah
    error_log("[ MIGRATION FATAL ERROR ] " . $e->getMessage());
}
?>