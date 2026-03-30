<?php
// RELAY STATION: STAR CHART UPDATER (V2 - WITH RADAR PING)
session_start();

// Keamanan: Hanya Kapten yang bisa menambah planet
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("UNAUTHORIZED_ACCESS");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planet_url = trim($_POST['planet_url'] ?? '');
    
    if (empty($planet_url)) {
        header("Location: ../console.php?error=empty_url");
        exit;
    }

    // 1. Normalisasi URL
    $planet_url = rtrim($planet_url, '/');
    if (strpos($planet_url, 'http') !== 0) {
        $planet_url = 'https://' . $planet_url;
    }

    // ==========================================
    // 📡 [ RADAR PING PROTOCOL: NODE VALIDATION ]
    // ==========================================
    $ping_url = $planet_url . '/api_ping.php';
    
    $ch = curl_init($ping_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Putuskan koneksi jika > 5 detik (Server mati)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Toleransi SSL untuk lokal/pengembangan
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Verifikasi Tanda Tangan: Apakah ada kata 'relay_station' di file JSON-nya?
    $is_valid_node = false;
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['software']) && $data['software'] === 'relay_station') {
            $is_valid_node = true;
        }
    }

    // Jika Target BUKAN Relay Station (Atau Web Biasa/Mati)
    if (!$is_valid_node) {
        header("Location: ../console.php?error=invalid_node");
        exit;
    }
    // ==========================================

    // 2. Jika Lolos Sensor, Eksekusi Penyimpanan
    // Alias otomatis menggunakan nama domain
    $alias = parse_url($planet_url, PHP_URL_HOST); 
    $db_file = '../data/relay_core.sqlite';
    
    try {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO following (planet_url, alias) VALUES (:url, :alias)");
        $stmt->execute([
            ':url' => $planet_url,
            ':alias' => htmlspecialchars($alias)
        ]);
        
        header("Location: ../console.php?status=node_locked");
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ ERROR ] Core Memory Offline.</h3>");
    }
}