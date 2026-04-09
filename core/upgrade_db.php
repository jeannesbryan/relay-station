<?php
// ==========================================
// 🛡️ RELAY STATION: BUNKER MODE MIGRATION
// ==========================================
// Skrip ini akan dieksekusi oleh updater.php dan otomatis dihancurkan.

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db_upgrade = new PDO("sqlite:" . $db_file);
    $db_upgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Menanamkan tuas Bunker Mode ke dalam sistem (Default: 0 / Public)
    // INSERT OR IGNORE memastikan aman dieksekusi berkali-kali tanpa error
    $db_upgrade->exec("INSERT OR IGNORE INTO system_config (config_key, config_value) VALUES ('bunker_mode', '0')");

} catch (Exception $e) {
    // Failsafe: Jika gagal, catat error tanpa merusak sistem stasiun
    error_log("[ MIGRATION ERROR ] " . $e->getMessage());
}