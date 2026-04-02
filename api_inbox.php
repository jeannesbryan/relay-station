<?php
// RELAY STATION: ATMOSPHERIC SHIELD & INBOX (V3.0 - SECURE RATE LIMITED)
// Endpoint untuk menerima sinyal (POST) dari planet lain, dilengkapi Firewall Anti-Spam dan Anti-Spoofing.

header('Content-Type: application/json');
date_default_timezone_set('UTC'); // Waktu kosmik standar

// 1. [ SHIELD ] Hanya menerima tembakan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '[ SHIELD REFLECTED ] Sinyal ditolak. Gunakan protokol POST.']);
    exit;
}

// 2. [ DECRYPTION ] Menangkap dan membuka kapsul JSON
$raw_payload = file_get_contents('php://input');
$signal = json_decode($raw_payload, true);

if (!$signal || empty($signal['content']) || empty($signal['author_alias']) || empty($signal['from_planet'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '[ CORRUPTED SIGNAL ] Kapsul data tidak lengkap.']);
    exit;
}

$content = trim($signal['content']);
$author = trim($signal['author_alias']);
$from_planet = trim($signal['from_planet']);
$visibility = $signal['visibility'] ?? 'public';
$expiry_date = $signal['expiry_date'] ?? null; 
$media_url = !empty($signal['media_url']) ? trim($signal['media_url']) : null;

// 🕵️ [ ANTI-SPOOFING ] Tangkap IP Fisik Pengirim (Mendukung Cloudflare/Proxy)
$sender_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

$db_file = 'data/relay_core.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // 🛠️ [ AUTO-UPGRADE SCHEMA ]
    // Menambahkan kolom sender_ip tanpa perlu hapus database lama
    // ==========================================
    try {
        $db->exec("ALTER TABLE transmissions ADD COLUMN sender_ip TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Abaikan jika kolom sudah ada
    }

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
            'message' => '[ FIREWALL REFLECTED ] Akses ditolak. Planet Anda tidak terdaftar di Peta Bintang kami.'
        ]);
        exit; // Tolak pendaratan, jangan simpan ke SQLite!
    }

    // 🛑 [ TRUE RATE LIMITING (BY IP) ] Maksimal 5 pesan per menit dari IP yang sama
    $stmt_rl = $db->prepare("SELECT COUNT(*) FROM transmissions WHERE sender_ip = :ip AND timestamp >= datetime('now', '-1 minute')");
    $stmt_rl->execute([':ip' => $sender_ip]);
    
    if ($stmt_rl->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => '[ RATE LIMIT ] Maksimal 5 transmisi per menit. Tunda siaran Anda.']);
        exit;
    }
    // ==========================================

    // Format Alias Otomatis: namapengirim@domainplanet.com
    $domain_host = parse_url($normalized_from, PHP_URL_HOST);
    $formatted_author = htmlspecialchars(str_replace(' ', '', $author) . '@' . $domain_host);

    // 3. [ CORE MEMORY INSERTION ]
    $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, is_remote, author_alias, expiry_date, media_url, sender_ip) VALUES (:content, :visibility, 1, :author, :expiry, :media_url, :ip)");
    $stmt->execute([
        ':content' => htmlspecialchars($content),
        ':visibility' => htmlspecialchars($visibility),
        ':author' => $formatted_author,
        ':expiry' => $expiry_date ? htmlspecialchars($expiry_date) : null,
        ':media_url' => $media_url ? htmlspecialchars($media_url) : null,
        ':ip' => $sender_ip
    ]);

    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'message' => '[ DOCKED ] Sinyal berhasil mendarat di RELAY STATION.',
        'receipt_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '[ INTERNAL FAILURE ] Reaktor Core Memory bermasalah.']);
}