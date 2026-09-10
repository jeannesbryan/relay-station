<?php
// ==========================================
// 🛠️ THE LIGHTHOUSE PROTOCOL: DISPOSABLE SETUP
// Eksekusi skrip ini SEKALI SAJA di browser, lalu HAPUS berkasnya.
// ==========================================
header('Content-Type: text/plain');

$db_dir = __DIR__ . '/data';

// 1. Ciptakan Ruang Penyimpanan
if (!is_dir($db_dir)) {
    if (mkdir($db_dir, 0755, true)) {
        echo "[ OK ] Folder 'data/' berhasil diciptakan.\n";
    } else {
        die("[ GAGAL ] Tidak bisa membuat folder 'data/'. Cek hak akses (chmod).\n");
    }
}

try {
    // 2. Bangun Reaktor SQLite
    $db = new PDO('sqlite:' . $db_dir . '/lighthouse.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 3. Aktifkan Mode Ngebut (WAL)
    $db->exec('PRAGMA journal_mode = WAL;');
    echo "[ OK ] Mode WAL (Write-Ahead Logging) diaktifkan.\n";

    // 4. Cor Coran Tabel Utama
    $db->exec("CREATE TABLE IF NOT EXISTS registry (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        planet_url TEXT UNIQUE NOT NULL,
        station_name TEXT NOT NULL,
        station_bio TEXT,
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "[ OK ] Tabel 'registry' siap menerima pendaratan sinyal.\n\n";
    echo "[ 🚨 PERHATIAN ] Pembangunan selesai. Harap SEGERA HAPUS berkas setup_lighthouse.php ini dari peladen Anda demi keamanan!";

} catch (PDOException $e) {
    echo "[ ERROR ] Reaktor gagal dibangun: " . $e->getMessage();
}