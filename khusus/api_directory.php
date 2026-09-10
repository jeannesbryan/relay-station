<?php
ob_start(); // Pembersihan output
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('UTC');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db_path = __DIR__ . '/data/lighthouse.sqlite';

if (!file_exists($db_path)) {
    ob_end_clean();
    echo json_encode(['status' => 'success', 'count' => 0, 'nodes' => [], 'message' => 'Empty']);
    exit;
}

try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 🛡️ [ INJEKSI ANTI TABRAKAN DATA (3 DETIK) ]
    $db->exec('PRAGMA busy_timeout = 3000;');

    $db->exec("DELETE FROM registry WHERE last_seen < datetime('now', '-7 days')");

    $stmt = $db->query("SELECT planet_url, station_name, station_bio, last_seen FROM registry ORDER BY last_seen DESC");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'count' => count($nodes),
        'timestamp' => date('Y-m-d H:i:s') . ' UTC',
        'nodes' => $nodes
    ], JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}