<?php
// ==========================================
// 🛡️ RELAY STATION: MIGRATION SCRIPT (OTA UPDATES)
// ==========================================
// Skrip sekali pakai untuk melakukan upgrade skema database SQLite
// Dipanggil secara otomatis oleh core/updater.php saat proses pembaruan.

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db_upgrade = new PDO("sqlite:" . $db_file);
    $db_upgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // 📩 [ V5.6 ] ACK PROTOCOL
    // ==========================================
    try {
        $db_upgrade->exec("ALTER TABLE transmissions ADD COLUMN status TEXT DEFAULT 'sent'");
    } catch (Exception $e) {
        // Redam error diam-diam jika kolom sudah tercipta sebelumnya
        error_log("[ MIGRATION V5.6 INFO ] " . $e->getMessage());
    }

    // ==========================================
    // 🌐 [ V6.2 ] THE SOVEREIGN NOMADIC UPDATE
    // Menambahkan Secret Handshake Token & Nomadic Radar
    // ==========================================
    try {
        $db_upgrade->exec("ALTER TABLE following ADD COLUMN handshake_token TEXT DEFAULT NULL");
    } catch (Exception $e) {
        error_log("[ MIGRATION V6.2 INFO ] " . $e->getMessage());
    }

    try {
        $db_upgrade->exec("ALTER TABLE followers ADD COLUMN handshake_token TEXT DEFAULT NULL");
    } catch (Exception $e) {
        error_log("[ MIGRATION V6.2 INFO ] " . $e->getMessage());
    }

    try {
        $db_upgrade->exec("INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('local_planet_url', '')");
    } catch (Exception $e) {
        error_log("[ MIGRATION V6.2 INFO ] " . $e->getMessage());
    }

    // ==========================================
    // 👁️ [ V7.0 ] THE ORACLE UPDATE
    // Menyiapkan slot konfigurasi default untuk Telegram Webhooks
    // ==========================================
    try {
        $db_upgrade->exec("INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('telegram_enabled', '0')");
    } catch (Exception $e) {
        error_log("[ MIGRATION V7.0 INFO ] " . $e->getMessage());
    }

    try {
        $db_upgrade->exec("INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('telegram_bot_token', '')");
    } catch (Exception $e) {
        error_log("[ MIGRATION V7.0 INFO ] " . $e->getMessage());
    }

    try {
        $db_upgrade->exec("INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('telegram_chat_id', '')");
    } catch (Exception $e) {
        error_log("[ MIGRATION V7.0 INFO ] " . $e->getMessage());
    }

} catch (Exception $e) {
    // Fatal error jika database gagal diakses sama sekali
    error_log("[ MIGRATION FATAL ERROR ] " . $e->getMessage());
}
?>