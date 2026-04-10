<?php
// ==========================================
// 🛡️ RELAY STATION: V5.1 MIGRATION SCRIPT
// ==========================================
// Skrip ini akan dieksekusi oleh updater.php dan langsung
// dihancurkan (unlink) setelah memodifikasi struktur memori.

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db_upgrade = new PDO("sqlite:" . $db_file);
    $db_upgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 📡 [ SONAR PULSE PROTOCOL ]
    // Menambahkan kolom 'payload' ke tabel 'alerts' untuk menyimpan Short Code
    // SQLite akan melempar error jika kolom sudah ada, dan catch block akan mengamankannya.
    $db_upgrade->exec("ALTER TABLE alerts ADD COLUMN payload TEXT DEFAULT NULL");

} catch (Exception $e) {
    // Jika gagal (biasanya karena kolom sudah ada), stasiun tetap berjalan aman.
    error_log("[ MIGRATION V5.1 ERROR / INFO ] " . $e->getMessage());
}
?>