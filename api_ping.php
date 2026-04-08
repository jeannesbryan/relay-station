<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📡 RELAY STATION: IDENTIFICATION BEACON
// ==========================================
// Merespon sinyal "Ping" dari stasiun asing untuk membuktikan validitas node dan kapabilitasnya.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Mengizinkan stasiun lain untuk membaca ping ini

$db_file = 'data/relay_core.sqlite';
$public_key = null;

// Mengambil Gembok (Public Key) dari Core Memory
try {
    if (file_exists($db_file)) {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
        
        $stmt = $db->query("SELECT config_value FROM system_config WHERE config_key = 'public_key'");
        if ($stmt) {
            $public_key = $stmt->fetchColumn();
        }
    }
} catch (Exception $e) {
    // Abaikan jika mesin memori belum siap (misal saat instalasi awal)
}

// Mengambil Versi Dinamis
$station_version = 'UNKNOWN';
if (file_exists('version.json')) {
    $v_data = json_decode(file_get_contents('version.json'), true);
    if (isset($v_data['version'])) {
        $station_version = $v_data['version'];
    }
}

// Format Tanda Tangan Digital RELAY STATION
echo json_encode([
    'status' => 'online',
    'software' => 'relay_station',
    'version' => $station_version,
    'protocol' => 'fediverse_lightweight',
    'features' => [
        'media_hotlinking' => true,
        'ghost_protocol' => true,
        'direct_messaging' => true,
        'base64_compression' => true,
        'rate_limiting' => true,
        'e2e_encryption' => true // [ NEW ] Tanda bahwa node ini mendukung E2E
    ],
    'public_key' => $public_key, // [ NEW ] Menyiarkan Gembok ke alam semesta
    'message' => 'Awaiting transmissions. Iron Bunker node active.'
]);
exit;