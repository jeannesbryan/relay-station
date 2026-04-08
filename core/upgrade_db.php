<?php
// ==========================================
// 🛡️ RELAY STATION: E2E MIGRATION SCRIPT
// ==========================================
// Skrip ini akan dieksekusi oleh updater.php dan langsung
// dihancurkan (unlink) sedetik setelah tugasnya selesai.

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db_upgrade = new PDO("sqlite:" . $db_file);
    $db_upgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Menanamkan slot 'public_key' ke dalam Core Memory (system_config)
    // INSERT OR IGNORE memastikan tidak akan ada error jika di-update dua kali
    $db_upgrade->exec("INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('public_key', '')");

} catch (Exception $e) {
    // Jika gagal, stasiun tetap berjalan tanpa merusak proses update
    error_log("[ MIGRATION ERROR ] " . $e->getMessage());
}