<?php
// ==========================================
// 🛡️ RELAY STATION: MIGRATION SCRIPT (ACK PROTOCOL)
// ==========================================
// Skrip sekali pakai untuk menambahkan fitur Read Receipts (Roger That)

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db_upgrade = new PDO("sqlite:" . $db_file);
    $db_upgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 📩 [ ACK PROTOCOL ]
    // Menambahkan kolom 'status' dengan nilai default 'sent'.
    // Nantinya akan berubah menjadi 'read' saat peluru balasan ACK diterima.
    $db_upgrade->exec("ALTER TABLE transmissions ADD COLUMN status TEXT DEFAULT 'sent'");

} catch (Exception $e) {
    // Redam error diam-diam jika kolom sudah tercipta sebelumnya
    error_log("[ MIGRATION ACK INFO ] " . $e->getMessage());
}
?>