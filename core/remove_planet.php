<?php
require_once 'ssl_shield.php';
// RELAY STATION: DISCONNECT PROTOCOL
// Manually remove a planet's coordinates from the radar (Unfollow)

session_start();

// Security: Only the Commander can disconnect nodes
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("UNAUTHORIZED_ACCESS");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
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