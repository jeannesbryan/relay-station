<?php
require_once 'ssl_shield.php';
// RELAY STATION: STAR CHART UPDATER

session_start();

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("UNAUTHORIZED_ACCESS");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planet_url = trim($_POST['planet_url'] ?? '');
    
    if (empty($planet_url)) {
        header("Location: ../console.php?error=empty_url");
        exit;
    }

    $planet_url = rtrim($planet_url, '/');
    if (strpos($planet_url, 'http') !== 0) { $planet_url = 'https://' . $planet_url; }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base_path = dirname(dirname($_SERVER['SCRIPT_NAME'])); 
    if ($base_path === '\\' || $base_path === '/') { $base_path = ''; }
    
    $my_planet_url = rtrim($protocol . $host . $base_path, '/');

    if (strtolower($planet_url) === strtolower($my_planet_url)) {
        header("Location: ../console.php?error=self_node");
        exit;
    }

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
            if (isset($data['version'])) { $node_version = $data['version']; }
        }
    }

    if (!$is_valid_node) {
        header("Location: ../console.php?error=invalid_node");
        exit;
    }

    $parsed_url = parse_url($planet_url);
    $domain_name = $parsed_url['host'] ?? 'UNKNOWN';
    $path = rtrim($parsed_url['path'] ?? '', '/');
    $alias_base = $domain_name . $path;
    
    if (strpos($node_version, '3.') === 0) { $alias = $alias_base . ' [v3]'; } 
    elseif (strpos($node_version, '2.') === 0) { $alias = $alias_base . ' [v2]'; } 
    else { $alias = $alias_base; }

    $db_file = '../data/relay_core.sqlite';
    
    try {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO following (planet_url, alias) VALUES (:url, :alias)");
        $stmt->execute([ ':url' => $planet_url, ':alias' => $alias ]);

        // ==========================================
        // 🤝 [ THE HANDSHAKE PROTOCOL ]
        // Kirim notifikasi "Knock-Knock" ke planet target
        // ==========================================
        $handshake_url = $planet_url . '/api_handshake.php';
        $hs_payload = json_encode(['from_planet' => $my_planet_url]);

        $ch_hs = curl_init($handshake_url);
        curl_setopt($ch_hs, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_hs, CURLOPT_POST, true);
        curl_setopt($ch_hs, CURLOPT_POSTFIELDS, $hs_payload);
        curl_setopt($ch_hs, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch_hs, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch_hs, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch_hs);
        curl_close($ch_hs);
        // ==========================================

        header("Location: ../console.php?status=node_locked");
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ SYSTEM ERROR ] Core Memory Malfunction: " . $e->getMessage() . "</h3>");
    }
} else {
    die("INVALID_PROTOCOL");
}