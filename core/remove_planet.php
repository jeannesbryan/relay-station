<?php
require_once 'ssl_shield.php';
// RELAY STATION: DISCONNECT PROTOCOL
// Menghapus koordinat planet dari radar secara manual (Unfollow)

session_start();

// Keamanan: Hanya Kapten yang bisa memutus koneksi
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("UNAUTHORIZED_ACCESS");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db_file = '../data/relay_core.sqlite';
    
    try {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("DELETE FROM following WHERE id = :id");
        $stmt->execute([':id' => $id]);

        header("Location: ../console.php?status=node_removed");
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ SYSTEM ERROR ] " . $e->getMessage() . "</h3>");
    }
} else {
    header("Location: ../console.php");
}