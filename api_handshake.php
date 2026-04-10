<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🤝 RELAY STATION: HANDSHAKE PROTOCOL
// Receives a signal that a foreign station has just followed this node.
// ==========================================

header('Content-Type: application/json');
date_default_timezone_set('UTC'); // Standard cosmic time

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid method. Use POST.']);
    exit;
}

$raw_payload = file_get_contents('php://input');
$data = json_decode($raw_payload, true);

if (!$data || empty($data['from_planet'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing from_planet coordinates.']);
    exit;
}

$from_planet = trim($data['from_planet']);

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';

try {
    // Ensure URL is valid
    $from_planet = rtrim($from_planet, '/');
    if (strpos($from_planet, 'http') !== 0) {
        $from_planet = 'https://' . $from_planet;
    }

    // Check if already followed back (if mutual, ignore this handshake)
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM following WHERE planet_url = :url");
    $stmt_check->execute([':url' => $from_planet]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Already mutuals.']);
        exit;
    }

    // Check if an unread alert from this planet already exists (prevents notification spam)
    $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE from_planet = :url AND is_read = 0");
    $stmt_alert_check->execute([':url' => $from_planet]);
    
    if ($stmt_alert_check->fetchColumn() == 0) {
        // Insert into alerts
        $stmt = $db->prepare("INSERT INTO alerts (type, from_planet, is_read) VALUES ('new_follower', :url, 0)");
        $stmt->execute([':url' => $from_planet]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Handshake received.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Core Memory database error.']);
}