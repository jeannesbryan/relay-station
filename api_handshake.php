<?php
// RELAY STATION: HANDSHAKE PROTOCOL (V3.0.6)
// Menerima sinyal bahwa stasiun asing baru saja mem-follow node ini.

header('Content-Type: application/json');
date_default_timezone_set('UTC');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid method.']);
    exit;
}

$raw_payload = file_get_contents('php://input');
$data = json_decode($raw_payload, true);

if (!$data || empty($data['from_planet'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing from_planet.']);
    exit;
}

$from_planet = trim($data['from_planet']);

$db_file = 'data/relay_core.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pastikan URL valid
    $from_planet = rtrim($from_planet, '/');
    if (strpos($from_planet, 'http') !== 0) {
        $from_planet = 'https://' . $from_planet;
    }

    // Cek apakah sudah difollow balik (jika sudah mutual, abaikan handshake ini)
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM following WHERE planet_url = :url");
    $stmt_check->execute([':url' => $from_planet]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Already mutuals.']);
        exit;
    }

    // Cek apakah alert dari planet ini sudah ada dan belum dibaca (mencegah spam notifikasi)
    $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE from_planet = :url AND is_read = 0");
    $stmt_alert_check->execute([':url' => $from_planet]);
    
    if ($stmt_alert_check->fetchColumn() == 0) {
        // Masukkan ke alert
        $stmt = $db->prepare("INSERT INTO alerts (type, from_planet, is_read) VALUES ('new_follower', :url, 0)");
        $stmt->execute([':url' => $from_planet]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Handshake received.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}