<?php
// ==========================================
// 🛡️ RELAY STATION: STRICT SSL ENFORCEMENT
// ==========================================
// Requires core/security.php to have been loaded first (it provides
// relay_is_https()). Every entry point includes security.php ahead of this
// file for exactly that reason.

// 1. Deteksi status HTTPS.
//
// v7.3 decided this by trusting HTTP_X_FORWARDED_PROTO and HTTP_X_FORWARDED_SSL
// outright. Both are request headers, so an attacker speaking plain HTTP could
// send `X-Forwarded-Proto: https` and turn this entire shield off - every
// "HTTPS required" guarantee in the app was one header away from being optional.
// relay_is_https() only honours those headers when the connection genuinely
// arrives from a Cloudflare edge address, and otherwise falls back to
// HTTPS / SERVER_PORT, which the client cannot influence.
$is_secure = relay_is_https();

// 2. Eksekusi Pertahanan jika jalur tidak aman (HTTP biasa)
if (!$is_secure) {

    // Deteksi apakah ini adalah jalur API (Komunikasi Mesin P2P)
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_api_request = (strpos($script_name, 'api_') !== false);

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
        //
        // HTTP_HOST is attacker-controlled: it arrives in the request and can
        // contain CRLF or an arbitrary host, which would let an attacker use
        // this redirect for header injection or to bounce a victim to their own
        // domain. Only a syntactically valid hostname is accepted; anything else
        // falls back to a relative redirect, which is always same-origin.
        $requested_host = $_SERVER['HTTP_HOST'] ?? '';
        $host_is_valid = (bool) preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $requested_host);

        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip anything that is not a plausible request path.
        $request_uri = preg_replace('/[\r\n].*$/', '', $request_uri);
        if ($request_uri === '' || $request_uri[0] !== '/') {
            $request_uri = '/';
        }

        header('HTTP/1.1 301 Moved Permanently');
        if ($host_is_valid) {
            header('Location: https://' . $requested_host . $request_uri);
        } else {
            header('Location: ' . $request_uri);
        }
        exit;
    }
}
