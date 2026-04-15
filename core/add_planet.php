<?php
require_once 'ssl_shield.php';
// ==========================================
// 🚀 RELAY STATION: STAR CHART UPDATER (V7.2)
// Equipped with The Symmetric Key Exchange Protocol
// ==========================================

session_start();

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("UNAUTHORIZED_ACCESS");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [ V7.1 ] Advanced Sanitization
    $planet_url = filter_var(trim($_POST['planet_url'] ?? ''), FILTER_SANITIZE_URL);
    $handshake_token = trim($_POST['handshake_token'] ?? ''); 
    
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
    // [ V7.1 ] WAF Bypass
    curl_setopt($ch, CURLOPT_USERAGENT, 'RelayStation-Transmitter/7.2');
    
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
    $alias_base = strip_tags($domain_name . $path);
    
    if (strpos($node_version, '3.') === 0) { $alias = $alias_base . ' [v3]'; } 
    elseif (strpos($node_version, '2.') === 0) { $alias = $alias_base . ' [v2]'; } 
    else { $alias = $alias_base; }

    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
        // ==========================================
        // 🔑 [ V7.2 ] THE SYMMETRIC KEY EXCHANGE FIX
        // ==========================================
        // Jika kita sedang melakukan "Follow Back", cek apakah musuh sudah 
        // pernah mengirimkan token mereka saat mereka mem-follow kita (ada di tabel followers).
        // Jika ada, kita WAJIB menggunakan token mereka, bukan token baru.
        
        $stmt_check = $db->prepare("SELECT handshake_token FROM followers WHERE planet_url = :url");
        $stmt_check->execute([':url' => $planet_url]);
        $existing_token = $stmt_check->fetchColumn();

        if (!empty($existing_token)) {
            // Paradoks dihindari: Gunakan kunci yang sudah disepakati sebelumnya
            $handshake_token = $existing_token;
        }

        // [ V7.2 ] Simpan Handshake Token ke dalam tabel following
        // Menggunakan INSERT OR REPLACE agar jika ganti URL / force resync, token bisa ter-update
        $stmt = $db->prepare("INSERT OR REPLACE INTO following (planet_url, alias, handshake_token) VALUES (:url, :alias, :token)");
        $stmt->execute([ ':url' => $planet_url, ':alias' => $alias, ':token' => $handshake_token ]);

        // ==========================================
        // 🤝 [ THE HANDSHAKE PROTOCOL ]
        // Send a "Knock-Knock" notification to the target planet
        // ==========================================
        $handshake_url = $planet_url . '/api_handshake.php';
        $hs_payload = json_encode([
            'from_planet' => $my_planet_url,
            'handshake_token' => $handshake_token 
        ]);

        $ch_hs = curl_init($handshake_url);
        curl_setopt($ch_hs, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_hs, CURLOPT_POST, true);
        curl_setopt($ch_hs, CURLOPT_POSTFIELDS, $hs_payload);
        curl_setopt($ch_hs, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: RelayStation-Transmitter/7.2'
        ]);
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