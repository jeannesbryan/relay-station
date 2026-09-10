<?php
require_once __DIR__ . '/security.php';
require_once 'ssl_shield.php';
// ==========================================================================
// RELAY STATION: UPDATE CHECK (V8.0)
// ==========================================================================
// The v7.3 updater downloaded a ZIP over an unverified connection, extracted it
// over the web root, and then `include`d every migration script the archive
// contained. That is a remote-code-execution chain, and it was reachable by GET
// so it could be triggered with a single link:
//
//   1. TLS verification was disabled on the beacon, so a MITM could forge the
//      response and point `download_url` at any host.
//   2. The payload was fetched with no host allowlist and no signature check.
//   3. Extraction only checked a prefix denylist, so a zip entry named
//      ../../shell.php - or an absolute path - escaped the intended directory
//      and overwrote any file, including PHP.
//   4. Each extracted upgrade_db*.php was then included, executing it.
//
// There is no trust anchor anywhere in that chain: nothing establishes that the
// archive came from the project rather than from whoever controlled the path.
// Hardening it properly needs a signing keypair with the private half held
// offline and the public half compiled into the app - that is real work and it
// is not this release. So the automated path has been REMOVED, not guarded.
//
// What remains is the read-only half: fetch the published version manifest,
// compare it, and tell the operator. Nothing is downloaded, extracted or
// executed. Updating is a manual, documented procedure.
// ==========================================================================

relay_session_start();
relay_require_auth(false);

// The beacon is a fixed, known host. It is still routed through the SSRF/TLS
// gate rather than a bare curl_init so the certificate is verified, redirects
// are not followed, and the DNS result is pinned.
const RELAY_BEACON_URL = 'https://raw.githubusercontent.com/jeannesbryan/relay-station/main/version.json';

$current_version = 'UNKNOWN';
if (file_exists(__DIR__ . '/../version.json')) {
    $v_data = json_decode((string) file_get_contents(__DIR__ . '/../version.json'), true);
    if (is_array($v_data) && isset($v_data['version']) && is_string($v_data['version'])) {
        $current_version = $v_data['version'];
    }
}

// Cache-bust without leaking timing: the beacon is fetched at most once a minute.
$check_url = RELAY_BEACON_URL . '?t=' . (int) (time() / 60);

$result = relay_http_get($check_url, 5);

if (!$result['ok'] || !is_string($result['body']) || $result['body'] === '') {
    error_log('[RELAY] update beacon unreachable: ' . $result['error']);
    echo "<h3 style='color:red;'>[ SIGNAL LOST ] Gagal menghubungi Pusat Komando. Coba lagi nanti.</h3>";
    exit;
}

$update_data = json_decode($result['body'], true);

if (!is_array($update_data) || !isset($update_data['version']) || !is_string($update_data['version'])) {
    // A malformed or hostile manifest is treated as "no information", never as
    // an instruction.
    error_log('[RELAY][SECURITY] update beacon returned an unusable manifest');
    echo "<h3 style='color:red;'>[ SIGNAL CORRUPT ] Manifest pembaruan tidak dapat dibaca.</h3>";
    exit;
}

// Only the fields we display are read, and all of them are escaped on output.
$remote_version = $update_data['version'];
$remote_changelog = isset($update_data['changelog']) && is_string($update_data['changelog'])
    ? $update_data['changelog']
    : '(tidak ada changelog)';

$escape = function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};

if (version_compare($remote_version, $current_version, '>')) {

    echo '<!DOCTYPE html><html lang="en"><head><title>SYSTEM UPDATE</title>'
       . '<link rel="stylesheet" href="../assets/terminal.css"></head>'
       . '<body class="t-crt t-center-screen">';
    echo '<div class="t-center-box t-card warning mb-0" style="max-width: 560px;">';
    echo '<h2 class="t-card-header">> SYSTEM_UPDATE_DETECTED</h2>';
    echo "<p class='mb-2'>Versi Saat Ini: <strong>" . $escape($current_version) . "</strong></p>";
    echo "<p class='mb-2'>Versi Terbaru: <strong class='text-success t-blink'>" . $escape($remote_version) . "</strong></p>";
    echo "<div class='t-window mb-4'><div class='t-window-header'>CHANGELOG</div>"
       . "<pre class='t-window-body' style='font-size:12px;'>" . $escape($remote_changelog) . "</pre></div>";

    echo "<div class='t-card danger mb-4 text-left' style='font-size:12px;'>"
       . "<strong>> PEMBARUAN MANUAL DIPERLUKAN</strong><br>"
       . "Pembaruan otomatis telah dinonaktifkan pada v8.0. Mesin OTA sebelumnya "
       . "mengunduh dan menjalankan kode dari jaringan tanpa memverifikasi asalnya, "
       . "sehingga tidak dapat diamankan. Perbarui secara manual:"
       . "<ol style='margin:8px 0 0 18px;'>"
       . "<li>Unduh rilis resmi dari repositori proyek.</li>"
       . "<li>Verifikasi berkasnya (checksum atau tanda tangan rilis).</li>"
       . "<li>Cadangkan <code>data/relay_core.sqlite</code> terlebih dahulu.</li>"
       . "<li>Ganti berkas aplikasi; jangan menimpa <code>data/</code> atau <code>media/</code>.</li>"
       . "</ol></div>";

    echo '<a href="../console.php" class="t-btn w-100">[ KEMBALI ]</a>';
    echo '</div></body></html>';

} else {
    echo "<script>alert('Sistem Anda sudah berada di versi terbaru (v" . $escape($current_version) . ").'); "
       . "window.location.href='../console.php';</script>";
}
