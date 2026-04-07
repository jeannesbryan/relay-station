<?php
// ==========================================
// 🛡️ RELAY STATION: STRICT SSL ENFORCEMENT
// ==========================================

// 1. Deteksi status HTTPS (Mendukung Cloudflare / Proxy)
$is_secure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $is_secure = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $is_secure = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
    $is_secure = true;
}

// 2. Eksekusi Pertahanan jika jalur tidak aman (HTTP biasa)
if (!$is_secure) {
    
    // Deteksi apakah ini adalah jalur API (Komunikasi Mesin P2P)
    $is_api_request = (strpos($_SERVER['SCRIPT_NAME'], 'api_') !== false);
    
    if ($is_api_request) {
        // [ Skenario A ] Jika mesin asing menyerang via HTTP, tolak mentah-mentah!
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'message' => '[ SHIELD REFLECTED ] Akses ditolak. Stasiun ini mewajibkan koneksi HTTPS yang dienkripsi.'
        ]);
        exit;
    } else {
        // [ Skenario B ] Jika manusia (browser) tersesat ke HTTP, paksa pindah ke HTTPS
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect_url);
        exit;
    }
}