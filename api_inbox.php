<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📡 RELAY STATION: ATMOSPHERIC SHIELD & INBOX (v7.2)
// Endpoint to receive incoming signals (POST) from foreign nodes. 
// Equipped with Anti-Spam, Anti-Spoofing, Re-Sync, Scorched Earth Firewalls,
// The Oracle (Telegram Webhooks), Advanced HTML Sanitization, and Resonance Protocol.
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

// ==========================================
// 🌐 [ V6.2 THE NOMADIC RE-SYNC RECEIVER ]
// ==========================================
// If this is a special nomadic pulse, intercept it before regular processing
if (isset($signal['action']) && $signal['action'] === 'resync') {
    require_once 'core/db_connect.php';
    try {
        $old_url = rtrim($signal['old_url'], '/');
        if (strpos($old_url, 'http') !== 0) $old_url = 'https://' . $old_url;
        
        $new_url = filter_var(trim($signal['new_url']), FILTER_SANITIZE_URL);
        $new_url = rtrim($new_url, '/');
        if (strpos($new_url, 'http') !== 0) $new_url = 'https://' . $new_url;
        
        $handshake_token = trim($signal['handshake_token'] ?? '');

        $stmt_check = $db->prepare("SELECT handshake_token FROM following WHERE planet_url = :url");
        $stmt_check->execute([':url' => $old_url]);
        $valid_token = $stmt_check->fetchColumn();

        if ($valid_token && $valid_token === $handshake_token) {
            $stmt_upd = $db->prepare("UPDATE following SET planet_url = :new_url WHERE planet_url = :old_url");
            $stmt_upd->execute([':new_url' => $new_url, ':old_url' => $old_url]);
            
            $stmt_upd_f = $db->prepare("UPDATE followers SET planet_url = :new_url WHERE planet_url = :old_url");
            $stmt_upd_f->execute([':new_url' => $new_url, ':old_url' => $old_url]);

            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => '[ RE-SYNC APPROVED ] Coordinates updated.']);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => '[ RE-SYNC REJECTED ] Invalid cryptographic token.']);
        }
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Core Memory Malfunction.']);
        exit;
    }
}

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';
require_once 'core/telegram.php'; // [ V7.0 THE ORACLE ]

$sender_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

try {
    // ==========================================
    // 🛡️ [ FIREWALL: RATE LIMITER (ANTI-SPAM) ]
    // Limit: 5 signals per 60 seconds per IP
    // ==========================================
    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (ip_address TEXT, timestamp DATETIME)");
    $db->exec("DELETE FROM rate_limits WHERE timestamp <= datetime('now', '-60 seconds')");
    
    $stmt_rate = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_address = :ip");
    $stmt_rate->execute([':ip' => $sender_ip]);
    if ($stmt_rate->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => '[ SHIELD REFLECTED ] Transmission rate limit exceeded.']);
        exit;
    }
    $db->prepare("INSERT INTO rate_limits (ip_address, timestamp) VALUES (:ip, datetime('now'))")->execute([':ip' => $sender_ip]);

    // ==========================================
    // 🛡️ [ PAYLOAD SANITIZATION & EXTRACTION ]
    // ==========================================
    $from_planet = filter_var(trim($signal['from_planet'] ?? ''), FILTER_SANITIZE_URL);
    $handshake_token = trim($signal['handshake_token'] ?? '');
    $visibility = strip_tags(trim($signal['visibility'] ?? 'public'));
    
    if (empty($from_planet)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '[ SHIELD REFLECTED ] Missing origin coordinates.']);
        exit;
    }

    // ==========================================
    // 🛡️ [ FIREWALL: STAR CHART & ANTI-SPOOFING ]
    // ==========================================
    $normalized_from = rtrim($from_planet, '/');
    if (strpos($normalized_from, 'http') !== 0) {
        $normalized_from = 'https://' . $normalized_from;
    }

    $firewall_stmt = $db->prepare("SELECT handshake_token FROM following WHERE planet_url = :url");
    $firewall_stmt->execute([':url' => $normalized_from]);
    $allied_node = $firewall_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$allied_node) {
        http_response_code(403); 
        echo json_encode([
            'status' => 'error', 
            'message' => '[ FIREWALL REFLECTED ] Access denied. Your node is not registered in our Star Chart.'
        ]);
        exit; 
    }

    // 🛡️ [ V7.2 ] STRICT ANTI-SPOOFING VERIFICATION (RESTORED - PARADOX FIXED)
    if (!empty($allied_node['handshake_token']) && $allied_node['handshake_token'] !== $handshake_token) {
        http_response_code(401); 
        echo json_encode([
            'status' => 'error', 
            'message' => '[ SPOOFING DETECTED ] Invalid Handshake Token. Intruder alert triggered. Signal destroyed.'
        ]);
        exit;
    }

    // ==========================================
    // ⚡ [ V7.2 ] SIGNAL RESONANCE RECEIVER (ROGER THAT)
    // ==========================================
    if (isset($signal['action']) && $signal['action'] === 'resonance') {
        $post_id = (int)($signal['post_id'] ?? 0);
        $reactor_alias = strip_tags(trim($signal['reactor'] ?? 'UNKNOWN'));
        $type = strip_tags(trim($signal['type'] ?? 'roger'));

        if ($post_id > 0) {
            // INSERT OR IGNORE mitigates Spam Pings at the database level
            $stmt_res = $db->prepare("INSERT OR IGNORE INTO signal_resonance (post_id, reactor_url, reactor_alias, resonance_type) VALUES (:pid, :url, :alias, :type)");
            $stmt_res->execute([
                ':pid' => $post_id,
                ':url' => $normalized_from,
                ':alias' => $reactor_alias,
                ':type' => $type
            ]);
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '[ RESONANCE ACKNOWLEDGED ]']);
        exit;
    }

    // ==========================================
    // 🔥 [ SCORCHED EARTH & GLOBAL PURGE PROTOCOLS ]
    // ==========================================
    $content = strip_tags(trim($signal['content'] ?? ''));

    if ($visibility === 'scorched_earth') {
        $stmt_del = $db->prepare("DELETE FROM transmissions WHERE is_remote = 1 AND target_planet = :my_url AND author_alias = :author");
        $stmt_del->execute([':my_url' => $my_planet_url ?? '', ':author' => strip_tags($signal['author_alias'] ?? '')]);
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '[ SCORCHED EARTH EXECUTED ]']);
        exit;
    }

    if ($visibility === 'global_purge') {
        $stmt_purge = $db->prepare("DELETE FROM transmissions WHERE content = :content AND is_remote = 1");
        $stmt_purge->execute([':content' => $content]);
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '[ GLOBAL PURGE EXECUTED ]']);
        exit;
    }

    // ==========================================
    // 📡 [ TACTICAL SONAR PULSE ]
    // ==========================================
    if ($visibility === 'sonar_pulse') {
        $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE type = 'sonar_pulse' AND from_planet = :url AND is_read = 0");
        $stmt_alert_check->execute([':url' => $normalized_from]);
        
        if ($stmt_alert_check->fetchColumn() == 0) {
            $stmt = $db->prepare("INSERT INTO alerts (type, from_planet, payload, is_read) VALUES ('sonar_pulse', :url, :payload, 0)");
            $stmt->execute([':url' => $normalized_from, ':payload' => $content]);

            // 👁️ [ V7.0 THE ORACLE: SONAR ALERT ]
            sendTelegramAlert("📡 *TACTICAL SONAR DETECTED*\nIncoming ping from: `" . $normalized_from . "`\nPayload: `" . $content . "`");
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '[ SONAR DOCKED ]']);
        exit;
    }

    // ==========================================
    // ✓ [ THE ACK PROTOCOL (READ RECEIPT) ]
    // ==========================================
    if ($visibility === 'ack_receipt') {
        $stmt_ack = $db->prepare("UPDATE transmissions SET status = 'read' WHERE content = :content AND is_remote = 0");
        $stmt_ack->execute([':content' => $content]);
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '[ ACKNOWLEDGED ]']);
        exit;
    }

    // ==========================================
    // 📥 [ STANDARD TRANSMISSION PROCESSING ]
    // ==========================================
    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '[ SHIELD REFLECTED ] Empty payload.']);
        exit;
    }

    $author = strip_tags(trim($signal['author_alias'] ?? 'UNKNOWN'));
    $formatted_author = $author . '@' . parse_url($normalized_from, PHP_URL_HOST);
    $expiry_date = $signal['expiry_date'] ?? null;
    
    $media_url = null;
    if (!empty($signal['media_url'])) {
        // Check if media is an array (JSON) or string
        if (is_array($signal['media_url'])) {
            $sanitized_media = array_map('filter_var', $signal['media_url'], array_fill(0, count($signal['media_url']), FILTER_SANITIZE_URL));
            $media_url = json_encode($sanitized_media, JSON_UNESCAPED_SLASHES);
        } else {
            $media_url = filter_var($signal['media_url'], FILTER_SANITIZE_URL);
        }
    }

    // Cek duplikasi sinyal
    $stmt_dup = $db->prepare("SELECT COUNT(*) FROM transmissions WHERE content = :content AND author_alias = :author AND timestamp >= datetime('now', '-5 minutes')");
    $stmt_dup->execute([':content' => $content, ':author' => $formatted_author]);
    if ($stmt_dup->fetchColumn() > 0) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '[ DUPLICATE REFLECTED ] Signal already exists.']);
        exit;
    }

    // 🗄️ Simpan ke Core Memory
    $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, is_remote, author_alias, expiry_date, media_url, sender_ip) VALUES (:content, :visibility, 1, :author, :expiry, :media_url, :ip)");
    $stmt->execute([
        ':content' => $content,
        ':visibility' => $visibility,
        ':author' => $formatted_author,
        ':expiry' => $expiry_date ? strip_tags($expiry_date) : null,
        ':media_url' => $media_url, 
        ':ip' => $sender_ip
    ]);

    // 🔔 [ NOTIFICATION TRIGGER: NEW DIRECT MESSAGE ]
    if ($visibility === 'direct') {
        $stmt_alert_check = $db->prepare("SELECT COUNT(*) FROM alerts WHERE type = 'new_dm' AND from_planet = :url AND is_read = 0");
        $stmt_alert_check->execute([':url' => $normalized_from]);
        
        if ($stmt_alert_check->fetchColumn() == 0) {
            $stmt_alert = $db->prepare("INSERT INTO alerts (type, from_planet, is_read) VALUES ('new_dm', :url, 0)");
            $stmt_alert->execute([':url' => $normalized_from]);

            // 👁️ [ V7.0 THE ORACLE: DM ALERT ]
            sendTelegramAlert("✉️ *INCOMING LASER LINK*\nEncrypted direct message received from: `" . $normalized_from . "`\nLogin to decrypt and read.");
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
    echo json_encode(['status' => 'error', 'message' => 'Core Memory Malfunction.']);
}