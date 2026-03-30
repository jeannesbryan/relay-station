<?php
// RELAY STATION: IDENTIFICATION BEACON
// Merespon sinyal "Ping" dari stasiun asing untuk membuktikan validitas node.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Mengizinkan stasiun lain untuk membaca ping ini

// Format Tanda Tangan Digital RELAY STATION
echo json_encode([
    'status' => 'online',
    'software' => 'relay_station',
    'version' => '1.0.0-dev',
    'message' => 'Awaiting transmissions.'
]);
exit;