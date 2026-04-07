<?php
require_once 'core/ssl_shield.php';
// RELAY STATION: IDENTIFICATION BEACON (FEDIVERSE EDITION V3.0)
// Merespon sinyal "Ping" dari stasiun asing untuk membuktikan validitas node dan kapabilitasnya.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Mengizinkan stasiun lain untuk membaca ping ini

// Format Tanda Tangan Digital RELAY STATION
echo json_encode([
    'status' => 'online',
    'software' => 'relay_station',
    'version' => '3.0',
    'protocol' => 'fediverse_lightweight',
    'features' => [
        'media_hotlinking' => true,
        'ghost_protocol' => true,
        'direct_messaging' => true,
        'base64_compression' => true,
        'rate_limiting' => true
    ],
    'message' => 'Awaiting transmissions. Fediverse V3 node active.'
]);
exit;