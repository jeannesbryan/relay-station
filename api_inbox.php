<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📡 RELAY STATION: ATMOSPHERIC SHIELD & INBOX
// Endpoint to receive incoming signals (POST) from foreign nodes. 
// Equipped with Anti-Spam and Anti-Spoofing Firewalls.
// ==========================================

header('Content-Type: application/json');
date_default_timezone_set('UTC'); // Standard cosmic time

// 1. [ SHIELD ] Only accept POST transmissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '[ SHIELD REFLECTED ] Signal rejected. Use POST protocol.']);
    exit;
}

// 2. [ DECRYPTION ] Intercept and open JSON capsule
$raw_payload = file_get_contents('php://input');
$signal = json_decode($raw_payload, true);

if (!$signal || empty($signal['content']) || empty($signal['author_alias']) || empty($signal['from_planet'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '[ CORRUPTED SIGNAL ] Incomplete data capsule.']);
    exit;
}

$content = trim($signal['content']);
$author = trim($signal['author_alias']);
$from_planet = trim($signal['from_planet']);
$visibility = $signal['visibility'] ?? 'public';
$expiry_date = $signal['expiry_date'] ?? null; 
$media_url = !empty($signal['media_url']) ? trim($signal['media_url']) : null;

// ==========================================
// 🛡️ [ DOUBLE SHIELD PROTOCOL: SONAR VALIDATION ]
// ==========================================
if ($visibility === 'sonar_pulse') {
    // Check for alphanumeric and max 15 characters
    if (!preg_match('/^[a-zA-Z0-9]{1,15}$/', $content)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '[ CORRUPTED SONAR ] Invalid tactical code. Alpha-numeric only (Max 15).']);
        exit;
    }
    // Force uppercase for the Morse Synthesizer
    $content = strtoupper($content);
}

// 🕵️ [ ANTI-SPOOFING ] Capture physical IP of the sender
$sender_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';

try {
    // ==========================================
    // 🛡️ [ FIREWALL ANTI-SPAM (STAR CHART CHECK) ]
    // ==========================================
    $normalized_from = rtrim($from_planet, '/');
    if (strpos($normalized_from, 'http') !== 0) {
        $normalized_from = 'https://' . $normalized_from;
    }

    $firewall_stmt = $db->prepare("SELECT COUNT(*) FROM following WHERE planet_url = :url");
    $firewall_stmt->execute([':url' => $normalized_from]);
    $is_allied = $firewall_stmt->fetchColumn();

    if (!$is_allied) {
        http_response_code(403); 
        echo json_encode([
            'status' => 'error', 
            'message' => '[ FIREWALL REFLECTED ] Access denied. Your node is not registered in our Star Chart.'
        ]);
        exit; // Reject landing, do not save to SQLite!
    }

    // 🛑 [ TRUE RATE LIMITING (BY IP / PLANET) ] Max 5 messages per minute
    if ($visibility === 'sonar_pulse') {
        $stmt_rl = $db->prepare("SELECT COUNT(*) FROM alerts WHERE type = 'sonar_pulse' AND from_planet = :url AND timestamp >= datetime('now', '-1 minute')");
        $stmt_rl->execute([':url' => $normalized_from]);
    } else {
        $stmt_rl = $db->prepare("SELECT COUNT(*) FROM transmissions WHERE sender_ip = :ip AND timestamp >= datetime('now', '-1 minute')");
        $stmt_rl->execute([':ip' => $sender_ip]);
    }
    
    if ($stmt_rl->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => '[ RATE LIMIT ] Max 5 transmissions per minute. Delay your broadcast.']);
        exit;
    }
    // ==========================================

    // Auto-Format Alias: sendername@domain.com/folder
    $parsed_url = parse_url($normalized_from);
    $domain_host = $parsed_url['host'] ?? 'UNKNOWN';
    $path = isset($parsed_url['path']) ? rtrim($parsed_url['path'], '/') : '';
    $formatted_author = htmlspecialchars(str_replace(' ', '', $author) . '@' . $domain_host . $path);

    // ==========================================
    // 🔀 [ SIGNAL ROUTER (DIVERGENT STORAGE) ]
    // ==========================================
    if ($visibility === 'sonar_pulse') {
        // 📡 [ SONAR PULSE ] Save as lightweight alert, NOT transmission
        $stmt_sonar = $db->prepare("INSERT INTO alerts (type, from_planet, payload, is_read) VALUES ('sonar_pulse', :url, :payload, 0)");
        $stmt_sonar->execute([
            ':url' => $normalized_from,
            ':payload' => $content
        ]);
    } else {
        // 📩 [ STANDARD TRANSMISSION ] Save to main chat logs
        $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, is_remote, author_alias, expiry_date, media_url, sender_ip) VALUES (:content, :visibility, 1, :author, :expiry, :media_url, :ip)");
        $stmt->execute([
            ':content' => htmlspecialchars($content),
            ':visibility' => htmlspecialchars($visibility),
            ':author' => $formatted_author,
            ':expiry' => $expiry_date ? htmlspecialchars($expiry_date) : null,
            ':media_url' => $media_url ? htmlspecialchars($media_url) : null,
            ':ip' => $sender_ip
        ]);

        // 🔔 [ NOTIFICATION TRIGGER: NEW DIRECT MESSAGE ]
        if ($visibility === 'direct') {
            // Check if an unread DM alert from this node already exists (Prevents alert spam)
            $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE type = 'new_dm' AND from_planet = :url AND is_read = 0");
            $stmt_alert_check->execute([':url' => $normalized_from]);
            
            if ($stmt_alert_check->fetchColumn() == 0) {
                $stmt_alert = $db->prepare("INSERT INTO alerts (type, from_planet, is_read) VALUES ('new_dm', :url, 0)");
                $stmt_alert->execute([':url' => $normalized_from]);
            }
        }
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'message' => '[ DOCKED ] Signal successfully landed at RELAY STATION.',
        'receipt_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '[ INTERNAL FAILURE ] Core Memory reactor malfunction.']);
}