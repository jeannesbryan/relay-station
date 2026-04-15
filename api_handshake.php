<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🤝 RELAY STATION: HANDSHAKE PROTOCOL (V7.2)
// Receives a signal that a foreign station has just followed this node.
// Equipped with Symmetric Key Exchange Enforcer to prevent 401 Spoofing.
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

// [ V7.1 ] Sanitization
$from_planet = filter_var(trim($data['from_planet']), FILTER_SANITIZE_URL);
$handshake_token = trim($data['handshake_token'] ?? ''); 

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) & THE ORACLE ]
require_once 'core/db_connect.php';
require_once 'core/telegram.php'; 

try {
    // Ensure URL is valid
    $from_planet = rtrim($from_planet, '/');
    if (strpos($from_planet, 'http') !== 0) {
        $from_planet = 'https://' . $from_planet;
    }

    // Auto-generate alias for new follower
    $parsed_url = parse_url($from_planet);
    $domain_name = $parsed_url['host'] ?? 'UNKNOWN';
    $path = rtrim($parsed_url['path'] ?? '', '/');
    $alias = strip_tags($domain_name . $path);

    // 🗄️ [ V7.2 ] SIMPAN KE TABEL FOLLOWERS
    // Pastikan kita menyimpan kunci (token) ini agar nanti jika kita mem-follow balik,
    // kita tidak membuat kunci baru yang memicu paradoks.
    $stmt_check_f = $db->prepare("SELECT id FROM followers WHERE planet_url = :url");
    $stmt_check_f->execute([':url' => $from_planet]);
    if ($stmt_check_f->fetchColumn()) {
        $stmt_upd = $db->prepare("UPDATE followers SET handshake_token = :token WHERE planet_url = :url");
        $stmt_upd->execute([':token' => $handshake_token, ':url' => $from_planet]);
    } else {
        $stmt_ins = $db->prepare("INSERT INTO followers (planet_url, alias, handshake_token) VALUES (:url, :alias, :token)");
        $stmt_ins->execute([':url' => $from_planet, ':alias' => $alias, ':token' => $handshake_token]);
    }

    // 🔑 [ V7.2 ] SYMMETRIC KEY ENFORCER
    // Cek apakah kita SUDAH follow mereka (Mutual). Jika iya, PAKSA tabel 'following'
    // kita untuk menggunakan token baru ini agar komunikasi tidak terputus (Anti 401 Spoofing).
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM following WHERE planet_url = :url");
    $stmt_check->execute([':url' => $from_planet]);
    
    if ($stmt_check->fetchColumn() > 0) {
        // Enforce sinkronisasi kunci dua arah
        $stmt_upd_following = $db->prepare("UPDATE following SET handshake_token = :token WHERE planet_url = :url");
        $stmt_upd_following->execute([':token' => $handshake_token, ':url' => $from_planet]);
        
        echo json_encode(['status' => 'success', 'message' => 'Already mutuals. Symmetric Token enforced and secured.']);
        exit;
    }

    // 🔔 ALERTS: Check if an unread alert from this planet already exists
    $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE type = 'new_follower' AND from_planet = :url AND is_read = 0");
    $stmt_alert_check->execute([':url' => $from_planet]);
    
    if ($stmt_alert_check->fetchColumn() == 0) {
        // Insert into alerts (Sisipkan juga token di payload untuk rekam jejak)
        $stmt = $db->prepare("INSERT INTO alerts (type, from_planet, payload, is_read) VALUES ('new_follower', :url, :payload, 0)");
        $stmt->execute([':url' => $from_planet, ':payload' => $handshake_token]);

        // 👁️ [ V7.0 THE ORACLE: NEW FOLLOWER ALERT ]
        sendTelegramAlert("🤝 *NEW FOLLOWER DETECTED*\nStation `" . $from_planet . "` has locked onto your coordinates.\nLogin to follow back.");
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Handshake received and token secured.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Core Memory database error.']);
}