<?php
// ==========================================
// 🛡️ RELAY STATION: CORE MEMORY ENGINE (V5)
// ==========================================
// Centralized Database Connection with WAL Mode

$db_file = __DIR__ . '/../data/relay_core.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    // 🚀 MENGAKTIFKAN WAL MODE (Write-Ahead Logging)
    // Mengubah jalan satu arah menjadi jalan tol multi-jalur.
    // Read dan Write kini bisa dieksekusi secara bersamaan tanpa mengunci database.
    $db->exec("PRAGMA journal_mode = WAL;");
    
    // ⚡ OPTIMASI TAMBAHAN (Synchronous Normal)
    // Standar bawaan SQLite adalah "FULL". Karena kita pakai WAL, 
    // menurunkannya ke "NORMAL" akan membuat penulisan pesan jauh lebih cepat 
    // dan sangat mengurangi beban I/O pada Shared Hosting.
    $db->exec("PRAGMA synchronous = NORMAL;");
    
} catch (PDOException $e) {
    // Jika gagal terhubung, langsung matikan eksekusi dengan pesan Terminal UI
    die("<h3 style='color: #ff0041; background: rgba(255,0,65,0.1); padding: 10px; border: 1px dashed #ff0041;'>[ CRITICAL ERROR ] Core Memory Offline: " . $e->getMessage() . "</h3>");
}