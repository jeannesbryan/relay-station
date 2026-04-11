<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📡 RELAY STATION: IDENTIFICATION BEACON
// ==========================================
// Responds to "Ping" signals from foreign stations to prove node validity and capabilities.

// 🛡️ [ BUG FIX 2 ]: BULLETPROOF CORS (Mencegah stuck di Pinging Target Handshake)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Jika browser mengirimkan request preflight (OPTIONS), langsung beri lampu hijau dan tutup koneksi
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 🛡️ [ BUG FIX 1 ]: ANTI-CACHE HEADERS (Mencegah Key Mismatch / Teks Enkripsi Gagal Decode)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json');

$public_key = null;
$bunker_mode = '0';

// Fetch Public Key & Bunker Mode Status from Core Memory
try {
    if (file_exists('data/relay_core.sqlite')) {
        // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
        require_once 'core/db_connect.php';
        
        // Fetch Public Key
        $stmt = $db->query("SELECT config_value FROM system_config WHERE config_key = 'public_key' ORDER BY rowid DESC LIMIT 1");
        if ($stmt) {
            $public_key = $stmt->fetchColumn();
        }

        // Fetch Bunker Mode status
        $stmt_bunker = $db->query("SELECT config_value FROM system_config WHERE config_key = 'bunker_mode' ORDER BY rowid DESC LIMIT 1");
        if ($stmt_bunker) {
            $bunker_mode = $stmt_bunker->fetchColumn() ?: '0';
        }
    }
} catch (Exception $e) {
    // Ignore if core memory is not ready (e.g., during fresh install)
}

// Fetch Dynamic Version
$station_version = 'UNKNOWN';
if (file_exists('version.json')) {
    $v_data = json_decode(file_get_contents('version.json'), true);
    if (isset($v_data['version'])) {
        $station_version = $v_data['version'];
    }
}

// Adjust Radar Message based on Bunker Mode
$radar_msg = ($bunker_mode === '1') 
    ? 'Awaiting handshakes. Private bunker node active.' 
    : 'Awaiting transmissions. Iron Bunker node active.';

// RELAY STATION Digital Signature Format
echo json_encode([
    'status' => 'online',
    'software' => 'relay_station',
    'version' => $station_version,
    'protocol' => 'fediverse_lightweight',
    'private_node' => ($bunker_mode === '1') ? true : false, // Flag for private account
    'features' => [
        'media_hotlinking' => true,
        'ghost_protocol' => true,
        'direct_messaging' => true,
        'base64_compression' => true,
        'rate_limiting' => true,
        'e2e_encryption' => true 
    ],
    'public_key' => $public_key, 
    'message' => $radar_msg
]);
exit;