<?php
// RELAY STATION: STAR CHART UPDATER (V3.0 - SUBFOLDER AWARE)
// Menambahkan koordinat planet ke radar (Following) setelah memverifikasi ping

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $is_valid_node = false;
    $node_version = '1.0'; 
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['software']) && $data['software'] === 'relay_station') {
            $is_valid_node = true;
            if (isset($data['version'])) {
                $node_version = $data['version']; 
            }
        }
    }

    if (!$is_valid_node) {
        header("Location: ../console.php?error=invalid_node");
        exit;
    }

    // ==========================================
    // 🏷️ [ SMART ALIASING (SUBFOLDER AWARE) ]
    // ==========================================
    $parsed_url = parse_url($planet_url);
    $domain_name = $parsed_url['host'] ?? 'UNKNOWN';
    $path = $parsed_url['path'] ?? ''; // Tangkap nama subfolder jika ada
    $path = rtrim($path, '/');
    
    // Gabungkan Domain + Subfolder (misal: website.com/relay)
    $alias_base = $domain_name . $path;
    
    // [ SMART TAGGING V3 ] 
    if (strpos($node_version, '3.') === 0) {
        $alias = $alias_base . ' [v3]';
    } elseif (strpos($node_version, '2.') === 0) {
        $alias = $alias_base . ' [v2]';
    } else {
        $alias = $alias_base;
    }

    $db_file = '../data/relay_core.sqlite';
    
    try {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO following (planet_url, alias) VALUES (:url, :alias)");
        $stmt->execute([
            ':url' => $planet_url,
            ':alias' => $alias
        ]);

        header("Location: ../console.php?status=node_locked");
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ SYSTEM ERROR ] Core Memory Malfunction: " . $e->getMessage() . "</h3>");
    }
} else {
    die("INVALID_PROTOCOL");
}