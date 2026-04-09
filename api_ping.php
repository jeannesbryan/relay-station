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
$bunker_mode = '0';

// Mengambil Gembok (Public Key) & Status Bunker dari Core Memory
try {
    if (file_exists($db_file)) {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
        
        // Menarik Public Key
        $stmt = $db->query("SELECT config_value FROM system_config WHERE config_key = 'public_key'");
        if ($stmt) {
            $public_key = $stmt->fetchColumn();
        }

        // [ NEW ] Menarik status Bunker Mode
        $stmt_bunker = $db->query("SELECT config_value FROM system_config WHERE config_key = 'bunker_mode'");
        if ($stmt_bunker) {
            $bunker_mode = $stmt_bunker->fetchColumn() ?: '0';
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

// Menyesuaikan Pesan Radar berdasarkan Mode
$radar_msg = ($bunker_mode === '1') 
    ? 'Awaiting handshakes. Private bunker node active.' 
    : 'Awaiting transmissions. Iron Bunker node active.';

// Format Tanda Tangan Digital RELAY STATION
echo json_encode([
    'status' => 'online',
    'software' => 'relay_station',
    'version' => $station_version,
    'protocol' => 'fediverse_lightweight',
    'private_node' => ($bunker_mode === '1') ? true : false, // [ NEW ] Mengibarkan bendera akun privat
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