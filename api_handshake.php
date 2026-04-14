<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🤝 RELAY STATION: HANDSHAKE PROTOCOL (V6.2)
// Receives a signal that a foreign station has just followed this node.
// Now captures Symmetric Handshake Tokens for Anti-Spoofing.
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
$handshake_token = $data['handshake_token'] ?? null; // [ NEW V6.2 ] Tangkap token rahasia

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';

try {
    // Ensure URL is valid
    $from_planet = rtrim($from_planet, '/');
    if (strpos($from_planet, 'http') !== 0) {
        $from_planet = 'https://' . $from_planet;
    }

    // 🗄️ [ V6.2 ] Simpan pengikut dan token mereka ke dalam memori inti.
    // Ini memastikan kita punya token mereka saat kita membalas transmisi.
    $stmt_check_f = $db->prepare("SELECT id FROM followers WHERE planet_url = :url");
    $stmt_check_f->execute([':url' => $from_planet]);
    if ($stmt_check_f->fetchColumn()) {
        $stmt_upd = $db->prepare("UPDATE followers SET handshake_token = :token WHERE planet_url = :url");
        $stmt_upd->execute([':token' => $handshake_token, ':url' => $from_planet]);
    } else {
        $stmt_ins = $db->prepare("INSERT INTO followers (planet_url, handshake_token) VALUES (:url, :token)");
        $stmt_ins->execute([':url' => $from_planet, ':token' => $handshake_token]);
    }

    // Check if already followed back (if mutual, ignore this handshake alert)
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM following WHERE planet_url = :url");
    $stmt_check->execute([':url' => $from_planet]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Already mutuals. Token secured.']);
        exit;
    }

    // Check if an unread alert from this planet already exists (prevents notification spam)
    $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE type = 'new_follower' AND from_planet = :url AND is_read = 0");
    $stmt_alert_check->execute([':url' => $from_planet]);
    
    if ($stmt_alert_check->fetchColumn() == 0) {
        // Insert into alerts (Sisipkan juga token di payload untuk rekam jejak)
        $stmt = $db->prepare("INSERT INTO alerts (type, from_planet, payload, is_read) VALUES ('new_follower', :url, :payload, 0)");
        $stmt->execute([':url' => $from_planet, ':payload' => $handshake_token]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Handshake received and token secured.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Core Memory database error.']);
}