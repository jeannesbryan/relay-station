<?php
require_once 'ssl_shield.php';
// ==========================================
// 🔔 RELAY STATION: ALERT HANDLER (V7.2)
// Clears radar notifications. For "Follow Back" actions, 
// the Symmetric Key Exchange is handled safely by add_planet.php.
// ==========================================

session_start();
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) { 
    die("[ UNAUTHORIZED ] Access Denied."); 
}

if (isset($_GET['id'])) {
    $id = (int) filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    
    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
        // Hapus notifikasi dari radar setelah dibaca / dieksekusi
        $stmt = $db->prepare("DELETE FROM alerts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Jika ini dari tombol [ FOLLOW BACK ] (AJAX), berikan sinyal hijau ke console.php 
        // agar console bisa melanjutkan eksekusi ke add_planet.php untuk pertukaran kunci simetris.
        if (isset($_GET['ajax'])) { 
            echo "OK"; 
            exit; 
        }
        
        // Jika ini dialihkan dari tombol 'Read DM'
        $redirect = isset($_GET['redirect']) ? strip_tags($_GET['redirect']) : '';
        if ($redirect === 'direct') {
            header("Location: ../direct.php"); 
            exit;
        }

    } catch (PDOException $e) {
        // Gagal dalam keheningan agar tidak merusak antarmuka UI
    }
}
header("Location: ../console.php");
exit;