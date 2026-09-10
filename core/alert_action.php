<?php
require_once __DIR__ . '/security.php';
require_once 'ssl_shield.php';
// ==========================================
// 🔔 RELAY STATION: ALERT HANDLER (V7.2)
// Clears radar notifications. For "Follow Back" actions, 
// the Symmetric Key Exchange is handled safely by add_planet.php.
// ==========================================

relay_session_start();

// Only the Commander may reach this endpoint.
relay_require_auth(false);

// Clearing an alert mutates state, so GET is no longer accepted (it was
// reachable from any <a href>, making it CSRF-able).
relay_require_post_and_csrf(false);

if (isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
        // Hapus notifikasi dari radar setelah dibaca / dieksekusi
        $stmt = $db->prepare("DELETE FROM alerts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Jika ini dari tombol [ FOLLOW BACK ] (AJAX), berikan sinyal hijau ke console.php 
        // agar console bisa melanjutkan eksekusi ke add_planet.php untuk pertukaran kunci simetris.
        if (isset($_POST['ajax'])) {
            echo "OK";
            exit;
        }
        
        // Jika ini dialihkan dari tombol 'Read DM'
        // Allowlist rather than strip_tags(): this value selects where the
        // response redirects to, so only a known-good token is acceptable.
        $redirect = relay_require_enum($_POST['redirect'] ?? null, ['direct'], '');
        if ($redirect === 'direct') {
            header("Location: ../direct.php"); 
            exit;
        }

    } catch (PDOException $e) {
        // Stay quiet in the response so the UI is not disturbed, but do not
        // lose the error - it is a real signal about the database.
        error_log('[RELAY][SECURITY] alert_action failed: ' . $e->getMessage());
    }
}
header("Location: ../console.php");
exit;