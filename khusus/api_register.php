<?php
require_once __DIR__ . '/../core/security.php';
ob_start(); // Taktik pembersihan output agar JSON murni
header("Access-Control-Allow-Origin: https://relay.emptyhub.my.id"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('UTC');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['status' => 'error', 'message' => 'Use POST.']); exit;
}

$user_ip = relay_client_ip();

$raw_payload = file_get_contents('php://input');
$signal = json_decode($raw_payload, true);

$action = $signal['action'] ?? 'ping';

if (!$signal || empty($signal['planet_url'])) {
    http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Incomplete data.']); exit;
}

if ($action === 'ping' && empty($signal['station_name'])) {
    http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Station name required.']); exit;
}

$planet_url = rtrim(trim($signal['planet_url']), '/');
$station_name = htmlspecialchars(trim($signal['station_name'] ?? ''));
$station_bio = htmlspecialchars(trim($signal['station_bio'] ?? ''));

try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/lighthouse.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 🛡️ [ INJEKSI ANTI TABRAKAN DATA (3 DETIK) ]
    $db->exec('PRAGMA busy_timeout = 3000;');

    // 🛡️ 1. THE RATE LIMITER ENGINE
    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (ip TEXT PRIMARY KEY, attempts INTEGER, last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("DELETE FROM rate_limits WHERE last_attempt < datetime('now', '-1 minute')"); // Bersihkan log > 1 menit

    $stmt_limit = $db->prepare("SELECT attempts FROM rate_limits WHERE ip = :ip");
    $stmt_limit->execute([':ip' => $user_ip]);
    $limit = $stmt_limit->fetch(PDO::FETCH_ASSOC);

    if ($limit && $limit['attempts'] >= 5) {
        ob_end_clean();
        http_response_code(429); // 429 Too Many Requests
        echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded. Tactical shield engaged.']); exit;
    }

    // Catat / Tambah jumlah tembakan dari IP ini
    $db->prepare("
        INSERT INTO rate_limits (ip, attempts, last_attempt) VALUES (:ip, 1, CURRENT_TIMESTAMP)
        ON CONFLICT(ip) DO UPDATE SET attempts = attempts + 1, last_attempt = CURRENT_TIMESTAMP
    ")->execute([':ip' => $user_ip]);


    // 🧹 2. THE SWEEPER (Hapus stasiun mati > 7 hari)
    $db->exec("DELETE FROM registry WHERE last_seen < datetime('now', '-7 days')");


    // 💥 3. THE KILL SIGNAL EXECUTION
    if ($action === 'kill') {
        $stmt_kill = $db->prepare("DELETE FROM registry WHERE planet_url = :url");
        $stmt_kill->execute([':url' => $planet_url]);
        
        ob_end_clean();
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Node vaporized from directory.', 'url' => $planet_url]);
        exit;
    }


    // 📡 4. NORMAL PING / REGISTRATION
    $stmt = $db->prepare("
        INSERT INTO registry (planet_url, station_name, station_bio, last_seen)
        VALUES (:url, :name, :bio, CURRENT_TIMESTAMP)
        ON CONFLICT(planet_url) DO UPDATE SET
            station_name = excluded.station_name,
            station_bio = excluded.station_bio,
            last_seen = CURRENT_TIMESTAMP
    ");

    $stmt->execute([':url' => $planet_url, ':name' => $station_name, ':bio' => $station_bio]);

    ob_end_clean();
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Docked.', 'url' => $planet_url]);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}